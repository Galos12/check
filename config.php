<?php
// Update these values with your MySQL credentials.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'hvac_checklists';
const DB_USER = 'root';
const DB_PASS = 'password';

function get_db_connection(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
