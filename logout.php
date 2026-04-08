<?php
require_once "config/koneksi.php";
session_unset();
session_destroy();
header("Location: " . BASE_URL . "auth/login.php?logout=success");
exit;
