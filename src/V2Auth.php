<?php
namespace raoptimus\openstack;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class V2Auth extends BaseAuth
{
    /**
     * @var string
     */
    public $region;
    /**
     * if set will use API key not Password
     *
     * @var bool
     */
    private $useApiKey;
    /**
     * if set won't change useApiKey any more
     *
     * @var bool
     */
    private $useApiKeyOk;
    /**
     * set after first run
     *
     * @var bool
     */
    private $notFirst;
    /**
     * @var array
     */
    private $authResponse;

    public function __construct(bool $useApiKey)
    {
        $this->useApiKey = $useApiKey;
    }

    public function getCdnUrl(): string
    {
        return $this->getEndpointUrl('rax:object-cdn', false);
    }

    public function getRequest(Connection $c): RequestInterface
    {
        $this->region = $c->region;

        if ($this->notFirst && !$this->useApiKeyOk) {
            $this->useApiKey = !$this->useApiKey;
        }

        $this->notFirst = true;
        $body = (object)($this->useApiKey
            ? $this->getAuthRequestRackspace($c)
            : $this->getAuthRequest($c));
        $authUrl = rtrim($c->authUrl, '/') . '/tokens';

        try {
            return $c->getClient()->createRequest(
                'POST',
                $authUrl,
                [
                    'json' => $body,
                    'timeout' => $c->timeout,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'User-Agent' => $c->userAgent,
                    ],
                ]
            );
        } catch (RequestException $ex) {
            throw new AuthException($ex->getMessage(), $ex->getCode());
        }
    }

    public function getStorageUrl(bool $internal): string
    {
        return $this->getEndpointUrl('object-store', $internal);
    }

    public function getToken(): string
    {
        return $this->authResponse['access']['token']['id'];
    }

    public function processResponse(ResponseInterface $resp): void
    {
        $body = $resp->json();

        if ($body && isset($body['access']['serviceCatalog'], $body['access']['token']['id'])) {
            $this->useApiKeyOk = true;
        }

        $this->authResponse = $body;
    }

    private function getAuthRequest(Connection $c): array
    {
        return [
            'auth' => [
                'passwordCredentials' => [
                    'username' => $c->username,
                    'password' => $c->apiKey,
                ],
                'tenantName' => $c->tenant,
                'tenantId' => $c->tenantId,
            ],
        ];
    }

    private function getAuthRequestRackspace(Connection $c): array
    {
        return [
            'auth' => [
                'apiKeyCredentials' => [
                    'username' => $c->username,
                    'apiKey' => $c->apiKey,
                ],
                'tenantName' => $c->tenant,
                'tenantId' => $c->tenantId,
            ],
        ];
    }

    private function getEndpointUrl(string $type, bool $internal): string
    {
        foreach ($this->authResponse['access']['serviceCatalog'] as $catalog) {
            if ($catalog['type'] !== $type) {
                continue;
            }
            foreach ($catalog['endpoints'] as $endpoint) {
                if (empty($this->region) || $endpoint['region'] == $this->region) {
                    if ($internal) {
                        return $endpoint['internalURL'];
                    }

                    return $endpoint['publicURL'];
                }
            }
        }

        return '';
    }
}
