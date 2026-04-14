<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../config/mailer.php";
require_once __DIR__ . "/../../includes/fungsi.php";

proteksi("admin");

// CEK DUPLIKAT USERNAME
if (isset($_POST['aksi']) && $_POST['aksi'] === "cek_duplikat_username") {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $cek = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id != {$_SESSION['user_id']}");
    header('Content-Type: application/json');
    echo json_encode(['duplikat' => mysqli_num_rows($cek) > 0]);
    exit;
}

// CEK DUPLIKAT EMAIL
if (isset($_POST['aksi']) && $_POST['aksi'] === "cek_duplikat_email") {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $cek = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' AND id != {$_SESSION['user_id']}");
    header('Content-Type: application/json');
    echo json_encode(['duplikat' => mysqli_num_rows($cek) > 0]);
    exit;
}

// KIRIM OTP VERIFIKASI EMAIL
if (isset($_POST['aksi']) && $_POST['aksi'] === "kirim_otp_email") {
    $email_baru = mysqli_real_escape_string($conn, trim($_POST['email_baru']));
    $cek = mysqli_query($conn, "SELECT id FROM users WHERE email='$email_baru' AND id != {$_SESSION['user_id']}");
    if (mysqli_num_rows($cek) > 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'gagal', 'pesan' => 'Email sudah digunakan akun lain.']);
        exit;
    }
    $otp        = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    mysqli_query($conn, "UPDATE users SET otp_code='$otp', otp_expires_at='$expires_at' WHERE id={$_SESSION['user_id']}");
    $_SESSION['pending_email']      = $email_baru;
    $_SESSION['pending_nama']       = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $_SESSION['pending_username']   = mysqli_real_escape_string($conn, trim($_POST['username']));
    $_SESSION['pending_foto']       = $_POST['foto_crop_data'] ?? '';
    $kirim = kirimOTPVerifikasiEmail($email_baru, $_SESSION['nama'], $otp);
    header('Content-Type: application/json');
    if ($kirim) {
        echo json_encode(['status' => 'berhasil']);
    } else {
        echo json_encode(['status' => 'gagal', 'pesan' => 'Gagal mengirim email. Periksa koneksi atau konfigurasi mailer.']);
    }
    exit;
}

