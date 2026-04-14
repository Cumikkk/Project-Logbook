<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../includes/fungsi.php";

proteksi("admin");

$flash_success = "";
$flash_error   = "";

// AJAX CEK DUPLIKAT USERNAME
if (isset($_POST['aksi']) && $_POST['aksi'] === "cek_username") {
    $username   = mysqli_real_escape_string($conn, trim($_POST['username']));
    $exclude_id = isset($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : 0;

    if ($exclude_id > 0) {
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id != $exclude_id");
    } else {
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
    }

    header('Content-Type: application/json');
    echo json_encode(['duplikat' => mysqli_num_rows($cek) > 0]);
    exit;
}

// AJAX CEK DUPLIKAT EMAIL
if (isset($_POST['aksi']) && $_POST['aksi'] === "cek_email") {
    $email      = mysqli_real_escape_string($conn, trim($_POST['email']));
    $exclude_id = isset($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : 0;

    if ($exclude_id > 0) {
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' AND id != $exclude_id");
    } else {
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
    }

    header('Content-Type: application/json');
    echo json_encode(['duplikat' => mysqli_num_rows($cek) > 0]);
    exit;
}

// AJAX HAPUS MASSAL
if (isset($_POST['aksi']) && $_POST['aksi'] === "hapus_massal") {
    $ids    = isset($_POST['ids']) ? $_POST['ids'] : [];
    $mode   = isset($_POST['mode']) ? $_POST['mode'] : 'cadangan';
    $berhasil = 0;

    foreach ($ids as $id) {
        $id = (int) $id;
        $intern = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT u.*, id2.divisi_id, id2.manajer_id, id2.tanggal_mulai, id2.tanggal_selesai
            FROM users u
            JOIN intern_detail id2 ON u.id = id2.user_id
            WHERE u.id = $id AND u.role = 'intern'
        "));

        if (!$intern) continue;

        if ($mode === 'cadangan') {
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
        }

        mysqli_query($conn, "DELETE FROM users WHERE id=$id");
        $berhasil++;
    }

    header('Content-Type: application/json');
    $label = $mode === 'permanen' ? 'dihapus permanen' : 'dipindahkan ke cadangan';
    echo json_encode([
        'status' => 'berhasil',
        'pesan'  => "$berhasil intern berhasil $label."
    ]);
    exit;
}

// AJAX HAPUS SEMUA
if (isset($_POST['aksi']) && $_POST['aksi'] === "hapus_semua") {
    $mode     = isset($_POST['mode']) ? $_POST['mode'] : 'cadangan';
    $semua    = mysqli_query($conn, "SELECT u.*, id2.divisi_id, id2.manajer_id, id2.tanggal_mulai, id2.tanggal_selesai FROM users u JOIN intern_detail id2 ON u.id = id2.user_id WHERE u.role = 'intern'");
    $berhasil = 0;

    while ($intern = mysqli_fetch_assoc($semua)) {
        if ($mode === 'cadangan') {
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
        }

        mysqli_query($conn, "DELETE FROM users WHERE id={$intern['id']}");
        $berhasil++;
    }

    header('Content-Type: application/json');
    $label = $mode === 'permanen' ? 'dihapus permanen' : 'dipindahkan ke cadangan';
    echo json_encode([
        'status' => 'berhasil',
        'pesan'  => "$berhasil intern berhasil $label."
    ]);
    exit;
}

// TAMBAH INTERN
if (isset($_POST['aksi']) && $_POST['aksi'] === "tambah") {
    $nama            = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $username        = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password        = mysqli_real_escape_string($conn, $_POST['password']);
    $divisi_id       = (int) $_POST['divisi_id'];
    $manajer_id      = (int) $_POST['manajer_id'];
    $tanggal_mulai   = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];

    $cek = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['flash_error'] = "Username sudah digunakan!";
    } else {
        mysqli_query($conn, "INSERT INTO users (nama, username, email, password, role, status, created_at)
            VALUES ('$nama', '$username', NULL, '$password', 'intern', 'aktif', NOW())");
        $user_id = mysqli_insert_id($conn);

        mysqli_query($conn, "INSERT INTO intern_detail (user_id, divisi_id, manajer_id, tanggal_mulai, tanggal_selesai, created_at)
            VALUES ($user_id, $divisi_id, $manajer_id, '$tanggal_mulai', '$tanggal_selesai', NOW())");

        $_SESSION['flash_success'] = "Intern berhasil ditambahkan!";
    }
    header("Location: " . BASE_URL . "role/admin/intern.php");
    exit;
}

// EDIT INTERN
if (isset($_POST['aksi']) && $_POST['aksi'] === "edit") {
    $id              = (int) $_POST['user_id'];
    $nama            = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $username        = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email           = mysqli_real_escape_string($conn, trim($_POST['email']));
    $divisi_id       = (int) $_POST['divisi_id'];
    $manajer_id      = (int) $_POST['manajer_id'];
    $tanggal_mulai   = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];
    $password_baru   = trim($_POST['password']);

    $cek_username = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id != $id");
    $cek_email    = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' AND id != $id");

    if (mysqli_num_rows($cek_username) > 0) {
        $_SESSION['flash_error'] = "Username sudah digunakan intern lain!";
    } elseif (mysqli_num_rows($cek_email) > 0) {
        $_SESSION['flash_error'] = "Email sudah digunakan intern lain!";
    } else {
        $status = (strtotime($tanggal_selesai) >= strtotime(date('Y-m-d'))) ? 'aktif' : 'tidak_aktif';

        if ($password_baru !== '') {
            $password_baru_escaped = mysqli_real_escape_string($conn, $password_baru);
            mysqli_query($conn, "UPDATE users SET nama='$nama', username='$username', email='$email', password='$password_baru_escaped', status='$status' WHERE id=$id");
        } else {
            mysqli_query($conn, "UPDATE users SET nama='$nama', username='$username', email='$email', status='$status' WHERE id=$id");
        }

        mysqli_query($conn, "UPDATE intern_detail SET divisi_id=$divisi_id, manajer_id=$manajer_id,
            tanggal_mulai='$tanggal_mulai', tanggal_selesai='$tanggal_selesai' WHERE user_id=$id");

        $_SESSION['flash_success'] = "Data intern berhasil diperbarui!";
    }
    header("Location: " . BASE_URL . "role/admin/intern.php");
    exit;
}

// HAPUS INTERN SINGLE
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $id   = (int) $_GET['hapus'];
    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'cadangan';

    $intern = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT u.*, id2.divisi_id, id2.manajer_id, id2.tanggal_mulai, id2.tanggal_selesai
        FROM users u
        JOIN intern_detail id2 ON u.id = id2.user_id
        WHERE u.id = $id AND u.role = 'intern'
    "));

    if ($intern) {
        if ($mode === 'cadangan') {
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
            $_SESSION['flash_success'] = "Intern berhasil dipindahkan ke cadangan!";
        } else {
            $_SESSION['flash_success'] = "Intern berhasil dihapus permanen!";
        }

        mysqli_query($conn, "DELETE FROM users WHERE id=$id");
    }

    header("Location: " . BASE_URL . "role/admin/intern.php");
    exit;
}

// AKTIFKAN KEMBALI INTERN
if (isset($_POST['aksi']) && $_POST['aksi'] === "aktifkan") {
    $id              = (int) $_POST['user_id'];
    $tanggal_selesai = $_POST['tanggal_selesai'];

    mysqli_query($conn, "UPDATE users SET status='aktif' WHERE id=$id");
    mysqli_query($conn, "UPDATE intern_detail SET tanggal_selesai='$tanggal_selesai' WHERE user_id=$id");

    $_SESSION['flash_success'] = "Intern berhasil diaktifkan kembali!";
    header("Location: " . BASE_URL . "role/admin/intern.php");
    exit;
}

// FLASH MESSAGE
if (isset($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// AMBIL DATA INTERN
$data = mysqli_query($conn, "
    SELECT u.*, id2.divisi_id, id2.manajer_id, id2.tanggal_mulai, id2.tanggal_selesai,
        d.nama_divisi, m.nama AS nama_manajer
    FROM users u
    JOIN intern_detail id2 ON u.id = id2.user_id
    JOIN divisi d ON id2.divisi_id = d.id
    JOIN users m ON id2.manajer_id = m.id
    WHERE u.role = 'intern'
    ORDER BY u.created_at DESC
");
$rows = [];
while ($row = mysqli_fetch_assoc($data)) {
    $rows[] = $row;
}

// AMBIL DAFTAR DIVISI + MANAJER
$q_divisi = mysqli_query($conn, "
    SELECT d.id, d.nama_divisi, d.manajer_id, u.nama AS nama_manajer
    FROM divisi d
    LEFT JOIN users u ON d.manajer_id = u.id AND u.role = 'manajer'
    ORDER BY d.nama_divisi ASC
");
$list_divisi   = [];
$map_divisi_manajer = [];
while ($d = mysqli_fetch_assoc($q_divisi)) {
    $list_divisi[] = $d;
    $map_divisi_manajer[$d['id']] = [
        'manajer_id'   => $d['manajer_id'],
        'nama_manajer' => $d['nama_manajer'] ?? ''
    ];
}

// AMBIL DAFTAR MANAJER
$q_manajer = mysqli_query($conn, "SELECT id, nama FROM users WHERE role='manajer' AND status='aktif' ORDER BY nama ASC");
$list_manajer = [];
while ($m = mysqli_fetch_assoc($q_manajer)) {
    $list_manajer[] = $m;
}

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

<!-- NOTIFIKASI LUAR -->
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
                <label class="form-label fw-semibold">Cari Intern</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-3"
                        placeholder="Nama, username...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Filter Divisi</label>
                <select id="filterDivisi" class="form-select rounded-3">
                    <option value="">Semua Divisi</option>
                    <?php foreach ($list_divisi as $div): ?>
                        <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Filter Status</label>
                <select id="filterStatus" class="form-select rounded-3">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="tidak_aktif">Tidak Aktif</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2 justify-content-md-end flex-wrap">
                <button id="btnHapusDipilih" class="btn btn-danger rounded-3 fw-semibold d-none"
                    onclick="konfirmasiHapusMassal()">
                    <i class="bi bi-trash-fill me-1"></i>Hapus (<span id="jumlahDipilih">0</span>)
                </button>
                <button id="btnHapusSemua" class="btn btn-outline-danger rounded-3 fw-semibold"
                    onclick="konfirmasiHapusSemua()">
                    <i class="bi bi-trash3-fill me-1"></i>Hapus Semua
                </button>
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
                        <th class="ps-3" style="width:40px;">
                            <input type="checkbox" id="checkAll" class="form-check-input">
                        </th>
                        <th style="width:50px;">No</th>
                        <th>Nama</th>
                        <th>Username</th>
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
                        <tr class="row-empty-data">
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
                            data-divisi="<?= $row['divisi_id'] ?>"
                            data-status="<?= $row['status'] ?>"
                            data-id="<?= $row['id'] ?>">
                            <td class="ps-3">
                                <input type="checkbox" class="form-check-input check-item" value="<?= $row['id'] ?>">
                            </td>
                            <td class="col-no"><?= $no++ ?></td>
                            <td class="fw-semibold col-nama"><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td>
                                <span class="badge bg-secondary rounded-pill px-3 py-2">
                                    <?= htmlspecialchars($row['nama_divisi']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['nama_manajer']) ?></td>
                            <td><?= formatTanggal($row['tanggal_mulai']) ?></td>
                            <td><?= formatTanggal($row['tanggal_selesai']) ?></td>
                            <td>
                                <?php if ($row['status'] === 'aktif'): ?>
                                    <span class="badge bg-success rounded-pill px-3 py-2">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3 py-2">Tidak Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning rounded-3 me-1 fw-semibold"
                                    onclick="bukaModalEdit(
                                        <?= $row['id'] ?>,
                                        '<?= htmlspecialchars($row['nama'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>',
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

                    <!-- EMPTY STATE SEARCH -->
                    <tr id="emptySearch" class="d-none">
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-search d-block mb-2" style="font-size:2rem;"></i>
                            <div class="fw-semibold">Tidak ada intern yang cocok</div>
                            <div class="small mt-1">Coba kata kunci lain</div>
                        </td>
                    </tr>
                </tbody>
            </table>
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
                    <div id="alertTambah" class="custom-alert mb-3">
                        <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                            <div id="alertTambahPesan"></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama" id="tambahNama" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" id="tambahUsername" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="text" name="password" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Divisi</label>
                            <select name="divisi_id" id="tambahDivisi" class="form-select rounded-3" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($list_divisi as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Manajer</label>
                            <input type="text" id="tambahManajerNama" class="form-control rounded-3 bg-light" readonly
                                placeholder="Otomatis dari divisi">
                            <input type="hidden" name="manajer_id" id="tambahManajerId">
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
                        <button type="submit" id="btnSubmitTambah" class="btn btn-intern rounded-3 fw-semibold flex-fill">
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
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" id="editUsername" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password Baru <small class="text-muted fw-normal">(kosongkan jika tidak diubah)</small></label>
                            <input type="text" name="password" id="editPassword" class="form-control rounded-3" placeholder="Kosongkan jika tidak diubah">
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
                            <input type="text" id="editManajerNama" class="form-control rounded-3 bg-light" readonly
                                placeholder="Otomatis dari divisi">
                            <input type="hidden" name="manajer_id" id="editManajerId">
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
                        <button type="submit" id="btnSubmitEdit" class="btn btn-warning rounded-3 fw-semibold flex-fill text-white">
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

<!-- MODAL HAPUS SINGLE -->
<div class="modal fade" id="modalHapus" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
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
                <p class="fw-bold fs-5 text-danger mb-3" id="hapusNama"></p>
                <div class="text-start">
                    <p class="fw-semibold mb-2">Pilih tindakan:</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="hapusMode" id="hapusModeCadangan" value="cadangan" checked>
                        <label class="form-check-label" for="hapusModeCadangan">
                            Pindahkan ke cadangan
                            <div class="text-muted small">(dapat dipulihkan dalam 30 hari)</div>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="hapusMode" id="hapusModePermanen" value="permanen">
                        <label class="form-check-label" for="hapusModePermanen">
                            Hapus permanen
                            <div class="text-muted small">(data tidak dapat dikembalikan)</div>
                        </label>
                    </div>
                </div>
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
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-hapus">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-trash-fill me-2"></i>Hapus Dipilih
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <div id="alertMassal" class="custom-alert mb-3">
                    <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0 text-start" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                        <div id="alertMassalPesan"></div>
                    </div>
                </div>
                <div style="font-size:2.5rem;" class="mb-3">⚠️</div>
                <p class="fw-semibold mb-3">Yakin ingin menghapus <span id="masalJumlah" class="text-danger"></span> intern yang dipilih?</p>
                <div class="text-start">
                    <p class="fw-semibold mb-2">Pilih tindakan:</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="hapusMassalMode" id="massalModeCadangan" value="cadangan" checked>
                        <label class="form-check-label" for="massalModeCadangan">
                            Pindahkan ke cadangan
                            <div class="text-muted small">(dapat dipulihkan dalam 30 hari)</div>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="hapusMassalMode" id="massalModePermanen" value="permanen">
                        <label class="form-check-label" for="massalModePermanen">
                            Hapus permanen
                            <div class="text-muted small">(data tidak dapat dikembalikan)</div>
                        </label>
                    </div>
                </div>
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
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-hapus">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-trash3-fill me-2"></i>Hapus Semua
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <div id="alertSemua" class="custom-alert mb-3">
                    <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0 text-start" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                        <div id="alertSemuaPesan"></div>
                    </div>
                </div>
                <div style="font-size:2.5rem;" class="mb-3">⚠️</div>
                <p class="fw-semibold mb-3">Yakin ingin menghapus semua intern?</p>
                <div class="text-start">
                    <p class="fw-semibold mb-2">Pilih tindakan:</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="hapusSemuaMode" id="semuaModeCadangan" value="cadangan" checked>
                        <label class="form-check-label" for="semuaModeCadangan">
                            Pindahkan ke cadangan
                            <div class="text-muted small">(dapat dipulihkan dalam 30 hari)</div>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="hapusSemuaMode" id="semuaModePermanen" value="permanen">
                        <label class="form-check-label" for="semuaModePermanen">
                            Hapus permanen
                            <div class="text-muted small">(data tidak dapat dikembalikan)</div>
                        </label>
                    </div>
                </div>
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

    .row-empty-data td,
    #emptySearch td {
        background-color: #f1f3f5 !important;
    }

    @media (max-width: 576px) {
        .intern-banner .d-flex {
            flex-direction: column;
            gap: 12px;
        }

        .pagination-container {
            flex-wrap: wrap;
            justify-content: center;
        }
    }
</style>

<script>
    // DATA DIVISI -> MANAJER (embed dari PHP)
    const dataDivisiManajer = <?= json_encode($map_divisi_manajer) ?>;

    function setManajerDariDivisi(divisiId, fieldNama, fieldId) {
        const data = dataDivisiManajer[divisiId];
        if (data && data.manajer_id) {
            fieldNama.value = data.nama_manajer;
            fieldId.value = data.manajer_id;
            fieldNama.placeholder = '';
        } else {
            fieldNama.value = '';
            fieldId.value = '';
            fieldNama.placeholder = 'Belum ada manajer';
        }
    }

    document.getElementById('tambahDivisi').addEventListener('change', function() {
        setManajerDariDivisi(
            this.value,
            document.getElementById('tambahManajerNama'),
            document.getElementById('tambahManajerId')
        );
    });

    document.getElementById('editDivisi').addEventListener('change', function() {
        setManajerDariDivisi(
            this.value,
            document.getElementById('editManajerNama'),
            document.getElementById('editManajerId')
        );
    });

    // PAGINATION
    let currentPage = 1;
    let perPage = 10;
    let filteredRows = [];
    let totalPages = 1;
    let idsMassal = [];

    function getAllRows() {
        return Array.from(document.querySelectorAll('#tabelBody tr:not(.row-empty-data):not(#emptySearch)'));
    }

    function applyFilter() {
        const keyword = document.getElementById('searchInput').value.trim().toLowerCase();
        const divisi = document.getElementById('filterDivisi').value;
        const status = document.getElementById('filterStatus').value;
        const all = getAllRows();

        filteredRows = all.filter(tr => {
            const matchKey = !keyword || (tr.dataset.nama || '').includes(keyword) || (tr.dataset.username || '').includes(keyword);
            const matchDivisi = !divisi || tr.dataset.divisi === divisi;
            const matchStatus = !status || tr.dataset.status === status;
            return matchKey && matchDivisi && matchStatus;
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
        });

        const emptySearch = document.getElementById('emptySearch');
        const adaData = getAllRows().length > 0;
        const adaHasil = filteredRows.length > 0;

        if (adaData && !adaHasil) {
            emptySearch.classList.remove('d-none');
        } else {
            emptySearch.classList.add('d-none');
        }

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
        document.getElementById('paginationWrapper').style.display = getAllRows().length === 0 ? 'none' : '';
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

    // SEARCH & FILTER
    function escapeRegex(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightText(text, keyword) {
        if (!keyword) return text;
        const re = new RegExp(`(${escapeRegex(keyword)})`, 'gi');
        return text.replace(re, '<mark class="highlight">$1</mark>');
    }

    document.getElementById('searchInput').addEventListener('input', applyFilter);
    document.getElementById('filterDivisi').addEventListener('change', applyFilter);
    document.getElementById('filterStatus').addEventListener('change', applyFilter);

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
            btn.title = jumlah <= 1 ? 'Pilih minimal 2 intern untuk hapus massal' : '';
        }
    }

    function updateTombolHapusSemua() {
        const total = getAllRows().length;
        const btn = document.getElementById('btnHapusSemua');
        btn.disabled = total <= 1;
        btn.title = total <= 1 ? 'Tidak bisa digunakan jika data hanya 1' : '';
    }

    // ALERT TIMERS
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

    // MODAL HAPUS SINGLE
    function konfirmasiHapus(id, nama) {
        document.getElementById('hapusNama').textContent = nama;
        document.getElementById('hapusModeCadangan').checked = true;

        document.getElementById('hapusLink').onclick = function(e) {
            e.preventDefault();
            const mode = document.querySelector('input[name="hapusMode"]:checked').value;
            window.location.href = '?hapus=' + id + '&mode=' + mode;
        };

        new bootstrap.Modal(document.getElementById('modalHapus')).show();
    }

    // MODAL HAPUS MASSAL
    function konfirmasiHapusMassal() {
        const checked = document.querySelectorAll('.check-item:checked');
        document.getElementById('masalJumlah').textContent = checked.length;
        idsMassal = Array.from(checked).map(cb => cb.value);
        document.getElementById('massalModeCadangan').checked = true;
        sembunyikanAlert('alertMassal');
        new bootstrap.Modal(document.getElementById('modalHapusMassal')).show();
    }

    function prosesHapusMassal() {
        const btn = document.getElementById('btnYaHapusMassal');
        const mode = document.querySelector('input[name="hapusMassalMode"]:checked').value;
        btn.disabled = true;

        const fd = new FormData();
        fd.append('aksi', 'hapus_massal');
        fd.append('mode', mode);
        idsMassal.forEach(id => fd.append('ids[]', id));

        fetch('', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                sessionStorage.setItem('flash_js', res.pesan);
                location.reload();
            })
            .catch(() => {
                tampilkanAlert('alertMassal', 'Terjadi kesalahan. Silakan coba lagi.');
                btn.disabled = false;
            });
    }

    // MODAL HAPUS SEMUA
    function konfirmasiHapusSemua() {
        document.getElementById('semuaModeCadangan').checked = true;
        sembunyikanAlert('alertSemua');
        new bootstrap.Modal(document.getElementById('modalHapusSemua')).show();
    }

    function prosesHapusSemua() {
        const btn = document.getElementById('btnYaHapusSemua');
        const mode = document.querySelector('input[name="hapusSemuaMode"]:checked').value;
        btn.disabled = true;

        const fd = new FormData();
        fd.append('aksi', 'hapus_semua');
        fd.append('mode', mode);

        fetch('', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                sessionStorage.setItem('flash_js', res.pesan);
                location.reload();
            })
            .catch(() => {
                tampilkanAlert('alertSemua', 'Terjadi kesalahan. Silakan coba lagi.');
                btn.disabled = false;
            });
    }

    // MODAL EDIT
    function bukaModalEdit(id, nama, username, email, divisiId, manajerId, tanggalMulai, tanggalSelesai) {
        document.getElementById('editUserId').value = id;
        document.getElementById('editNama').value = nama;
        document.getElementById('editUsername').value = username;
        document.getElementById('editEmail').value = email;
        document.getElementById('editDivisi').value = divisiId;
        document.getElementById('editTanggalMulai').value = tanggalMulai;
        document.getElementById('editTanggalSelesai').value = tanggalSelesai;
        document.getElementById('editPassword').value = '';

        setManajerDariDivisi(
            divisiId,
            document.getElementById('editManajerNama'),
            document.getElementById('editManajerId')
        );

        sembunyikanAlert('alertEdit');
        document.getElementById('btnSubmitEdit').disabled = false;
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    // MODAL AKTIFKAN
    function bukaModalAktifkan(id, nama) {
        document.getElementById('aktifkanUserId').value = id;
        document.getElementById('aktifkanNama').textContent = nama;
        document.getElementById('aktifkanTanggal').value = '';
        new bootstrap.Modal(document.getElementById('modalAktifkan')).show();
    }

    // AUTOFOCUS + RESET MODAL TAMBAH
    document.getElementById('modalTambah').addEventListener('shown.bs.modal', function() {
        document.getElementById('tambahNama').focus();
    });

    document.getElementById('modalTambah').addEventListener('hidden.bs.modal', function() {
        sembunyikanAlert('alertTambah');
        document.getElementById('tambahNama').value = '';
        document.getElementById('tambahUsername').value = '';
        document.getElementById('tambahDivisi').value = '';
        document.getElementById('tambahManajerNama').value = '';
        document.getElementById('tambahManajerNama').placeholder = 'Otomatis dari divisi';
        document.getElementById('tambahManajerId').value = '';
        document.getElementById('btnSubmitTambah').disabled = false;
    });

    // AUTOFOCUS MODAL EDIT
    document.getElementById('modalEdit').addEventListener('shown.bs.modal', function() {
        document.getElementById('editNama').focus();
    });

    // AJAX CEK DUPLIKAT USERNAME TAMBAH
    let usernameTimerTambah = null;

    document.getElementById('tambahUsername').addEventListener('input', function() {
        clearTimeout(usernameTimerTambah);
        const val = this.value.trim();
        if (!val) {
            sembunyikanAlert('alertTambah');
            document.getElementById('btnSubmitTambah').disabled = false;
            return;
        }
        usernameTimerTambah = setTimeout(() => {
            const fd = new FormData();
            fd.append('aksi', 'cek_username');
            fd.append('username', val);
            fetch('', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.duplikat) {
                        tampilkanAlert('alertTambah', 'Username sudah digunakan!');
                        document.getElementById('btnSubmitTambah').disabled = true;
                    } else {
                        sembunyikanAlert('alertTambah');
                        document.getElementById('btnSubmitTambah').disabled = false;
                    }
                })
                .catch(() => {
                    tampilkanAlert('alertTambah', 'Terjadi kesalahan. Silakan coba lagi.');
                    document.getElementById('btnSubmitTambah').disabled = false;
                });
        }, 500);
    });

    // AJAX CEK DUPLIKAT USERNAME EDIT
    let usernameTimerEdit = null;

    document.getElementById('editUsername').addEventListener('input', function() {
        clearTimeout(usernameTimerEdit);
        const val = this.value.trim();
        if (!val) {
            sembunyikanAlert('alertEdit');
            document.getElementById('btnSubmitEdit').disabled = false;
            return;
        }
        usernameTimerEdit = setTimeout(() => {
            const fd = new FormData();
            fd.append('aksi', 'cek_username');
            fd.append('username', val);
            fd.append('exclude_id', document.getElementById('editUserId').value);
            fetch('', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.duplikat) {
                        tampilkanAlert('alertEdit', 'Username sudah digunakan intern lain!');
                        document.getElementById('btnSubmitEdit').disabled = true;
                    } else {
                        sembunyikanAlert('alertEdit');
                        document.getElementById('btnSubmitEdit').disabled = false;
                    }
                })
                .catch(() => {
                    tampilkanAlert('alertEdit', 'Terjadi kesalahan. Silakan coba lagi.');
                    document.getElementById('btnSubmitEdit').disabled = false;
                });
        }, 500);
    });

    // AJAX CEK DUPLIKAT EMAIL EDIT
    let emailTimerEdit = null;

    document.getElementById('editEmail').addEventListener('input', function() {
        clearTimeout(emailTimerEdit);
        const val = this.value.trim();
        if (!val) {
            sembunyikanAlert('alertEdit');
            document.getElementById('btnSubmitEdit').disabled = false;
            return;
        }
        emailTimerEdit = setTimeout(() => {
            const fd = new FormData();
            fd.append('aksi', 'cek_email');
            fd.append('email', val);
            fd.append('exclude_id', document.getElementById('editUserId').value);
            fetch('', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.duplikat) {
                        tampilkanAlert('alertEdit', 'Email sudah digunakan intern lain!');
                        document.getElementById('btnSubmitEdit').disabled = true;
                    } else {
                        sembunyikanAlert('alertEdit');
                        document.getElementById('btnSubmitEdit').disabled = false;
                    }
                })
                .catch(() => {
                    tampilkanAlert('alertEdit', 'Terjadi kesalahan. Silakan coba lagi.');
                    document.getElementById('btnSubmitEdit').disabled = false;
                });
        }, 500);
    });

    // DOMCONTENTLOADED
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.focus();

        filteredRows = getAllRows();
        renderTable();

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
            const banner = document.querySelector('.intern-banner')?.closest('.card');
            if (banner) banner.after(wrapper);
            aktivasiAlertLuar(wrapper);
        }
    });
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>