<?php

$host = "127.0.0.1";
$user = "root";
$pass = "";
$db = "repair_system";
$port = 3307;

$conn = new mysqli(
    $host,
    $user,
    $pass,
    $db,
    $port
);

if ($conn->connect_error) {
    die(
        "資料庫連線失敗：" .
        $conn->connect_error
    );
}

/*
    中文及特殊符號使用 utf8mb4
*/
$conn->set_charset("utf8mb4");

?>