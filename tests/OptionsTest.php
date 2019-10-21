<?php

namespace raoptimus\openstack\tests;

use raoptimus\openstack\Connection;
use raoptimus\openstack\Options;
use yii\base\InvalidConfigException;

class OptionsTest extends BaseTestCase
{
    /**
     * @dataProvider dataProviderSupportedVersion
     *
     * @param int $version
     */
    public function testAuthVersionSuccessfully(int $version): void
    {
        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $conn = new Connection(new Options(['authUrl' => $authUrl]));
        self::assertEquals($version, $conn->getOptions()->authVersion);
    }

    public function testAuthVersionFailure(): void
    {
        $this->expectException(InvalidConfigException::class);
        $authUrl = 'https://localhost.loc:5000/v4.0';
        new Connection(new Options(['authUrl' => $authUrl]));
    }

    public function dataProviderSupportedVersion(): array
    {
        return [
            'v1' => [1],
            'v2' => [2],
            'v3' => [3],
        ];
    }
}
