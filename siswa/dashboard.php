<?php
// siswa/dashboard.php
include '../config.php';
include '../functions.php';

if (!isLoggedIn() || !isSiswa()) {
    redirect('../login.php');
}

$siswa_id = $_SESSION['user_id'];

// Handle presensi siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['presensi'])) {
    $sesi_id = $_POST['sesi_id'];
    $status = $_POST['status'];
    // optional submitted geolocation from browser
    $submitted_lat = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? $_POST['latitude'] : null;
    $submitted_lon = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? $_POST['longitude'] : null;
    // Cek status sesi: hanya izinkan presensi jika sesi masih 'aktif'
    $sesi_check = $conn->prepare("SELECT status FROM sesi_presensi WHERE id = ? LIMIT 1");
    if ($sesi_check) {
        $sesi_check->bind_param("i", $sesi_id);
        $sesi_check->execute();
        $sesi_res = $sesi_check->get_result();
        if ($sesi_res && $sesi_row = $sesi_res->fetch_assoc()) {
            if ($sesi_row['status'] !== 'aktif') {
                $error = "Sesi presensi sudah selesai. Anda tidak dapat melakukan presensi.";
                $sesi_check->close();
                // skip further processing
            }
        } else {
            $error = "Sesi presensi tidak ditemukan.";
            $sesi_check->close();
        }
    }
    
    // Cek apakah sudah presensi
    $cek_sql = "SELECT * FROM presensi_siswa WHERE sesi_id = ? AND siswa_id = ?";
    $cek_stmt = $conn->prepare($cek_sql);
    $cek_stmt->bind_param("ii", $sesi_id, $siswa_id);
    $cek_stmt->execute();
    $cek_result = $cek_stmt->get_result();
    
    if (!isset($error) && $cek_result->num_rows === 0) {
        // If the sesi has geo settings, verify submitted location is within radius
        $geo_ok = true;
        $geo_stmt = $conn->prepare("SELECT latitude, longitude, geo_radius_m FROM sesi_presensi WHERE id = ? LIMIT 1");
        if ($geo_stmt) {
            $geo_stmt->bind_param('i', $sesi_id);
            $geo_stmt->execute();
            $gres = $geo_stmt->get_result();
            if ($gres && $grow = $gres->fetch_assoc()) {
                $s_lat = $grow['latitude'];
                $s_lon = $grow['longitude'];
                $s_rad = $grow['geo_radius_m'];
                if ($s_lat !== null && $s_lon !== null && $s_rad !== null) {
                    // require submitted coords
                        if ($submitted_lat === null || $submitted_lon === null) {
                            $geo_ok = false;
                            $error = "Presensi ini memerlukan lokasi Anda. Mohon aktifkan geolocation pada browser dan coba lagi.";
                        } else {
                            $dist = haversineMeters($s_lat, $s_lon, $submitted_lat, $submitted_lon);
                            if ($dist > intval($s_rad)) {
                                // Jika di luar radius, ubah status otomatis menjadi 'alpha' dan lanjutkan
                                $status = 'alpha';
                                // catat informasi jarak untuk ditampilkan ke user nanti
                                $outside_note = "Anda berada di luar area presensi (jarak " . round($dist) . " m) — status diubah menjadi 'alpha'.";
                            }
                        }
                }
            }
            $geo_stmt->close();
        }

        if (!isset($error) && $geo_ok) {
        $sql = "INSERT INTO presensi_siswa (sesi_id, siswa_id, status, waktu_presensi) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $sesi_id, $siswa_id, $status);
        $stmt->execute();
        
        // Buat notifikasi jika tidak hadir
        if ($status != 'hadir') {
            buatNotifikasiAbsen($siswa_id, $sesi_id, $status);
        }
        
        $success = "Presensi berhasil!";
        if (isset($outside_note)) {
            $success .= " -- " . $outside_note;
        }
        }
    } else {
        $error = "Anda sudah melakukan presensi untuk sesi ini.";
    }
}

