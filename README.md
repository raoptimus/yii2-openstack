[![Stable Version](https://poser.pugx.org/raoptimus/yii2-openstack/v/stable)](https://packagist.org/packages/raoptimus/yii2-openstack)
[![Untable Version](https://poser.pugx.org/raoptimus/yii2-openstack/v/unstable)](https://packagist.org/packages/raoptimus/yii2-openstack)
[![License](https://poser.pugx.org/raoptimus/yii2-openstack/license)](https://packagist.org/packages/raoptimus/yii2-openstack)
[![Total Downloads](https://poser.pugx.org/raoptimus/yii2-openstack/downloads)](https://packagist.org/packages/raoptimus/yii2-openstack)
[![Build Status](https://travis-ci.com/raoptimus/yii2-openstack.svg?branch=master)](https://travis-ci.com/raoptimus/yii2-openstack)

# yii2-openstack
Openstack / swift client for Yii2 Framework

## Installation

Install with composer:

```bash
composer require raoptimus/yii2-openstack
```

## Usage samples

Configuration

```php
$swift = new raoptimus\openstack\Connection(
    new raoptimus\openstack\Options(
        [
            'authUrl' => 'https://somedomain.com:5000/v2.0',
            'username' => '',
            'apiKey' => '',
            'tenant' => '',
            'domain' => '',
            'domainId' => '',
        ]
    )
);
$container = $swift->getContainer('name of container');
```

Use connection

```php
$swift = \Yii::$app->get('swift');
$container = $swift->getContainer('name of container');
```

Push file to swift storage
```php
$container->pushObject($source, $target);
```

Pull file from swift storage
```php
$container->pullObject($source, $target);
```

Get stat of file from swift storage
```php
$container->getObject($filename);
```

Exists file in swift storage
```php
$container->existsObject($filename);
```

Delete file from swift storage
```php
$container->deleteObject($filename);
```
