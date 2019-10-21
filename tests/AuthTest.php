<?php

namespace raoptimus\openstack\tests;

use raoptimus\openstack\AuthException;
use raoptimus\openstack\HttpCode;

/**
 * @group auth
 */
class AuthTest extends BaseTestCase
{
    /**
     * @dataProvider dataProviderAuthResponses
     *
     * @param string $actualResponseContent
     * @param int $version
     * @param array $headers
     */
    public function testAuthSuccessfully(string $actualResponseContent, int $version, array $headers): void
    {
        $client = $this->mockHttpClient($actualResponseContent, HttpCode::OK, $headers);
        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';

        $connection = $this->mockConnection(['getHttpClient' => $client], ['authUrl' => $authUrl]);
        $connection->authenticate();

        self::assertEquals('http://localhost.loc/v1/AUTH_sharedacct', $connection->getStorageUrl());
        self::assertEquals('AUTH_tk4602560647c640de86924e2f28716b46', $connection->getAuthToken());
    }

    public function dataProviderAuthResponses(): array
    {
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
                $this->getDataContents('auth_v2.json'),
                2,
                [],
            ],
            'v3' => [
                $this->getDataContents('auth_v3.json'),
                3,
                [
                    'X-Subject-Token' => 'AUTH_tk4602560647c640de86924e2f28716b46',
                ],
            ],
        ];
    }

    /**
     * @group f
     * @param int $version
     * @param int $returnHttpCode
     *
     * @dataProvider dataProviderVersions
     */
    public function testAuthFailure(int $version, int $returnHttpCode): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionCode($returnHttpCode);

        $client = $this->mockHttpClient('', $returnHttpCode);
        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $connection = $this->mockConnection(['getHttpClient' => $client], ['authUrl' => $authUrl]);
        $connection->authenticate();
    }

    public function dataProviderVersions(): array
    {
        return [
            'v1/401' => [1, HttpCode::UNAUTHORIZED],
            'v1/400' => [1, HttpCode::BAD_REQUEST],
            'v2/401' => [2, HttpCode::UNAUTHORIZED],
            'v2/400' => [2, HttpCode::BAD_REQUEST],
            'v2/503' => [2, HttpCode::SERVICE_UNAVAILABLE],
            'v3/401' => [3, HttpCode::UNAUTHORIZED],
            'v3/400' => [3, HttpCode::BAD_REQUEST],
            'v3/503' => [3, HttpCode::SERVICE_UNAVAILABLE],
        ];
    }
}
