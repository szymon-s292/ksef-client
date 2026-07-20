<?php
namespace KSeFClient;

class Database {
    private static ?\mysqli $instance = null;

    public static function getConnection(): \mysqli {
        if (self::$instance === null) {
            if(!defined('KSEF_DB_HOST') || !defined('KSEF_DB_USER') || !defined('KSEF_DB_PASS') || !defined('KSEF_DB_NAME')) {
                throw new \Exception("Database configuration constants are not defined.");
            }

            self::$instance = new \mysqli(
                KSEF_DB_HOST,
                KSEF_DB_USER,
                KSEF_DB_PASS,
                KSEF_DB_NAME
            );

            if (self::$instance->connect_error) {
                throw new \Exception("Database connection error: " . self::$instance->connect_error);
            }
        }

        return self::$instance;
    }
}