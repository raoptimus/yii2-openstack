<?php

namespace raoptimus\openstack;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use yii\helpers\Json;

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

    public function createRequest(): RequestInterface
    {
        $body = (object)$this->buildRequestBody();
        $authUrl = $this->options->authUrl . '/auth/tokens';
        $headers = [
            'Content-Type' => 'application/json',
        ];

        return new Request(HttpMethod::POST, $authUrl, $headers, Json::encode($body));
    }

    public function processResponse(ResponseInterface $resp): void
    {
        $this->headers = $resp->getHeaders();
        $content = $resp->getBody()->getContents();
        $this->authResponse = Json::decode($content);
    }

    public function getStorageUrl(): string
    {
        $interface = $this->options->internal ? 'internal' : 'public';

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

    private function buildRequestBody(): array
    {
        return [
            'auth' => [
                'identity' => [
                    'methods' => ['password'],
                    'password' => [
                        'user' => [
                            'name' => $this->options->username,
                            'password' => $this->options->password,
                            'domain' => [
                                'id' => $this->options->domainId,
                                'name' => $this->options->domain,
                            ],
                        ],
                    ],
                ],
                'scope' => [
                    'project' => [
                        'domain' => [
                            'id' => $this->options->domainId,
                        ],
                        'name' => $this->options->tenant,
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
