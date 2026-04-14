<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

proteksi("admin");

// HAPUS PENGGUNA (single)
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    if ($id === (int) $_SESSION['user_id']) {
        $_SESSION['flash_error'] = "Tidak bisa menghapus akun sendiri!";
    } else {
        mysqli_query($conn, "UPDATE divisi SET manajer_id=NULL WHERE manajer_id=$id");
        mysqli_query($conn, "DELETE FROM users WHERE id=$id");
        $_SESSION['flash_success'] = "Pengguna berhasil dihapus!";
    }
    header("Location: " . BASE_URL . "role/admin/admin-manajer.php");
    exit;
}

// HAPUS MASSAL
if (isset($_POST['aksi']) && $_POST['aksi'] === "hapus_massal") {
    $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
    $berhasil = 0;
    $dilewati = 0;
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id === (int) $_SESSION['user_id']) {
            $dilewati++;
        } else {
            mysqli_query($conn, "UPDATE divisi SET manajer_id=NULL WHERE manajer_id=$id");
            mysqli_query($conn, "DELETE FROM users WHERE id=$id");
            $berhasil++;
        }
    }
    header('Content-Type: application/json');
    if ($berhasil === 0 && $dilewati > 0) {
        echo json_encode(['status' => 'gagal', 'pesan' => 'Semua data yang dipilih tidak bisa dihapus karena termasuk akun Anda sendiri.']);
    } else {
        $pesan = "";
        if ($berhasil > 0) $pesan .= "$berhasil pengguna berhasil dihapus.";
        if ($dilewati > 0) $pesan .= ($berhasil > 0 ? " " : "") . "$dilewati data dilewati (akun sendiri tidak bisa dihapus).";
        echo json_encode(['status' => 'berhasil', 'pesan' => $pesan]);
    }
    exit;
}

// HAPUS SEMUA
if (isset($_POST['aksi']) && $_POST['aksi'] === "hapus_semua") {
    $self = (int) $_SESSION['user_id'];
    $berhasil = 0;
    $dilewati = 0;
    $all = mysqli_query($conn, "SELECT id FROM users WHERE role IN ('admin','manajer')");
    while ($u = mysqli_fetch_assoc($all)) {
        $id = (int) $u['id'];
        if ($id === $self) {
            $dilewati++;
        } else {
            mysqli_query($conn, "UPDATE divisi SET manajer_id=NULL WHERE manajer_id=$id");
            mysqli_query($conn, "DELETE FROM users WHERE id=$id");
            $berhasil++;
        }
    }
    header('Content-Type: application/json');
    if ($berhasil === 0 && $dilewati > 0) {
        echo json_encode(['status' => 'gagal', 'pesan' => 'Tidak ada data yang bisa dihapus. Akun Anda sendiri tidak bisa dihapus.']);
    } else {
        $pesan = "";
        if ($berhasil > 0) $pesan .= "$berhasil pengguna berhasil dihapus.";
        if ($dilewati > 0) $pesan .= ($berhasil > 0 ? " " : "") . "$dilewati data dilewati (akun sendiri tidak bisa dihapus).";
        echo json_encode(['status' => 'berhasil', 'pesan' => $pesan]);
    }
    exit;
}

// TAMBAH PENGGUNA
if (isset($_POST['aksi']) && $_POST['aksi'] === "tambah") {
    $nama      = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $username  = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email     = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password  = mysqli_real_escape_string($conn, $_POST['password']);
    $role      = $_POST['role'];
    $divisi_id = isset($_POST['divisi_id']) ? (int) $_POST['divisi_id'] : null;

    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' OR email='$email'");
    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['flash_error'] = "Username atau email sudah digunakan!";
    } else {
        mysqli_query($conn, "INSERT INTO users (nama, username, email, password, role, status, created_at)
            VALUES ('$nama', '$username', '$email', '$password', '$role', 'aktif', NOW())");
        $user_id = mysqli_insert_id($conn);

        if ($role === "manajer" && $divisi_id) {
            mysqli_query($conn, "UPDATE divisi SET manajer_id=$user_id WHERE id=$divisi_id");
        }

        $_SESSION['flash_success'] = "Pengguna berhasil ditambahkan!";
    }
    header("Location: " . BASE_URL . "role/admin/admin-manajer.php");
    exit;
}

