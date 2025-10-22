<?php
// siswa/get_sesi_presensi.php
include '../config.php';
include '../functions.php';

if (!isLoggedIn() || !isSiswa()) {
    die("Akses ditolak.");
}

$kelas_id = $_POST['kelas_id'];
$is_ketua = $_POST['is_ketua'];
$mata_pelajaran = isset($_POST['mata_pelajaran']) ? trim($_POST['mata_pelajaran']) : '';
$siswa_id = $_SESSION['user_id'];

// Build sesi presensi query with optional mata_pelajaran filter
    $sesi_sql = "SELECT sp.*, u.nama_lengkap as nama_guru, 
                    (SELECT status FROM presensi_siswa WHERE sesi_id = sp.id AND siswa_id = ?) as status_presensi,
                    (SELECT status FROM presensi_guru WHERE sesi_id = sp.id AND guru_id = sp.guru_id) as status_guru
             FROM sesi_presensi sp 
             JOIN users u ON sp.guru_id = u.id 
             WHERE sp.kelas_id = ? AND sp.status = 'aktif'";

    $params = [];
    $types = '';
    // First param for subquery siswa_id
    $types .= 'i';
    $params[] = $siswa_id;

    // kelas_id param
    $types .= 'i';
    $params[] = $kelas_id;

    if ($mata_pelajaran !== '') {
        $sesi_sql .= " AND sp.mata_pelajaran = ?";
        $types .= 's';
        $params[] = $mata_pelajaran;
    }

    $sesi_sql .= " ORDER BY sp.tanggal DESC, sp.waktu_mulai DESC";

    $stmt = $conn->prepare($sesi_sql);
    if ($stmt === false) {
        die('Query prepare failed: ' . $conn->error);
    }

    // bind params dynamically
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    $stmt->execute();
    $sesi_result = $stmt->get_result();

if ($sesi_result->num_rows > 0) {
    while ($sesi = $sesi_result->fetch_assoc()) {
        $data_attrs = '';
        if ($sesi['latitude'] !== null) $data_attrs .= ' data-lat="' . htmlspecialchars($sesi['latitude']) . '"';
        if ($sesi['longitude'] !== null) $data_attrs .= ' data-lon="' . htmlspecialchars($sesi['longitude']) . '"';
        if ($sesi['geo_radius_m'] !== null) $data_attrs .= ' data-radius="' . htmlspecialchars($sesi['geo_radius_m']) . '"';
        echo '<div class="sesi-item"' . $data_attrs . '>';
        echo '<h4>' . $sesi['mata_pelajaran'] . '</h4>';
        echo '<p><strong>Guru:</strong> ' . $sesi['nama_guru'] . '</p>';
        echo '<p><strong>Tanggal:</strong> ' . $sesi['tanggal'] . '</p>';
        echo '<p><strong>Waktu:</strong> ' . $sesi['waktu_mulai'] . ' - ' . $sesi['waktu_selesai'] . '</p>';
        
        if ($sesi['kode_presensi']) {
            echo '<p><strong>Kode:</strong> ' . $sesi['kode_presensi'] . '</p>';
        }

        if ($sesi['latitude'] !== null && $sesi['longitude'] !== null) {
            $radius_info = $sesi['geo_radius_m'] ? ' (radius ' . intval($sesi['geo_radius_m']) . ' m)' : '';
            echo '<p><strong>Lokasi Presensi:</strong> ' . htmlspecialchars($sesi['latitude']) . ', ' . htmlspecialchars($sesi['longitude']) . $radius_info . '</p>';
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