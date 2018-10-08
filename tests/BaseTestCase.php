<?php

namespace raoptimus\openstack\tests;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use raoptimus\openstack\Connection;
use raoptimus\openstack\File;
use Yii;
use yii\console\Application;
use yii\helpers\FileHelper;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
abstract class BaseTestCase extends TestCase
{
    protected static function assertFileStatEquals(File $file, array $headers): void
    {
        self::assertEquals($file->createdAt, new \DateTime($headers['Date']));
        self::assertEquals($file->lastModified, new \DateTime($headers['Last-Modified']));
        self::assertEquals($file->size, $headers['Content-Length']);
        self::assertEquals($file->mimeType, $headers['Content-Type']);
        self::assertEquals($file->hash, $headers['Etag']);
    }

    protected function getDataPath(): string
    {
        return __DIR__ . '/_data';
    }

    protected function getTempPath(): string
    {
        return __DIR__ . '/_temp';
    }

    protected function setUp()
    {
        parent::setUp();
        FileHelper::removeDirectory($this->getTempPath());
        FileHelper::createDirectory($this->getTempPath());

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
        FileHelper::removeDirectory($this->getTempPath());
    }

    protected function destroyApplication(): void
    {
        Yii::$app = null;
    }

    /**
     * @param Stream $stream
     * @param int $statusCode
     * @param array $headers
     *
     * @return Client|MockObject
     */
    protected function mockHttpClient(Stream $stream, int $statusCode, array $headers = [])
    {
        $response = new Response($statusCode, $headers, $stream, []);

        $client = $this->getMockBuilder(Client::class)
                       ->setMethods(['send'])
                       ->getMock();
        $client->method('send')->willReturn($response);

        return $client;
    }

    /**
     * @param array $methods
     * @param array $config
     *
     * @return Connection|MockObject
     */
    protected function mockConnection(array $methods, array $config)
    {
        $cn = $this->getMockBuilder(Connection::class)
                   ->setConstructorArgs([$config])
                   ->setMethods(array_keys($methods))
                   ->getMock();
        foreach ($methods as $method => $value) {
            $cn->method($method)->willReturn($value);
        }

        return $cn;
    }

    protected function getDataContents(string $filename): string
    {
        return file_get_contents($this->getDataPath() . '/' . $filename);
    }

    protected function getHeaderByFilename(string $filename): array
    {
        $etag = hash_file('md5', $filename);
        $modifiedDate = date(\DateTime::COOKIE, filemtime($filename));
        $createdAt = date(\DateTime::COOKIE, filectime($filename));
        $size = filesize($filename);
        $contentType = 'text/plain';

        return [
            'Etag' => $etag,
            'Content-Type' => $contentType,
            'Content-Length' => $size,
            'Last-Modified' => $modifiedDate,
            'Date' => $createdAt,
        ];
    }
}