// EDIT PENGGUNA
if (isset($_POST['aksi']) && $_POST['aksi'] === "edit") {
    $id        = (int) $_POST['user_id'];
    $nama      = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $email     = mysqli_real_escape_string($conn, trim($_POST['email']));
    $role      = $_POST['role'];
    $status    = $_POST['status'];
    $divisi_id = isset($_POST['divisi_id']) ? (int) $_POST['divisi_id'] : null;

    $cek = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND id != $id");
    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['flash_error'] = "Email sudah digunakan pengguna lain!";
    } else {
        mysqli_query($conn, "UPDATE users SET nama='$nama', email='$email', role='$role', status='$status' WHERE id=$id");

        if ($role === "manajer" && $divisi_id) {
            mysqli_query($conn, "UPDATE divisi SET manajer_id=NULL WHERE manajer_id=$id");
            mysqli_query($conn, "UPDATE divisi SET manajer_id=$id WHERE id=$divisi_id");
        } elseif ($role === "admin") {
            mysqli_query($conn, "UPDATE divisi SET manajer_id=NULL WHERE manajer_id=$id");
        }

        $_SESSION['flash_success'] = "Pengguna berhasil diperbarui!";
    }
    header("Location: " . BASE_URL . "role/admin/admin-manajer.php");
    exit;
}

