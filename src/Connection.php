<?php
namespace raoptimus\openstack;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class Connection
{
    /**
     * @var AuthInterface
     */
    private $auth;
    /**
     * @var Client
     */
    private $client;
    /**
     * @var Container[]
     */
    private $containers = [];
    /**
     * @var Options
     */
    private $options;

    public function __construct(Options $options)
    {
        $optionsCopy = clone $options;
        $optionsCopy->setDefaults();
        $this->options = $optionsCopy;
        $this->auth = AuthFactory::create($this, $optionsCopy);
        $this->init();
    }

    public function init(): void
    {
        $this->client = new Client(
            [
                'handler' => new StreamHandler(),
                'timeout' => $this->options->timeout,
                'verify' => false,
                'http_errors' => true,
                'headers' => [
                    'User-Agent' => $this->options->userAgent,
                ],
            ]
        );
    }

    public function getStorageUrl(): string
    {
        return $this->auth->getStorageUrl();
    }

    public function getAuthToken(): string
    {
        return $this->auth->getToken();
    }

    public function getContainer(string $name): Container
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
     * @return Container[]
     */
    public function getContainers(): array
    {
        $this->containers = (new Container(['connection' => $this]))->all();

        return array_values($this->containers);
    }

    public function call(RequestOpts $opts): ResponseInterface
    {
        $retries = ($opts->retries > 0) ? $opts->retries : $this->options->retries;
        for (; $retries > 0; $retries--) {
            $this->authenticate();
            $requestUrl = $this->buildRequestUrl($opts);
            $headers = array_merge($opts->headers ?? [], ['X-Auth-Token' => $this->getAuthToken()]);
            $req = new Request($opts->method, $requestUrl, $headers, $opts->body);

            try {
                $resp = $this->getHttpClient()->send($req);
                HttpHelper::checkStatusCode($resp->getStatusCode(), $opts->errorMap);

                return $resp;
            } catch (SwiftException $ex) {
                if ($retries > 1) {
                    if ($ex->getCode() === HttpCode::UNAUTHORIZED) {
                        $this->auth->refresh();
                        continue;
                    }
                    if (in_array($opts->method, [HttpMethod::HEAD, HttpMethod::GET])) {
                        continue;
                    }
                }
                throw $ex;
            } catch (RequestException $ex) {
                if ($retries > 1) {
                    if ($ex->getCode() === HttpCode::UNAUTHORIZED) {
                        $this->auth->refresh();
                        continue;
                    }
                    if (in_array($opts->method, [HttpMethod::HEAD, HttpMethod::GET])) {
                        continue;
                    }
                }
                $msg = $opts->errorMap[$ex->getCode()] ?? $ex->getMessage();
                throw new SwiftException($msg, $ex->getCode());
            }
        }
    }

    public function authenticate(): void
    {
        $this->auth->authenticate();
    }

    public function getHttpClient(): Client
    {
        return $this->client;
    }

    public function getOptions(): Options
    {
        return clone $this->options;
    }

    private function buildRequestUrl(RequestOpts $opts): string
    {
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

        return HttpHelper::buildUrl($storageUrl);
    }
}
