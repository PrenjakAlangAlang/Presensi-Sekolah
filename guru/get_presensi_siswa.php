<?php
// guru/get_presensi_siswa.php
include '../config.php';
include '../functions.php';

if (!isLoggedIn() || !isGuru()) {
    die("Akses ditolak.");
}

$sesi_id = isset($_POST['sesi_id']) ? intval($_POST['sesi_id']) : 0;
if (!$sesi_id) {
    echo '<p>ID sesi tidak valid.</p>';
    exit;
}

// Ambil presensi siswa untuk sesi ini
$res = getPresensiBySesi($sesi_id);

if ($res && $res->num_rows > 0) {
    echo '<table style="width:100%; border-collapse:collapse;">';
    echo '<thead><tr><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Nama</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Username</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Status</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">Waktu</th></tr></thead>';
    echo '<tbody>';
    while ($row = $res->fetch_assoc()) {
        $status = htmlspecialchars($row['status']);
        $waktu = htmlspecialchars($row['created_at'] ?? '');
        echo '<tr>';
        echo '<td style="padding:8px; border-bottom:1px solid #eee;">' . htmlspecialchars($row['nama_lengkap']) . '</td>';
        echo '<td style="padding:8px; border-bottom:1px solid #eee;">' . htmlspecialchars($row['username']) . '</td>';
        echo '<td style="padding:8px; border-bottom:1px solid #eee;">' . $status . '</td>';
        echo '<td style="padding:8px; border-bottom:1px solid #eee;">' . $waktu . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p>Tidak ada data presensi untuk sesi ini.</p>';
}

?>