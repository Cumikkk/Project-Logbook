<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

proteksi("admin");

$flash_success = "";
$flash_error   = "";

// AJAX CEK DUPLIKAT
if (isset($_POST['aksi']) && $_POST['aksi'] === "cek_duplikat") {
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_divisi']));
    $id   = isset($_POST['divisi_id']) ? (int) $_POST['divisi_id'] : 0;

    if ($id > 0) {
        $cek = mysqli_query($conn, "SELECT id FROM divisi WHERE nama_divisi='$nama' AND id != $id");
    } else {
        $cek = mysqli_query($conn, "SELECT id FROM divisi WHERE nama_divisi='$nama'");
    }

    header('Content-Type: application/json');
    echo json_encode(['duplikat' => mysqli_num_rows($cek) > 0]);
    exit;
}

// AJAX HAPUS MASSAL
if (isset($_POST['aksi']) && $_POST['aksi'] === "hapus_massal") {
    $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
    $berhasil = 0;
    $dilewati = 0;

    foreach ($ids as $id) {
        $id = (int) $id;
        $cek = mysqli_query($conn, "SELECT * FROM intern_detail WHERE divisi_id=$id");
        if (mysqli_num_rows($cek) > 0) {
            $dilewati++;
        } else {
            mysqli_query($conn, "DELETE FROM divisi WHERE id=$id");
            $berhasil++;
        }
    }

    header('Content-Type: application/json');
    if ($berhasil === 0 && $dilewati > 0) {
        echo json_encode([
            'status'  => 'gagal',
            'pesan'   => 'Semua divisi yang dipilih masih memiliki intern dan tidak bisa dihapus.'
        ]);
    } else {
        $pesan = "";
        if ($berhasil > 0) $pesan .= "$berhasil divisi berhasil dihapus.";
        if ($dilewati > 0) $pesan .= ($berhasil > 0 ? " " : "") . "$dilewati divisi dilewati karena masih memiliki intern.";
        echo json_encode([
            'status'  => 'berhasil',
            'pesan'   => $pesan
        ]);
    }
    exit;
}

// AJAX HAPUS SEMUA
if (isset($_POST['aksi']) && $_POST['aksi'] === "hapus_semua") {
    $semua = mysqli_query($conn, "SELECT id FROM divisi");
    $berhasil = 0;
    $dilewati = 0;

    while ($d = mysqli_fetch_assoc($semua)) {
        $id = (int) $d['id'];
        $cek = mysqli_query($conn, "SELECT * FROM intern_detail WHERE divisi_id=$id");
        if (mysqli_num_rows($cek) > 0) {
            $dilewati++;
        } else {
            mysqli_query($conn, "DELETE FROM divisi WHERE id=$id");
            $berhasil++;
        }
    }

    header('Content-Type: application/json');
    if ($berhasil === 0 && $dilewati > 0) {
        echo json_encode([
            'status' => 'gagal',
            'pesan'  => 'Semua divisi masih memiliki intern dan tidak bisa dihapus.'
        ]);
    } else {
        $pesan = "";
        if ($berhasil > 0) $pesan .= "$berhasil divisi berhasil dihapus.";
        if ($dilewati > 0) $pesan .= ($berhasil > 0 ? " " : "") . "$dilewati divisi dilewati karena masih memiliki intern.";
        echo json_encode([
            'status' => 'berhasil',
            'pesan'  => $pesan
        ]);
    }
    exit;
}

// TAMBAH DIVISI
if (isset($_POST['aksi']) && $_POST['aksi'] === "tambah") {
    $nama_divisi = mysqli_real_escape_string($conn, trim($_POST['nama_divisi']));
    $cek = mysqli_query($conn, "SELECT id FROM divisi WHERE nama_divisi='$nama_divisi'");
    if (mysqli_num_rows($cek) === 0) {
        mysqli_query($conn, "INSERT INTO divisi (nama_divisi, created_at) VALUES ('$nama_divisi', NOW())");
        $_SESSION['flash_success'] = "Divisi berhasil ditambahkan!";
    }
    header("Location: " . BASE_URL . "role/admin/divisi.php");
    exit;
}