// VERIFIKASI OTP EMAIL
if (isset($_POST['aksi']) && $_POST['aksi'] === "verifikasi_otp_email") {
    $otp_input = mysqli_real_escape_string($conn, trim($_POST['otp']));
    $user_otp  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT otp_code, otp_expires_at FROM users WHERE id={$_SESSION['user_id']}"));
    header('Content-Type: application/json');
    if (!$user_otp['otp_code']) {
        echo json_encode(['status' => 'gagal', 'pesan' => 'Kode OTP tidak ditemukan. Silakan kirim ulang.']);
        exit;
    }
    if (strtotime($user_otp['otp_expires_at']) < time()) {
        echo json_encode(['status' => 'gagal', 'pesan' => 'Kode OTP sudah kedaluwarsa. Silakan kirim ulang.']);
        exit;
    }
    if ($otp_input !== $user_otp['otp_code']) {
        echo json_encode(['status' => 'gagal', 'pesan' => 'Kode OTP salah. Periksa kembali kode yang dikirim.']);
        exit;
    }

    $email_baru = $_SESSION['pending_email'] ?? '';
    $nama       = $_SESSION['pending_nama'] ?? '';
    $username   = $_SESSION['pending_username'] ?? '';
    $crop_data  = $_SESSION['pending_foto'] ?? '';

    if (!$email_baru) {
        echo json_encode(['status' => 'gagal', 'pesan' => 'Sesi verifikasi tidak ditemukan. Silakan ulangi.']);
        exit;
    }

    $user_now   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto_profil FROM users WHERE id={$_SESSION['user_id']}"));
    $foto_profil = $user_now['foto_profil'];

    if (!empty($crop_data)) {
        $crop_data  = preg_replace('/^data:image\/\w+;base64,/', '', $crop_data);
        $crop_data  = str_replace(' ', '+', $crop_data);
        $image_data = base64_decode($crop_data);
        if ($image_data !== false) {
            $nama_file = uniqid('foto_') . '.jpg';
            $tujuan    = BASE_PATH . 'uploads/foto-profil/' . $nama_file;
            if (file_put_contents($tujuan, $image_data)) {
                if ($foto_profil) hapusFile('uploads/foto-profil/' . $foto_profil);
                $foto_profil = $nama_file;
            }
        }
    }

    $nama_esc     = mysqli_real_escape_string($conn, $nama);
    $username_esc = mysqli_real_escape_string($conn, $username);
    $email_esc    = mysqli_real_escape_string($conn, $email_baru);

    mysqli_query($conn, "
        UPDATE users SET
            nama='$nama_esc',
            username='$username_esc',
            email='$email_esc',
            foto_profil=" . ($foto_profil ? "'$foto_profil'" : "NULL") . ",
            otp_code=NULL,
            otp_expires_at=NULL
        WHERE id={$_SESSION['user_id']}
    ");

    $_SESSION['nama']        = $nama;
    $_SESSION['username']    = $username;
    $_SESSION['email']       = $email_baru;
    $_SESSION['foto_profil'] = $foto_profil;

    unset($_SESSION['pending_email'], $_SESSION['pending_nama'], $_SESSION['pending_username'], $_SESSION['pending_foto']);

    echo json_encode(['status' => 'berhasil']);
    exit;
}

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id={$_SESSION['user_id']}"));

// HAPUS FOTO
if (isset($_POST['aksi']) && $_POST['aksi'] === "hapus_foto") {
    if ($user['foto_profil']) {
        hapusFile('uploads/foto-profil/' . $user['foto_profil']);
        mysqli_query($conn, "UPDATE users SET foto_profil=NULL WHERE id={$_SESSION['user_id']}");
        $_SESSION['foto_profil'] = null;
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id={$_SESSION['user_id']}"));
    }
    $_SESSION['flash_success'] = "Foto profil berhasil dihapus!";
    header('Location: profil.php');
    exit;
}

// SIMPAN PROFIL
if (isset($_POST['aksi']) && $_POST['aksi'] === "simpan_profil") {
    $nama     = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));

    $cek = mysqli_query($conn, "
        SELECT * FROM users
        WHERE (username='$username' OR email='$email')
        AND id != {$_SESSION['user_id']}
    ");

    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['flash_error'] = "Username atau email sudah digunakan akun lain!";
        header('Location: profil.php');
        exit;
    }

    // email berubah → perlu verifikasi OTP, kirim via AJAX terpisah
    // jika email sama, simpan langsung
    if ($email !== $user['email']) {
        // alur ini ditangani via AJAX aksi=kirim_otp_email
        // tidak akan sampai sini lewat form biasa
        $_SESSION['flash_error'] = "Gunakan tombol Simpan Profil untuk memverifikasi email baru.";
        header('Location: profil.php');
        exit;
    }

    $foto_profil = $user['foto_profil'];

    if (!empty($_POST['foto_crop_data'])) {
        $crop_data  = $_POST['foto_crop_data'];
        $crop_data  = preg_replace('/^data:image\/\w+;base64,/', '', $crop_data);
        $crop_data  = str_replace(' ', '+', $crop_data);
        $image_data = base64_decode($crop_data);
        if ($image_data !== false) {
            $nama_file = uniqid('foto_') . '.jpg';
            $tujuan    = BASE_PATH . 'uploads/foto-profil/' . $nama_file;
            if (file_put_contents($tujuan, $image_data)) {
                if ($foto_profil) hapusFile('uploads/foto-profil/' . $foto_profil);
                $foto_profil = $nama_file;
            } else {
                $_SESSION['flash_error'] = "Gagal menyimpan foto.";
                header('Location: profil.php');
                exit;
            }
        } else {
            $_SESSION['flash_error'] = "Data foto tidak valid.";
            header('Location: profil.php');
            exit;
        }
    }

    mysqli_query($conn, "
        UPDATE users SET
            nama='$nama',
            username='$username',
            email='$email',
            foto_profil=" . ($foto_profil ? "'$foto_profil'" : "NULL") . "
        WHERE id={$_SESSION['user_id']}
    ");

    $_SESSION['nama']        = $nama;
    $_SESSION['username']    = $username;
    $_SESSION['email']       = $email;
    $_SESSION['foto_profil'] = $foto_profil;

    $_SESSION['flash_success'] = "Profil berhasil diperbarui!";
    header('Location: profil.php');
    exit;
}

