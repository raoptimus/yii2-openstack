<?php

namespace raoptimus\openstack;

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
    public function getRequest(Connection $c): RequestInterface
    {
        // TODO: Implement getRequest() method.
    }

    public function processResponse(ResponseInterface $resp): void
    {
        // TODO: Implement response() method.
    }

    public function getStorageUrl(bool $internal): string
    {
        // TODO: Implement getStorageUrl() method.
    }

    public function getToken(): string
    {
        // TODO: Implement getToken() method.
    }

    public function getCdnUrl(): string
    {
        // TODO: Implement getCdnUrl() method.
    }
}
