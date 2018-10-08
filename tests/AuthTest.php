<?php

namespace raoptimus\openstack\tests;

use GuzzleHttp\Stream\Stream;
use raoptimus\openstack\AuthException;
use raoptimus\openstack\HttpCode;

class AuthTest extends BaseTestCase
{
    /**
     * @dataProvider dataProviderAuthResponses
     *
     * @param string $actualResponseContent
     * @param int $version
     * @param array $headers
     */
    public function testAuth(string $actualResponseContent, int $version, array $headers): void
    {
        $stream = Stream::factory($actualResponseContent);
        $client = $this->mockHttpClient($stream, HttpCode::OK, $headers);
        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $connection = $this->mockConnection(['getClient' => $client], ['authUrl' => $authUrl]);
        $connection->authenticate();
        self::assertTrue($connection->authenticated());
        self::assertEquals($connection->authVersion, $version);
        self::assertEquals('http://localhost.loc/v1/AUTH_sharedacct', $connection->getStorageUrl());
        self::assertEquals('AUTH_tk4602560647c640de86924e2f28716b46', $connection->getAuthToken());
    }

    public function dataProviderAuthResponses(): array
    {
        $actualResponseContent = $this->getDataContents('auth_v2.json');

        return [
            'v1' => [
                '',
                1,
                [
                    'X-Storage-Url' => 'http://localhost.loc/v1/AUTH_sharedacct',
                    'X-Auth-Token' => 'AUTH_tk4602560647c640de86924e2f28716b46',
                    'X-CDN-Management-Url' => 'AUTH_tk4602560647c640de86924e2f28716b46',
                ],
            ],
            'v2' => [
                $actualResponseContent,
                2,
                [],
            ],
        ];
    }

    /**
     * @param int $version
     * @param int $returnHttpCode
     *
     * @throws AuthException
     * @dataProvider dataProviderVersions
     */
    public function testFailedAuth(int $version, int $returnHttpCode): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionCode($returnHttpCode);

        $stream = Stream::factory('');
        $client = $this->mockHttpClient($stream, $returnHttpCode);
        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $connection = $this->mockConnection(['getClient' => $client], ['authUrl' => $authUrl]);
        $connection->authenticate();
    }

    public function dataProviderVersions(): array
    {
        return [
            'v1/401' => [1, HttpCode::UNAUTHORIZED],
            'v2/401' => [2, HttpCode::UNAUTHORIZED],
            'v1/400' => [1, HttpCode::BAD_REQUEST],
            'v2/400' => [2, HttpCode::BAD_REQUEST],
            'v2/503' => [2, HttpCode::SERVICE_UNAVAILABLE],
        ];
    }
}
