<?php
namespace raoptimus\openstack;

use GuzzleHttp\Client;
use GuzzleHttp\Ring\Client\StreamHandler;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class HttpClient extends Client
{
    public function __construct(array $config)
    {
        if (!isset($config['handler'])) {
            $config['handler'] = new StreamHandler();
        }

        parent::__construct($config);
    }
}
