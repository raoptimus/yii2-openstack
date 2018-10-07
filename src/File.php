<?php
namespace raoptimus\openstack;

use yii\base\Component;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 *
 * @property string name
 * @property Container container
 * @property Connection connection
 * @property string hash
 * @property \DateTime lastModified
 * @property \DateTime createdAt
 * @property string mimeType
 * @property int size
 */
class File extends Component
{
    private static $_objectErrorMap = [
        '400' => 'Bad Request',
        '403' => 'Operation forbidden',
        '404' => 'Object Not Found',
        '413' => 'Too Large Object',
        '422' => 'Object Corrupted',
    ];

    private static function getFileHandler($file, bool $isRead = true)
    {
        if (is_string($file)) {
            return fopen($file, $isRead ? 'r' : 'w');
        }

        if (is_resource($file)) {
            return $file;
        }

        throw new SwiftException('Can\'t read the file ' . var_export($file, true));
    }

    /**
     * @param array|null $headers
     *
     * @return bool
     * @throws SwiftException
     */
    public function delete(array $headers = null): bool
    {
        try {
            $this->connection->call($this->getRequestOpts('DELETE', $headers));
        } catch (SwiftException $ex) {
            if ($ex->getCode() === 404) {
                return true; //already removed
            }
            throw $ex;
        }

        return true;
    }

    public function copy($dstFilename, $dstContainerName = null, array $headers = null)
    {
        $headers = array_merge(
            $headers ?? [],
            [
                'Destination' => $dstContainerName ?? $this->container->name . '/' . $dstFilename,
            ]
        );
        $this->connection->call($this->getRequestOpts('COPY', $headers));

        return $this;
    }

    public function move($dstFilename, $dstContainerName = null, ?array $headers = null)
    {
        $container =
            ($dstContainerName ?? $this->container->name) === $this->container->name
                ? $this->container
                : $this->connection->getContainer($dstContainerName);

        $this->copy($dstFilename, $dstContainerName)->delete();
        $this->name = $dstFilename;
        $this->container = $container;

        return $this;
    }

    /**
     * ObjectUpdate adds, replaces or removes object metadata.
     *
     * @param array|null $headers
     *
     * @throws SwiftException
     */
    public function update(?array $headers = null)
    {
        $this->connection->call($this->getRequestOpts('POST', $headers));
    }

    /**
     * @param resource|string $targetFilename
     * @param array|null $headers
     *
     * @return File
     */
    public function pull($targetFilename, ?array $headers = null): File
    {
        $fs = self::getFileHandler($targetFilename, false);
        $opts = $this->getRequestOpts('GET', $headers);
        $resp = $this->connection->call($opts);
        stream_copy_to_stream($resp->getBody()->detach(), $fs);
        fclose($fs);
        $h = $resp->getHeaders();
        $this->parseHeaders($h);

        $hash = hash_file('md5', $targetFilename);
        $type = mime_content_type($targetFilename);

        if ($hash !== $this->hash) {
            unlink($targetFilename);
            throw new SwiftException(self::$_objectErrorMap[422] . ' / hash isn\'t valid', 422);
        }

        if ($this->mimeType !== $type) {
            unlink($targetFilename);
            throw new SwiftException(self::$_objectErrorMap[422] . ' / type isn\'t valid', 422);
        }

        return $this;
    }

    /**
     * @param resource|string $sourceFilename
     * @param array|null $headers
     *
     * @return File
     */
    public function push($sourceFilename, array $headers = null): File
    {
        $fs = self::getFileHandler($sourceFilename);
        //$path = stream_get_meta_data($fs)['uri'];
        $size = fstat($fs)['size'];
        $type = mime_content_type($fs);
        $ctx = hash_init('md5');
        hash_update_stream($ctx, $fs);
        $hash = hash_final($ctx);

        $extraHeaders = [
            'Content-Type' => $type,
            'Content-Length' => $size,
            'Etag' => $hash,
        ];
        $headers = array_merge($headers, $extraHeaders);
        $opts = $this->getRequestOpts('PUT', $headers);
        $opts->body = $fs;
        $this->connection->call($opts);

        //todo pullStat only for method's calls getSize, getHash etc...
        $this->pullStat();

        return $this;
    }

    public function pullStat(array $headers = null): File
    {
        try {
            $resp = $this->connection->call($this->getRequestOpts('HEAD', $headers));
            $h = $resp->getHeaders();
            $this->parseHeaders($h);

            return $this;
        } catch (SwiftException $ex) {
            if ($ex->getCode() == 404) {
                return null;
            }
            throw $ex;
        }
    }

    public function asArray()
    {
        return [
            'name' => $this->name,
            'hash' => $this->hash,
            'lastModified' => $this->lastModified->format(\DateTime::RFC822),
            'createdAt' => $this->createdAt->format(\DateTime::RFC822),
            'size' => $this->size,
        ];
    }

    protected function setName(string $name)
    {
        $this->name = $name;
    }

    protected function setHash(string $v)
    {
        $this->hash = $v;
    }

    protected function setMimeType(string $v)
    {
        $this->mimeType = $v;
    }

    protected function setLastModified(\DateTime $v)
    {
        $this->lastModified = $v;
    }

    protected function setCreatedAt(\DateTime $v)
    {
        $this->createdAt = $v;
    }

    protected function setSize(int $v)
    {
        $this->size = $v;
    }

    protected function setConnection(Connection $c)
    {
        $this->connection = $c;
    }

    protected function setContainer(Container $c)
    {
        $this->container = $c;
    }

    private function parseHeaders(array $h)
    {
        $this->hash = $h['Etag'][0];
        $this->lastModified = new \DateTime($h['Last-Modified'][0]);
        $this->mimeType = $h['Content-Type'][0];
        $this->createdAt = new \DateTime($h['Date'][0]);
        $this->size = $h['Content-Length'][0];
    }

    private function getRequestOpts(string $operation, array $headers = null): RequestOpts
    {
        return new RequestOpts(
            [
                'operation' => $operation,
                'container' => $this->container->name,
                'objectName' => $this->name,
                'headers' => $headers,
                'errorMap' => self::$_objectErrorMap,
            ]
        );
    }
}
