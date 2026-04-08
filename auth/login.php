<?php
require_once __DIR__ . "/../config/koneksi.php";

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

$alert = "";
$alert_type = "";
$redirect = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['aksi']) && $_POST['aksi'] === "login") {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $queryUser = mysqli_query($conn, "SELECT * FROM users WHERE username='$username'");
    $user = mysqli_fetch_assoc($queryUser);

    if ($user) {

        if ($user['status'] !== "aktif") {
            $alert = "Akun anda sedang tidak aktif. Silakan menghubungi admin.";
            $alert_type = "danger";
        } else {

            if ($password === $user['password']) {

                $_SESSION['user_id']       = $user['id'];
                $_SESSION['nama']          = $user['nama'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['email']         = $user['email'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['foto_profil']   = $user['foto_profil'];
                $_SESSION['last_activity'] = time();

                $alert = "Login berhasil. Mengalihkan ke dashboard...";
                $alert_type = "success";

                if ($user['role'] === "admin") {
                    $redirect = BASE_URL . "role/admin/dashboard.php";
                } elseif ($user['role'] === "manajer") {
                    $redirect = BASE_URL . "role/manajer/dashboard.php";
                } else {
                    $redirect = BASE_URL . "role/intern/dashboard.php";
                }
            } else {
                $alert = "Username atau password salah.";
                $alert_type = "warning";
            }
        }
    } else {
        $alert = "Username atau password salah.";
        $alert_type = "warning";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['aksi']) && $_POST['aksi'] === "kirim_otp") {

    $email = mysqli_real_escape_string($conn, $_POST['email']);

    $queryUser = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    $user = mysqli_fetch_assoc($queryUser);

    if ($user) {

        $otp     = rand(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        mysqli_query($conn, "UPDATE users SET otp_code='$otp', otp_expires_at='$expires' WHERE email='$email'");

        require_once __DIR__ . "/../config/mailer.php";
        $kirim = kirimOTP($email, $user['nama'], $otp);

        if ($kirim) {
            $alert = "Kode OTP telah dikirim ke email anda.";
            $alert_type = "success";
        } else {
            $alert = "Gagal mengirim OTP. Silakan coba lagi.";
            $alert_type = "danger";
        }
    } else {
        $alert = "Email tidak ditemukan.";
        $alert_type = "warning";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['aksi']) && $_POST['aksi'] === "verifikasi_otp") {

    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $otp   = mysqli_real_escape_string($conn, $_POST['otp']);

    $queryUser = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    $user = mysqli_fetch_assoc($queryUser);

    if ($user) {

        if ($otp === $user['otp_code'] && date('Y-m-d H:i:s') <= $user['otp_expires_at']) {
            $alert = "OTP valid. Silakan masukkan password baru.";
            $alert_type = "success";
            $_SESSION['reset_email'] = $email;
        } else {
            $alert = "OTP salah atau sudah kedaluwarsa.";
            $alert_type = "danger";
        }
    } else {
        $alert = "Email tidak ditemukan.";
        $alert_type = "warning";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['aksi']) && $_POST['aksi'] === "reset_password") {

    $email    = mysqli_real_escape_string($conn, $_SESSION['reset_email'] ?? '');
    $password = mysqli_real_escape_string($conn, $_POST['password_baru']);

    if ($email) {
        mysqli_query($conn, "UPDATE users SET password='$password', otp_code=NULL, otp_expires_at=NULL WHERE email='$email'");
        unset($_SESSION['reset_email']);
        $alert = "Password berhasil direset. Silakan login.";
        $alert_type = "success";
    } else {
        $alert = "Sesi reset password tidak valid.";
        $alert_type = "danger";
    }
}

if (empty($alert) && isset($_GET['timeout']) && $_GET['timeout'] === "1") {
    $alert = "Sesi anda telah berakhir. Silakan login kembali.";
    $alert_type = "warning";
}

if (empty($alert) && isset($_GET['logout']) && $_GET['logout'] === "success") {
    $alert = "Berhasil logout.";
    $alert_type = "success";
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Login - Logbook Magang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/favicon.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1E88B7, #5BAE3C);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            border-radius: 16px;
            border: none;
            padding: 32px 28px;
        }

        .login-logo {
            width: 80px;
            height: auto;
        }

        .login-title {
            margin-top: 16px;
            margin-bottom: 4px;
            font-size: 22px;
            font-weight: 700;
            color: #1E88B7;
        }

        .login-subtitle {
            font-size: 14px;
            color: #6c757d;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .form-control {
            font-size: 14px;
            padding: 11px 14px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .form-control:focus {
            border-color: #5BAE3C;
            box-shadow: 0 0 0 0.2rem rgba(91, 174, 60, 0.25);
        }

        .btn-login {
            background-color: #1E88B7;
            border: none;
            transition: all 0.3s ease;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 8px;
        }

        .btn-login:hover {
            background-color: #156b8c;
        }

        .btn-login:focus,
        .btn-login:focus-visible {
            background-color: #156b8c !important;
            color: #ffffff !important;
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(30, 136, 183, 0.4);
        }

        .btn-login.loading {
            pointer-events: none;
            opacity: 0.95;
        }

        .btn-login .spinner-border {
            width: 18px;
            height: 18px;
            border-width: 2px;
        }

        .link-lupa {
            font-size: 13px;
            color: #1E88B7;
            text-decoration: none;
            cursor: pointer;
        }

        .link-lupa:hover {
            text-decoration: underline;
            color: #156b8c;
        }

        .link-kembali {
            font-size: 13px;
            color: #6c757d;
            text-decoration: none;
            cursor: pointer;
        }

        .link-kembali:hover {
            color: #343a40;
            text-decoration: underline;
        }

        .custom-alert {
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.6s cubic-bezier(.4, 0, .2, 1);
        }

        .custom-alert.show {
            max-height: 200px;
            opacity: 1;
            transform: translateY(0);
        }

        .custom-alert.hide {
            max-height: 0;
            opacity: 0;
            transform: translateY(-6px);
            margin-bottom: 0 !important;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 14px;
            font-size: 14px;
        }

        .input-password-wrapper {
            position: relative;
        }

        .input-password-wrapper .form-control {
            padding-right: 42px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0;
            color: #6c757d;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
        }

        .toggle-password:hover {
            color: #343a40;
        }

        @media (max-width: 575px) {
            .login-wrapper {
                padding: 16px;
            }

            .login-card {
                max-width: 100%;
                padding: 28px 24px;
                border-radius: 14px;
            }

            .login-logo {
                width: 65px;
            }

            .login-title {
                font-size: 19px;
                margin-top: 14px;
            }

            .login-subtitle {
                font-size: 13px;
            }

            .form-control {
                padding: 10px 12px;
            }

            .btn-login {
                font-size: 14px;
                padding: 11px 18px;
            }
        }

        @media (min-width: 576px) and (max-width: 991px) {
            .login-wrapper {
                align-items: start;
                padding-top: 15vh;
                padding-bottom: 15vh;
            }

            .login-card {
                max-width: 420px;
            }

            .login-logo {
                width: 75px;
            }

            .login-title {
                font-size: 21px;
            }
        }

        @media (min-width: 992px) and (max-width: 1199px) {
            .login-card {
                max-width: 440px;
                padding: 36px 32px;
            }

            .login-logo {
                width: 85px;
            }

            .login-title {
                font-size: 23px;
            }
        }

        @media (min-width: 1200px) {
            .login-card {
                max-width: 460px;
                padding: 38px 34px;
            }

            .login-logo {
                width: 90px;
            }

            .login-title {
                font-size: 24px;
            }

            .form-control {
                padding: 12px 15px;
            }

            .btn-login {
                font-size: 16px;
                padding: 13px 22px;
            }
        }
    </style>
</head>

<body>

    <div class="login-wrapper">
        <div class="card login-card shadow">

            <div class="text-center mb-4">
                <img src="<?= BASE_URL ?>assets/img/favicon.png" class="login-logo" alt="Logbook">
                <h4 class="login-title">Logbook Magang</h4>
                <small class="login-subtitle">Silakan login untuk melanjutkan</small>
            </div>

            <?php if ($alert): ?>
                <div id="alertContainer" class="custom-alert mb-3">
                    <div class="alert alert-<?= $alert_type ?> d-flex align-items-center shadow-sm mb-0" role="alert">
                        <i class="bi <?= $alert_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2 flex-shrink-0"></i>
                        <div><?= $alert ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Login -->
            <div id="formLogin">
                <form method="POST" id="loginForm" autocomplete="off">
                    <input type="hidden" name="aksi" value="login">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Password</label>
                        <div class="input-password-wrapper">
                            <input type="password" name="password" id="inputPassword" class="form-control" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('inputPassword', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-4 text-end">
                        <a class="link-lupa" onclick="tampilForm('formLupaPassword')">Lupa Password?</a>
                    </div>
                    <button type="submit" id="loginBtn" class="btn btn-login w-100">
                        <span class="btn-text">Login</span>
                    </button>
                </form>
            </div>

            <!-- Form Lupa Password -->
            <div id="formLupaPassword" style="display:none;">
                <form method="POST" id="lupaForm" autocomplete="off">
                    <input type="hidden" name="aksi" value="kirim_otp">
                    <p class="text-muted mb-3" style="font-size:13px;">Masukkan email anda. Kami akan mengirimkan kode OTP untuk reset password.</p>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-login w-100 mb-3">Kirim OTP</button>
                    <div class="text-center">
                        <a class="link-kembali" onclick="tampilForm('formLogin')">← Kembali ke Login</a>
                    </div>
                </form>
            </div>

            <!-- Form Verifikasi OTP -->
            <div id="formVerifikasiOTP" style="display:none;">
                <form method="POST" id="otpForm" autocomplete="off">
                    <input type="hidden" name="aksi" value="verifikasi_otp">
                    <input type="hidden" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    <p class="text-muted mb-3" style="font-size:13px;">Masukkan kode OTP 6 digit yang telah dikirim ke email anda. Kode berlaku selama 10 menit.</p>
                    <div class="mb-3">
                        <label class="form-label">Kode OTP</label>
                        <input type="text" name="otp" class="form-control" maxlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-login w-100 mb-3">Verifikasi OTP</button>
                    <div class="text-center">
                        <a class="link-kembali" onclick="tampilForm('formLupaPassword')">← Kembali</a>
                    </div>
                </form>
            </div>

            <!-- Form Reset Password -->
            <div id="formResetPassword" style="display:none;">
                <form method="POST" id="resetForm" autocomplete="off">
                    <input type="hidden" name="aksi" value="reset_password">
                    <p class="text-muted mb-3" style="font-size:13px;">Masukkan password baru anda.</p>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <div class="input-password-wrapper">
                            <input type="password" name="password_baru" id="inputPasswordBaru" class="form-control" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('inputPasswordBaru', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login w-100">Simpan Password</button>
                </form>
            </div>

        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        function tampilForm(id) {
            const forms = ['formLogin', 'formLupaPassword', 'formVerifikasiOTP', 'formResetPassword'];
            forms.forEach(f => {
                document.getElementById(f).style.display = 'none';
            });
            document.getElementById(id).style.display = 'block';
        }

        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        function activateAlertAnimation(element, autoHide = true) {
            setTimeout(() => {
                element.classList.add("show");
            }, 50);

            if (autoHide) {
                setTimeout(() => {
                    element.classList.remove("show");
                    element.classList.add("hide");
                }, 4500);
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const alertContainer = document.getElementById("alertContainer");
            if (alertContainer) {
                activateAlertAnimation(alertContainer, true);
            }

            <?php if (isset($_POST['aksi'])): ?>
                <?php if ($_POST['aksi'] === 'kirim_otp' && $alert_type === 'success'): ?>
                    tampilForm('formVerifikasiOTP');
                <?php elseif ($_POST['aksi'] === 'verifikasi_otp' && $alert_type === 'success'): ?>
                    tampilForm('formResetPassword');
                <?php elseif ($_POST['aksi'] === 'kirim_otp' && $alert_type !== 'success'): ?>
                    tampilForm('formLupaPassword');
                <?php elseif ($_POST['aksi'] === 'verifikasi_otp' && $alert_type !== 'success'): ?>
                    tampilForm('formVerifikasiOTP');
                <?php elseif ($_POST['aksi'] === 'reset_password' && $alert_type !== 'success'): ?>
                    tampilForm('formResetPassword');
                <?php endif; ?>
            <?php endif; ?>
        });

        const loginForm = document.getElementById("loginForm");
        const loginBtn = document.getElementById("loginBtn");

        loginForm.addEventListener("submit", function() {
            loginBtn.classList.add("loading");
            loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm text-light me-2"></span>Memproses...';
        });

        <?php if ($redirect): ?>
            loginBtn.classList.add("loading");
            loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm text-light me-2"></span>Mengalihkan...';
            setTimeout(() => {
                window.location.href = "<?= $redirect ?>";
            }, 2000);
        <?php endif; ?>
    </script>

</body>

</html>