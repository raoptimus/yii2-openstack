<?php

namespace raoptimus\openstack;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class AuthFactory
{
    public static function create(Connection $connection, Options $options): AuthInterface
    {
        switch ($options->authVersion) {
            case 1:
                return new V1Auth($connection, $options);
            case 2:
                $useApiKey = strlen($options->apiKey) >= 32;

                return new V2Auth($useApiKey, $connection, $options);
            case 3:
                return new V3Auth($connection, $options);
            default:
                throw new AuthException(
                    sprintf('auth version %d not supported', $options->authVersion),
                    HttpCode::INTERNAL_SERVER_ERROR
                );
        }
    }
}
