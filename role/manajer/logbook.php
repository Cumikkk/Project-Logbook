<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";
require_once __DIR__ . "/../../includes/fungsi.php";

proteksi("manajer");

$manajer_id = $_SESSION['user_id'];

// Ambil divisi manajer ini
$q_divisi = mysqli_query($conn, "SELECT id, nama_divisi FROM divisi WHERE manajer_id = $manajer_id LIMIT 1");
$divisi = mysqli_fetch_assoc($q_divisi);
$divisi_id   = $divisi['id'] ?? null;
$nama_divisi = $divisi['nama_divisi'] ?? '-';

// Mode: lihat logbook intern tertentu
$mode_detail = false;
$intern_dipilih = null;
$logbook_rows = [];

if (isset($_GET['intern_id']) && is_numeric($_GET['intern_id']) && $divisi_id) {
    $intern_id = (int) $_GET['intern_id'];

    // Validasi: intern ini harus milik divisi manajer
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT u.id, u.nama, u.username, u.foto_profil, u.status,
            id.tanggal_mulai, id.tanggal_selesai
        FROM users u
        JOIN intern_detail id ON u.id = id.user_id
        WHERE u.id = $intern_id
        AND id.divisi_id = $divisi_id
        AND id.manajer_id = $manajer_id
    "));

    if ($cek) {
        $mode_detail   = true;
        $intern_dipilih = $cek;

        $q_logbook = mysqli_query($conn, "
            SELECT * FROM logbook
            WHERE intern_id = $intern_id
            AND is_deleted = 0
            ORDER BY tanggal DESC
        ");
        while ($row = mysqli_fetch_assoc($q_logbook)) {
            $logbook_rows[] = $row;
        }
    }
}

// Ambil daftar intern di divisi ini
$intern_rows = [];
if ($divisi_id) {
    $q_intern = mysqli_query($conn, "
        SELECT u.id, u.nama, u.username, u.foto_profil, u.status,
            id.tanggal_mulai, id.tanggal_selesai,
            (SELECT COUNT(*) FROM logbook l WHERE l.intern_id = u.id AND l.is_deleted = 0) AS total_logbook
        FROM users u
        JOIN intern_detail id ON u.id = id.user_id
        WHERE u.role = 'intern'
        AND id.divisi_id = $divisi_id
        AND id.manajer_id = $manajer_id
        ORDER BY u.nama ASC
    ");
    while ($row = mysqli_fetch_assoc($q_intern)) {
        $intern_rows[] = $row;
    }
}

$judul_halaman = "Logbook";
include BASE_PATH . "includes/header.php";
include BASE_PATH . "includes/sidebar.php";
?>

<!-- BANNER -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="logbook-banner px-4 py-4">
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <?php if ($mode_detail): ?>
                    <h3 class="text-white mb-1 fw-bold">
                        <i class="bi bi-journal-text me-2"></i>Logbook Intern
                    </h3>
                    <p class="text-white mb-0" style="color: rgba(255,255,255,0.75) !important;">
                        <?= htmlspecialchars($intern_dipilih['nama']) ?> &mdash; <?= htmlspecialchars($nama_divisi) ?>
                    </p>
                <?php else: ?>
                    <h3 class="text-white mb-1 fw-bold">
                        <i class="bi bi-journal-text me-2"></i>Logbook
                    </h3>
                    <p class="text-white mb-0" style="color: rgba(255,255,255,0.75) !important;">
                        Daftar intern &mdash; <?= htmlspecialchars($nama_divisi) ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php if ($mode_detail): ?>
                <a href="<?= BASE_URL ?>role/manajer/logbook.php" class="btn btn-light fw-semibold rounded-3">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$divisi_id): ?>
    <div class="alert alert-warning d-flex align-items-center rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div>Anda belum ditugaskan ke divisi manapun. Hubungi Admin untuk pengaturan divisi.</div>
    </div>
<?php elseif (!$mode_detail): ?>

    <!-- DAFTAR INTERN -->
    <div class="card border-0 shadow mb-4 card-solid">
        <div class="p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Cari Intern</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-3"
                            placeholder="Nama atau username...">
                    </div>
                </div>
                <div class="col-md-6">
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

    <div class="card border-0 shadow rounded-4 overflow-hidden card-solid">
        <div class="p-4">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0 custom-table" id="tabelIntern">
                    <thead>
                        <tr>
                            <th class="ps-3">No</th>
                            <th>Intern</th>
                            <th>Status</th>
                            <th>Periode Magang</th>
                            <th>Total Logbook</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tabelBody">
                        <?php if (count($intern_rows) === 0): ?>
                            <tr class="row-empty">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-people d-block mb-2" style="font-size:2rem;"></i>
                                    <div class="fw-semibold">Belum ada intern di divisi ini</div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php $no = 1;
                        foreach ($intern_rows as $intern): ?>
                            <tr
                                data-nama="<?= strtolower(htmlspecialchars($intern['nama'])) ?>"
                                data-username="<?= strtolower(htmlspecialchars($intern['username'])) ?>"
                                data-status="<?= $intern['status'] ?>">

                                <td class="ps-3 col-no"><?= $no++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= $intern['foto_profil']
                                                        ? BASE_URL . 'uploads/foto-profil/' . htmlspecialchars($intern['foto_profil'])
                                                        : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($intern['username']) ?>"
                                            class="rounded-circle" width="36" height="36"
                                            style="object-fit:cover;">
                                        <div>
                                            <div class="fw-semibold text-dark" style="font-size:13px;"><?= htmlspecialchars($intern['nama']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($intern['username']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($intern['status'] === 'aktif'): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-2">Tidak Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:13px;"><?= formatTanggal($intern['tanggal_mulai']) ?></div>
                                    <small class="text-muted">s/d <?= formatTanggal($intern['tanggal_selesai']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill px-3 py-2">
                                        <?= $intern['total_logbook'] ?> entri
                                    </span>
                                </td>
                                <td>
                                    <a href="?intern_id=<?= $intern['id'] ?>"
                                        class="btn btn-sm btn-logbook rounded-3 fw-semibold">
                                        <i class="bi bi-journal-text me-1"></i>Lihat Logbook
                                    </a>
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

<?php else: ?>

    <!-- INFO INTERN -->
    <div class="card border-0 shadow-sm mb-4 card-solid">
        <div class="p-4">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= $intern_dipilih['foto_profil']
                                ? BASE_URL . 'uploads/foto-profil/' . htmlspecialchars($intern_dipilih['foto_profil'])
                                : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($intern_dipilih['username']) ?>"
                    class="rounded-circle" width="56" height="56"
                    style="object-fit:cover;">
                <div>
                    <div class="fw-bold fs-6"><?= htmlspecialchars($intern_dipilih['nama']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($intern_dipilih['username']) ?></div>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-calendar2-range me-1"></i>
                        <?= formatTanggal($intern_dipilih['tanggal_mulai']) ?> &mdash; <?= formatTanggal($intern_dipilih['tanggal_selesai']) ?>
                        &nbsp;
                        <?php if ($intern_dipilih['status'] === 'aktif'): ?>
                            <span class="badge bg-success rounded-pill px-2 py-1">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-danger rounded-pill px-2 py-1">Tidak Aktif</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-secondary rounded-pill px-3 py-2 fs-6">
                        <?= count($logbook_rows) ?> entri
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER LOGBOOK -->
    <div class="card border-0 shadow mb-4 card-solid">
        <div class="p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Cari Kegiatan</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" id="searchLogbook" class="form-control border-start-0 rounded-end-3"
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
                            <th class="ps-3">No</th>
                            <th>Tanggal</th>
                            <th>Nama Kegiatan</th>
                            <th>Deskripsi</th>
                            <th>Lampiran</th>
                        </tr>
                    </thead>
                    <tbody id="tabelLogbookBody">
                        <?php if (count($logbook_rows) === 0): ?>
                            <tr class="row-empty">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-journal-x d-block mb-2" style="font-size:2rem;"></i>
                                    <div class="fw-semibold">Belum ada entri logbook</div>
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
                                <td style="max-width:300px;">
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- EMPTY STATE SEARCH -->
                <div id="emptyLogbook" class="text-center py-5 text-muted d-none">
                    <i class="bi bi-search d-block mb-2" style="font-size:2rem;"></i>
                    <div class="fw-semibold">Tidak ada entri yang cocok</div>
                    <div class="small mt-1">Coba kata kunci lain</div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

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

    .card-solid {
        background-color: #ffffff;
        border: 1px solid #d6dee3 !important;
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
    }
</style>

<script>
    // Filter tabel daftar intern
    <?php if (!$mode_detail && $divisi_id): ?>

        function filterIntern() {
            const keyword = document.getElementById('searchInput').value.trim().toLowerCase();
            const status = document.getElementById('filterStatus').value;
            const rows = document.querySelectorAll('#tabelBody tr:not(.row-empty)');
            let visible = 0;

            rows.forEach(tr => {
                const nama = tr.dataset.nama || '';
                const username = tr.dataset.username || '';
                const tStatus = tr.dataset.status || '';

                const matchKey = !keyword || nama.includes(keyword) || username.includes(keyword);
                const matchStatus = !status || tStatus === status;

                if (matchKey && matchStatus) {
                    tr.classList.remove('d-none');
                    visible++;
                } else {
                    tr.classList.add('d-none');
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

        document.getElementById('searchInput').addEventListener('input', filterIntern);
        document.getElementById('filterStatus').addEventListener('change', filterIntern);
    <?php endif; ?>

    // Filter tabel logbook
    <?php if ($mode_detail): ?>

        function filterLogbook() {
            const keyword = document.getElementById('searchLogbook').value.trim().toLowerCase();
            const bulan = document.getElementById('filterBulan').value;
            const rows = document.querySelectorAll('#tabelLogbookBody tr:not(.row-empty)');
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
                    const cells = tr.querySelectorAll('td');
                    const origKegiatan = tr.dataset.kegiatan.replace(/\b\w/g, c => c.toUpperCase());
                    cells[2].innerHTML = keyword ? highlightText(origKegiatan, keyword) : origKegiatan;
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

            document.getElementById('emptyLogbook').classList.toggle('d-none', visible > 0);
            document.getElementById('tabelLogbook').classList.toggle('d-none', visible === 0);
        }

        document.getElementById('searchLogbook').addEventListener('input', filterLogbook);
        document.getElementById('filterBulan').addEventListener('change', filterLogbook);
    <?php endif; ?>
</script>

<?php include BASE_PATH . "includes/footer.php"; ?>