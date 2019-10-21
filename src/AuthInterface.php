<?php
namespace raoptimus\openstack;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
interface AuthInterface
{
    public function createRequest(): RequestInterface;

    public function processResponse(ResponseInterface $resp): void;

    public function getStorageUrl(): string;

    public function getToken(): string;

    public function getCdnUrl(): string;

    public function isAuthenticated(): bool;

    public function authenticate(): void;

    public function refresh(): void;
}
