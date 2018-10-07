<?php

namespace raoptimus\openstack;

use yii\base\Component;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class ContainersOpts extends Component
{
    /**
     *  For an integer value n, limits the number of results to at most n values.
     *
     * @var integer
     */
    public $limit;
    /**
     * Given a string value x, return container names matching the specified prefix.
     *
     * @var string
     */
    public $prefix;
    /**
     * Given a string value x, return container names greater in value than the specified marker.
     *
     * @var string
     */
    public $marker;   // string  // Given a string value x, return container names greater in value than the specified marker.
    /**
     * Given a string value x, return container names less in value than the specified marker.
     *
     * @var string
     */
    public $endMarker;
    /**
     * @var array
     */
    public $headers;
}
