<?php
// ---- EDIT THESE for your server ----
const DB_DSN  = 'mysql:host=localhost;dbname=inventory_db;charset=utf8mb4';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO {
    static $pdo;
    return $pdo ??= new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

const CATEGORIES = ['Electronics', 'Groceries', 'Clothing', 'Stationery', 'Household', 'Other'];
const LOW_STOCK_THRESHOLD = 5;
