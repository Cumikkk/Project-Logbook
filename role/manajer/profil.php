<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../includes/fungsi.php";

proteksi("manajer");

$success_profil   = "";
$error_profil     = "";
$success_password = "";
$error_password   = "";

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id={$_SESSION['user_id']}"));

// HAPUS FOTO
if (isset($_POST['aksi']) && $_POST['aksi'] === "hapus_foto") {
    if ($user['foto_profil']) {
        hapusFile('uploads/foto-profil/' . $user['foto_profil']);
        mysqli_query($conn, "UPDATE users SET foto_profil=NULL WHERE id={$_SESSION['user_id']}");
        $_SESSION['foto_profil'] = null;
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id={$_SESSION['user_id']}"));
    }
    $success_profil = "Foto profil berhasil dihapus!";
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
        $error_profil = "Username atau email sudah digunakan akun lain!";
    } else {
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
                    if ($foto_profil) {
                        hapusFile('uploads/foto-profil/' . $foto_profil);
                    }
                    $foto_profil = $nama_file;
                } else {
                    $error_profil = "Gagal menyimpan foto.";
                }
            } else {
                $error_profil = "Data foto tidak valid.";
            }
        }

        if (!$error_profil) {
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

            $user           = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id={$_SESSION['user_id']}"));
            $success_profil = "Profil berhasil diperbarui!";
        }
    }
}

// GANTI PASSWORD
if (isset($_POST['aksi']) && $_POST['aksi'] === "ganti_password") {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi    = $_POST['konfirmasi_password'];

    if ($password_lama !== $user['password']) {
        $error_password = "Password lama tidak sesuai!";
    } elseif (strlen($password_baru) < 3) {
        $error_password = "Password baru minimal 3 karakter!";
    } elseif ($password_baru !== $konfirmasi) {
        $error_password = "Konfirmasi password tidak cocok!";
    } else {
        $password_baru = mysqli_real_escape_string($conn, $password_baru);
        mysqli_query($conn, "UPDATE users SET password='$password_baru' WHERE id={$_SESSION['user_id']}");
        $success_password = "Password berhasil diperbarui!";
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id={$_SESSION['user_id']}"));
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
    <div class="profil-banner px-4 py-4">
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

<!-- SECTION PROFIL -->
<div class="card border-0 shadow rounded-4 overflow-hidden card-solid mb-4">
    <div class="card-header-section">
        <h5 class="fw-bold mb-0 text-white">
            <i class="bi bi-person-fill me-2"></i>Informasi Profil
        </h5>
    </div>
    <div class="p-4">

        <?php if ($success_profil): ?>
            <div class="alert alert-success d-flex align-items-center rounded-3 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= $success_profil ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error_profil): ?>
            <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= $error_profil ?></div>
            </div>
        <?php endif; ?>

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
                            <input type="text" name="nama" class="form-control rounded-3"
                                value="<?= htmlspecialchars($user['nama']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control rounded-3"
                                value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control rounded-3"
                                value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <input type="text" class="form-control rounded-3 bg-light"
                                value="Manajer" disabled>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-profil rounded-3 fw-semibold px-4">
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

        <?php if ($success_password): ?>
            <div class="alert alert-success d-flex align-items-center rounded-3 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= $success_password ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error_password): ?>
            <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= $error_password ?></div>
            </div>
        <?php endif; ?>

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

    @media (max-width: 768px) {
        .col-md-3.text-center {
            margin-bottom: 8px;
        }
    }
</style>

<script>
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
                if (cropper) {
                    cropper.destroy();
                }
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
            imageSmoothingQuality: 'high',
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