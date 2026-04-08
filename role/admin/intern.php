<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../includes/fungsi.php";

proteksi("admin");

$success = "";
$error   = "";

// TAMBAH INTERN
if (isset($_POST['aksi']) && $_POST['aksi'] === "tambah") {
    $nama            = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $username        = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email           = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password        = mysqli_real_escape_string($conn, $_POST['password']);
    $divisi_id       = (int) $_POST['divisi_id'];
    $manajer_id      = (int) $_POST['manajer_id'];
    $tanggal_mulai   = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];

    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' OR email='$email'");
    if (mysqli_num_rows($cek) > 0) {
        $error = "Username atau email sudah digunakan!";
    } else {
        mysqli_query($conn, "INSERT INTO users (nama, username, email, password, role, status, created_at)
            VALUES ('$nama', '$username', '$email', '$password', 'intern', 'aktif', NOW())");
        $user_id = mysqli_insert_id($conn);

        mysqli_query($conn, "INSERT INTO intern_detail (user_id, divisi_id, manajer_id, tanggal_mulai, tanggal_selesai, created_at)
            VALUES ($user_id, $divisi_id, $manajer_id, '$tanggal_mulai', '$tanggal_selesai', NOW())");

        $success = "Intern berhasil ditambahkan!";
    }
}

// EDIT INTERN
if (isset($_POST['aksi']) && $_POST['aksi'] === "edit") {
    $id              = (int) $_POST['user_id'];
    $nama            = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $email           = mysqli_real_escape_string($conn, trim($_POST['email']));
    $divisi_id       = (int) $_POST['divisi_id'];
    $manajer_id      = (int) $_POST['manajer_id'];
    $tanggal_mulai   = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];

    $cek = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND id != $id");
    if (mysqli_num_rows($cek) > 0) {
        $error = "Email sudah digunakan pengguna lain!";
    } else {
        // Cek status otomatis berdasarkan tanggal selesai
        $status = (strtotime($tanggal_selesai) >= strtotime(date('Y-m-d'))) ? 'aktif' : 'tidak_aktif';

        mysqli_query($conn, "UPDATE users SET nama='$nama', email='$email', status='$status' WHERE id=$id");
        mysqli_query($conn, "UPDATE intern_detail SET divisi_id=$divisi_id, manajer_id=$manajer_id,
            tanggal_mulai='$tanggal_mulai', tanggal_selesai='$tanggal_selesai' WHERE user_id=$id");

        $success = "Data intern berhasil diperbarui!";
    }
}

// HAPUS INTERN (masuk cadangan)
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    $intern = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT u.*, id.divisi_id, id.manajer_id, id.tanggal_mulai, id.tanggal_selesai
        FROM users u
        JOIN intern_detail id ON u.id = id.user_id
        WHERE u.id = $id
    "));

    if ($intern) {
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

        mysqli_query($conn, "INSERT INTO cadangan (user_id, nama, username, email, foto_profil, divisi_id, manajer_id, tanggal_mulai, tanggal_selesai, deleted_at, expires_at)
            VALUES (
                {$intern['id']},
                '" . mysqli_real_escape_string($conn, $intern['nama']) . "',
                '" . mysqli_real_escape_string($conn, $intern['username']) . "',
                '" . mysqli_real_escape_string($conn, $intern['email']) . "',
                '" . mysqli_real_escape_string($conn, $intern['foto_profil']) . "',
                {$intern['divisi_id']},
                {$intern['manajer_id']},
                '{$intern['tanggal_mulai']}',
                '{$intern['tanggal_selesai']}',
                NOW(),
                '$expires_at'
            )");

        mysqli_query($conn, "DELETE FROM users WHERE id=$id");
        header("Location: " . BASE_URL . "role/admin/intern.php?hapus=success");
        exit;
    }
}

if (isset($_GET['hapus']) && $_GET['hapus'] === "success") {
    $success = "Intern berhasil dipindahkan ke cadangan!";
}

// AKTIFKAN KEMBALI INTERN
if (isset($_POST['aksi']) && $_POST['aksi'] === "aktifkan") {
    $id              = (int) $_POST['user_id'];
    $tanggal_selesai = $_POST['tanggal_selesai'];

    mysqli_query($conn, "UPDATE users SET status='aktif' WHERE id=$id");
    mysqli_query($conn, "UPDATE intern_detail SET tanggal_selesai='$tanggal_selesai' WHERE user_id=$id");

    $success = "Intern berhasil diaktifkan kembali!";
}

// AMBIL DATA INTERN
$data = mysqli_query($conn, "
    SELECT u.*, id.divisi_id, id.manajer_id, id.tanggal_mulai, id.tanggal_selesai,
        d.nama_divisi, m.nama AS nama_manajer
    FROM users u
    JOIN intern_detail id ON u.id = id.user_id
    JOIN divisi d ON id.divisi_id = d.id
    JOIN users m ON id.manajer_id = m.id
    WHERE u.role = 'intern'
    ORDER BY u.created_at DESC
");
$rows = [];
while ($row = mysqli_fetch_assoc($data)) {
    $rows[] = $row;
}

// AMBIL DAFTAR DIVISI
$q_divisi = mysqli_query($conn, "SELECT * FROM divisi ORDER BY nama_divisi ASC");
$list_divisi = [];
while ($d = mysqli_fetch_assoc($q_divisi)) {
    $list_divisi[] = $d;
}

// AMBIL DAFTAR MANAJER
$q_manajer = mysqli_query($conn, "SELECT * FROM users WHERE role='manajer' AND status='aktif' ORDER BY nama ASC");
$list_manajer = [];
while ($m = mysqli_fetch_assoc($q_manajer)) {
    $list_manajer[] = $m;
}

$judul_halaman = "Intern";
include BASE_PATH . "includes/header.php";
include BASE_PATH . "includes/sidebar.php";
?>

<!-- BANNER -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="intern-banner px-4 py-4">
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <h3 class="text-white mb-1 fw-bold"><i class="bi bi-person-badge-fill me-2"></i>Kelola Intern</h3>
                <p class="text-white mb-0" style="color: rgba(255,255,255,0.75) !important;">
                    Manajemen akun dan periode magang intern
                </p>
            </div>
            <button class="btn btn-light fw-semibold rounded-3"
                data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg me-1"></i>Tambah Intern
            </button>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div><?= $success ?></div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div><?= $error ?></div>
    </div>
<?php endif; ?>

<!-- SEARCH & FILTER -->
<div class="card border-0 shadow mb-4 card-solid">
    <div class="p-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Cari Intern</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-3"
                        placeholder="Nama, username, atau email...">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Filter Divisi</label>
                <select id="filterDivisi" class="form-select rounded-3">
                    <option value="">Semua Divisi</option>
                    <?php foreach ($list_divisi as $div): ?>
                        <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Filter Status</label>
                <select id="filterStatus" class="form-select rounded-3">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="tidak_aktif">Tidak Aktif</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- TABEL DATA INTERN -->
<div class="card border-0 shadow rounded-4 overflow-hidden card-solid">
    <div class="p-4">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0 custom-table" id="tabelIntern">
                <thead>
                    <tr>
                        <th class="ps-3">No</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Divisi</th>
                        <th>Manajer</th>
                        <th>Tanggal Mulai</th>
                        <th>Tanggal Selesai</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tabelBody">
                    <?php if (count($rows) === 0): ?>
                        <tr class="row-empty">
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x d-block mb-2" style="font-size:2rem;"></i>
                                <div class="fw-semibold">Belum ada data intern</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php $no = 1;
                    foreach ($rows as $row): ?>
                        <tr
                            data-nama="<?= strtolower(htmlspecialchars($row['nama'])) ?>"
                            data-username="<?= strtolower(htmlspecialchars($row['username'])) ?>"
                            data-email="<?= strtolower(htmlspecialchars($row['email'])) ?>"
                            data-divisi="<?= $row['divisi_id'] ?>"
                            data-status="<?= $row['status'] ?>">

                            <td class="ps-3 col-no"><?= $no++ ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td>
                                <span class="badge bg-secondary rounded-pill px-3 py-2">
                                    <?= htmlspecialchars($row['nama_divisi']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['nama_manajer']) ?></td>
                            <td><?= formatTanggal($row['tanggal_mulai']) ?></td>
                            <td><?= formatTanggal($row['tanggal_selesai']) ?></td>

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
                                        <?= $row['divisi_id'] ?>,
                                        <?= $row['manajer_id'] ?>,
                                        '<?= $row['tanggal_mulai'] ?>',
                                        '<?= $row['tanggal_selesai'] ?>'
                                    )">
                                    <i class="bi bi-pencil-fill me-1"></i>Edit
                                </button>

                                <?php if ($row['status'] === 'tidak_aktif'): ?>
                                    <button class="btn btn-sm btn-success rounded-3 me-1 fw-semibold"
                                        onclick="bukaModalAktifkan(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Aktifkan
                                    </button>
                                <?php endif; ?>

                                <button class="btn btn-sm btn-danger rounded-3 fw-semibold"
                                    onclick="konfirmasiHapus(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama'], ENT_QUOTES) ?>')">
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
                <div class="fw-semibold">Tidak ada intern yang cocok</div>
                <div class="small mt-1">Coba kata kunci lain</div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH INTERN -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <form method="POST">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header-custom modal-header-tambah">
                    <h5 class="text-white fw-bold mb-0">
                        <i class="bi bi-person-plus-fill me-2"></i>Tambah Intern
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control rounded-3" required>
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
                            <label class="form-label fw-semibold">Divisi</label>
                            <select name="divisi_id" class="form-select rounded-3" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($list_divisi as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Manajer</label>
                            <select name="manajer_id" class="form-select rounded-3" required>
                                <option value="">-- Pilih Manajer --</option>
                                <?php foreach ($list_manajer as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Selesai</label>
                            <input type="date" name="tanggal_selesai" class="form-control rounded-3" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-intern rounded-3 fw-semibold flex-fill">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Intern
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT INTERN -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <form method="POST">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header-custom modal-header-edit">
                    <h5 class="text-white fw-bold mb-0">
                        <i class="bi bi-pencil-fill me-2"></i>Edit Intern
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-4">
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
                            <label class="form-label fw-semibold">Divisi</label>
                            <select name="divisi_id" id="editDivisi" class="form-select rounded-3" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($list_divisi as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Manajer</label>
                            <select name="manajer_id" id="editManajer" class="form-select rounded-3" required>
                                <option value="">-- Pilih Manajer --</option>
                                <?php foreach ($list_manajer as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" id="editTanggalMulai" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Selesai</label>
                            <input type="date" name="tanggal_selesai" id="editTanggalSelesai" class="form-control rounded-3" required>
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

<!-- MODAL AKTIFKAN KEMBALI -->
<div class="modal fade" id="modalAktifkan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <form method="POST">
                <input type="hidden" name="aksi" value="aktifkan">
                <input type="hidden" name="user_id" id="aktifkanUserId">
                <div class="modal-header-custom modal-header-aktifkan">
                    <h5 class="text-white fw-bold mb-0">
                        <i class="bi bi-arrow-clockwise me-2"></i>Aktifkan Kembali
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <p class="mb-3">Aktifkan kembali intern: <strong id="aktifkanNama"></strong></p>
                    <label class="form-label fw-semibold">Tanggal Selesai Baru</label>
                    <input type="date" name="tanggal_selesai" id="aktifkanTanggal" class="form-control rounded-3" required>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-success rounded-3 fw-semibold flex-fill">
                            <i class="bi bi-arrow-clockwise me-1"></i>Aktifkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL HAPUS -->
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
                <p class="fw-semibold mb-1">Yakin ingin menghapus intern:</p>
                <p class="fw-bold fs-5 text-danger mb-2" id="hapusNama"></p>
                <p class="text-muted small mb-0">Data akan dipindahkan ke cadangan selama 30 hari sebelum dihapus permanen.</p>
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

<style>
    .intern-banner {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .btn-intern {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
        color: #fff;
        border: none;
    }

    .btn-intern:hover {
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

    .modal-header-aktifkan {
        background: linear-gradient(135deg, #5BAE3C 0%, #388e3c 100%);
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

    @media (max-width: 576px) {
        .intern-banner .d-flex {
            flex-direction: column;
            gap: 12px;
        }
    }
</style>

<script>
    function filterTabel() {
        const keyword = document.getElementById('searchInput').value.trim().toLowerCase();
        const divisi = document.getElementById('filterDivisi').value;
        const status = document.getElementById('filterStatus').value;
        const rows = document.querySelectorAll('#tabelBody tr:not(.row-empty)');
        let visible = 0;

        function escapeRegex(s) {
            return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function highlightText(text, keyword) {
            if (!keyword) return text;
            const re = new RegExp(`(${escapeRegex(keyword)})`, 'gi');
            return text.replace(re, '<mark class="highlight">$1</mark>');
        }

        rows.forEach(tr => {
            const nama = tr.dataset.nama || '';
            const username = tr.dataset.username || '';
            const email = tr.dataset.email || '';
            const tDivisi = tr.dataset.divisi || '';
            const tStatus = tr.dataset.status || '';

            const matchKey = !keyword || nama.includes(keyword) || username.includes(keyword) || email.includes(keyword);
            const matchDivisi = !divisi || tDivisi === divisi;
            const matchStatus = !status || tStatus === status;

            if (matchKey && matchDivisi && matchStatus) {
                tr.classList.remove('d-none');
                tr.classList.toggle('row-match', keyword.length > 0);
                const cells = tr.querySelectorAll('td');
                const origNama = tr.dataset.nama.replace(/\b\w/g, c => c.toUpperCase());
                cells[1].innerHTML = keyword ? highlightText(origNama, keyword) : origNama;
                visible++;
            } else {
                tr.classList.add('d-none');
                tr.classList.remove('row-match');
            }
        });

        let no = 1;
        rows.forEach(tr => {
            if (!tr.classList.contains('d-none')) {
                tr.querySelector('.col-no').textContent = no++;
            }
        });

        document.getElementById('emptySearch').classList.toggle('d-none', visible > 0);
        document.getElementById('tabelIntern').classList.toggle('d-none', visible === 0);
    }

    document.getElementById('searchInput').addEventListener('input', filterTabel);
    document.getElementById('filterDivisi').addEventListener('change', filterTabel);
    document.getElementById('filterStatus').addEventListener('change', filterTabel);

    function bukaModalEdit(id, nama, email, divisiId, manajerId, tanggalMulai, tanggalSelesai) {
        document.getElementById('editUserId').value = id;
        document.getElementById('editNama').value = nama;
        document.getElementById('editEmail').value = email;
        document.getElementById('editDivisi').value = divisiId;
        document.getElementById('editManajer').value = manajerId;
        document.getElementById('editTanggalMulai').value = tanggalMulai;
        document.getElementById('editTanggalSelesai').value = tanggalSelesai;
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    function bukaModalAktifkan(id, nama) {
        document.getElementById('aktifkanUserId').value = id;
        document.getElementById('aktifkanNama').textContent = nama;
        document.getElementById('aktifkanTanggal').value = '';
        new bootstrap.Modal(document.getElementById('modalAktifkan')).show();
    }

    function konfirmasiHapus(id, nama) {
        document.getElementById('hapusNama').textContent = nama;
        document.getElementById('hapusLink').href = '?hapus=' + id;
        new bootstrap.Modal(document.getElementById('modalHapus')).show();
    }
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>