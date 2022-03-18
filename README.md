ProxmoxVE API Client
====================

This **PHP 5.5+** library allows you to interact with your Proxmox server via API.

Installation
------------

Recommended installation is using [Composer], if you do not have [Composer] what are you waiting?

In the root of your project execute the following:

```sh
$ composer require klauke-enterprises/proxmoxve ~5.0.0
```

Or add this to your `composer.json` file:

```json
{
    "require": {
        "klauke-enterprises/proxmoxve": "~5.0.0"
    }
}
```

Then perform the installation:
```sh
$ composer install --no-dev
```


Usage
-----

```php
<?php

// Require the autoloader
require_once 'vendor/autoload.php';

// Use the library namespace
use ProxmoxVE\Proxmox;

// Then simply pass your credentials when creating the API client object.
$proxmox = new Proxmox(
    "felix@klauke-enterprises.com!myTokenId", // Your API Token Id
    "9bb6dea8-b41b-479d-806f-cfd86f983f67" // Your API Token Secret
);

$allNodes = $proxmox->get('/nodes');

print_r($allNodes);
```


Sample output:

```php
Array
(
    [data] => Array
        (
            [0] => Array
                (
                    [disk] => 2539465464
                    [cpu] => 0.031314446882002
                    [maxdisk] => 30805066770
                    [maxmem] => 175168446464
                    [node] => mynode1
                    [maxcpu] => 24
                    [level] =>
                    [uptime] => 139376
                    [id] => node/mynode1
                    [type] => node
                    [mem] => 20601992182
                )

        )

)
```

License
-------

This project is released under the MIT License. See the bundled [LICENSE] file for details.

[LICENSE]:./LICENSE