// Handle presensi guru (oleh ketua kelas)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['presensi_guru'])) {
    $sesi_id = $_POST['sesi_id'];
    $guru_id = $_POST['guru_id'];
    $status = $_POST['status_guru'];
    // Cek status sesi: hanya izinkan presensi guru jika sesi masih 'aktif'
    $sesi_check = $conn->prepare("SELECT status FROM sesi_presensi WHERE id = ? LIMIT 1");
    if ($sesi_check) {
        $sesi_check->bind_param("i", $sesi_id);
        $sesi_check->execute();
        $sesi_res = $sesi_check->get_result();
        if ($sesi_res && $sesi_row = $sesi_res->fetch_assoc()) {
            if ($sesi_row['status'] !== 'aktif') {
                $error = "Sesi presensi sudah selesai. Anda tidak dapat melakukan presensi guru.";
                $sesi_check->close();
            }
        } else {
            $error = "Sesi presensi tidak ditemukan.";
            $sesi_check->close();
        }
    }
    
    // Cek apakah sudah presensi
    $cek_sql = "SELECT * FROM presensi_guru WHERE sesi_id = ? AND guru_id = ?";
    $cek_stmt = $conn->prepare($cek_sql);
    $cek_stmt->bind_param("ii", $sesi_id, $guru_id);
    $cek_stmt->execute();
    $cek_result = $cek_stmt->get_result();
    
    if (!isset($error) && $cek_result->num_rows === 0) {
        $sql = "INSERT INTO presensi_guru (sesi_id, guru_id, status) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $sesi_id, $guru_id, $status);
        $stmt->execute();
        $success_guru = "Presensi guru berhasil!";
    }
}

