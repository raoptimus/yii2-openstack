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
    private const DEFAULT_USERAGENT = 'phpswift/1.0';
    private const DEFAULT_RETRIES = 3;
    private const AUTH_ERROR_MAP = [
        400 => 'Bad Request',
        401 => 'Authorization Failed',
        403 => 'Operation forbidden',
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
     * Http User agent (default phpswift/1.0)
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
    private $_storageUrl;
    /**
     * @var string
     */
    private $_authToken;
    /**
     * @var BaseAuth
     */
    private $_auth;
    /**
     * @var \GuzzleHttp\Client
     */
    private $_client;
    /**
     * @var Container[]
     */
    private $_containers = [];

    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    public static function buildUrl(array $url): string
    {
        $q = empty($url['query']) ? '' : ('?' . $url['query']);
        $p = $url['port'] ? ':' . $url['port'] : '';

        return sprintf(
            '%s://%s%s%s%s',
            $url['scheme'],
            $url['host'],
            $p,
            $url['path'],
            $q
        );
    }

    /**
     * @param $name string
     *
     * @return Container
     */
    public function getContainer($name): Container
    {
        if (!isset($this->_containers[$name])) {
            $this->_containers[$name] = new Container(
                [
                    'connection' => $this,
                    'name' => $name,
                ]
            );
        }

        return $this->_containers[$name];
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->_client;
    }

    /**
     * @return Container[]
     */
    public function getContainers(): array
    {
        $this->_containers = (new Container(
            [
                'connection' => $this,
            ]
        ))->all();

        return array_values($this->_containers);
    }

    public function authenticated(): bool
    {
        return !empty($this->_storageUrl) && !empty($this->_authToken);
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

            $authToken = $this->_authToken;
            $storageUrl = parse_url($this->_storageUrl);

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

            $req = $this->_client->createRequest(
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
                $resp = $this->_client->send($req);
            } catch (RequestException $ex) {
                if ($ex->getCode() === 401 && $retries - 1 > 0) {
                    $this->unAuthenticate();
                    $retries--;
                    continue;
                }
                if (in_array($opts->operation, ['HEAD', 'GET'], true) && $retries - 1 > 0) {
                    $retries--;
                    continue;
                }
                $msg = $opts->errorMap[$ex->getCode()] ?? $ex->getMessage();
                throw new SwiftException($msg, $ex->getCode());
            }

            break;
        }

        $this->checkStatusCode($resp->getStatusCode(), $opts->errorMap);

        return $resp;
    }

    /**
     * @throws AuthException
     * @throws SwiftException
     */
    public function authenticate()
    {
        $this->setDefaults();

        if (!$this->_auth) {
            $this->_auth = BaseAuth::create($this);
        }
        $retries = 1;
        again:
        $req = $this->_auth->getRequest($this);
        try {
            $resp = $this->_client->send($req);
        } catch (RequestException $ex) {
            if (in_array($ex->getCode(), [401, 400], true) && $retries > 0) {
                $retries--;
                goto again;
            }
            $msg = self::AUTH_ERROR_MAP[$ex->getCode()] ?? $ex->getMessage();
            throw new AuthException($msg, $ex->getCode());
        }

        $this->checkStatusCode($resp->getStatusCode(), self::AUTH_ERROR_MAP);

        $this->_auth->response($resp);
        $this->_storageUrl = $this->_auth->getStorageUrl($this->internal);
        $this->_authToken = $this->_auth->getToken();

        if (!$this->authenticated()) {
            throw new AuthException('Response didn\'t have storage url and auth token');
        }
    }

    private function checkStatusCode(int $statusCode, array $errMap): bool//checkStatus
    {
        if (isset($errMap[$statusCode])) {
            throw new SwiftException($errMap[$statusCode], $statusCode);
        }
        if ($statusCode < 200 || $statusCode > 299) {
            throw new SwiftException(sprintf('HTTP Error: %d', $statusCode), $statusCode);
        }

        return true;
    }

    private function setDefaults()
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

        if (!$this->_client) {
            $this->_client = new HttpClient(
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

    private function unAuthenticate()
    {
        $this->_storageUrl = '';
        $this->_authToken = '';
    }
}
