<?php
namespace raoptimus\openstack;

use yii\base\Exception;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class SwiftException extends Exception
{
    public function getName()
    {
        return 'SwiftException';
    }
}
