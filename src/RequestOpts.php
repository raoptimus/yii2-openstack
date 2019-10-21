<?php
namespace raoptimus\openstack;

use Psr\Http\Message\StreamInterface;
use yii\base\BaseObject;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class RequestOpts extends BaseObject
{
    /**
     * @var string
     */
    public $container;
    /**
     * @var string
     */
    public $objectName;
    /**
     * @var string HEAD GET POST PUT etc
     */
    public $method;
    /**
     * @var array
     */
    public $parameters;
    /**
     * @var array
     */
    public $headers;
    /**
     * @var array
     */
    public $errorMap;
    /**
     * @var StreamInterface
     */
    public $body;
    /**
     * @var int
     */
    public $retries;
}