// EDIT DIVISI
if (isset($_POST['aksi']) && $_POST['aksi'] === "edit") {
    $id          = (int) $_POST['divisi_id'];
    $nama_divisi = mysqli_real_escape_string($conn, trim($_POST['nama_divisi']));
    $cek = mysqli_query($conn, "SELECT id FROM divisi WHERE nama_divisi='$nama_divisi' AND id != $id");
    if (mysqli_num_rows($cek) === 0) {
        mysqli_query($conn, "UPDATE divisi SET nama_divisi='$nama_divisi' WHERE id=$id");
        $_SESSION['flash_success'] = "Divisi berhasil diperbarui!";
    }
    header("Location: " . BASE_URL . "role/admin/divisi.php");
    exit;
}

// HAPUS DIVISI SATU
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    $cek_intern = mysqli_query($conn, "SELECT * FROM intern_detail WHERE divisi_id=$id");
    if (mysqli_num_rows($cek_intern) > 0) {
        $_SESSION['flash_error'] = "Divisi tidak bisa dihapus karena masih memiliki intern terdaftar!";
    } else {
        mysqli_query($conn, "DELETE FROM divisi WHERE id=$id");
        $_SESSION['flash_success'] = "Divisi berhasil dihapus!";
    }
    header("Location: " . BASE_URL . "role/admin/divisi.php");
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

