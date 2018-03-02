# Streamlike PHP API SDK

## Requirements

PHP needs to be a minimum version of PHP 5.4.0.

## Installation

Download package and include Sdk.php classe.

Or with composer:

`composer require streamlike/php-api-sdk`

## Samples

### Autoloading

```php
<?php

// via composer autoload
require './vendor/autoload.php';

// or basic require
require './src/Streamlike/Api/Sdk.php';
```

### Authentication - Get a session token

```php
<?php

$login = 'myStreamlikeLogin';
$password = 'myStreamlikePassword';

$sdk = new \Streamlike\Api\Sdk();
try {
    $result = $sdk->authenticate($login, $password);

    $sessionToken = $result['token'];

    var_dump($sessionToken);
} catch (\Exception $e) {
    if ($e instanceof Streamlike\Api\Exception\InvalidInputException) {
        print_r($e->getErrors());
    }
}
```

### GET medias list

```php
<?php

$sdk = new \Streamlike\Api\Sdk($sessionToken);
try {
    $result = $sdk->call('medias');

    var_dump($result);
} catch (\Exception $e) {
    // handle errors
}

```

### POST a new media

```php
<?php

$sdk = new \Streamlike\Api\Sdk($sessionToken);
try {
    $data = [
        'name' => 'via sdk',
        'permalink' => 'via-sdk',
        'type' => 'video',
        'visibility' => [
            'state' => 'online',
        ],
    ];

    $files = [
        'source' => [
            'encode' => [
                'media_file' => '/path/to/video/file',
            ],
        ],
    ];

    $result = $sdk->call('medias', 'POST', $data, $files);
} catch (\Exception $e) {
    if ($e instanceof Streamlike\Api\Exception\InvalidInputException) {
        print_r($e->getErrors());
    }
}
```

## Running tests

Do a `git clone` on the Github repository:

```
git clone git@github.com:Streamlike/php-api-sdk.git streamlike-api
cd streamlike-api
```

Install dependencies using composer:

```
composer install
```

Run tests:

```
php vendor/bin/atoum -d tests
```
