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

    public function __construct(bool $useApiKey, Connection $connection, Options $options)
    {
        parent::__construct($connection, $options);
        $this->useApiKey = $useApiKey;
    }

    public function getCdnUrl(): string
    {
        return $this->getEndpointUrl('rax:object-cdn', false);
    }

    public function createRequest(): RequestInterface
    {
        $this->region = $this->options->region;

        if ($this->notFirst && !$this->useApiKeyOk) {
            $this->useApiKey = !$this->useApiKey;
        }

        $this->notFirst = true;
        $body = (object)($this->useApiKey
            ? $this->buildRequestRackspaceBody()
            : $this->buildRequestBody());
        $authUrl = $this->options->authUrl . '/tokens';
        $headers = ['Content-Type' => 'application/json'];

        return new Request(HttpMethod::POST, $authUrl, $headers, Json::encode($body));
    }

    public function getStorageUrl(): string
    {
        return $this->getEndpointUrl('object-store', $this->options->internal);
    }

    public function getToken(): string
    {
        return $this->authResponse['access']['token']['id'];
    }

    public function processResponse(ResponseInterface $resp): void
    {
        $content = $resp->getBody()->getContents();
        $body = Json::decode($content);

        if ($body && isset($body['access']['serviceCatalog'], $body['access']['token']['id'])) {
            $this->useApiKeyOk = true;
        }

        $this->authResponse = $body;
    }

    private function buildRequestBody(): array
    {
        return [
            'auth' => [
                'passwordCredentials' => [
                    'username' => $this->options->username,
                    'password' => $this->options->apiKey,
                ],
                'tenantName' => $this->options->tenant,
                'tenantId' => $this->options->tenantId,
            ],
        ];
    }

    private function buildRequestRackspaceBody(): array
    {
        return [
            'auth' => [
                'apiKeyCredentials' => [
                    'username' => $this->options->username,
                    'apiKey' => $this->options->apiKey,
                ],
                'tenantName' => $this->options->tenant,
                'tenantId' => $this->options->tenantId,
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
