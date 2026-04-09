<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

proteksi("intern");

$user_id = $_SESSION['user_id'];
$today = date("Y-m-d");
$bulan = date("m");
$tahun = date("Y");

// Data intern detail
$q_detail = mysqli_query($conn, "
    SELECT id.*, d.nama_divisi, u2.nama AS nama_manajer
    FROM intern_detail id
    JOIN divisi d ON id.divisi_id = d.id
    JOIN users u2 ON id.manajer_id = u2.id
    WHERE id.user_id = '$user_id'
");
$detail = mysqli_fetch_assoc($q_detail);

// Hitung sisa hari magang
$sisa_hari = null;
$persen_progress = 0;
if ($detail) {
    $mulai = new DateTime($detail['tanggal_mulai']);
    $selesai = new DateTime($detail['tanggal_selesai']);
    $now = new DateTime($today);

    $total_hari = $mulai->diff($selesai)->days;
    $sudah_berjalan = $mulai->diff($now)->days;
    $sisa_hari = $now->diff($selesai)->days;

    if ($now > $selesai) {
        $sisa_hari = 0;
        $persen_progress = 100;
    } else {
        $persen_progress = $total_hari > 0 ? min(100, round(($sudah_berjalan / $total_hari) * 100)) : 0;
    }
}

// Total semua entri logbook milik intern
$q_total = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM logbook
    WHERE intern_id = '$user_id' AND is_deleted = 0
");
$total_entri = mysqli_fetch_assoc($q_total)['total'] ?? 0;

// Total entri bulan ini
$q_bulan = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM logbook
    WHERE intern_id = '$user_id'
    AND is_deleted = 0
    AND MONTH(tanggal) = '$bulan'
    AND YEAR(tanggal) = '$tahun'
");
$entri_bulan_ini = mysqli_fetch_assoc($q_bulan)['total'] ?? 0;

// Total lampiran terupload
$q_lampiran = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM logbook
    WHERE intern_id = '$user_id'
    AND is_deleted = 0
    AND lampiran IS NOT NULL
    AND lampiran != ''
");
$total_lampiran = mysqli_fetch_assoc($q_lampiran)['total'] ?? 0;

// Logbook terbaru milik intern (5 entri)
$q_logbook = mysqli_query($conn, "
    SELECT * FROM logbook
    WHERE intern_id = '$user_id' AND is_deleted = 0
    ORDER BY tanggal DESC, created_at DESC
    LIMIT 5
");

// Greeting berdasarkan waktu
$jam = (int)date('H');
if ($jam >= 5 && $jam < 11) {
    $greeting = "Selamat Pagi";
    $icon = '<i class="bi bi-brightness-high-fill"></i>';
} elseif ($jam >= 11 && $jam < 15) {
    $greeting = "Selamat Siang";
    $icon = '<i class="bi bi-sun-fill"></i>';
} elseif ($jam >= 15 && $jam < 18) {
    $greeting = "Selamat Sore";
    $icon = '<i class="bi bi-sunset-fill"></i>';
} else {
    $greeting = "Selamat Malam";
    $icon = '<i class="bi bi-moon-stars-fill"></i>';
}

include BASE_PATH . "includes/header.php";
include BASE_PATH . "includes/sidebar.php";
?>

<!-- Notifikasi magang akan selesai -->
<?php if ($detail && $sisa_hari !== null && $sisa_hari <= 7 && $sisa_hari > 0): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            Masa magangmu akan berakhir dalam <strong><?= $sisa_hari ?> hari</strong> lagi
            (<?= date("d M Y", strtotime($detail['tanggal_selesai'])) ?>).
            Pastikan logbookmu sudah lengkap!
        </div>
    </div>
<?php endif; ?>

<!-- HEADER DASHBOARD -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="dashboard-banner px-4 py-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h3 class="text-white mb-1 fw-bold">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </h3>
                <p class="mb-0" style="color: rgba(255,255,255,0.85);">
                    <?= $greeting ?> <?= $icon ?> Semangat hari ini, <?= htmlspecialchars($_SESSION['nama']) ?>!
                </p>
            </div>
            <?php if ($detail): ?>
                <div class="text-end">
                    <div class="text-white fw-semibold" style="font-size:13px;">
                        <i class="bi bi-diagram-3-fill me-1"></i><?= htmlspecialchars($detail['nama_divisi']) ?>
                    </div>
                    <div style="color: rgba(255,255,255,0.75); font-size:12px;">
                        <i class="bi bi-person-badge-fill me-1"></i><?= htmlspecialchars($detail['nama_manajer']) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row">

    <!-- TOTAL ENTRI LOGBOOK -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-total rounded-4 p-3 text-center shadow-sm h-100">
            <div class="stat-icon mb-1"><i class="bi bi-journal-text"></i></div>
            <div class="stat-num"><?= $total_entri ?></div>
            <div class="stat-label">Total Entri</div>
            <div class="stat-sub">Semua logbook</div>
        </div>
    </div>

    <!-- ENTRI BULAN INI -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-bulan rounded-4 p-3 text-center shadow-sm h-100">
            <div class="stat-icon mb-1"><i class="bi bi-calendar2-check-fill"></i></div>
            <div class="stat-num"><?= $entri_bulan_ini ?></div>
            <div class="stat-label">Entri Bulan Ini</div>
            <div class="stat-sub"><?= date("F Y") ?></div>
        </div>
    </div>

    <!-- TOTAL LAMPIRAN -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-lampiran rounded-4 p-3 text-center shadow-sm h-100">
            <div class="stat-icon mb-1"><i class="bi bi-paperclip"></i></div>
            <div class="stat-num"><?= $total_lampiran ?></div>
            <div class="stat-label">Total Lampiran</div>
            <div class="stat-sub">File terupload</div>
        </div>
    </div>

    <!-- SISA HARI MAGANG -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-sisa rounded-4 p-3 text-center shadow-sm h-100">
            <div class="stat-icon mb-1"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-num"><?= $sisa_hari !== null ? $sisa_hari : '-' ?></div>
            <div class="stat-label">Sisa Hari</div>
            <div class="stat-sub">Masa magang</div>
        </div>
    </div>

</div>

<!-- 2 KOLOM BAWAH -->
<div class="row">

    <!-- PROGRESS MAGANG + INFO -->
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow h-100 overflow-hidden">
            <div class="card-banner px-4 py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="text-white fw-bold mb-0">Info Magang</h5>
                        <small style="color: rgba(255,255,255,0.75);">Status & progres magang</small>
                    </div>
                    <i class="bi bi-person-workspace text-white opacity-75 fs-3"></i>
                </div>
            </div>
            <div class="card-body px-4 py-4">
                <?php if ($detail): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="fw-semibold text-muted">Progres Magang</small>
                            <small class="fw-bold text-primary"><?= $persen_progress ?>%</small>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 99px;">
                            <div class="progress-bar bg-primary" role="progressbar"
                                style="width: <?= $persen_progress ?>%; border-radius: 99px;"
                                aria-valuenow="<?= $persen_progress ?>" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    <ul class="list-unstyled mb-0 info-magang-list">
                        <li>
                            <span class="label"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Divisi</span>
                            <span class="value"><?= htmlspecialchars($detail['nama_divisi']) ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Manajer</span>
                            <span class="value"><?= htmlspecialchars($detail['nama_manajer']) ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="bi bi-calendar-event me-2 text-primary"></i>Mulai</span>
                            <span class="value"><?= date("d M Y", strtotime($detail['tanggal_mulai'])) ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="bi bi-calendar-check me-2 text-primary"></i>Selesai</span>
                            <span class="value"><?= date("d M Y", strtotime($detail['tanggal_selesai'])) ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="bi bi-hourglass-split me-2 text-primary"></i>Sisa</span>
                            <span class="value">
                                <?php if ($sisa_hari === 0): ?>
                                    <span class="badge bg-secondary">Selesai</span>
                                <?php elseif ($sisa_hari <= 7): ?>
                                    <span class="badge bg-warning text-dark"><?= $sisa_hari ?> hari lagi</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><?= $sisa_hari ?> hari lagi</span>
                                <?php endif; ?>
                            </span>
                        </li>
                    </ul>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                        <p class="fw-semibold">Data magang tidak ditemukan</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($detail): ?>
                <div class="px-4 pb-4">
                    <a href="<?= BASE_URL ?>role/intern/logbook.php" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-journal-plus me-1"></i> Tambah Entri Logbook
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- LOGBOOK TERBARU -->
    <div class="col-lg-7 mb-4">
        <div class="card border-0 shadow h-100 overflow-hidden">
            <div class="card-banner px-4 py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="text-white fw-bold mb-0">Logbook Terbaru</h5>
                        <small style="color: rgba(255,255,255,0.75);">5 entri paling baru</small>
                    </div>
                    <i class="bi bi-journal-check text-white opacity-75 fs-3"></i>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (mysqli_num_rows($q_logbook) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4" style="font-size:12px; width:110px;">Tanggal</th>
                                    <th style="font-size:12px;">Kegiatan</th>
                                    <th class="text-center" style="font-size:12px; width:80px;">Lampiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = mysqli_fetch_assoc($q_logbook)): ?>
                                    <tr>
                                        <td class="px-4">
                                            <span class="badge bg-secondary"><?= date("d M Y", strtotime($log['tanggal'])) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark" style="font-size:13px;">
                                                <?= htmlspecialchars($log['nama_kegiatan']) ?>
                                            </div>
                                            <small class="text-muted text-truncate d-block" style="max-width:280px;">
                                                <?= htmlspecialchars($log['deskripsi']) ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($log['lampiran']): ?>
                                                <a href="<?= BASE_URL ?>uploads/lampiran/<?= htmlspecialchars($log['lampiran']) ?>"
                                                    target="_blank"
                                                    class="text-primary" title="Lihat lampiran">
                                                    <i class="bi bi-paperclip fs-5"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="bi bi-dash"></i></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 border-top">
                        <a href="<?= BASE_URL ?>role/intern/logbook.php" class="btn btn-sm btn-outline-primary w-100">
                            Lihat Semua Logbook
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                        <p class="fw-semibold">Belum ada entri logbook</p>
                        <a href="<?= BASE_URL ?>role/intern/logbook.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Entri
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<style>
    .dashboard-banner {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .card-banner {
        background: linear-gradient(135deg, #2f8f9d 0%, #1a6b76 100%);
    }

    .stat-card {
        transition: transform 0.15s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .stat-total {
        background: linear-gradient(135deg, #a8d8ea, #74b9d4);
        color: #0a3d55;
    }

    .stat-bulan {
        background: linear-gradient(135deg, #b7e4c7, #95d5b2);
        color: #155724;
    }

    .stat-lampiran {
        background: linear-gradient(135deg, #ffe08a, #ffd040);
        color: #6d4c00;
    }

    .stat-sisa {
        background: linear-gradient(135deg, #f5b7b1, #f1948a);
        color: #6b1a1a;
    }

    .stat-icon {
        font-size: 26px;
    }

    .stat-num {
        font-size: 32px;
        font-weight: 800;
        line-height: 1.1;
    }

    .stat-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.85;
    }

    .stat-sub {
        font-size: 11px;
        opacity: 0.7;
        margin-top: 2px;
    }

    .info-magang-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: 13px;
    }

    .info-magang-list li:last-child {
        border-bottom: none;
    }

    .info-magang-list .label {
        color: #6c757d;
        font-weight: 500;
    }

    .info-magang-list .value {
        font-weight: 600;
        color: #333;
        text-align: right;
    }

    @media (max-width: 576px) {
        .stat-num {
            font-size: 26px;
        }

        .stat-icon {
            font-size: 20px;
        }

        .stat-label {
            font-size: 10px;
        }
    }
</style>

<?php include BASE_PATH . "includes/footer.php"; ?>