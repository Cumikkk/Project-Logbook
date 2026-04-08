<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title><?= isset($judul_halaman) ? $judul_halaman . ' — Logbook Magang' : 'Logbook Magang' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/favicon.png">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/bootstrap/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>

<body>

    <!-- Header -->
    <div class="top-header">
        <!-- Hamburger Mobile/Tablet Only -->
        <button class="hamburger-btn d-lg-none" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>

        <!-- Logo Mobile/Tablet Only -->
        <div class="header-brand d-lg-none">
            <img src="<?= BASE_URL ?>assets/img/favicon.png" width="30" alt="Logbook">
            <span class="fw-bold">Logbook Magang</span>
        </div>

        <!-- Empty Space Desktop -->
        <div class="d-none d-lg-block"></div>

        <!-- Logout Button All Devices -->
        <a href="<?= BASE_URL ?>logout.php" class="header-logout" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>

    <div class="d-flex">