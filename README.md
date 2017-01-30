# vaibhavpandeyvpz/databoss
Simple abstraction for [MySQL](https://www.mysql.com/)/[MariaDB](https://mariadb.org/) and [PostgreSQL](https://www.postgresql.org/) database server(s), compatible with [PHP](http://www.php.net/) >= 5.3.

[![Build status][build-status-image]][build-status-url]
[![Code Coverage][code-coverage-image]][code-coverage-url]
[![Latest Version][latest-version-image]][latest-version-url]
[![Downloads][downloads-image]][downloads-url]
[![PHP Version][php-version-image]][php-version-url]
[![License][license-image]][license-url]

[![SensioLabsInsight][insights-image]][insights-url]

Install
-------
```bash
composer require vaibhavpandeyvpz/databoss
```

Usage
-----
```php
<?php

$db = new Databoss\Connection([
    Databoss\Connection::OPT_DATABASE => 'test',
    Databoss\Connection::OPT_USERNAME => 'root',
    Databoss\Connection::OPT_PASSWORD => '12345678',
]);

/**
 * @desc Simplest it can be
 */
$track = $db->first('music', [
    'artist' => 'Tyga',
    'duration{>}' => 180,
]);
```

Documentation
-------
To view installation and usage instructions, visit this [Wiki](https://github.com/vaibhavpandeyvpz/databoss/wiki).

License
-------
See [LICENSE.md][license-url] file.

[build-status-image]: https://img.shields.io/travis/vaibhavpandeyvpz/databoss.svg?style=flat-square
[build-status-url]: https://travis-ci.org/vaibhavpandeyvpz/databoss
[code-coverage-image]: https://img.shields.io/codecov/c/github/vaibhavpandeyvpz/databoss.svg?style=flat-square
[code-coverage-url]: https://codecov.io/gh/vaibhavpandeyvpz/databoss
[latest-version-image]: https://img.shields.io/github/release/vaibhavpandeyvpz/databoss.svg?style=flat-square
[latest-version-url]: https://github.com/vaibhavpandeyvpz/databoss/releases
[downloads-image]: https://img.shields.io/packagist/dt/vaibhavpandeyvpz/databoss.svg?style=flat-square
[downloads-url]: https://packagist.org/packages/vaibhavpandeyvpz/databoss
[php-version-image]: http://img.shields.io/badge/php-5.3+-8892be.svg?style=flat-square
[php-version-url]: https://packagist.org/packages/vaibhavpandeyvpz/databoss
[license-image]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[license-url]: LICENSE.md
[insights-image]: https://insight.sensiolabs.com/projects/6e4c6fda-7671-4827-807f-060b39970a07/small.png
[insights-url]: https://insight.sensiolabs.com/projects/6e4c6fda-7671-4827-807f-060b39970a07
