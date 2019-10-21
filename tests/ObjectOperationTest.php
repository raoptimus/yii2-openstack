<?php

namespace raoptimus\openstack\tests;

use DateTime;
use raoptimus\openstack\HttpCode;
use raoptimus\openstack\HttpContentType;

/**
 * @group ops
 */
class ObjectOperationTest extends BaseTestCase
{
    /**
     * @dataProvider dataProviderVersions
     *
     * @param int $version
     */
    public function testDeleteSuccess(int $version): void
    {
        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $client = $this->mockHttpClient('', HttpCode::OK);
        $connection = $this->mockConnection(
            [
                'getHttpClient' => $client,
                'getStorageUrl' => 'http://localhost.loc/v1/AUTH_sharedacct',
                'authenticate' => true,
                'getAuthToken' => '',
            ],
            [
                'authUrl' => $authUrl,
            ]
        );
        $result = $connection->getContainer('test')->deleteObject('filename');
        self::assertTrue($result);
    }

    /**
     * @dataProvider dataProviderVersions
     *
     * @param int $version
     */
    public function testPushSuccess(int $version): void
    {
        $sourceFilename = $this->getDataPath() . '/source.txt';
        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $etag = hash_file('md5', $sourceFilename);
        $modifiedDate = date(DateTime::COOKIE, time() - 86400);
        $createdAt = date(DateTime::COOKIE);
        $size = filesize($sourceFilename);
        $contentType = HttpContentType::TEXT;

        $client = $this->mockHttpClient(
            '',
            HttpCode::OK,
            [
                'Etag' => $etag,
                'Content-Type' => $contentType,
                'Content-Length' => $size,
                'Last-Modified' => $modifiedDate,
                'Date' => $createdAt,
            ]
        );
        $connection = $this->mockConnection(
            [
                'getHttpClient' => $client,
                'authenticate' => true,
                'getStorageUrl' => 'http://localhost.loc/v1/AUTH_sharedacct',
                'getAuthToken' => '',
            ],
            [
                'authUrl' => $authUrl,
            ]
        );

        $pushedFile = $connection
            ->getContainer('test')
            ->pushObject($sourceFilename, 'target');

        self::assertEquals($pushedFile->createdAt, new DateTime($createdAt));
        self::assertEquals($pushedFile->lastModified, new DateTime($modifiedDate));
        self::assertEquals($pushedFile->size, $size);
        self::assertEquals($pushedFile->mimeType, $contentType);
        self::assertEquals($pushedFile->hash, $etag);
    }

    /**
     * @dataProvider dataProviderVersions
     *
     * @param int $version
     */
    public function testPullSuccess(int $version): void
    {
        $targetFilename = $this->getTempPath() . '/tempfile.txt';
        $sourceFilename = $this->getDataPath() . '/source.txt';
        $headers = $this->getHeaderByFilename($sourceFilename);

        $stream = fopen($sourceFilename, 'rb');
        $client = $this->mockHttpClient($stream, HttpCode::OK, $headers);
        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $connection = $this->mockConnection(
            [
                'getHttpClient' => $client,
                'authenticate' => true,
                'getStorageUrl' => 'http://localhost.loc/v1/AUTH_sharedacct',
                'getAuthToken' => '',
            ],
            [
                'authUrl' => $authUrl,
            ]
        );

        $pulledFile = $connection
            ->getContainer('test')
            ->pullObject($sourceFilename, $targetFilename);

        self::assertFileStatEquals($pulledFile, $headers);
    }

    /**
     * @dataProvider dataProviderVersions
     *
     * @param int $version
     */
    public function testPullStatSuccess(int $version): void
    {
        $sourceFilename = $this->getDataPath() . '/source.txt';
        $headers = $this->getHeaderByFilename($sourceFilename);

        $client = $this->mockHttpClient('', HttpCode::OK, $headers);

        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $connection = $this->mockConnection(
            [
                'getHttpClient' => $client,
                'authenticate' => true,
                'getStorageUrl' => 'http://localhost.loc/v1/AUTH_sharedacct',
                'getAuthToken' => '',
            ],
            [
                'authUrl' => $authUrl,
            ]
        );

        $receivedFile = $connection
            ->getContainer('test')
            ->getObject('target');

        self::assertFileStatEquals($receivedFile, $headers);
    }

    public function dataProviderVersions(): array
    {
        return [
            'v1' => [1],
            'v2' => [2],
            'v3' => [3],
        ];
    }
}
