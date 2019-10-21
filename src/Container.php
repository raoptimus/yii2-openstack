<?php
namespace raoptimus\openstack;

use yii\base\Component;
use yii\helpers\Json;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 *
 * @property int count
 * @property int bytes
 * @property string name
 * @property Connection $connection
 */
class Container extends Component
{
    private const CONTAINER_ERROR_MAP = [
        HttpCode::BAD_REQUEST => HttpCode::CODE_DESC_MAP[HttpCode::BAD_REQUEST],
        HttpCode::FORBIDDEN => HttpCode::CODE_DESC_MAP[HttpCode::FORBIDDEN],
        HttpCode::NOT_FOUND => 'Container not found',
        HttpCode::CONFLICT => 'Container is not empty',
    ];
    /**
     * @var bool
     */
    private $loaded;
    /**
     * @var int
     */
    private $internalBytes;
    /**
     * @var int
     */
    private $internalCount;

    public function __construct(array $config)
    {
        if (isset($config['bytes'], $config['count'])) {
            $this->loaded = true;
        }
        parent::__construct($config);
    }

    public function getBytes(): int
    {
        if (!$this->loaded) {
            $this->loadStat();
        }

        return $this->internalBytes;
    }

    public function getCount(): int
    {
        if (!$this->loaded) {
            $this->loadStat();
        }

        return $this->internalCount;
    }

    public function delete(?array $headers = null): Container
    {
        $this->connection->call($this->getRequestOpts('DELETE', $headers));

        return $this;
    }

    public function update(?array $headers = null): Container
    {
        $this->connection->call($this->getRequestOpts('POST', $headers));

        return $this;
    }

    /**
     * @param array|null $headers
     *
     * @return Container[] map containers [name => Container...]
     */
    public function all(?array $headers = null): array
    {
        $containers = [];
        $opts = new RequestOpts(
            [
                'method' => 'GET',
                'parameters' => ['format' => 'json'],
                'headers' => $headers,
                'errorMap' => self::CONTAINER_ERROR_MAP,
            ]
        );
        $resp = $this->connection->call($opts);
        $items = Json::decode($resp->getBody()->getContents(), true);

        foreach ($items as $item) {
            $item['connection'] = $this->connection;
            $containers[$item['name']] = new Container($item);
        }

        return $containers;
    }

    /**
     * @param string $sourceFilename object name
     * @param string|resource $targetFilename local file name
     * @param array|null $headers
     *
     * @return File
     */
    public function pullObject(string $sourceFilename, $targetFilename, ?array $headers = null): File
    {
        return (new File(
            [
                'connection' => $this->connection,
                'container' => $this,
                'name' => $sourceFilename,
            ]
        ))->pull($targetFilename, $headers);
    }

    /**
     * @param resource|string $sourceFilename local file name
     * @param string $targetFilename object name
     * @param array $headers
     *
     * @return File
     */
    public function pushObject($sourceFilename, string $targetFilename, ?array $headers = null): File
    {
        return (new File(
            [
                'connection' => $this->connection,
                'container' => $this,
                'name' => $targetFilename,
            ]
        ))->push($sourceFilename, $headers);
    }

    /**
     * @param string $filename
     * @param array|null $headers
     *
     * @return File
     */
    public function getObject(string $filename, ?array $headers = null): File
    {
        return (new File(
            [
                'connection' => $this->connection,
                'container' => $this,
                'name' => $filename,
            ]
        ))->pullStat($headers);
    }

    /**
     * @param string $filename
     * @param array|null $headers
     *
     * @return bool
     */
    public function existsObject(string $filename, ?array $headers = null): bool
    {
        return (bool)$this->getObject($filename, $headers);
    }

    /**
     * @param string $filename
     * @param array|null $headers
     *
     * @return bool
     */
    public function deleteObject(string $filename, ?array $headers = null): bool
    {
        return (new File(
            [
                'connection' => $this->connection,
                'container' => $this,
                'name' => $filename,
            ]
        ))->delete($headers);
    }

    protected function setBytes($v): void
    {
        $this->internalBytes = $v;
    }

    protected function setCount($v): void
    {
        $this->internalCount = $v;
    }

    protected function setName($v): void
    {
        $this->name = $v;
    }

    protected function setConnection(Connection $c): void
    {
        $this->connection = $c;
    }

    private function loadStat(): void
    {
        $resp = $this->connection->call($this->getRequestOpts('HEAD'));
        $h = $resp->getHeaders();
        $this->bytes = $h['X-Container-Bytes-Used'][0];
        $this->count = $h['X-Container-Object-Count'][0];
        $this->loaded = true;
    }

    private function getRequestOpts(string $operation, ?array $headers = null): RequestOpts
    {
        return new RequestOpts(
            [
                'container' => $this->name,
                'method' => $operation,
                'errorMap' => self::CONTAINER_ERROR_MAP,
                'headers' => $headers,
            ]
        );
    }
}
