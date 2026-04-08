<?php
// Cek apakah sesi sudah berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah sudah login
function cekLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

// Cek role pengguna
function cekRole($role_diizinkan)
{
    if ($_SESSION['role'] !== $role_diizinkan) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

// Cek sesi 30 menit tidak aktif
function cekSesi()
{
    if (isset($_SESSION['last_activity'])) {
        $tidak_aktif = time() - $_SESSION['last_activity'];
        if ($tidak_aktif > 1800) {
            session_unset();
            session_destroy();
            header('Location: ' . BASE_URL . 'auth/login.php?timeout=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

// Jalankan semua pengecekan sekaligus
function proteksi($role_diizinkan)
{
    cekLogin();
    cekRole($role_diizinkan);
    cekSesi();
}
