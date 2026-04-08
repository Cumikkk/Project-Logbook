<?php
function formatTanggal($tanggal)
{
    $bulan = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember'
    ];
    $d = date('d', strtotime($tanggal));
    $m = date('m', strtotime($tanggal));
    $y = date('Y', strtotime($tanggal));
    return $d . ' ' . $bulan[$m] . ' ' . $y;
}

function sisaHari($tanggal)
{
    $sekarang = strtotime(date('Y-m-d'));
    $target   = strtotime($tanggal);
    $selisih  = $target - $sekarang;
    return (int) ceil($selisih / 86400);
}

function validasiLampiran($file)
{
    $ekstensi_izin = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $ukuran_maks   = 5 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'pesan' => 'Terjadi kesalahan saat upload file.'];
    }

    $ekstensi = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ekstensi, $ekstensi_izin)) {
        return ['status' => false, 'pesan' => 'Format file tidak diizinkan. Gunakan jpg, png, pdf, doc, atau docx.'];
    }

    if ($file['size'] > $ukuran_maks) {
        return ['status' => false, 'pesan' => 'Ukuran file maksimal 5MB.'];
    }

    return ['status' => true, 'pesan' => ''];
}

function uploadLampiran($file)
{
    $validasi = validasiLampiran($file);
    if (!$validasi['status']) {
        return ['status' => false, 'pesan' => $validasi['pesan']];
    }

    $ekstensi  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $nama_file = uniqid('lampiran_') . '.' . $ekstensi;
    $tujuan    = BASE_PATH . 'uploads/lampiran/' . $nama_file;

    if (move_uploaded_file($file['tmp_name'], $tujuan)) {
        return ['status' => true, 'nama_file' => $nama_file];
    }

    return ['status' => false, 'pesan' => 'Gagal menyimpan file.'];
}

function uploadFotoProfil($file)
{
    $ekstensi_izin = ['jpg', 'jpeg', 'png'];
    $ukuran_maks   = 2 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'pesan' => 'Terjadi kesalahan saat upload foto.'];
    }

    $ekstensi = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ekstensi, $ekstensi_izin)) {
        return ['status' => false, 'pesan' => 'Format foto tidak diizinkan. Gunakan jpg atau png.'];
    }

    if ($file['size'] > $ukuran_maks) {
        return ['status' => false, 'pesan' => 'Ukuran foto maksimal 2MB.'];
    }

    $nama_file = uniqid('foto_') . '.' . $ekstensi;
    $tujuan    = BASE_PATH . 'uploads/foto-profil/' . $nama_file;

    if (move_uploaded_file($file['tmp_name'], $tujuan)) {
        return ['status' => true, 'nama_file' => $nama_file];
    }

    return ['status' => false, 'pesan' => 'Gagal menyimpan foto.'];
}

function hapusFile($path)
{
    if ($path && file_exists(BASE_PATH . $path)) {
        unlink(BASE_PATH . $path);
    }
}

function cekNotifikasiCadangan($conn)
{
    $hari_ini = date('Y-m-d');
    $batas    = date('Y-m-d', strtotime('+7 days'));

    $query = mysqli_query($conn, "
        SELECT * FROM cadangan
        WHERE expires_at BETWEEN '$hari_ini' AND '$batas'
    ");

    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

function cekNotifikasiMagang($conn)
{
    $target = date('Y-m-d', strtotime('+7 days'));

    $query = mysqli_query($conn, "
        SELECT u.nama, u.email, id.tanggal_selesai
        FROM users u
        JOIN intern_detail id ON u.id = id.user_id
        WHERE id.tanggal_selesai = '$target'
        AND u.status = 'aktif'
        AND u.role = 'intern'
    ");

    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

function generateOTP()
{
    return rand(100000, 999999);
}

function redirect($url)
{
    header('Location: ' . BASE_URL . $url);
    exit;
}

function bersihkan($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}