// FLASH MESSAGE
$flash_success = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$flash_error   = isset($_SESSION['flash_error'])   ? $_SESSION['flash_error']   : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// AMBIL DATA PENGGUNA (admin & manajer)
$data = mysqli_query($conn, "
    SELECT u.*,
        d.id as divisi_id,
        d.nama_divisi
    FROM users u
    LEFT JOIN divisi d ON d.manajer_id = u.id
    WHERE u.role IN ('admin', 'manajer')
    ORDER BY u.created_at DESC
");
$rows = [];
while ($row = mysqli_fetch_assoc($data)) {
    $rows[] = $row;
}

// AMBIL DAFTAR DIVISI UNTUK DROPDOWN
$q_divisi = mysqli_query($conn, "SELECT * FROM divisi ORDER BY nama_divisi ASC");
$list_divisi = [];
while ($d = mysqli_fetch_assoc($q_divisi)) {
    $list_divisi[] = $d;
}

include BASE_PATH . "includes/header.php";
include BASE_PATH . "includes/sidebar.php";
?>

<!-- BANNER -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="pengguna-banner banner-utama px-4 py-4">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="flex-grow-1">
                <h3 class="text-white mb-1 fw-bold"><i class="bi bi-person-gear me-2"></i>Kelola Pengguna</h3>
                <p class="text-white mb-0" style="color: rgba(255,255,255,0.75) !important;">
                    Manajemen akun Admin dan Manajer
                </p>
            </div>
            <button class="btn btn-light fw-semibold rounded-3"
                data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg me-1"></i>Tambah Pengguna
            </button>
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

<!-- SEARCH & FILTER -->
<div class="card border-0 shadow mb-4 card-solid">
    <div class="p-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Cari Pengguna</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-3"
                        placeholder="Nama, username, atau email...">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Filter Role</label>
                <select id="filterRole" class="form-select rounded-3">
                    <option value="">Semua Role</option>
                    <option value="admin">Admin</option>
                    <option value="manajer">Manajer</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Filter Status</label>
                <select id="filterStatus" class="form-select rounded-3">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="tidak_aktif">Tidak Aktif</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                <button class="btn btn-outline-danger rounded-3 fw-semibold d-none" id="btnHapusDipilih"
                    onclick="konfirmasiHapusMassal()">
                    <i class="bi bi-trash-fill me-1"></i>Hapus Dipilih
                    (<span id="jumlahDipilih">0</span>)
                </button>
                <button class="btn btn-danger rounded-3 fw-semibold" id="btnHapusSemua"
                    onclick="konfirmasiHapusSemua()" disabled>
                    <i class="bi bi-trash3-fill me-1"></i>Hapus Semua
                </button>
            </div>
        </div>
    </div>
</div>

<!-- TABEL DATA PENGGUNA -->
<div class="card border-0 shadow rounded-4 overflow-hidden card-solid">
    <div class="p-4">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0 custom-table" id="tabelPengguna">
                <thead>
                    <tr>
                        <th class="ps-3" style="width:40px;"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                        <th style="width:50px;">No</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Password</th>
                        <th>Role</th>
                        <th>Divisi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tabelBody">
                    <?php if (count($rows) === 0): ?>
                        <tr class="row-empty">
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x d-block mb-2" style="font-size:2rem;"></i>
                                <div class="fw-semibold">Belum ada data pengguna</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php $no = 1;
                    foreach ($rows as $row): ?>
                        <tr
                            data-nama="<?= strtolower(htmlspecialchars($row['nama'])) ?>"
                            data-username="<?= strtolower(htmlspecialchars($row['username'])) ?>"
                            data-email="<?= strtolower(htmlspecialchars($row['email'])) ?>"
                            data-role="<?= $row['role'] ?>"
                            data-status="<?= $row['status'] ?>"
                            data-id="<?= $row['id'] ?>">

                            <td class="ps-3">
                                <input type="checkbox" class="form-check-input check-item" value="<?= $row['id'] ?>">
                            </td>
                            <td class="col-no"><?= $no++ ?></td>
                            <td class="fw-semibold col-nama"><?= htmlspecialchars($row['nama']) ?></td>
                            <td class="col-username"><?= htmlspecialchars($row['username']) ?></td>

                            <!-- EMAIL -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="val-email" data-val="<?= htmlspecialchars($row['email']) ?>">••••••••</span>
                                    <button class="btn btn-sm btn-link p-0 text-muted toggle-val" data-target="email">
                                        <i class="bi bi-eye-slash"></i>
                                    </button>
                                </div>
                            </td>

                            <!-- PASSWORD -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="val-password" data-val="<?= htmlspecialchars($row['password']) ?>">••••••••</span>
                                    <button class="btn btn-sm btn-link p-0 text-muted toggle-val" data-target="password">
                                        <i class="bi bi-eye-slash"></i>
                                    </button>
                                </div>
                            </td>

                            <!-- ROLE -->
                            <td>
                                <span class="badge rounded-pill px-3 py-2 <?= $row['role'] === 'admin' ? 'bg-primary' : 'bg-info text-dark' ?>">
                                    <?= ucfirst($row['role']) ?>
                                </span>
                            </td>

                            <!-- DIVISI -->
                            <td>
                                <?php if ($row['role'] === 'manajer' && $row['nama_divisi']): ?>
                                    <span class="badge bg-secondary rounded-pill px-3 py-2">
                                        <?= htmlspecialchars($row['nama_divisi']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:13px;">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- STATUS -->
                            <td>
                                <?php if ($row['status'] === 'aktif'): ?>
                                    <span class="badge bg-success rounded-pill px-3 py-2">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3 py-2">Tidak Aktif</span>
                                <?php endif; ?>
                            </td>

                            <!-- AKSI -->
                            <td>
                                <button class="btn btn-sm btn-warning rounded-3 me-1 fw-semibold"
                                    onclick="bukaModalEdit(
                                        <?= $row['id'] ?>,
                                        '<?= htmlspecialchars($row['nama'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>',
                                        '<?= $row['role'] ?>',
                                        '<?= $row['status'] ?>',
                                        <?= $row['divisi_id'] ?? 'null' ?>
                                    )">
                                    <i class="bi bi-pencil-fill me-1"></i>Edit
                                </button>
                                <button class="btn btn-sm btn-danger rounded-3 fw-semibold"
                                    onclick="konfirmasiHapus(
                                        <?= $row['id'] ?>,
                                        '<?= htmlspecialchars($row['nama'], ENT_QUOTES) ?>',
                                        <?= $row['id'] === (int)$_SESSION['user_id'] ? 'true' : 'false' ?>
                                    )">
                                    <i class="bi bi-trash-fill me-1"></i>Hapus
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- EMPTY STATE SEARCH -->
            <div id="emptySearch" class="text-center py-5 text-muted d-none">
                <i class="bi bi-search d-block mb-2" style="font-size:2rem;"></i>
                <div class="fw-semibold">Tidak ada pengguna yang cocok</div>
                <div class="small mt-1">Coba kata kunci lain</div>
            </div>
        </div>

        <!-- PAGINATION -->
        <div id="paginationWrapper" class="mt-4">
            <div class="d-flex flex-column align-items-center gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-center">
                    <div class="pagination-container d-flex align-items-center gap-1">
                        <button class="btn btn-sm btn-outline-secondary rounded-3 px-3" id="btnFirst" onclick="goPage(1)">« First</button>
                        <button class="btn btn-sm btn-outline-secondary rounded-3 px-3" id="btnPrev" onclick="goPage(currentPage - 1)">‹ Prev</button>
                        <div id="pageNumbers" class="d-flex gap-1"></div>
                        <button class="btn btn-sm btn-outline-secondary rounded-3 px-3" id="btnNext" onclick="goPage(currentPage + 1)">Next ›</button>
                        <button class="btn btn-sm btn-outline-secondary rounded-3 px-3" id="btnLast" onclick="goPage(totalPages)">Last »</button>
                    </div>
                    <select id="perPageSelect" class="form-select form-select-sm rounded-3" style="width:auto;">
                        <option value="10">10/hal</option>
                        <option value="25">25/hal</option>
                        <option value="50">50/hal</option>
                        <option value="100">100/hal</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
                <div id="paginationInfo" class="text-muted" style="font-size:13px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH PENGGUNA -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <form method="POST" id="formTambah">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header-custom modal-header-tambah">
                    <h5 class="text-white fw-bold mb-0">
                        <i class="bi bi-person-plus-fill me-2"></i>Tambah Pengguna
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <!-- ALERT DALAM MODAL TAMBAH -->
                    <div id="alertTambah" class="custom-alert mb-3">
                        <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                            <div id="alertTambahPesan"></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama" id="inputTambahNama" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="text" name="password" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" id="tambahRole" class="form-select rounded-3" required
                                onchange="toggleDivisiTambah(this.value)">
                                <option value="admin">Admin</option>
                                <option value="manajer">Manajer</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="wrapDivisiTambah" style="display:none;">
                            <label class="form-label fw-semibold">Divisi</label>
                            <select name="divisi_id" id="tambahDivisi" class="form-select rounded-3">
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($list_divisi as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-pengguna rounded-3 fw-semibold flex-fill">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Pengguna
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT PENGGUNA -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <form method="POST">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header-custom modal-header-edit">
                    <h5 class="text-white fw-bold mb-0">
                        <i class="bi bi-pencil-fill me-2"></i>Edit Pengguna
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <!-- ALERT DALAM MODAL EDIT -->
                    <div id="alertEdit" class="custom-alert mb-3">
                        <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                            <div id="alertEditPesan"></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama" id="editNama" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" id="editRole" class="form-select rounded-3"
                                onchange="toggleDivisiEdit(this.value)">
                                <option value="admin">Admin</option>
                                <option value="manajer">Manajer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="editStatus" class="form-select rounded-3">
                                <option value="aktif">Aktif</option>
                                <option value="tidak_aktif">Tidak Aktif</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="wrapDivisiEdit" style="display:none;">
                            <label class="form-label fw-semibold">Divisi</label>
                            <select name="divisi_id" id="editDivisi" class="form-select rounded-3">
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($list_divisi as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-warning rounded-3 fw-semibold flex-fill text-white">
                            <i class="bi bi-save-fill me-1"></i>Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL HAPUS SINGLE -->
<div class="modal fade" id="modalHapus" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-hapus">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-trash-fill me-2"></i>Konfirmasi Hapus
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <div style="font-size:2.5rem;" class="mb-3">⚠️</div>
                <p class="fw-semibold mb-1">Yakin ingin menghapus pengguna:</p>
                <p class="fw-bold fs-5 text-danger mb-2" id="hapusNama"></p>
                <p class="text-muted small mb-0" id="hapusInfo">Data yang dihapus tidak dapat dikembalikan.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <div class="d-flex gap-2 w-100">
                    <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left me-1"></i>Batal
                    </button>
                    <a id="hapusLink" href="#" class="btn btn-danger rounded-3 fw-semibold flex-fill">
                        <i class="bi bi-trash-fill me-1"></i>Ya, Hapus
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL HAPUS MASSAL -->
<div class="modal fade" id="modalHapusMassal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-hapus">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-trash-fill me-2"></i>Hapus Dipilih
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <!-- ALERT DALAM MODAL HAPUS MASSAL -->
                <div id="alertMassal" class="custom-alert mb-3 text-start">
                    <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                        <div id="alertMassalPesan"></div>
                    </div>
                </div>
                <div style="font-size:2.5rem;" class="mb-3">⚠️</div>
                <p class="fw-semibold mb-1">Yakin ingin menghapus <span id="masalJumlah" class="text-danger fw-bold"></span> pengguna yang dipilih?</p>
                <p class="text-muted small mb-0">Data yang dihapus tidak dapat dikembalikan.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <div class="d-flex gap-2 w-100">
                    <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left me-1"></i>Batal
                    </button>
                    <button type="button" id="btnYaHapusMassal" class="btn btn-danger rounded-3 fw-semibold flex-fill"
                        onclick="prosesHapusMassal()">
                        <i class="bi bi-trash-fill me-1"></i>Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL HAPUS SEMUA -->
<div class="modal fade" id="modalHapusSemua" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-hapus">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-trash3-fill me-2"></i>Hapus Semua
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <!-- ALERT DALAM MODAL HAPUS SEMUA -->
                <div id="alertSemua" class="custom-alert mb-3 text-start">
                    <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                        <div id="alertSemuaPesan"></div>
                    </div>
                </div>
                <div style="font-size:2.5rem;" class="mb-3">⚠️</div>
                <p class="fw-semibold mb-1">Yakin ingin menghapus <strong>semua pengguna</strong>?</p>
                <p class="text-muted small mb-0">Data yang dihapus tidak dapat dikembalikan.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <div class="d-flex gap-2 w-100">
                    <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left me-1"></i>Batal
                    </button>
                    <button type="button" id="btnYaHapusSemua" class="btn btn-danger rounded-3 fw-semibold flex-fill"
                        onclick="prosesHapusSemua()">
                        <i class="bi bi-trash3-fill me-1"></i>Ya, Hapus Semua
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .pengguna-banner {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .btn-pengguna {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
        color: #fff;
        border: none;
    }

    .btn-pengguna:hover {
        background: linear-gradient(135deg, #1a6b76 0%, #134f58 100%);
        color: #fff;
    }

    .card-solid {
        background-color: #ffffff;
        border: 1px solid #d6dee3 !important;
    }

    .custom-table thead th {
        background-color: #2f8f9d !important;
        color: #ffffff !important;
        border-right: 1px solid rgba(255, 255, 255, 0.3) !important;
    }

    .custom-table thead th:last-child {
        border-right: none !important;
    }

    .custom-table td {
        border-right: 1px solid #e9ecef;
    }

    .custom-table td:last-child {
        border-right: none;
    }

    .custom-table tbody tr:hover {
        background-color: #f1f3f5;
        transition: 0.2s ease;
    }

    .modal-header-custom {
        padding: 18px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-header-tambah {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .modal-header-edit {
        background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);
    }

    .modal-header-hapus {
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
        background-color: #fff3cd;
        color: inherit;
        padding: 0 2px;
        border-radius: 3px;
        font-weight: 600;
    }

    tr.row-match td {
        background-color: #fffbea !important;
    }

    .pagination-container .btn {
        min-width: 36px;
    }

    .pagination-container .btn.active {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
        color: #fff;
        border-color: transparent;
    }

    .toggle-val i {
        font-size: 0.95rem;
        transition: 0.2s;
    }

    .toggle-val:hover i {
        color: #2f8f9d !important;
    }

    @media (max-width: 576px) {
        .pengguna-banner .d-flex {
            flex-direction: column;
            gap: 12px;
        }
    }
</style>

<script>
    // FUNGSI INTI ALERT
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

    // PAGINATION
    let currentPage = 1;
    let perPage = 10;
    let filteredRows = [];
    let totalPages = 1;

    function getAllRows() {
        return Array.from(document.querySelectorAll('#tabelBody tr:not(.row-empty)'));
    }

    function escapeRegex(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightText(text, keyword) {
        if (!keyword) return text;
        const re = new RegExp(`(${escapeRegex(keyword)})`, 'gi');
        return text.replace(re, '<mark class="highlight">$1</mark>');
    }

    function applyFilter() {
        const keyword = document.getElementById('searchInput').value.trim().toLowerCase();
        const role = document.getElementById('filterRole').value;
        const status = document.getElementById('filterStatus').value;
        const all = getAllRows();

        filteredRows = all.filter(tr => {
            const nama = tr.dataset.nama || '';
            const username = tr.dataset.username || '';
            const email = tr.dataset.email || '';
            const tRole = tr.dataset.role || '';
            const tStatus = tr.dataset.status || '';

            const matchKey = !keyword || nama.includes(keyword) || username.includes(keyword) || email.includes(keyword);
            const matchRole = !role || tRole === role;
            const matchStatus = !status || tStatus === status;

            return matchKey && matchRole && matchStatus;
        });

        currentPage = 1;
        renderTable();
    }

    function renderTable() {
        const all = getAllRows();
        const keyword = document.getElementById('searchInput').value.trim().toLowerCase();

        all.forEach(tr => {
            tr.classList.add('d-none');
            tr.classList.remove('row-match');
        });

        const isAll = perPage === 'all';
        totalPages = isAll ? 1 : Math.ceil(filteredRows.length / perPage);
        if (currentPage > totalPages) currentPage = totalPages || 1;

        const start = isAll ? 0 : (currentPage - 1) * perPage;
        const end = isAll ? filteredRows.length : start + perPage;
        const pageRows = filteredRows.slice(start, end);

        pageRows.forEach((tr, i) => {
            tr.classList.remove('d-none');
            if (keyword) tr.classList.add('row-match');
            tr.querySelector('.col-no').textContent = start + i + 1;

            const cellNama = tr.querySelector('.col-nama');
            const origNama = tr.dataset.nama.replace(/\b\w/g, c => c.toUpperCase());
            cellNama.innerHTML = keyword ? highlightText(origNama, keyword) : origNama;

            const cellUsername = tr.querySelector('.col-username');
            const origUsername = tr.dataset.username;
            if (cellUsername) {
                cellUsername.innerHTML = keyword ? highlightText(origUsername, keyword) : origUsername;
            }
        });

        document.getElementById('emptySearch').classList.toggle('d-none', filteredRows.length > 0);
        document.getElementById('tabelPengguna').classList.toggle('d-none', filteredRows.length === 0);

        updateTombolHapusSemua();
        renderPagination();
    }

    function renderPagination() {
        const isAll = perPage === 'all';
        const total = filteredRows.length;
        const start = isAll ? 1 : ((currentPage - 1) * perPage) + 1;
        const end = isAll ? total : Math.min(currentPage * perPage, total);

        document.getElementById('btnFirst').disabled = currentPage <= 1;
        document.getElementById('btnPrev').disabled = currentPage <= 1;
        document.getElementById('btnNext').disabled = currentPage >= totalPages;
        document.getElementById('btnLast').disabled = currentPage >= totalPages;

        const pageNumbers = document.getElementById('pageNumbers');
        pageNumbers.innerHTML = '';

        if (!isAll) {
            let pages = [];
            if (totalPages <= 5) {
                for (let i = 1; i <= totalPages; i++) pages.push(i);
            } else {
                pages.push(1);
                if (currentPage > 3) pages.push('...');
                for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) pages.push(i);
                if (currentPage < totalPages - 2) pages.push('...');
                pages.push(totalPages);
            }
            pages.forEach(p => {
                if (p === '...') {
                    const span = document.createElement('span');
                    span.className = 'px-1 align-self-center text-muted';
                    span.textContent = '...';
                    pageNumbers.appendChild(span);
                } else {
                    const btn = document.createElement('button');
                    btn.className = 'btn btn-sm btn-outline-secondary rounded-3 px-3' + (p === currentPage ? ' active' : '');
                    btn.textContent = p;
                    btn.onclick = () => goPage(p);
                    pageNumbers.appendChild(btn);
                }
            });
        }

        const info = total === 0 ? 'Tidak ada data' :
            isAll ? `Menampilkan semua ${total} data` :
            `Menampilkan ${start}–${end} dari ${total} data • Halaman ${currentPage} / ${totalPages}`;

        document.getElementById('paginationInfo').textContent = info;
        document.getElementById('paginationWrapper').style.display = total === 0 ? 'none' : '';
    }

    function goPage(p) {
        if (p < 1 || p > totalPages) return;
        currentPage = p;
        renderTable();
    }

    document.getElementById('perPageSelect').addEventListener('change', function() {
        perPage = this.value === 'all' ? 'all' : parseInt(this.value);
        currentPage = 1;
        renderTable();
    });

    // CHECKBOX
    document.getElementById('checkAll').addEventListener('change', function() {
        const visible = filteredRows.filter(tr => !tr.classList.contains('d-none'));
        visible.forEach(tr => {
            tr.querySelector('.check-item').checked = this.checked;
        });
        updateHapusDipilih();
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('check-item')) {
            updateHapusDipilih();
            const all = filteredRows.filter(tr => !tr.classList.contains('d-none'));
            const checked = all.filter(tr => tr.querySelector('.check-item').checked);
            document.getElementById('checkAll').checked = all.length > 0 && checked.length === all.length;
        }
    });

    function updateHapusDipilih() {
        const checked = document.querySelectorAll('.check-item:checked');
        const btn = document.getElementById('btnHapusDipilih');
        const jumlah = checked.length;
        document.getElementById('jumlahDipilih').textContent = jumlah;
        if (jumlah === 0) {
            btn.classList.add('d-none');
        } else {
            btn.classList.remove('d-none');
            btn.disabled = jumlah <= 1;
            btn.title = jumlah <= 1 ? 'Pilih minimal 2 untuk hapus massal' : '';
        }
    }

    function updateTombolHapusSemua() {
        const total = getAllRows().length;
        const btn = document.getElementById('btnHapusSemua');
        btn.disabled = total <= 1;
        btn.title = total <= 1 ? 'Tidak bisa digunakan jika data hanya 1' : '';
    }

    // AJAX HAPUS MASSAL
    let idsMassal = [];

    function konfirmasiHapusMassal() {
        const checked = document.querySelectorAll('.check-item:checked');
        document.getElementById('masalJumlah').textContent = checked.length;
        idsMassal = Array.from(checked).map(cb => cb.value);
        sembunyikanAlert('alertMassal');
        new bootstrap.Modal(document.getElementById('modalHapusMassal')).show();
    }

    function prosesHapusMassal() {
        const btn = document.getElementById('btnYaHapusMassal');
        btn.disabled = true;
        const fd = new FormData();
        fd.append('aksi', 'hapus_massal');
        idsMassal.forEach(id => fd.append('ids[]', id));
        fetch('', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'gagal') {
                    tampilkanAlert('alertMassal', res.pesan);
                    btn.disabled = false;
                } else {
                    sessionStorage.setItem('flash_js', res.pesan);
                    location.reload();
                }
            })
            .catch(() => {
                tampilkanAlert('alertMassal', 'Terjadi kesalahan. Silakan coba lagi.');
                btn.disabled = false;
            });
    }

    function konfirmasiHapusSemua() {
        sembunyikanAlert('alertSemua');
        new bootstrap.Modal(document.getElementById('modalHapusSemua')).show();
    }

    function prosesHapusSemua() {
        const btn = document.getElementById('btnYaHapusSemua');
        btn.disabled = true;
        const fd = new FormData();
        fd.append('aksi', 'hapus_semua');
        fetch('', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'gagal') {
                    tampilkanAlert('alertSemua', res.pesan);
                    btn.disabled = false;
                } else {
                    sessionStorage.setItem('flash_js', res.pesan);
                    location.reload();
                }
            })
            .catch(() => {
                tampilkanAlert('alertSemua', 'Terjadi kesalahan. Silakan coba lagi.');
                btn.disabled = false;
            });
    }

    // MODAL HAPUS SINGLE
    function konfirmasiHapus(id, nama, isSelf) {
        document.getElementById('hapusNama').textContent = nama;
        const hapusLink = document.getElementById('hapusLink');
        if (isSelf) {
            document.getElementById('hapusInfo').textContent = 'Anda tidak bisa menghapus akun sendiri.';
            hapusLink.classList.add('disabled');
            hapusLink.setAttribute('aria-disabled', 'true');
            hapusLink.href = '#';
        } else {
            document.getElementById('hapusInfo').textContent = 'Data yang dihapus tidak dapat dikembalikan.';
            hapusLink.classList.remove('disabled');
            hapusLink.removeAttribute('aria-disabled');
            hapusLink.href = '?hapus=' + id;
        }
        new bootstrap.Modal(document.getElementById('modalHapus')).show();
    }

    // TOGGLE DIVISI
    function toggleDivisiTambah(role) {
        document.getElementById('wrapDivisiTambah').style.display = role === 'manajer' ? 'block' : 'none';
    }

    function toggleDivisiEdit(role) {
        document.getElementById('wrapDivisiEdit').style.display = role === 'manajer' ? 'block' : 'none';
    }

    // TOGGLE EMAIL & PASSWORD
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.toggle-val');
        if (!btn) return;
        const target = btn.dataset.target;
        const row = btn.closest('tr');
        const span = row.querySelector(`.val-${target}`);
        const icon = btn.querySelector('i');
        const hidden = '••••••••';
        if (span.textContent.trim() === hidden) {
            span.textContent = span.dataset.val;
            icon.className = 'bi bi-eye';
        } else {
            span.textContent = hidden;
            icon.className = 'bi bi-eye-slash';
        }
    });

    // MODAL AUTOFOCUS & RESET
    document.getElementById('modalTambah').addEventListener('shown.bs.modal', function() {
        document.getElementById('inputTambahNama').focus();
    });

    document.getElementById('modalTambah').addEventListener('hidden.bs.modal', function() {
        sembunyikanAlert('alertTambah');
        document.getElementById('formTambah').reset();
        toggleDivisiTambah('admin');
    });

    document.getElementById('modalEdit').addEventListener('shown.bs.modal', function() {
        document.getElementById('editNama').focus();
    });

    function bukaModalEdit(id, nama, email, role, status, divisiId) {
        document.getElementById('editUserId').value = id;
        document.getElementById('editNama').value = nama;
        document.getElementById('editEmail').value = email;
        document.getElementById('editRole').value = role;
        document.getElementById('editStatus').value = status;
        toggleDivisiEdit(role);
        if (role === 'manajer' && divisiId) {
            document.getElementById('editDivisi').value = divisiId;
        } else {
            document.getElementById('editDivisi').value = '';
        }
        sembunyikanAlert('alertEdit');
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    // INIT
    document.addEventListener('DOMContentLoaded', function() {
        aktivasiAlertLuar(document.getElementById('alertLuar'));

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

        applyFilter();
    });

    document.getElementById('searchInput').addEventListener('input', applyFilter);
    document.getElementById('filterRole').addEventListener('change', applyFilter);
    document.getElementById('filterStatus').addEventListener('change', applyFilter);
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>