// GANTI PASSWORD
if (isset($_POST['aksi']) && $_POST['aksi'] === "ganti_password") {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi    = $_POST['konfirmasi_password'];

    if ($password_lama !== $user['password']) {
        $_SESSION['flash_error'] = "Password lama tidak sesuai!";
        header('Location: profil.php');
        exit;
    } elseif (strlen($password_baru) < 3) {
        $_SESSION['flash_error'] = "Password baru minimal 3 karakter!";
        header('Location: profil.php');
        exit;
    } elseif ($password_baru !== $konfirmasi) {
        $_SESSION['flash_error'] = "Konfirmasi password tidak cocok!";
        header('Location: profil.php');
        exit;
    } else {
        $password_baru = mysqli_real_escape_string($conn, $password_baru);
        mysqli_query($conn, "UPDATE users SET password='$password_baru' WHERE id={$_SESSION['user_id']}");
        $_SESSION['flash_success'] = "Password berhasil diperbarui!";
        header('Location: profil.php');
        exit;
    }
}

$judul_halaman = "Profil";
include BASE_PATH . "includes/header.php";
include BASE_PATH . "includes/sidebar.php";
?>

<!-- Cropper.js CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<!-- BANNER -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="profil-banner px-4 py-4 banner-utama">
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <h3 class="text-white mb-1 fw-bold"><i class="bi bi-person-circle me-2"></i>Profil Saya</h3>
                <p class="text-white mb-0" style="color: rgba(255,255,255,0.75) !important;">
                    Kelola informasi akun dan keamanan
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ALERT LUAR -->
<?php if ($flash_success): ?>
    <div id="alertLuar" class="custom-alert mb-4">
        <div class="alert alert-success d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5 flex-shrink-0"></i>
            <div><?= $flash_success ?></div>
        </div>
    </div>
<?php elseif ($flash_error): ?>
    <div id="alertLuar" class="custom-alert mb-4">
        <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
            <div><?= $flash_error ?></div>
        </div>
    </div>
<?php endif; ?>

