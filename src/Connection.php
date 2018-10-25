<?php
namespace raoptimus\openstack;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\ResponseInterface;
use yii\base\Component;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class Connection extends Component
{
    private const DEFAULT_USERAGENT = 'github.com/raoptimus/yii2-openstack/1.0.1';
    private const DEFAULT_RETRIES = 3;
    private const AUTH_ERROR_MAP = [
        HttpCode::BAD_REQUEST => HttpCode::CODE_DESC_MAP[HttpCode::BAD_REQUEST],
        HttpCode::UNAUTHORIZED => HttpCode::CODE_DESC_MAP[HttpCode::UNAUTHORIZED],
        HttpCode::FORBIDDEN => HttpCode::CODE_DESC_MAP[HttpCode::FORBIDDEN],
    ];
    /**
     * UserName for api
     *
     * @var string
     */
    public $username;
    /**
     * Key for api access
     *
     * @var string
     */
    public $apiKey;
    /**
     * @var string auth server url
     */
    public $authUrl;
    /**
     * Retries on error (default is 3)
     *
     * @var integer
     */
    public $retries = self::DEFAULT_RETRIES;
    /**
     * Http User agent (default github.com/raoptimus/yii2-openstack/1.0.1)
     *
     * @var string
     */
    public $userAgent = self::DEFAULT_USERAGENT;
    /**
     * Region to use eg "LON", "ORD" - default is use first region (V2 auth only)
     *
     * @var string
     */
    public $region;
    /**
     * Set this to true to use the the internal / service network
     *
     * @var bool
     */
    public $internal = false;
    /**
     * Name of the tenant (v2 auth only)
     *
     * @var string
     */
    public $tenant;
    /**
     * Id of the tenant (v2 auth only)
     *
     * @var string
     */
    public $tenantId;
    /**
     * Id of the trust (v3 auth only)
     *
     * @var string
     */
    public $trustId;
    /**
     * User's domain name
     *
     * @var string
     */
    public $domain;
    /**
     * User's domain Id
     *
     * @var string
     */
    public $domainId;
    /**
     * Set to 1 or 2 or leave at 0 for autodetect
     *
     * @var integer
     */
    public $authVersion;
    /**
     * Connect channel timeout (default 10s)
     *
     * @var integer
     */
    public $connectionTimeout = 10;
    /**
     * Data channel timeout (default 60s)
     *
     * @var integer
     */
    public $timeout = 60;
    /**
     * @var string
     */
    protected $storageUrl;
    /**
     * @var string
     */
    protected $authToken;
    /**
     * @var BaseAuth
     */
    protected $auth;
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var Container[]
     */
    protected $containers = [];

    public static function buildUrl(array $url): string
    {
        $q = empty($url['query']) ? '' : ('?' . $url['query']);
        $p = isset($url['port']) ? ':' . $url['port'] : '';

        return sprintf(
            '%s://%s%s%s%s',
            $url['scheme'],
            $url['host'],
            $p,
            $url['path'],
            $q
        );
    }

    public function getStorageUrl(): ?string
    {
        return $this->storageUrl;
    }

    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    /**
     * @param $name string
     *
     * @return Container
     */
    public function getContainer($name): Container
    {
        if (!isset($this->containers[$name])) {
            $this->containers[$name] = new Container(
                [
                    'connection' => $this,
                    'name' => $name,
                ]
            );
        }

        return $this->containers[$name];
    }

    /**
     * @return Client
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * @return Container[]
     */
    public function getContainers(): array
    {
        $this->containers = (new Container(
            [
                'connection' => $this,
            ]
        ))->all();

        return array_values($this->containers);
    }

    public function authenticated(): bool
    {
        return !empty($this->getStorageUrl()) && !empty($this->getAuthToken());
    }

    /**
     * @param RequestOpts $opts
     *
     * @return ResponseInterface
     * @throws AuthException
     * @throws SwiftException
     */
    public function call(RequestOpts $opts): ResponseInterface
    {
        $this->setDefaults();

        $retries = ($opts->retries > 0) ? $opts->retries : $this->retries;
        $resp = null;

        while ($retries > 0) {
            if (!$this->authenticated()) {
                $this->authenticate();
            }

            $authToken = $this->getAuthToken();
            $storageUrl = parse_url($this->getStorageUrl());

            if (!empty($opts->container)) {
                $storageUrl['path'] = rtrim($storageUrl['path'], '/');
                $storageUrl['path'] .= '/' . ltrim($opts->container, '/');

                if (!empty($opts->objectName)) {
                    $storageUrl['path'] .= '/' . ltrim($opts->objectName, '/');
                }
            }

            if (!empty($opts->parameters)) {
                $storageUrl['query'] = http_build_query($opts->parameters);
            }

            $storageUrl = self::buildUrl($storageUrl);

            $req = $this->getClient()->createRequest(
                $opts->operation,
                $storageUrl,
                [
                    'body' => $opts->body,
                    'headers' => array_merge(
                        $opts->headers ?? [],
                        [
                            'X-Auth-Token' => $authToken,
                            'User-Agent' => $this->userAgent,
                        ]
                    ),
                ]
            );

            try {
                $resp = $this->getClient()->send($req);
            } catch (RequestException $ex) {
                if ($ex->getCode() === HttpCode::UNAUTHORIZED && $retries - 1 > 0) {
                    $this->unAuthenticate();
                    $retries--;
                    continue;
                }
                if (in_array($opts->operation, ['HEAD', 'GET'], true) && $retries - 1 > 0) {
                    $retries--;
                    continue;
                }
                $msg = $opts->errorMap[$ex->getCode()] ?? $ex->getMessage();
                throw new AuthException($msg, $ex->getCode());
            }

            break;
        }

        $this->checkStatusCode($resp->getStatusCode(), $opts->errorMap);

        return $resp;
    }

    public function authenticate(): void
    {
        $this->setDefaults();

        if (!$this->auth) {
            $this->auth = BaseAuth::create($this);
        }
        $retries = 1;
        again:
        $req = $this->auth->getRequest($this);
        try {
            $resp = $this->getClient()->send($req);
        } catch (RequestException $ex) {
            if (in_array($ex->getCode(), [HttpCode::UNAUTHORIZED, HttpCode::BAD_REQUEST], true) && $retries > 0) {
                $retries--;
                goto again;
            }
            $msg = self::AUTH_ERROR_MAP[$ex->getCode()] ?? $ex->getMessage();
            throw new AuthException($msg, $ex->getCode());
        }

        $this->checkStatusCode($resp->getStatusCode(), self::AUTH_ERROR_MAP, AuthException::class);

        $this->auth->processResponse($resp);
        $this->storageUrl = $this->auth->getStorageUrl($this->internal);
        $this->authToken = $this->auth->getToken();

        if (!$this->authenticated()) {
            throw new AuthException('Response haven`t got a storage url and auth token');
        }
    }

    private function checkStatusCode(int $statusCode, array $errMap, string $exceptionClass = SwiftException::class): bool
    {
        if (isset($errMap[$statusCode])) {
            throw new $exceptionClass($errMap[$statusCode], $statusCode);
        }
        if ($statusCode < HttpCode::OK || $statusCode >= HttpCode::MULTIPLE_CHOICES) {
            throw new $exceptionClass(sprintf('HTTP Error: %d', $statusCode), $statusCode);
        }

        return true;
    }

    private function setDefaults(): void
    {
        if (empty($this->userAgent)) {
            $this->userAgent = self::DEFAULT_USERAGENT;
        }

        if ($this->retries <= 0) {
            $this->retries = self::DEFAULT_RETRIES;
        }

        if ($this->connectionTimeout <= 0) {
            $this->connectionTimeout = 10;
        }

        if ($this->timeout <= 0) {
            $this->timeout = 60;
        }

        if (empty($this->domain)) {
            $this->domain = 'default';
            $this->domainId = 'default';
        }

        if (!$this->getClient()) {
            $this->client = new HttpClient(
                [
                    'defaults' => [
                        'timeout' => $this->timeout,
                        'verify' => false,
                        'headers' => [
                            'User-Agent' => $this->userAgent,
                        ],
                    ],
                ]
            );
        }
    }

    private function unAuthenticate(): void
    {
        $this->storageUrl = '';
        $this->authToken = '';
    }
}
