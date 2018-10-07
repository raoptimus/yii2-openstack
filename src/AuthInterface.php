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
interface AuthInterface
{
    public function getRequest(Connection $c): RequestInterface;

    public function response(ResponseInterface $resp): void;

    public function getStorageUrl(bool $internal): string;

    public function getToken(): string;

    public function getCdnUrl(): string;
}
