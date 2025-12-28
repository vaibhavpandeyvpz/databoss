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
 * Enum EscapeMode
 *
 * Defines the different modes for escaping database identifiers and values.
 */
enum EscapeMode: int
{
    /** Escape mode for column aliases (e.g., "column{alias}") */
    case ALIAS = 1;

    /** Escape mode for column or table names */
    case COLUMN_OR_TABLE = 2;

    /** Escape mode for column names with table prefix (e.g., "table.column") */
    case COLUMN_WITH_TABLE = 3;

    /** Escape mode for SQL values (uses PDO quote) */
    case VALUE = 4;
}
