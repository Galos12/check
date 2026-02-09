<?php
// Use a file-based SQLite DB for Fly.io deployment. The DB file lives in /data.
const DB_FILE = '/data/data.sqlite';

function get_db_connection(): PDO
{
    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $dsn = 'sqlite:' . DB_FILE;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo = new PDO($dsn, null, null, $options);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}