<!-- SECTION PROFIL -->
<div class="card border-0 shadow rounded-4 overflow-hidden card-solid mb-4">
    <div class="card-header-section">
        <h5 class="fw-bold mb-0 text-white">
            <i class="bi bi-person-fill me-2"></i>Informasi Profil
        </h5>
    </div>
    <div class="p-4">
        <form method="POST" id="formProfil">
            <input type="hidden" name="aksi" value="simpan_profil">
            <input type="hidden" name="foto_crop_data" id="fotoCropData">
            <div class="row g-4 align-items-start">

                <!-- FOTO PROFIL -->
                <div class="col-md-3 text-center">
                    <div class="avatar-wrap mb-3">
                        <?php if ($user['foto_profil']): ?>
                            <img src="<?= BASE_URL ?>uploads/foto-profil/<?= htmlspecialchars($user['foto_profil']) ?>"
                                id="previewFoto" class="avatar-img" alt="Foto Profil">
                        <?php else: ?>
                            <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?= $_SESSION['username'] ?>"
                                id="previewFoto" class="avatar-img" alt="Avatar">
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-column gap-2 align-items-center">
                        <label for="foto_profil" class="btn btn-outline-secondary rounded-3 btn-sm fw-semibold w-100">
                            <i class="bi bi-camera-fill me-1"></i>Ganti Foto
                        </label>
                        <?php if ($user['foto_profil']): ?>
                            <button type="button" class="btn btn-outline-danger rounded-3 btn-sm fw-semibold w-100"
                                onclick="konfirmasiHapusFoto()">
                                <i class="bi bi-trash-fill me-1"></i>Hapus Foto
                            </button>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="foto_profil" id="foto_profil" class="d-none"
                        accept=".jpg,.jpeg,.png">
                    <div class="text-muted small mt-2">JPG, PNG — Maks. 2MB</div>
                </div>

                <!-- FORM INFO -->
                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama" id="inputNama" class="form-control rounded-3"
                                value="<?= htmlspecialchars($user['nama']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" id="inputUsername" class="form-control rounded-3"
                                value="<?= htmlspecialchars($user['username']) ?>" required>
                            <div id="alertUsername" class="custom-alert mt-2">
                                <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0 py-2" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2 fs-6 flex-shrink-0"></i>
                                    <div id="alertUsernamePesan" style="font-size:13px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" id="inputEmail" class="form-control rounded-3"
                                value="<?= htmlspecialchars($user['email']) ?>" required>
                            <div id="alertEmail" class="custom-alert mt-2">
                                <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0 py-2" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2 fs-6 flex-shrink-0"></i>
                                    <div id="alertEmailPesan" style="font-size:13px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <input type="text" class="form-control rounded-3 bg-light"
                                value="Admin" disabled>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="button" id="btnSimpanProfil" class="btn btn-profil rounded-3 fw-semibold px-4"
                            onclick="prosesSimPanProfil()">
                            <i class="bi bi-save-fill me-1"></i>Simpan Profil
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- SECTION GANTI PASSWORD -->
<div class="card border-0 shadow rounded-4 overflow-hidden card-solid mb-4">
    <div class="card-header-section card-header-password">
        <h5 class="fw-bold mb-0 text-white">
            <i class="bi bi-shield-lock-fill me-2"></i>Ganti Password
        </h5>
    </div>
    <div class="p-4">
        <form method="POST">
            <input type="hidden" name="aksi" value="ganti_password">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Password Lama</label>
                    <div class="input-group">
                        <input type="password" name="password_lama" id="passwordLama"
                            class="form-control rounded-start-3" required>
                        <button type="button" class="btn btn-outline-secondary rounded-end-3"
                            onclick="togglePassword('passwordLama', this)">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Password Baru</label>
                    <div class="input-group">
                        <input type="password" name="password_baru" id="passwordBaru"
                            class="form-control rounded-start-3" required>
                        <button type="button" class="btn btn-outline-secondary rounded-end-3"
                            onclick="togglePassword('passwordBaru', this)">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                    <div class="input-group">
                        <input type="password" name="konfirmasi_password" id="konfirmasiPassword"
                            class="form-control rounded-start-3" required>
                        <button type="button" class="btn btn-outline-secondary rounded-end-3"
                            onclick="togglePassword('konfirmasiPassword', this)">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-password rounded-3 fw-semibold px-4">
                    <i class="bi bi-shield-check me-1"></i>Simpan Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CROP FOTO -->
<div class="modal fade" id="modalCrop" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-crop">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-crop me-2"></i>Sesuaikan Foto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    onclick="batalCrop()"></button>
            </div>
            <div class="modal-body p-4">
                <div class="crop-container">
                    <img id="cropImage" src="" alt="Crop">
                </div>
                <div class="text-center text-muted small mt-3">
                    <i class="bi bi-info-circle me-1"></i>Geser dan zoom untuk menyesuaikan foto
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <div class="d-flex gap-2 w-100">
                    <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill"
                        data-bs-dismiss="modal" onclick="batalCrop()">
                        <i class="bi bi-arrow-left me-1"></i>Batal
                    </button>
                    <button type="button" class="btn btn-profil rounded-3 fw-semibold flex-fill"
                        onclick="konfirmasiCrop()">
                        <i class="bi bi-check-lg me-1"></i>Gunakan Foto Ini
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL HAPUS FOTO -->
<div class="modal fade" id="modalHapusFoto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-hapus-foto">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-trash-fill me-2"></i>Hapus Foto Profil
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <div style="font-size:2.5rem;" class="mb-3">🖼️</div>
                <p class="fw-semibold mb-1">Yakin ingin menghapus foto profil?</p>
                <p class="text-muted small mb-0">Foto akan diganti kembali ke avatar otomatis.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <form method="POST">
                    <input type="hidden" name="aksi" value="hapus_foto">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill"
                            data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-danger rounded-3 fw-semibold flex-fill">
                            <i class="bi bi-trash-fill me-1"></i>Ya, Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL OTP VERIFIKASI EMAIL -->
