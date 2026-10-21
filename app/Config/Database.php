<?php

declare(strict_types=1);

namespace App\Config;

use mysqli;

/**
 * Single shared mysqli connection built from .env values.
 * mysqli only -- never PDO.
 */
class Database
{
    private static ?mysqli $connection = null;

    public static function connection(): mysqli
    {
        if (self::$connection instanceof mysqli) {
            return self::$connection;
        }

        // We handle errors manually instead of throwing mysqli exceptions.
        mysqli_report(MYSQLI_REPORT_OFF);

        $host = Env::get('DB_HOST', '127.0.0.1');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');
        $name = Env::get('DB_NAME', 'mobiletech_pro');
        $port = (int) (Env::get('DB_PORT', '3306') ?? 3306);
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        $conn = @new mysqli($host, $user, $pass, $name, $port);

        if ($conn->connect_errno) {
            http_response_code(500);
            $debug = Env::get('APP_DEBUG', 'false') === 'true';
            if ($debug) {
                exit('Database connection failed: ' . $conn->connect_error);
            }
            exit('Service temporarily unavailable.');
        }

        $conn->set_charset((string) $charset);
        self::$connection = $conn;

        return $conn;
    }
}
