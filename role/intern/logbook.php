<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../includes/fungsi.php";

proteksi("intern");

$intern_id = $_SESSION['user_id'];
$today = date("Y-m-d");

// Proses tambah entri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $tanggal       = trim($_POST['tanggal'] ?? '');
    $nama_kegiatan = trim($_POST['nama_kegiatan'] ?? '');
    $deskripsi     = trim($_POST['deskripsi'] ?? '');
    $lampiran      = null;

    if ($tanggal && $nama_kegiatan && $deskripsi) {
        // Upload lampiran
        if (!empty($_FILES['lampiran']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
            $ext = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $nama_file = 'lampiran_' . $intern_id . '_' . time() . '.' . $ext;
                $tujuan = __DIR__ . '/../../uploads/lampiran/' . $nama_file;
                if (move_uploaded_file($_FILES['lampiran']['tmp_name'], $tujuan)) {
                    $lampiran = $nama_file;
                }
            }
        }

        $tanggal_esc       = mysqli_real_escape_string($conn, $tanggal);
        $nama_kegiatan_esc = mysqli_real_escape_string($conn, $nama_kegiatan);
        $deskripsi_esc     = mysqli_real_escape_string($conn, $deskripsi);
        $lampiran_esc      = $lampiran ? mysqli_real_escape_string($conn, $lampiran) : null;
        $now               = date('Y-m-d H:i:s');

        if ($lampiran_esc) {
            mysqli_query($conn, "
                INSERT INTO logbook (intern_id, tanggal, nama_kegiatan, deskripsi, lampiran, is_deleted, created_at, updated_at)
                VALUES ('$intern_id', '$tanggal_esc', '$nama_kegiatan_esc', '$deskripsi_esc', '$lampiran_esc', 0, '$now', '$now')
            ");
        } else {
            mysqli_query($conn, "
                INSERT INTO logbook (intern_id, tanggal, nama_kegiatan, deskripsi, lampiran, is_deleted, created_at, updated_at)
                VALUES ('$intern_id', '$tanggal_esc', '$nama_kegiatan_esc', '$deskripsi_esc', NULL, 0, '$now', '$now')
            ");
        }
    }

    header("Location: logbook.php");
    exit;
}

// Proses edit entri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'edit') {
    $id            = (int)($_POST['id'] ?? 0);
    $tanggal       = trim($_POST['tanggal'] ?? '');
    $nama_kegiatan = trim($_POST['nama_kegiatan'] ?? '');
    $deskripsi     = trim($_POST['deskripsi'] ?? '');

    if ($id && $tanggal && $nama_kegiatan && $deskripsi) {
        // Cek kepemilikan
        $cek = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT * FROM logbook WHERE id = $id AND intern_id = '$intern_id' AND is_deleted = 0
        "));

        if ($cek) {
            $lampiran = $cek['lampiran'];

            // Hapus lampiran lama jika ada file baru
            if (!empty($_FILES['lampiran']['name'])) {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                $ext = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $nama_file = 'lampiran_' . $intern_id . '_' . time() . '.' . $ext;
                    $tujuan = __DIR__ . '/../../uploads/lampiran/' . $nama_file;
                    if (move_uploaded_file($_FILES['lampiran']['tmp_name'], $tujuan)) {
                        // Hapus file lama
                        if ($cek['lampiran'] && file_exists(__DIR__ . '/../../uploads/lampiran/' . $cek['lampiran'])) {
                            unlink(__DIR__ . '/../../uploads/lampiran/' . $cek['lampiran']);
                        }
                        $lampiran = $nama_file;
                    }
                }
            }

            // Hapus lampiran jika diminta
            if (isset($_POST['hapus_lampiran']) && $_POST['hapus_lampiran'] == '1') {
                if ($cek['lampiran'] && file_exists(__DIR__ . '/../../uploads/lampiran/' . $cek['lampiran'])) {
                    unlink(__DIR__ . '/../../uploads/lampiran/' . $cek['lampiran']);
                }
                $lampiran = null;
            }

            $tanggal_esc       = mysqli_real_escape_string($conn, $tanggal);
            $nama_kegiatan_esc = mysqli_real_escape_string($conn, $nama_kegiatan);
            $deskripsi_esc     = mysqli_real_escape_string($conn, $deskripsi);
            $now               = date('Y-m-d H:i:s');

            if ($lampiran) {
                $lampiran_esc = mysqli_real_escape_string($conn, $lampiran);
                mysqli_query($conn, "
                    UPDATE logbook SET
                        tanggal = '$tanggal_esc',
                        nama_kegiatan = '$nama_kegiatan_esc',
                        deskripsi = '$deskripsi_esc',
                        lampiran = '$lampiran_esc',
                        updated_at = '$now'
                    WHERE id = $id AND intern_id = '$intern_id'
                ");
            } else {
                mysqli_query($conn, "
                    UPDATE logbook SET
                        tanggal = '$tanggal_esc',
                        nama_kegiatan = '$nama_kegiatan_esc',
                        deskripsi = '$deskripsi_esc',
                        lampiran = NULL,
                        updated_at = '$now'
                    WHERE id = $id AND intern_id = '$intern_id'
                ");
            }
        }
    }

    header("Location: logbook.php");
    exit;
}

// Proses hapus entri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        // Cek kepemilikan
        $cek = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT * FROM logbook WHERE id = $id AND intern_id = '$intern_id' AND is_deleted = 0
        "));
        if ($cek) {
            // Hapus file lampiran jika ada
            if ($cek['lampiran'] && file_exists(__DIR__ . '/../../uploads/lampiran/' . $cek['lampiran'])) {
                unlink(__DIR__ . '/../../uploads/lampiran/' . $cek['lampiran']);
            }
            mysqli_query($conn, "
                UPDATE logbook SET is_deleted = 1, updated_at = NOW()
                WHERE id = $id AND intern_id = '$intern_id'
            ");
        }
    }

    header("Location: logbook.php");
    exit;
}

