<?php
// siswa/get_sesi_presensi.php
include '../config.php';
include '../functions.php';

if (!isLoggedIn() || !isSiswa()) {
    die("Akses ditolak.");
}

$kelas_id = $_POST['kelas_id'];
$is_ketua = $_POST['is_ketua'];
$siswa_id = $_SESSION['user_id'];

// Get sesi presensi aktif untuk kelas ini
$sesi_sql = "SELECT sp.*, u.nama_lengkap as nama_guru, 
                    (SELECT status FROM presensi_siswa WHERE sesi_id = sp.id AND siswa_id = $siswa_id) as status_presensi,
                    (SELECT status FROM presensi_guru WHERE sesi_id = sp.id AND guru_id = sp.guru_id) as status_guru
             FROM sesi_presensi sp 
             JOIN users u ON sp.guru_id = u.id 
             WHERE sp.kelas_id = ? AND sp.status = 'aktif' 
             ORDER BY sp.tanggal DESC, sp.waktu_mulai DESC";
$stmt = $conn->prepare($sesi_sql);
$stmt->bind_param("i", $kelas_id);
$stmt->execute();
$sesi_result = $stmt->get_result();

if ($sesi_result->num_rows > 0) {
    while ($sesi = $sesi_result->fetch_assoc()) {
        echo '<div class="sesi-item">';
        echo '<h4>' . $sesi['mata_pelajaran'] . '</h4>';
        echo '<p><strong>Guru:</strong> ' . $sesi['nama_guru'] . '</p>';
        echo '<p><strong>Tanggal:</strong> ' . $sesi['tanggal'] . '</p>';
        echo '<p><strong>Waktu:</strong> ' . $sesi['waktu_mulai'] . ' - ' . $sesi['waktu_selesai'] . '</p>';
        
        if ($sesi['kode_presensi']) {
            echo '<p><strong>Kode:</strong> ' . $sesi['kode_presensi'] . '</p>';
        }
        
        // Form presensi siswa
        if ($sesi['status_presensi']) {
            echo '<p><strong>Status Presensi Anda:</strong> ' . $sesi['status_presensi'] . '</p>';
        } else {
            echo '<div class="form-group">';
            echo '<select id="status_' . $sesi['id'] . '">';
            echo '<option value="">Pilih Status</option>';
            echo '<option value="hadir">Hadir</option>';
            echo '<option value="sakit">Sakit</option>';
            echo '<option value="izin">Izin</option>';
            echo '<option value="alpha">Alpha</option>';
            echo '</select>';
            echo '<button class="btn" onclick="presensiSiswa(' . $sesi['id'] . ')">Presensi</button>';
            echo '</div>';
        }
        
        // Form presensi guru (hanya untuk ketua kelas)
        if ($is_ketua == 1) {
            echo '<hr>';
            if ($sesi['status_guru']) {
                echo '<p><strong>Status Guru:</strong> ' . $sesi['status_guru'] . '</p>';
            } else {
                echo '<div class="form-group">';
                echo '<select id="status_guru_' . $sesi['id'] . '">';
                echo '<option value="">Presensi Guru</option>';
                echo '<option value="hadir">Guru Hadir</option>';
                echo '<option value="tidak_hadir">Guru Tidak Hadir</option>';
                echo '</select>';
                echo '<button class="btn" onclick="presensiGuru(' . $sesi['id'] . ', ' . $sesi['guru_id'] . ')">Presensi Guru</button>';
                echo '</div>';
            }
        }
        
        echo '</div>';
    }
} else {
    echo '<p>Tidak ada sesi presensi aktif untuk kelas ini.</p>';
}
?>