<?php
namespace raoptimus\openstack;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use PHPUnit\Runner\Exception;

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
        return $this->headers['X-CDN-Management-Url'] ?? '';
    }

    public function getRequest(Connection $c): RequestInterface
    {
        try {
            return $c->getClient()->createRequest(
                'GET',
                $c->authUrl,
                [
                    'timeout' => $c->timeout,
                    'headers' => [
                        'User-Agent' => $c->userAgent,
                        'X-Auth-Key' => $c->apiKey,
                        'X-Auth-User' => $c->username,
                    ],
                ]
            );
        } catch (Exception $ex) {
            throw new AuthException($ex->getMessage(), 500);
        }
    }

    public function response(ResponseInterface $resp): void
    {
        $this->headers = $resp->getHeaders();
    }

    public function getStorageUrl(bool $internal): string
    {
        $storageUrl = $this->headers['X-Storage-Url'] ?? '';
        if ($internal) {
            $newUrl = parse_url($storageUrl);
            $newUrl['host'] = 'snet-' . $newUrl['host'];
            $storageUrl = Connection::buildUrl($newUrl);
        }

        return $storageUrl;
    }

    public function getToken(): string
    {
        return $this->headers['X-Auth-Token'] ?? '';
    }
}