// Ambil semua entri logbook milik intern
$logbook_rows = [];
$q_logbook = mysqli_query($conn, "
    SELECT * FROM logbook
    WHERE intern_id = '$intern_id' AND is_deleted = 0
    ORDER BY tanggal DESC, created_at DESC
");
while ($row = mysqli_fetch_assoc($q_logbook)) {
    $logbook_rows[] = $row;
}

// Ambil data detail intern untuk info banner
$q_detail = mysqli_query($conn, "
    SELECT id.tanggal_mulai, id.tanggal_selesai, d.nama_divisi
    FROM intern_detail id
    JOIN divisi d ON id.divisi_id = d.id
    WHERE id.user_id = '$intern_id'
");
$detail = mysqli_fetch_assoc($q_detail);

$judul_halaman = "Logbook";
include BASE_PATH . "includes/header.php";
include BASE_PATH . "includes/sidebar.php";
?>

<!-- BANNER -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="logbook-banner px-4 py-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h3 class="text-white mb-1 fw-bold">
                    <i class="bi bi-journal-text me-2"></i>Logbook Saya
                </h3>
                <p class="mb-0" style="color: rgba(255,255,255,0.75);">
                    <?= $detail ? htmlspecialchars($detail['nama_divisi']) . ' &mdash; ' . formatTanggal($detail['tanggal_mulai']) . ' s/d ' . formatTanggal($detail['tanggal_selesai']) : htmlspecialchars($_SESSION['nama']) ?>
                </p>
            </div>
            <button class="btn btn-light fw-semibold rounded-3" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="bi bi-plus-lg me-1"></i>Tambah Entri
            </button>
        </div>
    </div>
</div>

<!-- FILTER -->
<div class="card border-0 shadow mb-4 card-solid">
    <div class="p-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Cari Kegiatan</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-3"
                        placeholder="Nama kegiatan atau deskripsi...">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Filter Bulan</label>
                <select id="filterBulan" class="form-select rounded-3">
                    <option value="">Semua Bulan</option>
                    <?php
                    $bulan_list = [];
                    foreach ($logbook_rows as $lb) {
                        $key = date('Y-m', strtotime($lb['tanggal']));
                        if (!isset($bulan_list[$key])) {
                            $bulan_list[$key] = date('F Y', strtotime($lb['tanggal']));
                        }
                    }
                    krsort($bulan_list);
                    foreach ($bulan_list as $val => $label): ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- TABEL LOGBOOK -->
<div class="card border-0 shadow rounded-4 overflow-hidden card-solid">
    <div class="p-4">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0 custom-table" id="tabelLogbook">
                <thead>
                    <tr>
                        <th class="ps-3" style="width:50px;">No</th>
                        <th style="width:120px;">Tanggal</th>
                        <th>Nama Kegiatan</th>
                        <th>Deskripsi</th>
                        <th style="width:100px;">Lampiran</th>
                        <th style="width:120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tabelBody">
                    <?php if (count($logbook_rows) === 0): ?>
                        <tr class="row-empty">
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x d-block mb-2" style="font-size:2rem;"></i>
                                <div class="fw-semibold">Belum ada entri logbook</div>
                                <div class="small mt-1">Klik tombol <strong>Tambah Entri</strong> untuk mulai</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php $no = 1;
                    foreach ($logbook_rows as $lb): ?>
                        <tr
                            data-kegiatan="<?= strtolower(htmlspecialchars($lb['nama_kegiatan'])) ?>"
                            data-deskripsi="<?= strtolower(htmlspecialchars($lb['deskripsi'])) ?>"
                            data-bulan="<?= date('Y-m', strtotime($lb['tanggal'])) ?>">

                            <td class="ps-3 col-no"><?= $no++ ?></td>
                            <td style="white-space:nowrap;"><?= formatTanggal($lb['tanggal']) ?></td>
                            <td class="fw-semibold col-kegiatan"><?= htmlspecialchars($lb['nama_kegiatan']) ?></td>
                            <td>
                                <div class="text-truncate" style="max-width:280px;" title="<?= htmlspecialchars($lb['deskripsi']) ?>">
                                    <?= htmlspecialchars($lb['deskripsi']) ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($lb['lampiran']): ?>
                                    <a href="<?= BASE_URL ?>uploads/lampiran/<?= htmlspecialchars($lb['lampiran']) ?>"
                                        target="_blank"
                                        class="btn btn-sm btn-outline-secondary rounded-3">
                                        <i class="bi bi-paperclip me-1"></i>Lihat
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary rounded-3"
                                        title="Edit"
                                        onclick="bukaEdit(
                                            <?= $lb['id'] ?>,
                                            '<?= addslashes($lb['tanggal']) ?>',
                                            '<?= addslashes(htmlspecialchars($lb['nama_kegiatan'])) ?>',
                                            '<?= addslashes(htmlspecialchars($lb['deskripsi'])) ?>',
                                            '<?= addslashes($lb['lampiran'] ?? '') ?>'
                                        )">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger rounded-3"
                                        title="Hapus"
                                        onclick="bukaHapus(<?= $lb['id'] ?>, '<?= addslashes(htmlspecialchars($lb['nama_kegiatan'])) ?>')">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- EMPTY STATE SEARCH -->
            <div id="emptySearch" class="text-center py-5 text-muted d-none">
                <i class="bi bi-search d-block mb-2" style="font-size:2rem;"></i>
                <div class="fw-semibold">Tidak ada entri yang cocok</div>
                <div class="small mt-1">Coba kata kunci lain</div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="labelTambah" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header logbook-banner border-0">
                <h5 class="modal-title text-white fw-bold" id="labelTambah">
                    <i class="bi bi-plus-circle-fill me-2"></i>Tambah Entri Logbook
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" class="form-control rounded-3"
                                max="<?= $today ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Lampiran
                                <small class="text-muted fw-normal">(jpg, png, pdf, doc, docx)</small>
                            </label>
                            <input type="file" name="lampiran" class="form-control rounded-3"
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nama Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_kegiatan" class="form-control rounded-3"
                                placeholder="Masukkan nama kegiatan..." maxlength="255" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi Kegiatan <span class="text-danger">*</span></label>
                            <textarea name="deskripsi" class="form-control rounded-3" rows="4"
                                placeholder="Deskripsikan kegiatan yang dilakukan..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-logbook rounded-3 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="labelEdit" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header logbook-banner border-0">
                <h5 class="modal-title text-white fw-bold" id="labelEdit">
                    <i class="bi bi-pencil-fill me-2"></i>Edit Entri Logbook
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="hapus_lampiran" id="hapusLampiranInput" value="0">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" id="editTanggal" class="form-control rounded-3"
                                max="<?= $today ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Lampiran Baru
                                <small class="text-muted fw-normal">(opsional)</small>
                            </label>
                            <input type="file" name="lampiran" id="editLampiran" class="form-control rounded-3"
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nama Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_kegiatan" id="editNamaKegiatan" class="form-control rounded-3"
                                maxlength="255" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi Kegiatan <span class="text-danger">*</span></label>
                            <textarea name="deskripsi" id="editDeskripsi" class="form-control rounded-3" rows="4" required></textarea>
                        </div>
                        <!-- Info lampiran lama -->
                        <div class="col-12" id="wrapLampiranLama">
                            <div class="d-flex align-items-center gap-2 p-3 rounded-3 bg-light border" id="infoLampiranLama">
                                <i class="bi bi-paperclip text-secondary fs-5"></i>
                                <span class="text-muted small" id="namaLampiranLama"></span>
                                <a href="#" id="linkLampiranLama" target="_blank"
                                    class="btn btn-sm btn-outline-secondary rounded-3 ms-1">
                                    <i class="bi bi-eye me-1"></i>Lihat
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-3 ms-auto"
                                    onclick="hapusLampiran()">
                                    <i class="bi bi-trash3 me-1"></i>Hapus Lampiran
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-logbook rounded-3 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL HAPUS -->
<div class="modal fade" id="modalHapus" tabindex="-1" aria-labelledby="labelHapus" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="labelHapus">
                    <i class="bi bi-trash3-fill me-2"></i>Hapus Entri
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pt-2">
                <p class="mb-1">Yakin ingin menghapus entri logbook:</p>
                <p class="fw-bold text-dark" id="namaHapus"></p>
                <p class="text-muted small mb-0">Entri yang dihapus tidak dapat dikembalikan.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" id="hapusId">
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-3 fw-semibold">
                        <i class="bi bi-trash3-fill me-1"></i>Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .logbook-banner {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .btn-logbook {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
        color: #fff;
        border: none;
    }

    .btn-logbook:hover {
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
        .logbook-banner .d-flex {
            flex-direction: column;
            gap: 12px;
        }

        .logbook-banner .btn {
            width: 100%;
        }
    }
</style>

<script>
    // Buka modal edit
    function bukaEdit(id, tanggal, namaKegiatan, deskripsi, lampiran) {
        document.getElementById('editId').value = id;
        document.getElementById('editTanggal').value = tanggal;
        document.getElementById('editNamaKegiatan').value = namaKegiatan;
        document.getElementById('editDeskripsi').value = deskripsi;
        document.getElementById('hapusLampiranInput').value = '0';
        document.getElementById('editLampiran').value = '';

        const wrapLama = document.getElementById('wrapLampiranLama');
        if (lampiran) {
            document.getElementById('namaLampiranLama').textContent = lampiran;
            document.getElementById('linkLampiranLama').href = '<?= BASE_URL ?>uploads/lampiran/' + lampiran;
            wrapLama.classList.remove('d-none');
        } else {
            wrapLama.classList.add('d-none');
        }

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    // Tandai hapus lampiran lama
    function hapusLampiran() {
        document.getElementById('hapusLampiranInput').value = '1';
        document.getElementById('wrapLampiranLama').classList.add('d-none');
    }

    // Buka modal hapus
    function bukaHapus(id, nama) {
        document.getElementById('hapusId').value = id;
        document.getElementById('namaHapus').textContent = nama;
        new bootstrap.Modal(document.getElementById('modalHapus')).show();
    }

    // Filter dan pencarian
    function filterLogbook() {
        const keyword = document.getElementById('searchInput').value.trim().toLowerCase();
        const bulan = document.getElementById('filterBulan').value;
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
            const kegiatan = tr.dataset.kegiatan || '';
            const deskripsi = tr.dataset.deskripsi || '';
            const tBulan = tr.dataset.bulan || '';

            const matchKey = !keyword || kegiatan.includes(keyword) || deskripsi.includes(keyword);
            const matchBulan = !bulan || tBulan === bulan;

            if (matchKey && matchBulan) {
                tr.classList.remove('d-none');
                tr.classList.toggle('row-match', keyword.length > 0);
                const origKegiatan = tr.querySelector('.col-kegiatan').dataset.orig ||
                    tr.querySelector('.col-kegiatan').textContent.trim();
                tr.querySelector('.col-kegiatan').dataset.orig = origKegiatan;
                tr.querySelector('.col-kegiatan').innerHTML = keyword ?
                    highlightText(origKegiatan, keyword) :
                    origKegiatan;
                visible++;
            } else {
                tr.classList.add('d-none');
                tr.classList.remove('row-match');
            }
        });

        // Update nomor urut
        let no = 1;
        rows.forEach(tr => {
            if (!tr.classList.contains('d-none')) {
                tr.querySelector('.col-no').textContent = no++;
            }
        });

        document.getElementById('emptySearch').classList.toggle('d-none', visible > 0);
        document.getElementById('tabelLogbook').classList.toggle('d-none', visible === 0);
    }

    document.getElementById('searchInput').addEventListener('input', filterLogbook);
    document.getElementById('filterBulan').addEventListener('change', filterLogbook);
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>