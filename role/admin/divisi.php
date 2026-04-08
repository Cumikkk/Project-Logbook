<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

proteksi("admin");

$success = "";
$error   = "";

// TAMBAH DIVISI
if (isset($_POST['aksi']) && $_POST['aksi'] === "tambah") {
    $nama_divisi = mysqli_real_escape_string($conn, trim($_POST['nama_divisi']));

    $cek = mysqli_query($conn, "SELECT * FROM divisi WHERE nama_divisi='$nama_divisi'");
    if (mysqli_num_rows($cek) > 0) {
        $error = "Nama divisi sudah ada!";
    } else {
        mysqli_query($conn, "INSERT INTO divisi (nama_divisi, created_at) VALUES ('$nama_divisi', NOW())");
        $success = "Divisi berhasil ditambahkan!";
    }
}

// EDIT DIVISI
if (isset($_POST['aksi']) && $_POST['aksi'] === "edit") {
    $id          = (int) $_POST['divisi_id'];
    $nama_divisi = mysqli_real_escape_string($conn, trim($_POST['nama_divisi']));

    $cek = mysqli_query($conn, "SELECT * FROM divisi WHERE nama_divisi='$nama_divisi' AND id != $id");
    if (mysqli_num_rows($cek) > 0) {
        $error = "Nama divisi sudah digunakan!";
    } else {
        mysqli_query($conn, "UPDATE divisi SET nama_divisi='$nama_divisi' WHERE id=$id");
        $success = "Divisi berhasil diperbarui!";
    }
}

// HAPUS DIVISI
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    $cek_intern = mysqli_query($conn, "SELECT * FROM intern_detail WHERE divisi_id=$id");
    if (mysqli_num_rows($cek_intern) > 0) {
        $error = "Divisi tidak bisa dihapus karena masih memiliki intern terdaftar!";
    } else {
        mysqli_query($conn, "DELETE FROM divisi WHERE id=$id");
        header("Location: " . BASE_URL . "role/admin/divisi.php?hapus=success");
        exit;
    }
}

if (empty($error) && isset($_GET['hapus']) && $_GET['hapus'] === "success") {
    $success = "Divisi berhasil dihapus!";
}

