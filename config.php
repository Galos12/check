<?php
// Update these values with your MySQL credentials.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'hvac_checklists';
const DB_USER = 'root';
const DB_PASS = 'password';

function get_db_connection(): mysqli
{
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($connection->connect_error) {
        throw new RuntimeException('Database connection failed: ' . $connection->connect_error);
    }

    if (!$connection->set_charset('utf8mb4')) {
        throw new RuntimeException('Unable to set database charset.');
    }

    return $connection;
}
