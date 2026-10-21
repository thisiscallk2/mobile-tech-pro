<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use mysqli;

/**
 * Base model: provides the shared mysqli connection to subclasses.
 * All writes in subclasses must use prepared statements.
 */
abstract class Model
{
    protected mysqli $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }
}