// AMBIL DATA DIVISI + JUMLAH INTERN
$data = mysqli_query($conn, "
    SELECT d.*,
        COUNT(DISTINCT id2.user_id) as jumlah_intern,
        COUNT(DISTINCT u.id) as jumlah_manajer
    FROM divisi d
    LEFT JOIN intern_detail id2 ON d.id = id2.divisi_id
    LEFT JOIN users u ON d.id = (
        SELECT divisi_id FROM intern_detail WHERE manajer_id = u.id LIMIT 1
    ) AND u.role = 'manajer'
    GROUP BY d.id
    ORDER BY d.created_at DESC
");
$rows = [];
while ($row = mysqli_fetch_assoc($data)) {
    $rows[] = $row;
}

include BASE_PATH . "includes/header.php";
include BASE_PATH . "includes/sidebar.php";
?>

<!-- BANNER -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="divisi-banner px-4 py-4">
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <h3 class="text-white mb-1 fw-bold"><i class="bi bi-diagram-3-fill me-2"></i>Kelola Divisi</h3>
                <p class="text-white mb-0" style="color: rgba(255,255,255,0.75) !important;">
                    Manajemen divisi dan struktur organisasi
                </p>
            </div>
            <button class="btn btn-light fw-semibold rounded-3"
                data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg me-1"></i>Tambah Divisi
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

<!-- SEARCH -->
<div class="card border-0 shadow mb-4 card-solid">
    <div class="p-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold">Cari Divisi</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-3"
                        placeholder="Nama divisi...">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABEL DATA DIVISI -->
<div class="card border-0 shadow rounded-4 overflow-hidden card-solid">
    <div class="p-4">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0 custom-table" id="tabelDivisi">
                <thead>
                    <tr>
                        <th class="ps-3">No</th>
                        <th>Nama Divisi</th>
                        <th>Jumlah Intern</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tabelBody">
                    <?php if (count($rows) === 0): ?>
                        <tr class="row-empty">
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-diagram-3 d-block mb-2" style="font-size:2rem;"></i>
                                <div class="fw-semibold">Belum ada divisi terdaftar</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php $no = 1;
                    foreach ($rows as $row): ?>
                        <tr data-nama="<?= strtolower(htmlspecialchars($row['nama_divisi'])) ?>">
                            <td class="ps-3 col-no"><?= $no++ ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($row['nama_divisi']) ?></td>
                            <td>
                                <span class="badge bg-info text-dark rounded-pill px-3 py-2">
                                    <?= $row['jumlah_intern'] ?> Intern
                                </span>
                            </td>
                            <td class="text-muted" style="font-size:13px;">
                                <?= date("d M Y", strtotime($row['created_at'])) ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning rounded-3 me-1 fw-semibold"
                                    onclick="bukaModalEdit(
                                        <?= $row['id'] ?>,
                                        '<?= htmlspecialchars($row['nama_divisi'], ENT_QUOTES) ?>'
                                    )">
                                    <i class="bi bi-pencil-fill me-1"></i>Edit
                                </button>
                                <button class="btn btn-sm btn-danger rounded-3 fw-semibold"
                                    onclick="konfirmasiHapus(
                                        <?= $row['id'] ?>,
                                        '<?= htmlspecialchars($row['nama_divisi'], ENT_QUOTES) ?>',
                                        <?= $row['jumlah_intern'] ?>
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
                <div class="fw-semibold">Tidak ada divisi yang cocok</div>
                <div class="small mt-1">Coba kata kunci lain</div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH DIVISI -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <form method="POST">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header-custom modal-header-tambah">
                    <h5 class="text-white fw-bold mb-0">
                        <i class="bi bi-plus-circle-fill me-2"></i>Tambah Divisi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Divisi</label>
                        <input type="text" name="nama_divisi" class="form-control rounded-3" required
                            placeholder="Contoh: Divisi IT, Divisi Marketing...">
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-divisi rounded-3 fw-semibold flex-fill">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Divisi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT DIVISI -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <form method="POST">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="divisi_id" id="editDivisiId">
                <div class="modal-header-custom modal-header-edit">
                    <h5 class="text-white fw-bold mb-0">
                        <i class="bi bi-pencil-fill me-2"></i>Edit Divisi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Divisi</label>
                        <input type="text" name="nama_divisi" id="editNamaDivisi" class="form-control rounded-3" required>
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
                <p class="fw-semibold mb-1">Yakin ingin menghapus divisi:</p>
                <p class="fw-bold fs-5 text-danger mb-2" id="hapusNama"></p>
                <p class="text-muted small mb-0" id="hapusInfo"></p>
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
    .divisi-banner {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .btn-divisi {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
        color: #fff;
        border: none;
    }

    .btn-divisi:hover {
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

    @media (max-width: 576px) {
        .divisi-banner .d-flex {
            flex-direction: column;
            gap: 12px;
        }
    }
</style>

<script>
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
        const rows = document.querySelectorAll('#tabelBody tr:not(.row-empty)');
        let visible = 0;

        rows.forEach(tr => {
            const nama = tr.dataset.nama || '';
            const match = !keyword || nama.includes(keyword);

            if (match) {
                tr.classList.remove('d-none');
                tr.classList.toggle('row-match', keyword.length > 0);
                const cell = tr.querySelectorAll('td')[1];
                const orig = tr.dataset.nama.replace(/\b\w/g, c => c.toUpperCase());
                cell.innerHTML = keyword ? highlightText(orig, keyword) : orig;
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
        document.getElementById('tabelDivisi').classList.toggle('d-none', visible === 0);
    }

    document.getElementById('searchInput').addEventListener('input', filterTabel);

    function bukaModalEdit(id, nama) {
        document.getElementById('editDivisiId').value = id;
        document.getElementById('editNamaDivisi').value = nama;
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    function konfirmasiHapus(id, nama, jumlahIntern) {
        document.getElementById('hapusNama').textContent = nama;
        document.getElementById('hapusInfo').textContent = jumlahIntern > 0 ?
            'Divisi ini masih memiliki ' + jumlahIntern + ' intern terdaftar dan tidak bisa dihapus.' :
            'Data yang dihapus tidak dapat dikembalikan.';
        document.getElementById('hapusLink').href = jumlahIntern > 0 ? '#' : '?hapus=' + id;

        const hapusBtn = document.getElementById('hapusLink');
        if (jumlahIntern > 0) {
            hapusBtn.classList.add('disabled');
            hapusBtn.setAttribute('aria-disabled', 'true');
        } else {
            hapusBtn.classList.remove('disabled');
            hapusBtn.removeAttribute('aria-disabled');
        }

        new bootstrap.Modal(document.getElementById('modalHapus')).show();
    }
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>