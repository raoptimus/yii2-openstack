<?php
namespace raoptimus\openstack;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
abstract class BaseAuth implements AuthInterface
{
    public static function create(Connection $c): AuthInterface
    {
        if ($c->authVersion <= 0) {
            if (stripos($c->authUrl, 'v3') !== false) {
                $c->authVersion = 3;
            } else if (stripos($c->authUrl, 'v2') !== false) {
                $c->authVersion = 2;
            } else if (stripos($c->authUrl, 'v1') !== false) {
                $c->authVersion = 1;
            } else {
                throw new AuthException('Can\'t find AuthVersion in AuthUrl - set explicitly', 500);
            }
        }

        switch ($c->authVersion) {
            case 1:
                return new V1Auth();
            case 2:
                return new V2Auth(strlen($c->apiKey) >= 32);
            case 3:
                return new V3Auth();
            default:
                throw new AuthException(sprintf('Auth Version %d not supported', $c->authVersion), 500);
        }
    }
}
