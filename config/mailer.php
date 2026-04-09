<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_USERNAME', 'm.fahrulalfanani200505@gmail.com');
define('MAIL_PASSWORD', 'tgqd fazs ipwn kery');
define('MAIL_FROM',     'm.fahrulalfanani200505@gmail.com');
define('MAIL_NAME',     'Logbook Magang');

function buatMailer()
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(MAIL_FROM, MAIL_NAME);
    return $mail;
}

function kirimOTP($email, $nama, $otp)
{
    try {
        $mail = buatMailer();
        $mail->addAddress($email, $nama);
        $mail->isHTML(true);
        $mail->Subject = 'Kode OTP Reset Password - Logbook Magang';
        $mail->Body    = '
            <div style="font-family: Segoe UI, sans-serif; max-width: 480px; margin: auto; padding: 24px; border: 1px solid #dee2e6; border-radius: 12px;">
                <h3 style="color: #1E88B7; margin-bottom: 8px;">Reset Password</h3>
                <p style="color: #495057; font-size: 14px;">Halo <strong>' . $nama . '</strong>,</p>
                <p style="color: #495057; font-size: 14px;">Gunakan kode OTP berikut untuk mereset password anda:</p>
                <div style="text-align: center; margin: 24px 0;">
                    <span style="font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #1E88B7;">' . $otp . '</span>
                </div>
                <p style="color: #6c757d; font-size: 13px;">Kode ini hanya berlaku selama <strong>10 menit</strong>. Jangan bagikan kode ini kepada siapapun.</p>
                <hr style="border-color: #dee2e6; margin: 20px 0;">
                <p style="color: #adb5bd; font-size: 12px;">Jika anda tidak merasa melakukan permintaan ini, abaikan email ini.</p>
            </div>
        ';
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function kirimNotifikasiCadangan($email, $nama, $nama_intern, $sisa_hari)
{
    try {
        $mail = buatMailer();
        $mail->addAddress($email, $nama);
        $mail->isHTML(true);
        $mail->Subject = 'Notifikasi Cadangan Intern - Logbook Magang';
        $mail->Body    = '
            <div style="font-family: Segoe UI, sans-serif; max-width: 480px; margin: auto; padding: 24px; border: 1px solid #dee2e6; border-radius: 12px;">
                <h3 style="color: #1E88B7; margin-bottom: 8px;">Notifikasi Cadangan</h3>
                <p style="color: #495057; font-size: 14px;">Halo <strong>' . $nama . '</strong>,</p>
                <p style="color: #495057; font-size: 14px;">Data intern <strong>' . $nama_intern . '</strong> yang ada di cadangan akan terhapus permanen dalam <strong>' . $sisa_hari . ' hari</strong>.</p>
                <p style="color: #495057; font-size: 14px;">Segera pulihkan data tersebut jika masih diperlukan.</p>
                <hr style="border-color: #dee2e6; margin: 20px 0;">
                <p style="color: #adb5bd; font-size: 12px;">Email ini dikirim otomatis oleh sistem Logbook Magang.</p>
            </div>
        ';
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function kirimNotifikasiMagangHabis($email, $nama, $tanggal_selesai)
{
    try {
        $mail = buatMailer();
        $mail->addAddress($email, $nama);
        $mail->isHTML(true);
        $mail->Subject = 'Notifikasi Periode Magang - Logbook Magang';
        $mail->Body    = '
            <div style="font-family: Segoe UI, sans-serif; max-width: 480px; margin: auto; padding: 24px; border: 1px solid #dee2e6; border-radius: 12px;">
                <h3 style="color: #1E88B7; margin-bottom: 8px;">Periode Magang Akan Berakhir</h3>
                <p style="color: #495057; font-size: 14px;">Halo <strong>' . $nama . '</strong>,</p>
                <p style="color: #495057; font-size: 14px;">Periode magang anda akan berakhir pada <strong>' . date('d F Y', strtotime($tanggal_selesai)) . '</strong> (7 hari lagi).</p>
                <p style="color: #495057; font-size: 14px;">Pastikan semua logbook anda sudah terisi dengan lengkap sebelum periode magang berakhir.</p>
                <hr style="border-color: #dee2e6; margin: 20px 0;">
                <p style="color: #adb5bd; font-size: 12px;">Email ini dikirim otomatis oleh sistem Logbook Magang.</p>
            </div>
        ';
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