// Get data kelas siswa
$kelas_siswa = $conn->query("SELECT k.*, sk.is_ketua FROM kelas k 
                            JOIN siswa_kelas sk ON k.id = sk.kelas_id 
                            WHERE sk.siswa_id = $siswa_id");

// Fungsi buat notifikasi
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f8f9fa; }
        .header { background: #17a2b8; color: white; padding: 15px; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .section { margin-bottom: 30px; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .card { background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .card h3 { margin-top: 0; color: #17a2b8; }
        .card p { margin: 8px 0; color: #666; }
        .btn { display: inline-block; padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #138496; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 10% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; }
        .form-group { margin-bottom: 15px; }
        select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .sesi-list { margin-top: 15px; }
        .sesi-item { border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard Siswa</h1>
        <p>Selamat datang, <?php echo $_SESSION['nama_lengkap']; ?> | 
           <a href="../logout.php" style="color: white;">Logout</a></p>
    </div>
    
    <div class="container">
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($success_guru)): ?>
            <div class="success"><?php echo $success_guru; ?></div>
        <?php endif; ?>

        <!-- Daftar Kelas Siswa -->
        <div class="section">
            <h2>Kelas Saya</h2>
            <div class="card-container">
                <?php while ($kelas = $kelas_siswa->fetch_assoc()): ?>
                <?php
                    $kelas_id = $kelas['id'];

                    // For each class, fetch mata_pelajaran separately so we can render one card per subject
                    $mp_stmt = $conn->prepare("SELECT DISTINCT mata_pelajaran FROM guru_kelas WHERE kelas_id = ? ORDER BY mata_pelajaran ASC");
                    $mp_stmt->bind_param("i", $kelas_id);
                    $mp_stmt->execute();
                    $mp_result = $mp_stmt->get_result();

                    // If no subjects found, still render a single card for the class
                    if ($mp_result->num_rows === 0) {
                        // compute attendance for whole class (no subject filter)
                        $total_sessions = 0;
                        $totStmt = $conn->prepare("SELECT COUNT(*) as total FROM sesi_presensi WHERE kelas_id = ?");
                        if ($totStmt) {
                            $totStmt->bind_param("i", $kelas_id);
                            $totStmt->execute();
                            $totRes = $totStmt->get_result();
                            if ($totRes && $r = $totRes->fetch_assoc()) {
                                $total_sessions = (int)$r['total'];
                            }
                            $totStmt->close();
                        }
                        // hadir count for this student in this kelas (no subject filter)
                        $hadir_count = 0;
                        $hadirStmt = $conn->prepare("SELECT COUNT(*) as hadir FROM presensi_siswa ps JOIN sesi_presensi sp ON ps.sesi_id = sp.id WHERE ps.siswa_id = ? AND sp.kelas_id = ? AND ps.status = 'hadir'");
                        if ($hadirStmt) {
                            $hadirStmt->bind_param("ii", $siswa_id, $kelas_id);
                            $hadirStmt->execute();
                            $hadirRes = $hadirStmt->get_result();
                            if ($hadirRes && $rr = $hadirRes->fetch_assoc()) {
                                $hadir_count = (int)$rr['hadir'];
                            }
                            $hadirStmt->close();
                        }
                        $attendance_pct = null;
                        if ($total_sessions > 0) {
                            $attendance_pct = round(($hadir_count / $total_sessions) * 100, 1);
                        }
                ?>
                <div class="card">
                    <h3><?php echo $kelas['nama_kelas']; ?></h3>
                    <p><strong>Tingkat:</strong> <?php echo $kelas['tingkat']; ?></p>
                    <p><strong>Status:</strong> <?php echo $kelas['is_ketua'] ? 'Ketua Kelas' : 'Siswa'; ?></p>
                    <?php if ($attendance_pct !== null): ?>
                        <p><strong>Persentase Kehadiran:</strong> <?php echo $attendance_pct; ?>%</p>
                        <div style="background:#e9ecef; width:100%; height:10px; border-radius:5px; overflow:hidden;">
                            <div style="width:<?php echo $attendance_pct; ?>%; height:100%; background:#17a2b8;"></div>
                        </div>
                    <?php else: ?>
                        <p><em>Belum ada sesi untuk kelas ini.</em></p>
                    <?php endif; ?>
                    <button class="btn" onclick="openModal(<?php echo $kelas['id']; ?>, '<?php echo addslashes($kelas['nama_kelas']); ?>', <?php echo $kelas['is_ketua']; ?>)">
                        Lihat Presensi
                    </button>
                </div>
                <?php
                    } else {
                        while ($mp = $mp_result->fetch_assoc()) {
                            $mata_pelajaran = $mp['mata_pelajaran'];
                            // compute attendance for this specific mata_pelajaran
                            $total_sessions_sub = 0;
                            $totStmtSub = $conn->prepare("SELECT COUNT(*) as total FROM sesi_presensi WHERE kelas_id = ? AND mata_pelajaran = ?");
                            if ($totStmtSub) {
                                $totStmtSub->bind_param("is", $kelas_id, $mata_pelajaran);
                                $totStmtSub->execute();
                                $totResSub = $totStmtSub->get_result();
                                if ($totResSub && $rsub = $totResSub->fetch_assoc()) {
                                    $total_sessions_sub = (int)$rsub['total'];
                                }
                                $totStmtSub->close();
                            }
                            $hadir_count_sub = 0;
                            $hadirStmtSub = $conn->prepare("SELECT COUNT(*) as hadir FROM presensi_siswa ps JOIN sesi_presensi sp ON ps.sesi_id = sp.id WHERE ps.siswa_id = ? AND sp.kelas_id = ? AND sp.mata_pelajaran = ? AND ps.status = 'hadir'");
                            if ($hadirStmtSub) {
                                $hadirStmtSub->bind_param("iis", $siswa_id, $kelas_id, $mata_pelajaran);
                                $hadirStmtSub->execute();
                                $hadirResSub = $hadirStmtSub->get_result();
                                if ($hadirResSub && $rrsub = $hadirResSub->fetch_assoc()) {
                                    $hadir_count_sub = (int)$rrsub['hadir'];
                                }
                                $hadirStmtSub->close();
                            }
                            $attendance_pct_sub = null;
                            if ($total_sessions_sub > 0) {
                                $attendance_pct_sub = round(($hadir_count_sub / $total_sessions_sub) * 100, 1);
                            }
                ?>
                <div class="card">
                    <h3><?php echo $kelas['nama_kelas']; ?> - <?php echo htmlspecialchars($mata_pelajaran); ?></h3>
                    <p><strong>Tingkat:</strong> <?php echo $kelas['tingkat']; ?></p>
                    <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($mata_pelajaran); ?></p>
                    <p><strong>Status:</strong> <?php echo $kelas['is_ketua'] ? 'Ketua Kelas' : 'Siswa'; ?></p>
                    <?php if ($attendance_pct_sub !== null): ?>
                        <p><strong>Persentase Kehadiran:</strong> <?php echo $attendance_pct_sub; ?>%</p>
                        <div style="background:#e9ecef; width:100%; height:10px; border-radius:5px; overflow:hidden;">
                            <div style="width:<?php echo $attendance_pct_sub; ?>%; height:100%; background:#17a2b8;"></div>
                        </div>
                    <?php else: ?>
                        <p><em>Belum ada sesi untuk mata pelajaran ini.</em></p>
                    <?php endif; ?>
                    <br>    
                    <button class="btn" onclick="openModal(<?php echo $kelas['id']; ?>, '<?php echo addslashes($kelas['nama_kelas']); ?>', <?php echo $kelas['is_ketua']; ?>, '<?php echo addslashes($mata_pelajaran); ?>')">
                        Lihat Presensi
                    </button>
                </div>
                <?php
                        }
                    }
                    $mp_stmt->close();
                ?>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Modal untuk presensi -->
    <div id="presensiModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Presensi Kelas</h2>
            <div id="modalContent">
                <!-- Konten akan diisi oleh JavaScript -->
            </div>
            <button class="btn" style="background: #6c757d; margin-top: 15px;" onclick="closeModal()">Tutup</button>
        </div>
    </div>

    <script>
        function openModal(kelasId, namaKelas, isKetua, mataPelajaran) {
            document.getElementById('modalTitle').textContent = 'Presensi - ' + namaKelas;

            // Load sesi presensi via AJAX
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById('modalContent').innerHTML = xhr.responseText;
                }
            };
            xhr.open('POST', 'get_sesi_presensi.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            var data = 'kelas_id=' + encodeURIComponent(kelasId) + '&is_ketua=' + encodeURIComponent(isKetua);
            if (typeof mataPelajaran !== 'undefined' && mataPelajaran !== null && mataPelajaran !== '') {
                data += '&mata_pelajaran=' + encodeURIComponent(mataPelajaran);
            }
            xhr.send(data);

            document.getElementById('presensiModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('presensiModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('presensiModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        function presensiSiswa(sesiId) {
            var status = document.getElementById('status_' + sesiId).value;
            if (!status) {
                alert('Pilih status presensi!');
                return;
            }
            // Try to get geolocation and include it in the POST
            function submitWithCoords(lat, lon) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                var inputs = [
                    {name: 'sesi_id', value: sesiId},
                    {name: 'status', value: status},
                    {name: 'presensi', value: '1'},
                    {name: 'latitude', value: lat},
                    {name: 'longitude', value: lon}
                ];
                inputs.forEach(function(i) {
                    var el = document.createElement('input');
                    el.type = 'hidden';
                    el.name = i.name;
                    el.value = i.value;
                    form.appendChild(el);
                });
                document.body.appendChild(form);
                form.submit();
            }

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    submitWithCoords(position.coords.latitude, position.coords.longitude);
                }, function(err) {
                    // If user denies or error, ask whether to submit without coords
                    if (confirm('Gagal mendapatkan lokasi (' + err.message + '). Kirim presensi tanpa lokasi?')) {
                        submitWithCoords('', '');
                    }
                }, { enableHighAccuracy: true, timeout: 8000 });
            } else {
                if (confirm('Browser Anda tidak mendukung geolocation. Kirim presensi tanpa lokasi?')) {
                    submitWithCoords('', '');
                }
            }
        }
        
        function presensiGuru(sesiId, guruId) {
            var status = document.getElementById('status_guru_' + sesiId).value;
            if (!status) {
                alert('Pilih status presensi guru!');
                return;
            }
            
            var form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            var inputs = [
                {name: 'sesi_id', value: sesiId},
                {name: 'guru_id', value: guruId},
                {name: 'status_guru', value: status},
                {name: 'presensi_guru', value: '1'}
            ];
            inputs.forEach(function(i) {
                var el = document.createElement('input');
                el.type = 'hidden';
                el.name = i.name;
                el.value = i.value;
                form.appendChild(el);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>