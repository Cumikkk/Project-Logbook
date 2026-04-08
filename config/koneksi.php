<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_logbook";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

date_default_timezone_set("Asia/Jakarta");
session_start();

define('BASE_PATH', dirname(__DIR__) . '/');
define('BASE_URL', '/Project-Logbook/');
