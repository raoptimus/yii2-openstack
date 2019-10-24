<?php

namespace raoptimus\openstack;

use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * This file is part of the raoptimus/yii2-openstack library
 *
 * @copyright Copyright (c) Evgeniy Urvantsev <resmus@gmail.com>
 * @license https://github.com/raoptimus/yii2-openstack/blob/master/LICENSE.md
 * @link https://github.com/raoptimus/yii2-openstack
 */
class Options extends Component
{
    public const DEFAULT_USERAGENT = 'github.com/raoptimus/yii2-openstack/1.0.4';
    private const DEFAULT_RETRIES = 3;

    /**
     * UserName for api
     *
     * @var string
     */
    public $username;
    /**
     * Key for api access
     *
     * @var string
     */
    public $apiKey;
    /**
     * Key for api access/password
     *
     * @var string
     */
    public $password;
    /**
     * @var string auth server url
     */
    public $authUrl;
    /**
     * Retries on error (default is 3)
     *
     * @var integer
     */
    public $retries;
    /**
     * Http User agent (default github.com/raoptimus/yii2-openstack/1.0.1)
     *
     * @var string
     */
    public $userAgent;
    /**
     * Region to use eg "LON", "ORD" - default is use first region (V2 auth only)
     *
     * @var string
     */
    public $region;
    /**
     * Set this to true to use the the internal / service network
     *
     * @var bool
     */
    public $internal = false;
    /**
     * Name of the tenant
     *
     * @var string
     */
    public $tenant;
    /**
     * Id of the tenant (v2 auth only)
     *
     * @var string
     */
    public $tenantId;
    /**
     * Id of the trust (v3 auth only)
     *
     * @var string
     */
    public $trustId;
    /**
     * User's domain name
     *
     * @var string
     */
    public $domain;
    /**
     * User's domain Id
     *
     * @var string
     */
    public $domainId;
    /**
     * Set to 1 or 2 or leave at 0 for autodetect
     *
     * @var integer
     */
    public $authVersion;
    /**
     * Data channel timeout (default 60s)
     *
     * @var integer
     */
    public $timeout = 60;

    public function setDefaults(): void
    {
        if (empty($this->userAgent)) {
            $this->userAgent = self::DEFAULT_USERAGENT;
        }

        if ($this->retries <= 0) {
            $this->retries = self::DEFAULT_RETRIES;
        }

        if ($this->timeout <= 0) {
            $this->timeout = 60;
        }

        if (empty($this->domain)) {
            $this->domain = 'default';
            $this->domainId = 'default';
        }

        if ($this->authVersion <= 0) {
            if (stripos($this->authUrl, 'v3') !== false) {
                $this->authVersion = 3;
            } elseif (stripos($this->authUrl, 'v2') !== false) {
                $this->authVersion = 2;
            } elseif (stripos($this->authUrl, 'v1') !== false) {
                $this->authVersion = 1;
            }
        }

        if (!in_array($this->authVersion ?? 0, [1, 2, 3], true)) {
            throw new InvalidConfigException('invalid auth version');
        }

        $this->authUrl = rtrim($this->authUrl, '/');
    }
}
