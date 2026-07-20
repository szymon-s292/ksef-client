<?php
namespace KSeFClient;

class Database {
    private static ?\mysqli $instance = null;

    public static function getConnection(): \mysqli {
        if (self::$instance === null) {
            $config = require __DIR__ . '/config.php';
            
            self::$instance = new \mysqli(
                $config['db_host'] ?? '127.0.0.1',
                $config['db_user'] ?? 'root',
                $config['db_pass'] ?? '',
                $config['db_name'] ?? 'ksef'
            );

            if (self::$instance->connect_error) {
                throw new \Exception("Database connection error: " . self::$instance->connect_error);
            }
        }

        return self::$instance;
    }
}