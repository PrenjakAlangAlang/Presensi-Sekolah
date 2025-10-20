<?php
// notifikasi.php - Script untuk membuat notifikasi ketika siswa tidak hadir
include 'config.php';

// Script ini bisa dijalankan via cron job atau dipanggil setelah presensi
function buatNotifikasiAbsen($siswa_id, $sesi_id, $status_absen) {
    global $conn;
    
    // Dapatkan orang tua dari siswa
    $sql = "SELECT orangtua_id FROM orangtua_siswa WHERE siswa_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($orangtua = $result->fetch_assoc()) {
        // Buat notifikasi
        $insert_sql = "INSERT INTO notifikasi_absen (orangtua_id, siswa_id, sesi_id, status_absen, tanggal) 
                      VALUES (?, ?, ?, ?, CURDATE())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiis", $orangtua['orangtua_id'], $siswa_id, $sesi_id, $status_absen);
        $insert_stmt->execute();
    }
}

// Contoh penggunaan: panggil fungsi ini ketika siswa melakukan presensi dengan status tidak hadir
?>