// AMBIL DATA DIVISI
$data = mysqli_query($conn, "
    SELECT d.*,
        COUNT(DISTINCT id2.user_id) AS jumlah_intern,
        u.nama AS nama_manajer
    FROM divisi d
    LEFT JOIN intern_detail id2 ON d.id = id2.divisi_id
    LEFT JOIN users u ON d.manajer_id = u.id AND u.role = 'manajer'
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

<!-- SEARCH + TOMBOL HAPUS -->
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
                        placeholder="Nama divisi atau manajer...">
                </div>
            </div>
            <div class="col-md-7 d-flex gap-2 justify-content-md-end flex-wrap">
                <button id="btnHapusDipilih" class="btn btn-danger rounded-3 fw-semibold d-none"
                    onclick="konfirmasiHapusMassal()">
                    <i class="bi bi-trash-fill me-1"></i>Hapus Dipilih (<span id="jumlahDipilih">0</span>)
                </button>
                <button id="btnHapusSemua" class="btn btn-outline-danger rounded-3 fw-semibold"
                    onclick="konfirmasiHapusSemua()">
                    <i class="bi bi-trash3-fill me-1"></i>Hapus Semua
                </button>
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
                        <th class="ps-3" style="width:40px;">
                            <input type="checkbox" id="checkAll" class="form-check-input">
                        </th>
                        <th style="width:50px;">No</th>
                        <th>Nama Divisi</th>
                        <th>Nama Manajer</th>
                        <th>Jumlah Intern</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tabelBody">
                    <?php if (count($rows) === 0): ?>
                        <tr class="row-empty-data">
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-diagram-3 d-block mb-2" style="font-size:2rem;"></i>
                                <div class="fw-semibold">Belum ada divisi terdaftar</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php $no = 1;
                    foreach ($rows as $row): ?>
                        <tr data-nama="<?= strtolower(htmlspecialchars($row['nama_divisi'])) ?>"
                            data-manajer="<?= strtolower(htmlspecialchars($row['nama_manajer'] ?? '')) ?>"
                            data-id="<?= $row['id'] ?>"
                            data-intern="<?= $row['jumlah_intern'] ?>">
                            <td class="ps-3">
                                <input type="checkbox" class="form-check-input check-item"
                                    value="<?= $row['id'] ?>">
                            </td>
                            <td class="col-no"><?= $no++ ?></td>
                            <td class="fw-semibold col-nama"><?= htmlspecialchars($row['nama_divisi']) ?></td>
                            <td class="col-manajer">
                                <?php if ($row['nama_manajer']): ?>
                                    <span class="badge bg-primary rounded-pill px-3 py-2">
                                        <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($row['nama_manajer']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Belum ada manajer</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark rounded-pill px-3 py-2">
                                    <?= $row['jumlah_intern'] ?> Intern
                                </span>
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

                    <!-- EMPTY STATE SEARCH -->
                    <tr id="emptySearch" class="d-none">
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-search d-block mb-2" style="font-size:2rem;"></i>
                            <div class="fw-semibold">Tidak ada divisi yang cocok</div>
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

<!-- MODAL TAMBAH DIVISI -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <form id="formTambah" method="POST">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header-custom modal-header-tambah">
                    <h5 class="text-white fw-bold mb-0">
                        <i class="bi bi-plus-circle-fill me-2"></i>Tambah Divisi
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
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Divisi</label>
                        <input type="text" name="nama_divisi" id="inputTambahNama" class="form-control rounded-3" required
                            placeholder="Contoh: Divisi IT, Divisi Marketing...">
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </button>
                        <button type="submit" id="btnSubmitTambah" class="btn btn-divisi rounded-3 fw-semibold flex-fill">
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
            <form id="formEdit" method="POST">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="divisi_id" id="editDivisiId">
                <div class="modal-header-custom modal-header-edit">
                    <h5 class="text-white fw-bold mb-0">
                        <i class="bi bi-pencil-fill me-2"></i>Edit Divisi
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
                        <button type="submit" id="btnSubmitEdit" class="btn btn-warning rounded-3 fw-semibold flex-fill text-white">
                            <i class="bi bi-save-fill me-1"></i>Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL HAPUS SATU -->
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
                <div id="alertMassal" class="custom-alert mb-3">
                    <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0 text-start" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                        <div id="alertMassalPesan"></div>
                    </div>
                </div>
                <div style="font-size:2.5rem;" class="mb-3">⚠️</div>
                <p class="fw-semibold mb-1">Yakin ingin menghapus <span id="masalJumlah" class="text-danger"></span> divisi yang dipilih?</p>
                <p class="text-muted small mb-0">Divisi yang masih memiliki intern tidak akan dihapus.</p>
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
                <div id="alertSemua" class="custom-alert mb-3">
                    <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm mb-0 text-start" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                        <div id="alertSemuaPesan"></div>
                    </div>
                </div>
                <div style="font-size:2.5rem;" class="mb-3">⚠️</div>
                <p class="fw-semibold mb-1">Yakin ingin menghapus semua divisi?</p>
                <p class="text-muted small mb-0">Divisi yang masih memiliki intern tidak akan dihapus.</p>
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

    @media (max-width: 576px) {
        .divisi-banner .d-flex {
            flex-direction: column;
            gap: 12px;
        }

        .pagination-container {
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    #emptySearch td {
        background-color: #f1f3f5 !important;
    }
</style>

<script>
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
        const all = getAllRows();
        filteredRows = all.filter(tr => {
            const nama = tr.dataset.nama || '';
            const manajer = tr.dataset.manajer || '';
            return !keyword || nama.includes(keyword) || manajer.includes(keyword);
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

            const cellManajer = tr.querySelector('.col-manajer');
            const origManajer = tr.dataset.manajer;
            if (origManajer && cellManajer) {
                const origManajerCapitalized = origManajer.replace(/\b\w/g, c => c.toUpperCase());
                const badgeEl = cellManajer.querySelector('.badge');
                if (badgeEl) {
                    badgeEl.innerHTML = keyword ?
                        `<i class="bi bi-person-fill me-1"></i>${highlightText(origManajerCapitalized, keyword)}` :
                        `<i class="bi bi-person-fill me-1"></i>${origManajerCapitalized}`;
                }
            }
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
                for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
                    pages.push(i);
                }
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

        const info = total === 0 ?
            'Tidak ada data' :
            isAll ?
            `Menampilkan semua ${total} data` :
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

    // SEARCH
    function escapeRegex(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightText(text, keyword) {
        if (!keyword) return text;
        const re = new RegExp(`(${escapeRegex(keyword)})`, 'gi');
        return text.replace(re, '<mark class="highlight">$1</mark>');
    }

    document.getElementById('searchInput').addEventListener('input', applyFilter);

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
            btn.title = jumlah <= 1 ? 'Pilih minimal 2 divisi untuk hapus massal' : '';
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

    // MODAL HAPUS SATU
    function konfirmasiHapus(id, nama, jumlahIntern) {
        document.getElementById('hapusNama').textContent = nama;
        document.getElementById('hapusInfo').textContent = jumlahIntern > 0 ?
            'Divisi ini masih memiliki ' + jumlahIntern + ' intern terdaftar dan tidak bisa dihapus.' :
            'Data yang dihapus tidak dapat dikembalikan.';

        const hapusBtn = document.getElementById('hapusLink');
        if (jumlahIntern > 0) {
            hapusBtn.href = '#';
            hapusBtn.classList.add('disabled');
            hapusBtn.setAttribute('aria-disabled', 'true');
        } else {
            hapusBtn.href = '?hapus=' + id;
            hapusBtn.classList.remove('disabled');
            hapusBtn.removeAttribute('aria-disabled');
        }
        new bootstrap.Modal(document.getElementById('modalHapus')).show();
    }

    // MODAL HAPUS MASSAL — buka modal, simpan IDs
    function konfirmasiHapusMassal() {
        const checked = document.querySelectorAll('.check-item:checked');
        document.getElementById('masalJumlah').textContent = checked.length;
        idsMassal = Array.from(checked).map(cb => cb.value);
        sembunyikanAlert('alertMassal');
        new bootstrap.Modal(document.getElementById('modalHapusMassal')).show();
    }

    // PROSES HAPUS MASSAL — AJAX
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

    // MODAL HAPUS SEMUA — buka modal
    function konfirmasiHapusSemua() {
        sembunyikanAlert('alertSemua');
        new bootstrap.Modal(document.getElementById('modalHapusSemua')).show();
    }

    // PROSES HAPUS SEMUA — AJAX
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

    // MODAL EDIT
    function bukaModalEdit(id, nama) {
        document.getElementById('editDivisiId').value = id;
        document.getElementById('editNamaDivisi').value = nama;
        sembunyikanAlert('alertEdit');
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    // AUTOFOCUS MODAL TAMBAH
    document.getElementById('modalTambah').addEventListener('shown.bs.modal', function() {
        document.getElementById('inputTambahNama').focus();
    });

    // RESET ALERT + INPUT SAAT MODAL TAMBAH DITUTUP
    document.getElementById('modalTambah').addEventListener('hidden.bs.modal', function() {
        sembunyikanAlert('alertTambah');
        document.getElementById('inputTambahNama').value = '';
    });

    // AUTOFOCUS MODAL EDIT
    document.getElementById('modalEdit').addEventListener('shown.bs.modal', function() {
        document.getElementById('editNamaDivisi').focus();
    });

    // AJAX CEK DUPLIKAT TAMBAH — real-time saat mengetik
    let namaTambahTimer = null;

    document.getElementById('inputTambahNama').addEventListener('input', function() {
        clearTimeout(namaTambahTimer);
        const val = this.value.trim();
        if (!val) {
            sembunyikanAlert('alertTambah');
            document.getElementById('btnSubmitTambah').disabled = false;
            return;
        }
        namaTambahTimer = setTimeout(() => {
            const fd = new FormData();
            fd.append('aksi', 'cek_duplikat');
            fd.append('nama_divisi', val);
            fetch('', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.duplikat) {
                        tampilkanAlert('alertTambah', 'Nama divisi sudah ada!');
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

    // AJAX CEK DUPLIKAT EDIT — real-time saat mengetik
    let namaEditTimer = null;

    document.getElementById('editNamaDivisi').addEventListener('input', function() {
        clearTimeout(namaEditTimer);
        const val = this.value.trim();
        if (!val) {
            sembunyikanAlert('alertEdit');
            document.getElementById('btnSubmitEdit').disabled = false;
            return;
        }
        namaEditTimer = setTimeout(() => {
            const fd = new FormData();
            fd.append('aksi', 'cek_duplikat');
            fd.append('nama_divisi', val);
            fd.append('divisi_id', document.getElementById('editDivisiId').value);
            fetch('', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.duplikat) {
                        tampilkanAlert('alertEdit', 'Nama divisi sudah digunakan!');
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

    // FLASH DARI SESSIONSTORAGE (hasil hapus massal/semua berhasil)
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.focus();

        filteredRows = getAllRows();
        renderTable();

        // flash dari PHP
        aktivasiAlertLuar(document.getElementById('alertLuar'));

        // flash dari JS setelah reload
        const flashJs = sessionStorage.getItem('flash_js');
        if (flashJs) {
            sessionStorage.removeItem('flash_js');
            const wrapper = document.createElement('div');
            wrapper.id = 'alertLuarJs';
            wrapper.className = 'custom-alert mb-4';
            wrapper.innerHTML = `
                <div class="alert alert-success d-flex align-items-center rounded-3 shadow-sm mb-0" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5 flex-shrink-0"></i>
                    <div>${flashJs}</div>
                </div>`;
            const banner = document.querySelector('.divisi-banner')?.closest('.card');
            if (banner) banner.after(wrapper);
            aktivasiAlertLuar(wrapper);
        }
    });
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>