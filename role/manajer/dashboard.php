<?php
require_once __DIR__ . "/../../config/koneksi.php";
require_once __DIR__ . "/../../config/auth.php";

proteksi("manajer");

$today = date("Y-m-d");
$bulan = date("m");
$tahun = date("Y");
$manajer_id = $_SESSION['user_id'];

// Divisi manajer ini
$q_divisi = mysqli_query($conn, "
    SELECT d.id, d.nama_divisi
    FROM divisi d
    WHERE d.manajer_id = '$manajer_id'
    LIMIT 1
");
$divisi = mysqli_fetch_assoc($q_divisi);
$divisi_id = $divisi['id'] ?? null;
$nama_divisi = $divisi['nama_divisi'] ?? '-';

// Total intern aktif di divisi ini
$total_intern = 0;
if ($divisi_id) {
    $q_intern = mysqli_query($conn, "
        SELECT COUNT(*) as total FROM users u
        JOIN intern_detail id ON u.id = id.user_id
        WHERE u.role = 'intern'
        AND u.status = 'aktif'
        AND id.divisi_id = '$divisi_id'
    ");
    $total_intern = mysqli_fetch_assoc($q_intern)['total'] ?? 0;
}

// Total intern tidak aktif di divisi ini
$total_nonaktif = 0;
if ($divisi_id) {
    $q_nonaktif = mysqli_query($conn, "
        SELECT COUNT(*) as total FROM users u
        JOIN intern_detail id ON u.id = id.user_id
        WHERE u.role = 'intern'
        AND u.status = 'tidak_aktif'
        AND id.divisi_id = '$divisi_id'
    ");
    $total_nonaktif = mysqli_fetch_assoc($q_nonaktif)['total'] ?? 0;
}

// Total entri logbook bulan ini di divisi ini
$total_logbook = 0;
if ($divisi_id) {
    $q_logbook = mysqli_query($conn, "
        SELECT COUNT(*) as total FROM logbook l
        JOIN intern_detail id ON l.intern_id = id.user_id
        WHERE id.divisi_id = '$divisi_id'
        AND MONTH(l.tanggal) = '$bulan'
        AND YEAR(l.tanggal) = '$tahun'
        AND l.is_deleted = 0
    ");
    $total_logbook = mysqli_fetch_assoc($q_logbook)['total'] ?? 0;
}

// Total cadangan di divisi ini
$total_cadangan = 0;
if ($divisi_id) {
    $q_total_cad = mysqli_query($conn, "
        SELECT COUNT(*) as total FROM cadangan
        WHERE divisi_id = '$divisi_id'
    ");
    $total_cadangan = mysqli_fetch_assoc($q_total_cad)['total'] ?? 0;
}

// Intern akan selesai magang dalam 7 hari ke depan
$batas = date("Y-m-d", strtotime("+7 days"));
$q_akan_selesai = null;
if ($divisi_id) {
    $q_akan_selesai = mysqli_query($conn, "
        SELECT u.nama, u.foto_profil, u.username, id.tanggal_selesai
        FROM users u
        JOIN intern_detail id ON u.id = id.user_id
        WHERE u.role = 'intern'
        AND u.status = 'aktif'
        AND id.divisi_id = '$divisi_id'
        AND id.tanggal_selesai BETWEEN '$today' AND '$batas'
        ORDER BY id.tanggal_selesai ASC
        LIMIT 5
    ");
}

// Cadangan mendekati batas hapus (7 hari ke depan)
$q_cadangan = null;
if ($divisi_id) {
    $q_cadangan = mysqli_query($conn, "
        SELECT c.*, d.nama_divisi
        FROM cadangan c
        LEFT JOIN divisi d ON c.divisi_id = d.id
        WHERE c.divisi_id = '$divisi_id'
        AND c.expires_at BETWEEN '$today' AND '$batas'
        ORDER BY c.expires_at ASC
        LIMIT 5
    ");
}

// Logbook terbaru di divisi ini
$q_logbook_terbaru = null;
if ($divisi_id) {
    $q_logbook_terbaru = mysqli_query($conn, "
        SELECT l.*, u.nama, u.foto_profil, u.username
        FROM logbook l
        JOIN users u ON l.intern_id = u.id
        JOIN intern_detail id ON u.id = id.user_id
        WHERE id.divisi_id = '$divisi_id'
        AND l.is_deleted = 0
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
}

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

<!-- HEADER DASHBOARD -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="dashboard-banner px-4 py-4">
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <h3 class="text-white mb-1 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h3>
                <p class="text-white mb-0" style="color: rgba(255,255,255,0.75) !important;">
                    <?= $greeting ?> <?= $icon ?> Semangat bekerja hari ini, <?= htmlspecialchars($_SESSION['nama']) ?>!
                </p>
            </div>
            <div class="text-end d-none d-md-block">
                <span class="badge bg-white text-primary fw-semibold px-3 py-2" style="font-size:13px;">
                    <i class="bi bi-diagram-3-fill me-1"></i><?= htmlspecialchars($nama_divisi) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row">

    <!-- TOTAL INTERN AKTIF -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-intern rounded-4 p-3 text-center shadow-sm h-100">
            <div class="stat-icon mb-1"><i class="bi bi-people-fill"></i></div>
            <div class="stat-num"><?= $total_intern ?></div>
            <div class="stat-label">Intern Aktif</div>
            <div class="stat-sub">Di divisi <?= htmlspecialchars($nama_divisi) ?></div>
        </div>
    </div>

    <!-- INTERN TIDAK AKTIF -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-nonaktif rounded-4 p-3 text-center shadow-sm h-100">
            <div class="stat-icon mb-1"><i class="bi bi-person-slash-fill"></i></div>
            <div class="stat-num"><?= $total_nonaktif ?></div>
            <div class="stat-label">Intern Tidak Aktif</div>
            <div class="stat-sub">Masa magang selesai</div>
        </div>
    </div>

    <!-- ENTRI LOGBOOK BULAN INI -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-logbook rounded-4 p-3 text-center shadow-sm h-100">
            <div class="stat-icon mb-1"><i class="bi bi-journal-text"></i></div>
            <div class="stat-num"><?= $total_logbook ?></div>
            <div class="stat-label">Entri Logbook</div>
            <div class="stat-sub"><?= date("F Y") ?></div>
        </div>
    </div>

    <!-- TOTAL CADANGAN -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-cadangan rounded-4 p-3 text-center shadow-sm h-100">
            <div class="stat-icon mb-1"><i class="bi bi-archive-fill"></i></div>
            <div class="stat-num"><?= $total_cadangan ?></div>
            <div class="stat-label">Cadangan</div>
            <div class="stat-sub">Intern dihapus</div>
        </div>
    </div>

</div>

<!-- 3 KOLOM BAWAH -->
<div class="row">

    <!-- INTERN AKAN SELESAI MAGANG -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow h-100 overflow-hidden">
            <div class="card-banner px-4 py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="text-white fw-bold mb-0">Segera Selesai Magang</h5>
                        <small style="color: rgba(255,255,255,0.75);">Dalam 7 hari ke depan</small>
                    </div>
                    <i class="bi bi-calendar-event text-white opacity-75 fs-3"></i>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($q_akan_selesai && mysqli_num_rows($q_akan_selesai) > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php while ($intern = mysqli_fetch_assoc($q_akan_selesai)): ?>
                            <li class="list-group-item px-4 py-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= $intern['foto_profil']
                                                        ? BASE_URL . 'uploads/foto-profil/' . htmlspecialchars($intern['foto_profil'])
                                                        : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($intern['username']) ?>"
                                            class="rounded-circle" width="36" height="36"
                                            style="object-fit:cover;">
                                        <div>
                                            <div class="fw-semibold text-dark" style="font-size:13px;"><?= htmlspecialchars($intern['nama']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($nama_divisi) ?></small>
                                        </div>
                                    </div>
                                    <span class="badge bg-warning text-dark"><?= date("d M", strtotime($intern['tanggal_selesai'])) ?></span>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                    <div class="px-4 py-3 border-top">
                        <a href="<?= BASE_URL ?>role/manajer/intern.php" class="btn btn-sm btn-outline-primary w-100">
                            Lihat Semua Intern
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                        <p class="fw-semibold">Tidak ada intern yang akan selesai</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CADANGAN MENDEKATI BATAS -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow h-100 overflow-hidden">
            <div class="card-banner px-4 py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="text-white fw-bold mb-0">Cadangan Kadaluarsa</h5>
                        <small style="color: rgba(255,255,255,0.75);">Mendekati batas 30 hari</small>
                    </div>
                    <i class="bi bi-trash3 text-white opacity-75 fs-3"></i>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($q_cadangan && mysqli_num_rows($q_cadangan) > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php while ($cad = mysqli_fetch_assoc($q_cadangan)): ?>
                            <li class="list-group-item px-4 py-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-semibold text-dark" style="font-size:13px;"><?= htmlspecialchars($cad['nama']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($cad['nama_divisi'] ?? '-') ?></small>
                                    </div>
                                    <span class="badge bg-danger">
                                        <?= date("d M", strtotime($cad['expires_at'])) ?>
                                    </span>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                    <div class="px-4 py-3 border-top">
                        <a href="<?= BASE_URL ?>role/manajer/cadangan.php" class="btn btn-sm btn-outline-primary w-100">
                            Lihat Cadangan
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                        <p class="fw-semibold">Tidak ada cadangan mendekati batas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- LOGBOOK TERBARU -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow h-100 overflow-hidden">
            <div class="card-banner px-4 py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="text-white fw-bold mb-0">Logbook Terbaru</h5>
                        <small style="color: rgba(255,255,255,0.75);">Entri paling baru</small>
                    </div>
                    <i class="bi bi-journal-check text-white opacity-75 fs-3"></i>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($q_logbook_terbaru && mysqli_num_rows($q_logbook_terbaru) > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php while ($log = mysqli_fetch_assoc($q_logbook_terbaru)): ?>
                            <li class="list-group-item px-4 py-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= $log['foto_profil']
                                                        ? BASE_URL . 'uploads/foto-profil/' . htmlspecialchars($log['foto_profil'])
                                                        : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($log['username']) ?>"
                                            class="rounded-circle" width="36" height="36"
                                            style="object-fit:cover;">
                                        <div>
                                            <div class="fw-semibold text-dark" style="font-size:13px;"><?= htmlspecialchars($log['nama_kegiatan']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($log['nama']) ?></small>
                                        </div>
                                    </div>
                                    <span class="badge bg-secondary"><?= date("d M", strtotime($log['tanggal'])) ?></span>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                    <div class="px-4 py-3 border-top">
                        <a href="<?= BASE_URL ?>role/manajer/logbook.php" class="btn btn-sm btn-outline-primary w-100">
                            Lihat Semua Logbook
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                        <p class="fw-semibold">Belum ada entri logbook</p>
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

    .stat-intern {
        background: linear-gradient(135deg, #a8d8ea, #74b9d4);
        color: #0a3d55;
    }

    .stat-nonaktif {
        background: linear-gradient(135deg, #d3d3d3, #b0b0b0);
        color: #3a3a3a;
    }

    .stat-logbook {
        background: linear-gradient(135deg, #f5b7b1, #f1948a);
        color: #6b1a1a;
    }

    .stat-cadangan {
        background: linear-gradient(135deg, #ffe08a, #ffd040);
        color: #6d4c00;
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