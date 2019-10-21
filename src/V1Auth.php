<?php
namespace raoptimus\openstack;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class V1Auth extends BaseAuth
{
    /**
     * @var array
     */
    private $headers;

    public function getCdnUrl(): string
    {
        return $this->headers['X-CDN-Management-Url'][0] ?? '';
    }

    public function createRequest(): RequestInterface
    {
        $headers = [
            'X-Auth-Key' => $this->options->apiKey,
            'X-Auth-User' => $this->options->username,
        ];

        return new Request(HttpMethod::GET, $this->options->authUrl, $headers);
    }

    public function processResponse(ResponseInterface $resp): void
    {
        $this->headers = $resp->getHeaders();
    }

    public function getStorageUrl(): string
    {
        $storageUrl = $this->headers['X-Storage-Url'][0] ?? '';

        if ($this->options->internal) {
            $newUrl = parse_url($storageUrl);
            $newUrl['host'] = 'snet-' . $newUrl['host'];
            $storageUrl = HttpHelper::buildUrl($newUrl);
        }

        return $storageUrl;
    }

    public function getToken(): string
    {
        return $this->headers['X-Auth-Token'][0] ?? '';
    }
}
