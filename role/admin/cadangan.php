<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../includes/fungsi.php";

proteksi("admin");

$success = "";
$error   = "";

// HAPUS PERMANEN
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    $cadangan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM cadangan WHERE id=$id"));
    if ($cadangan) {
        if ($cadangan['foto_profil']) {
            hapusFile('uploads/foto-profil/' . $cadangan['foto_profil']);
        }
        mysqli_query($conn, "DELETE FROM cadangan WHERE id=$id");
        header("Location: " . BASE_URL . "role/admin/cadangan.php?hapus=success");
        exit;
    }
}

if (isset($_GET['hapus']) && $_GET['hapus'] === "success") {
    $success = "Data cadangan berhasil dihapus permanen!";
}

// PULIHKAN INTERN
if (isset($_GET['pulihkan'])) {
    $id = (int) $_GET['pulihkan'];

    $cadangan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM cadangan WHERE id=$id"));
    if ($cadangan) {
        // Cek apakah username atau email sudah dipakai lagi
        $cek = mysqli_query($conn, "
            SELECT * FROM users
            WHERE username='{$cadangan['username']}' OR email='{$cadangan['email']}'
        ");

        if (mysqli_num_rows($cek) > 0) {
            $error = "Gagal memulihkan: username atau email sudah digunakan akun lain.";
        } else {
            // Cek status otomatis berdasarkan tanggal selesai
            $status = (strtotime($cadangan['tanggal_selesai']) >= strtotime(date('Y-m-d'))) ? 'aktif' : 'tidak_aktif';

            mysqli_query($conn, "INSERT INTO users (id, nama, username, email, password, foto_profil, role, status, created_at)
                SELECT {$cadangan['user_id']}, '{$cadangan['nama']}', '{$cadangan['username']}', '{$cadangan['email']}',
                password, foto_profil, 'intern', '$status', NOW()
                FROM users WHERE id={$cadangan['user_id']}
            ");

            // Jika user_id lama sudah tidak ada, insert manual
            $cek_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id={$cadangan['user_id']}"));
            if (!$cek_user) {
                mysqli_query($conn, "INSERT INTO users (nama, username, email, password, foto_profil, role, status, created_at)
                    VALUES (
                        '" . mysqli_real_escape_string($conn, $cadangan['nama']) . "',
                        '" . mysqli_real_escape_string($conn, $cadangan['username']) . "',
                        '" . mysqli_real_escape_string($conn, $cadangan['email']) . "',
                        '123',
                        " . ($cadangan['foto_profil'] ? "'" . mysqli_real_escape_string($conn, $cadangan['foto_profil']) . "'" : "NULL") . ",
                        'intern',
                        '$status',
                        NOW()
                    )");
                $new_user_id = mysqli_insert_id($conn);
            } else {
                $new_user_id = $cadangan['user_id'];
            }

            mysqli_query($conn, "INSERT INTO intern_detail (user_id, divisi_id, manajer_id, tanggal_mulai, tanggal_selesai, created_at)
                VALUES (
                    $new_user_id,
                    {$cadangan['divisi_id']},
                    {$cadangan['manajer_id']},
                    '{$cadangan['tanggal_mulai']}',
                    '{$cadangan['tanggal_selesai']}',
                    NOW()
                )");

            mysqli_query($conn, "DELETE FROM cadangan WHERE id=$id");
            header("Location: " . BASE_URL . "role/admin/cadangan.php?pulihkan=success");
            exit;
        }
    }
}

if (isset($_GET['pulihkan']) && $_GET['pulihkan'] === "success") {
    $success = "Intern berhasil dipulihkan!";
}

// HAPUS OTOMATIS YANG SUDAH EXPIRED
mysqli_query($conn, "DELETE FROM cadangan WHERE expires_at < NOW()");

// AMBIL DATA CADANGAN
$data = mysqli_query($conn, "
    SELECT c.*, d.nama_divisi, u.nama AS nama_manajer
    FROM cadangan c
    LEFT JOIN divisi d ON c.divisi_id = d.id
    LEFT JOIN users u ON c.manajer_id = u.id
    ORDER BY c.expires_at ASC
");
$rows = [];
while ($row = mysqli_fetch_assoc($data)) {
    $row['sisa_hari'] = sisaHari($row['expires_at']);
    $rows[] = $row;
}

// AMBIL DAFTAR DIVISI UNTUK FILTER
$q_divisi = mysqli_query($conn, "SELECT * FROM divisi ORDER BY nama_divisi ASC");
$list_divisi = [];
while ($d = mysqli_fetch_assoc($q_divisi)) {
    $list_divisi[] = $d;
}

$judul_halaman = "Cadangan";
include BASE_PATH . "includes/header.php";
include BASE_PATH . "includes/sidebar.php";
?>

<!-- BANNER -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="cadangan-banner px-4 py-4">
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <h3 class="text-white mb-1 fw-bold"><i class="bi bi-trash2-fill me-2"></i>Cadangan Intern</h3>
                <p class="text-white mb-0" style="color: rgba(255,255,255,0.75) !important;">
                    Data intern yang dihapus akan disimpan selama 30 hari sebelum dihapus permanen
                </p>
            </div>
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
                        placeholder="Nama atau username...">
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
                <label class="form-label fw-semibold">Filter Sisa Hari</label>
                <select id="filterSisa" class="form-select rounded-3">
                    <option value="">Semua</option>
                    <option value="hampir">Hampir Expired (≤ 7 hari)</option>
                    <option value="aman">Aman (> 7 hari)</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- TABEL DATA CADANGAN -->
<div class="card border-0 shadow rounded-4 overflow-hidden card-solid">
    <div class="p-4">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0 custom-table" id="tabelCadangan">
                <thead>
                    <tr>
                        <th class="ps-3">No</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Divisi</th>
                        <th>Manajer</th>
                        <th>Tgl Dihapus</th>
                        <th>Kedaluwarsa</th>
                        <th>Sisa Hari</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tabelBody">
                    <?php if (count($rows) === 0): ?>
                        <tr class="row-empty">
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-trash2 d-block mb-2" style="font-size:2rem;"></i>
                                <div class="fw-semibold">Cadangan kosong</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php $no = 1;
                    foreach ($rows as $row):
                        $sisa      = $row['sisa_hari'];
                        $kategori  = $sisa <= 7 ? 'hampir' : 'aman';
                    ?>
                        <tr
                            data-nama="<?= strtolower(htmlspecialchars($row['nama'])) ?>"
                            data-username="<?= strtolower(htmlspecialchars($row['username'])) ?>"
                            data-divisi="<?= $row['divisi_id'] ?>"
                            data-sisa="<?= $kategori ?>">

                            <td class="ps-3 col-no"><?= $no++ ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td>
                                <span class="badge bg-secondary rounded-pill px-3 py-2">
                                    <?= htmlspecialchars($row['nama_divisi'] ?? '-') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['nama_manajer'] ?? '-') ?></td>
                            <td><?= formatTanggal($row['deleted_at']) ?></td>
                            <td><?= formatTanggal($row['expires_at']) ?></td>

                            <!-- SISA HARI -->
                            <td>
                                <?php if ($sisa <= 3): ?>
                                    <span class="badge bg-danger rounded-pill px-3 py-2">
                                        <?= $sisa ?> hari
                                    </span>
                                <?php elseif ($sisa <= 7): ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                                        <?= $sisa ?> hari
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success rounded-pill px-3 py-2">
                                        <?= $sisa ?> hari
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- AKSI -->
                            <td>
                                <button class="btn btn-sm btn-success rounded-3 me-1 fw-semibold"
                                    onclick="konfirmasiPulihkan(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama'], ENT_QUOTES) ?>')">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Pulihkan
                                </button>
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
                <div class="fw-semibold">Tidak ada data yang cocok</div>
                <div class="small mt-1">Coba kata kunci atau filter lain</div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PULIHKAN -->
<div class="modal fade" id="modalPulihkan" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-pulihkan">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Konfirmasi Pulihkan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <div style="font-size:2.5rem;" class="mb-3">♻️</div>
                <p class="fw-semibold mb-1">Pulihkan intern:</p>
                <p class="fw-bold fs-5 text-success mb-2" id="pulihkanNama"></p>
                <p class="text-muted small mb-0">Intern akan dikembalikan ke divisi dan manajer semula.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <div class="d-flex gap-2 w-100">
                    <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left me-1"></i>Batal
                    </button>
                    <a id="pulihkanLink" href="#" class="btn btn-success rounded-3 fw-semibold flex-fill">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Ya, Pulihkan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL HAPUS PERMANEN -->
<div class="modal fade" id="modalHapus" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header-custom modal-header-hapus">
                <h5 class="text-white fw-bold mb-0">
                    <i class="bi bi-trash-fill me-2"></i>Hapus Permanen
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <div style="font-size:2.5rem;" class="mb-3">⚠️</div>
                <p class="fw-semibold mb-1">Yakin ingin menghapus permanen:</p>
                <p class="fw-bold fs-5 text-danger mb-2" id="hapusNama"></p>
                <p class="text-muted small mb-0">Data tidak dapat dikembalikan setelah dihapus permanen.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <div class="d-flex gap-2 w-100">
                    <button type="button" class="btn btn-light rounded-3 fw-semibold flex-fill" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left me-1"></i>Batal
                    </button>
                    <a id="hapusLink" href="#" class="btn btn-danger rounded-3 fw-semibold flex-fill">
                        <i class="bi bi-trash-fill me-1"></i>Ya, Hapus Permanen
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .cadangan-banner {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
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

    .modal-header-pulihkan {
        background: linear-gradient(135deg, #5BAE3C 0%, #388e3c 100%);
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
        .cadangan-banner .d-flex {
            flex-direction: column;
            gap: 12px;
        }
    }
</style>

<script>
    function filterTabel() {
        const keyword = document.getElementById('searchInput').value.trim().toLowerCase();
        const divisi = document.getElementById('filterDivisi').value;
        const sisa = document.getElementById('filterSisa').value;
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
            const tDivisi = tr.dataset.divisi || '';
            const tSisa = tr.dataset.sisa || '';

            const matchKey = !keyword || nama.includes(keyword) || username.includes(keyword);
            const matchDivisi = !divisi || tDivisi === divisi;
            const matchSisa = !sisa || tSisa === sisa;

            if (matchKey && matchDivisi && matchSisa) {
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
        document.getElementById('tabelCadangan').classList.toggle('d-none', visible === 0);
    }

    document.getElementById('searchInput').addEventListener('input', filterTabel);
    document.getElementById('filterDivisi').addEventListener('change', filterTabel);
    document.getElementById('filterSisa').addEventListener('change', filterTabel);

    function konfirmasiPulihkan(id, nama) {
        document.getElementById('pulihkanNama').textContent = nama;
        document.getElementById('pulihkanLink').href = '?pulihkan=' + id;
        new bootstrap.Modal(document.getElementById('modalPulihkan')).show();
    }

    function konfirmasiHapus(id, nama) {
        document.getElementById('hapusNama').textContent = nama;
        document.getElementById('hapusLink').href = '?hapus=' + id;
        new bootstrap.Modal(document.getElementById('modalHapus')).show();
    }
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>