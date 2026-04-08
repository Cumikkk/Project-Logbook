<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

proteksi("admin");

$success = "";
$error   = "";

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
        $error = "Username atau email sudah digunakan!";
    } else {
        mysqli_query($conn, "INSERT INTO users (nama, username, email, password, role, status, created_at)
            VALUES ('$nama', '$username', '$email', '$password', '$role', 'aktif', NOW())");
        $user_id = mysqli_insert_id($conn);

        if ($role === "manajer" && $divisi_id) {
            mysqli_query($conn, "UPDATE divisi SET manajer_id=$user_id WHERE id=$divisi_id");
        }

        $success = "Pengguna berhasil ditambahkan!";
    }
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
        $error = "Email sudah digunakan pengguna lain!";
    } else {
        mysqli_query($conn, "UPDATE users SET nama='$nama', email='$email', role='$role', status='$status' WHERE id=$id");

        if ($role === "manajer" && $divisi_id) {
            // Lepas divisi lama yang mungkin masih assign ke user ini
            mysqli_query($conn, "UPDATE divisi SET manajer_id=NULL WHERE manajer_id=$id");
            mysqli_query($conn, "UPDATE divisi SET manajer_id=$id WHERE id=$divisi_id");
        } elseif ($role === "admin") {
            // Kalau diubah jadi admin, lepas assign divisi
            mysqli_query($conn, "UPDATE divisi SET manajer_id=NULL WHERE manajer_id=$id");
        }

        $success = "Pengguna berhasil diperbarui!";
    }
}

// HAPUS PENGGUNA
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    // Cegah hapus diri sendiri
    if ($id === (int) $_SESSION['user_id']) {
        $error = "Tidak bisa menghapus akun sendiri!";
    } else {
        mysqli_query($conn, "UPDATE divisi SET manajer_id=NULL WHERE manajer_id=$id");
        mysqli_query($conn, "DELETE FROM users WHERE id=$id");
        header("Location: " . BASE_URL . "role/admin/pengguna.php?hapus=success");
        exit;
    }
}

if (empty($error) && isset($_GET['hapus']) && $_GET['hapus'] === "success") {
    $success = "Pengguna berhasil dihapus!";
}

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
    <div class="pengguna-banner px-4 py-4">
        <div class="d-flex align-items-center">
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
            <div class="col-md-5">
                <label class="form-label fw-semibold">Cari Pengguna</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-3"
                        placeholder="Nama, username, atau email...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Filter Role</label>
                <select id="filterRole" class="form-select rounded-3">
                    <option value="">Semua Role</option>
                    <option value="admin">Admin</option>
                    <option value="manajer">Manajer</option>
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
                        <th class="ps-3">No</th>
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
                            <td colspan="9" class="text-center py-5 text-muted">
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
                            data-status="<?= $row['status'] ?>">

                            <td class="ps-3 col-no"><?= $no++ ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>

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
                <p class="fw-semibold mb-1" id="hapusDesc">Yakin ingin menghapus pengguna:</p>
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
    // Toggle show/hide email & password
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

    // Toggle dropdown divisi di form tambah
    function toggleDivisiTambah(role) {
        const wrap = document.getElementById('wrapDivisiTambah');
        wrap.style.display = role === 'manajer' ? 'block' : 'none';
    }

    // Toggle dropdown divisi di form edit
    function toggleDivisiEdit(role) {
        const wrap = document.getElementById('wrapDivisiEdit');
        wrap.style.display = role === 'manajer' ? 'block' : 'none';
    }

    // Search & filter live
    function escapeRegex(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightText(text, keyword) {
        if (!keyword) return text;
        const re = new RegExp(`(${escapeRegex(keyword)})`, 'gi');
        return text.replace(re, '<mark class="highlight">$1</mark>');
    }

    function filterTabel() {
        const keyword = document.getElementById('searchInput').value.trim().toLowerCase();
        const role = document.getElementById('filterRole').value;
        const status = document.getElementById('filterStatus').value;
        const rows = document.querySelectorAll('#tabelBody tr:not(.row-empty)');
        let visible = 0;

        rows.forEach(tr => {
            const nama = tr.dataset.nama || '';
            const username = tr.dataset.username || '';
            const email = tr.dataset.email || '';
            const tRole = tr.dataset.role || '';
            const tStatus = tr.dataset.status || '';

            const matchKey = !keyword || nama.includes(keyword) || username.includes(keyword) || email.includes(keyword);
            const matchRole = !role || tRole === role;
            const matchStatus = !status || tStatus === status;

            if (matchKey && matchRole && matchStatus) {
                tr.classList.remove('d-none');
                tr.classList.toggle('row-match', keyword.length > 0);
                const cells = tr.querySelectorAll('td');
                [1, 2].forEach(i => {
                    const key = i === 1 ? 'nama' : 'username';
                    const orig = tr.dataset[key].replace(/\b\w/g, c => c.toUpperCase());
                    cells[i].innerHTML = keyword ? highlightText(orig, keyword) : orig;
                });
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
        document.getElementById('tabelPengguna').classList.toggle('d-none', visible === 0);
    }

    document.getElementById('searchInput').addEventListener('input', filterTabel);
    document.getElementById('filterRole').addEventListener('change', filterTabel);
    document.getElementById('filterStatus').addEventListener('change', filterTabel);

    // Buka modal edit
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

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    // Buka modal hapus
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
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>