<div class="modal fade" id="modalOTP" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-otp">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-envelope-check-fill me-2"></i>Verifikasi Email Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <!-- ALERT DALAM MODAL OTP -->
                <div id="alertOTP" class="custom-alert mb-3">
                    <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                        <div id="alertOTPPesan"></div>
                    </div>
                </div>
                <p class="text-muted mb-1" style="font-size:14px;">Kode OTP telah dikirim ke:</p>
                <p class="fw-semibold mb-3" id="otpEmailTujuan" style="font-size:14px;"></p>
                <p class="mb-3" style="font-size:14px;">Masukkan kode OTP 6 digit:</p>
                <div class="d-flex gap-2 justify-content-center mb-3" id="otpInputGroup">
                    <input type="text" class="form-control text-center fw-bold otp-box rounded-3"
                        maxlength="1" inputmode="numeric" pattern="[0-9]" style="width:48px;height:52px;font-size:1.4rem;">
                    <input type="text" class="form-control text-center fw-bold otp-box rounded-3"
                        maxlength="1" inputmode="numeric" pattern="[0-9]" style="width:48px;height:52px;font-size:1.4rem;">
                    <input type="text" class="form-control text-center fw-bold otp-box rounded-3"
                        maxlength="1" inputmode="numeric" pattern="[0-9]" style="width:48px;height:52px;font-size:1.4rem;">
                    <input type="text" class="form-control text-center fw-bold otp-box rounded-3"
                        maxlength="1" inputmode="numeric" pattern="[0-9]" style="width:48px;height:52px;font-size:1.4rem;">
                    <input type="text" class="form-control text-center fw-bold otp-box rounded-3"
                        maxlength="1" inputmode="numeric" pattern="[0-9]" style="width:48px;height:52px;font-size:1.4rem;">
                    <input type="text" class="form-control text-center fw-bold otp-box rounded-3"
                        maxlength="1" inputmode="numeric" pattern="[0-9]" style="width:48px;height:52px;font-size:1.4rem;">
                </div>
                <div class="text-center text-muted small mb-2">
                    <i class="bi bi-clock me-1"></i>Berlaku 10 menit
                </div>
                <div class="text-center">
                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0"
                        id="btnKirimUlangOTP" onclick="kirimUlangOTP()">
                        Tidak dapat kode? <span class="fw-semibold">Kirim Ulang</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <div class="d-flex gap-2 w-100">
                    <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill"
                        data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left me-1"></i>Batal
                    </button>
                    <button type="button" class="btn btn-profil rounded-3 fw-semibold flex-fill"
                        id="btnVerifikasiOTP" onclick="verifikasiOTP()">
                        <i class="bi bi-check-lg me-1"></i>Verifikasi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .profil-banner {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .card-header-section {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
        padding: 16px 24px;
    }

    .card-header-password {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .card-solid {
        background-color: #ffffff;
        border: 1px solid #d6dee3 !important;
    }

    .avatar-wrap {
        width: 110px;
        height: 110px;
        margin: 0 auto;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid #2f8f9d;
        box-shadow: 0 4px 12px rgba(47, 143, 157, 0.25);
    }

    .avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .btn-profil {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
        color: #fff;
        border: none;
    }

    .btn-profil:hover {
        background: linear-gradient(135deg, #1a6b76 0%, #134f58 100%);
        color: #fff;
    }

    .btn-password {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
        color: #fff;
        border: none;
    }

    .btn-password:hover {
        background: linear-gradient(135deg, #1a6b76 0%, #134f58 100%);
        color: #fff;
    }

    .modal-header-custom {
        padding: 18px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-header-crop {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .modal-header-hapus-foto {
        background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%);
    }

    .modal-header-otp {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .modal-footer {
        display: block !important;
    }

    .modal-footer .btn-light {
        background-color: #ffffff;
        border: 1.5px solid #ced4da;
        color: #555;
    }

    .modal-footer .btn-light:hover {
        background-color: #f1f3f5;
        border-color: #adb5bd;
    }

    .crop-container {
        width: 100%;
        max-height: 350px;
        overflow: hidden;
        background: #000;
        border-radius: 8px;
    }

    .crop-container img {
        display: block;
        max-width: 100%;
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

    mark.highlight {
        background-color: #cfe2ff;
        color: #084298;
        padding: 0 2px;
        border-radius: 3px;
        font-weight: 600;
    }

    .otp-box:focus {
        border-color: #2f8f9d;
        box-shadow: 0 0 0 0.2rem rgba(47, 143, 157, 0.25);
    }

    @media (max-width: 768px) {
        .col-md-3.text-center {
            margin-bottom: 8px;
        }

        .otp-box {
            width: 40px !important;
            height: 44px !important;
            font-size: 1.2rem !important;
        }
    }
</style>

<script>
    const alertTimers = {};

    function tampilkanAlert(elId, pesan) {
        const el = document.getElementById(elId);
        const pesanEl = document.getElementById(elId + 'Pesan');
        if (!el || !pesanEl) return;
        if (alertTimers[elId]) {
            clearTimeout(alertTimers[elId]);
            delete alertTimers[elId];
        }
        pesanEl.textContent = pesan;
        el.classList.remove('show', 'hide');
        el.offsetHeight;
        el.classList.add('show');
        alertTimers[elId] = setTimeout(() => {
            el.classList.remove('show');
            el.classList.add('hide');
            delete alertTimers[elId];
        }, 4500);
    }

    function sembunyikanAlert(elId) {
        const el = document.getElementById(elId);
        if (!el) return;
        if (alertTimers[elId]) {
            clearTimeout(alertTimers[elId]);
            delete alertTimers[elId];
        }
        el.classList.remove('show');
        el.classList.add('hide');
    }

    function aktivasiAlertLuar(el) {
        if (!el) return;
        setTimeout(() => el.classList.add('show'), 50);
        setTimeout(() => {
            el.classList.remove('show');
            el.classList.add('hide');
        }, 4500);
    }

    document.addEventListener('DOMContentLoaded', function() {

        // AUTOFOCUS NAMA
        const inputNama = document.getElementById('inputNama');
        if (inputNama) inputNama.focus();

        // ALERT LUAR DARI SESSION
        aktivasiAlertLuar(document.getElementById('alertLuar'));

        // ALERT LUAR DARI SESSIONSTORGE (setelah AJAX reload)
        const flashJs = sessionStorage.getItem('flash_js');
        if (flashJs) {
            sessionStorage.removeItem('flash_js');
            const wrapper = document.createElement('div');
            wrapper.className = 'custom-alert mb-4';
            wrapper.innerHTML = `
                <div class="alert alert-success d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5 flex-shrink-0"></i>
                    <div>${flashJs}</div>
                </div>`;
            const banner = document.querySelector('.banner-utama')?.closest('.card');
            if (banner) banner.after(wrapper);
            aktivasiAlertLuar(wrapper);
        }

        // CEK DUPLIKAT USERNAME REALTIME
        let usernameTimer = null;
        const emailAwal = <?= json_encode($user['email']) ?>;

        document.getElementById('inputUsername').addEventListener('input', function() {
            clearTimeout(usernameTimer);
            const val = this.value.trim();
            if (!val) {
                sembunyikanAlert('alertUsername');
                document.getElementById('btnSimpanProfil').disabled = false;
                return;
            }
            usernameTimer = setTimeout(() => {
                const fd = new FormData();
                fd.append('aksi', 'cek_duplikat_username');
                fd.append('username', val);
                fetch('', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.duplikat) {
                            tampilkanAlert('alertUsername', 'Username sudah digunakan akun lain.');
                            document.getElementById('btnSimpanProfil').disabled = true;
                        } else {
                            sembunyikanAlert('alertUsername');
                            document.getElementById('btnSimpanProfil').disabled = false;
                        }
                    })
                    .catch(() => {
                        tampilkanAlert('alertUsername', 'Terjadi kesalahan. Silakan coba lagi.');
                        document.getElementById('btnSimpanProfil').disabled = false;
                    });
            }, 500);
        });

        // CEK DUPLIKAT EMAIL REALTIME
        let emailTimer = null;

        document.getElementById('inputEmail').addEventListener('input', function() {
            clearTimeout(emailTimer);
            const val = this.value.trim();
            if (!val) {
                sembunyikanAlert('alertEmail');
                document.getElementById('btnSimpanProfil').disabled = false;
                return;
            }
            emailTimer = setTimeout(() => {
                const fd = new FormData();
                fd.append('aksi', 'cek_duplikat_email');
                fd.append('email', val);
                fetch('', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.duplikat) {
                            tampilkanAlert('alertEmail', 'Email sudah digunakan akun lain.');
                            document.getElementById('btnSimpanProfil').disabled = true;
                        } else {
                            sembunyikanAlert('alertEmail');
                            document.getElementById('btnSimpanProfil').disabled = false;
                        }
                    })
                    .catch(() => {
                        tampilkanAlert('alertEmail', 'Terjadi kesalahan. Silakan coba lagi.');
                        document.getElementById('btnSimpanProfil').disabled = false;
                    });
            }, 500);
        });

        // OTP INPUT NAVIGASI OTOMATIS
        const otpBoxes = document.querySelectorAll('.otp-box');
        otpBoxes.forEach((box, i) => {
            box.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value && i < otpBoxes.length - 1) {
                    otpBoxes[i + 1].focus();
                }
            });
            box.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && i > 0) {
                    otpBoxes[i - 1].focus();
                }
            });
            box.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
                paste.split('').forEach((ch, idx) => {
                    if (otpBoxes[idx]) otpBoxes[idx].value = ch;
                });
                const last = Math.min(paste.length, otpBoxes.length) - 1;
                if (otpBoxes[last]) otpBoxes[last].focus();
            });
        });

        // AUTOFOCUS OTP BOX PERTAMA SAAT MODAL DIBUKA
        document.getElementById('modalOTP').addEventListener('shown.bs.modal', function() {
            otpBoxes[0].focus();
        });

        // RESET OTP SAAT MODAL DITUTUP
        document.getElementById('modalOTP').addEventListener('hidden.bs.modal', function() {
            otpBoxes.forEach(b => b.value = '');
            sembunyikanAlert('alertOTP');
            document.getElementById('btnVerifikasiOTP').disabled = false;
        });

    });

    // PROSES SIMPAN PROFIL (cek apakah email berubah)
    function prosesSimPanProfil() {
        const emailAwal = <?= json_encode($user['email']) ?>;
        const emailBaru = document.getElementById('inputEmail').value.trim();
        const nama = document.getElementById('inputNama').value.trim();
        const username = document.getElementById('inputUsername').value.trim();
        const cropData = document.getElementById('fotoCropData').value;

        if (emailBaru !== emailAwal) {
            // kirim OTP ke email baru
            const btn = document.getElementById('btnSimpanProfil');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengirim OTP...';

            const fd = new FormData();
            fd.append('aksi', 'kirim_otp_email');
            fd.append('email_baru', emailBaru);
            fd.append('nama', nama);
            fd.append('username', username);
            fd.append('foto_crop_data', cropData);

            fetch('', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-save-fill me-1"></i>Simpan Profil';
                    if (res.status === 'berhasil') {
                        document.getElementById('otpEmailTujuan').textContent = emailBaru;
                        otpBoxes().forEach(b => b.value = '');
                        sembunyikanAlert('alertOTP');
                        new bootstrap.Modal(document.getElementById('modalOTP')).show();
                    } else {
                        sessionStorage.setItem('flash_js_error', res.pesan);
                        tampilkanAlertLuarError(res.pesan);
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-save-fill me-1"></i>Simpan Profil';
                    tampilkanAlertLuarError('Terjadi kesalahan. Silakan coba lagi.');
                });
        } else {
            // email tidak berubah, submit form biasa
            document.getElementById('formProfil').submit();
        }
    }

    function otpBoxes() {
        return Array.from(document.querySelectorAll('.otp-box'));
    }

    function tampilkanAlertLuarError(pesan) {
        let existing = document.getElementById('alertLuarDinamis');
        if (existing) existing.remove();
        const wrapper = document.createElement('div');
        wrapper.id = 'alertLuarDinamis';
        wrapper.className = 'custom-alert mb-4';
        wrapper.innerHTML = `
            <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                <div>${pesan}</div>
            </div>`;
        const banner = document.querySelector('.banner-utama')?.closest('.card');
        if (banner) banner.after(wrapper);
        aktivasiAlertLuar(wrapper);
    }

    // VERIFIKASI OTP
    function verifikasiOTP() {
        const kode = otpBoxes().map(b => b.value).join('');
        if (kode.length < 6) {
            tampilkanAlert('alertOTP', 'Masukkan 6 digit kode OTP terlebih dahulu.');
            return;
        }
        const btn = document.getElementById('btnVerifikasiOTP');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memverifikasi...';

        const fd = new FormData();
        fd.append('aksi', 'verifikasi_otp_email');
        fd.append('otp', kode);

        fetch('', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'berhasil') {
                    bootstrap.Modal.getInstance(document.getElementById('modalOTP')).hide();
                    sessionStorage.setItem('flash_js', 'Profil berhasil diperbarui!');
                    location.reload();
                } else {
                    tampilkanAlert('alertOTP', res.pesan);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Verifikasi';
                }
            })
            .catch(() => {
                tampilkanAlert('alertOTP', 'Terjadi kesalahan. Silakan coba lagi.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Verifikasi';
            });
    }

    // KIRIM ULANG OTP
    function kirimUlangOTP() {
        const emailBaru = document.getElementById('otpEmailTujuan').textContent;
        const nama = document.getElementById('inputNama').value.trim();
        const username = document.getElementById('inputUsername').value.trim();
        const cropData = document.getElementById('fotoCropData').value;
        const btn = document.getElementById('btnKirimUlangOTP');
        btn.disabled = true;

        const fd = new FormData();
        fd.append('aksi', 'kirim_otp_email');
        fd.append('email_baru', emailBaru);
        fd.append('nama', nama);
        fd.append('username', username);
        fd.append('foto_crop_data', cropData);

        fetch('', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                if (res.status === 'berhasil') {
                    otpBoxes().forEach(b => b.value = '');
                    sembunyikanAlert('alertOTP');
                    tampilkanAlert('alertOTP', 'Kode OTP baru telah dikirim ke email.');
                    // ganti warna alert jadi success sementara
                    const alertEl = document.getElementById('alertOTP');
                    const innerAlert = alertEl.querySelector('.alert');
                    innerAlert.classList.remove('alert-danger');
                    innerAlert.classList.add('alert-success');
                    innerAlert.querySelector('i').className = 'bi bi-check-circle-fill me-2 fs-5 flex-shrink-0';
                    setTimeout(() => {
                        innerAlert.classList.remove('alert-success');
                        innerAlert.classList.add('alert-danger');
                        innerAlert.querySelector('i').className = 'bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0';
                    }, 4600);
                } else {
                    tampilkanAlert('alertOTP', res.pesan);
                }
            })
            .catch(() => {
                btn.disabled = false;
                tampilkanAlert('alertOTP', 'Terjadi kesalahan. Silakan coba lagi.');
            });
    }

    // CROP FOTO
    let cropper = null;

    document.getElementById('foto_profil').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran foto maksimal 2MB.');
            this.value = '';
            return;
        }
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['jpg', 'jpeg', 'png'].includes(ext)) {
            alert('Format foto tidak diizinkan. Gunakan jpg atau png.');
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('cropImage').src = e.target.result;
            const modal = new bootstrap.Modal(document.getElementById('modalCrop'));
            modal.show();
            document.getElementById('modalCrop').addEventListener('shown.bs.modal', function initCropper() {
                if (cropper) cropper.destroy();
                cropper = new Cropper(document.getElementById('cropImage'), {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.8,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
                document.getElementById('modalCrop').removeEventListener('shown.bs.modal', initCropper);
            });
        };
        reader.readAsDataURL(file);
    });

    function konfirmasiCrop() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        document.getElementById('previewFoto').src = dataUrl;
        document.getElementById('fotoCropData').value = dataUrl;
        bootstrap.Modal.getInstance(document.getElementById('modalCrop')).hide();
        cropper.destroy();
        cropper = null;
    }

    function batalCrop() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        document.getElementById('foto_profil').value = '';
    }

    function konfirmasiHapusFoto() {
        new bootstrap.Modal(document.getElementById('modalHapusFoto')).show();
    }

    function togglePassword(id, btn) {
        const input = document.getElementById(id);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye-slash';
        }
    }
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>