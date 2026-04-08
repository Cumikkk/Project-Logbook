<?php
require_once "config/koneksi.php";
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === "admin") {
        header("Location: " . BASE_URL . "role/admin/dashboard.php");
    } elseif ($_SESSION['role'] === "manajer") {
        header("Location: " . BASE_URL . "role/manajer/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "role/intern/dashboard.php");
    }
    exit;
}
header("Location: " . BASE_URL . "auth/login.php");
exit;
