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
class V3Auth extends BaseAuth
{
    /**
     * @var string
     */
    public $region;
    /**
     * @var array
     */
    private $authResponse;
    /**
     * @var array
     */
    private $headers;

    public function getRequest(Connection $c): RequestInterface
    {
//        $this->region = $c->region;

        $body = (object)$this->getAuthRequest($c);
        $authUrl = rtrim($c->authUrl, '/') . '/auth/tokens';

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

    public function processResponse(ResponseInterface $resp): void
    {
        $this->headers = $resp->getHeaders();
        $this->authResponse = $resp->json();
    }

    public function getStorageUrl(bool $internal): string
    {
        $interface = $internal ? 'internal' : 'public';

        return $this->getEndpointUrl('object-store', $interface);
    }

    public function getToken(): string
    {
        return $this->headers['X-Subject-Token'][0];
    }

    public function getCdnUrl(): string
    {
        return '';
    }

    private function getAuthRequest(Connection $c): array
    {
        return [
            'auth' => [
                'identity' => [
                    'methods' => ['password'],
                    'password' => [
                        'user' => [
                            'name' => $c->username,
                            'password' => $c->password,
                            'domain' => [
                                'id' => $c->domainId,
                                'name' => $c->domain,
                            ],
                        ],
                    ],
                ],
                'scope' => [
                    'project' => [
                        'domain' => [
                            'id' => $c->domainId,
                        ],
                        'name' => $c->tenant,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param string $type
     * @param string $interface
     *
     * @return string
     */
    private function getEndpointUrl(string $type, string $interface = 'public'): string
    {
        foreach ($this->authResponse['token']['catalog'] ?? [] as $catalog) {
            if ($catalog['type'] !== $type) {
                continue;
            }
            foreach ($catalog['endpoints'] as $endpoint) {
                if (empty($this->region) || $endpoint['region'] == $this->region) {
                    if ($endpoint['interface'] == $interface) {
                        return $endpoint['url'];
                    }
                }
            }

            foreach ($catalog['endpoints'] as $endpoint) {
                if (empty($this->region) || $endpoint['region'] == $this->region) {
                    return $endpoint['url'];
                }
            }
        }

        return '';
    }
}
