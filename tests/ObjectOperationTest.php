<?php

namespace raoptimus\openstack\tests;

use GuzzleHttp\Stream\Stream;
use raoptimus\openstack\HttpCode;

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
        $stream = Stream::factory('');
        $client = $this->mockHttpClient($stream, HttpCode::OK);
        $connection = $this->mockConnection(
            [
                'getClient' => $client,
                'getStorageUrl' => 'http://localhost.loc/v1/AUTH_sharedacct',
                'authenticate' => true,
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
        $modifiedDate = date(\DateTime::COOKIE, time() - 86400);
        $createdAt = date(\DateTime::COOKIE);
        $size = filesize($sourceFilename);
        $contentType = 'text/plain';

        $stream = Stream::factory('');
        $client = $this->mockHttpClient(
            $stream,
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
                'getClient' => $client,
                'authenticate' => true,
                'getStorageUrl' => 'http://localhost.loc/v1/AUTH_sharedacct',
            ],
            [
                'authUrl' => $authUrl,
            ]
        );

        $pushedFile = $connection
            ->getContainer('test')
            ->pushObject($sourceFilename, 'target');

        self::assertEquals($pushedFile->createdAt, new \DateTime($createdAt));
        self::assertEquals($pushedFile->lastModified, new \DateTime($modifiedDate));
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

        $stream = new Stream(fopen($sourceFilename, 'rb'));
        $client = $this->mockHttpClient($stream, HttpCode::OK, $headers);
        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $connection = $this->mockConnection(
            [
                'getClient' => $client,
                'authenticate' => true,
                'getStorageUrl' => 'http://localhost.loc/v1/AUTH_sharedacct',
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

        $stream = Stream::factory('');
        $client = $this->mockHttpClient($stream, HttpCode::OK, $headers);

        $authUrl = 'https://localhost.loc:5000/v' . $version . '.0';
        $connection = $this->mockConnection(
            [
                'getClient' => $client,
                'authenticate' => true,
                'getStorageUrl' => 'http://localhost.loc/v1/AUTH_sharedacct',
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
        ];
    }
}
