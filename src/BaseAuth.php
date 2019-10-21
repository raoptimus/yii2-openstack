<?php
namespace raoptimus\openstack;

use GuzzleHttp\Exception\RequestException;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
abstract class BaseAuth implements AuthInterface
{
    private const AUTH_ERROR_MAP = [
        HttpCode::BAD_REQUEST => HttpCode::CODE_DESC_MAP[HttpCode::BAD_REQUEST],
        HttpCode::UNAUTHORIZED => HttpCode::CODE_DESC_MAP[HttpCode::UNAUTHORIZED],
        HttpCode::FORBIDDEN => HttpCode::CODE_DESC_MAP[HttpCode::FORBIDDEN],
    ];

    /** @var Connection */
    protected $connection;
    /** @var Options */
    protected $options;
    /** @var bool */
    private $isActive = false;

    public function __construct(Connection $connection, Options $options)
    {
        $this->connection = $connection;
        $this->options = $options;
    }

    public function isAuthenticated(): bool
    {
        return $this->isActive;
    }

    public function authenticate(): void
    {
        if ($this->isActive) {
            return;
        }

        try {
            $req = $this->createRequest();
            $httpClient = $this->connection->getHttpClient();
            $resp = $httpClient->send($req);
            $this->processResponse($resp);
            HttpHelper::checkStatusCode(
                $resp->getStatusCode(),
                self::AUTH_ERROR_MAP,
                AuthException::class
            );
            if (!empty($this->getStorageUrl()) && !empty($this->getToken())) {
                $this->isActive = true;

                return;
            }
            throw new AuthException('Response haven`t got a storage url or auth token');
        } catch (RequestException $ex) {
            $msg = self::AUTH_ERROR_MAP[$ex->getCode()] ?? $ex->getMessage();
            throw new AuthException($msg, $ex->getCode());
        }
    }

    public function refresh(): void
    {
        $this->isActive = false;
    }
}
