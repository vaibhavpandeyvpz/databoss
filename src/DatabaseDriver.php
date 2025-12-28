<?php

declare(strict_types=1);

/*
 * This file is part of vaibhavpandeyvpz/databoss package.
 *
 * (c) Vaibhav Pandey <contact@vaibhavpandey.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Databoss;

/**
 * Enum DatabaseDriver
 *
 * Represents supported database drivers for the connection.
 */
enum DatabaseDriver: string
{
    /** MySQL/MariaDB database driver */
    case MYSQL = 'mysql';

    /** PostgreSQL database driver */
    case POSTGRES = 'pgsql';

    /** SQLite database driver */
    case SQLITE = 'sqlite';
}
