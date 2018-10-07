<?php

namespace raoptimus\openstack\tests;

use Yii;
use yii\console\Application;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
    }

    protected function mockApplication(): void
    {
        new Application(
            [
                'id' => 'testapp',
                'basePath' => __DIR__,
                'vendorPath' => \dirname(__DIR__) . '/vendor',
                'runtimePath' => __DIR__ . '/runtime',
            ]
        );
    }

    protected function tearDown()
    {
        $this->destroyApplication();
        parent::tearDown();
    }

    protected function destroyApplication(): void
    {
        Yii::$app = null;
    }
}
