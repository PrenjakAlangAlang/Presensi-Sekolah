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
    
    // Cek apakah sudah presensi
    $cek_sql = "SELECT * FROM presensi_siswa WHERE sesi_id = ? AND siswa_id = ?";
    $cek_stmt = $conn->prepare($cek_sql);
    $cek_stmt->bind_param("ii", $sesi_id, $siswa_id);
    $cek_stmt->execute();
    $cek_result = $cek_stmt->get_result();
    
    if ($cek_result->num_rows === 0) {
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
    } else {
        $error = "Anda sudah melakukan presensi untuk sesi ini.";
    }
}

// Handle presensi guru (oleh ketua kelas)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['presensi_guru'])) {
    $sesi_id = $_POST['sesi_id'];
    $guru_id = $_POST['guru_id'];
    $status = $_POST['status_guru'];
    
    // Cek apakah sudah presensi
    $cek_sql = "SELECT * FROM presensi_guru WHERE sesi_id = ? AND guru_id = ?";
    $cek_stmt = $conn->prepare($cek_sql);
    $cek_stmt->bind_param("ii", $sesi_id, $guru_id);
    $cek_stmt->execute();
    $cek_result = $cek_stmt->get_result();
    
    if ($cek_result->num_rows === 0) {
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
                <div class="card">
                    <h3><?php echo $kelas['nama_kelas']; ?></h3>
                    <p><strong>Tingkat:</strong> <?php echo $kelas['tingkat']; ?></p>
                    <p><strong>Status:</strong> <?php echo $kelas['is_ketua'] ? 'Ketua Kelas' : 'Siswa'; ?></p>
                    <button class="btn" onclick="openModal(<?php echo $kelas['id']; ?>, '<?php echo $kelas['nama_kelas']; ?>', <?php echo $kelas['is_ketua']; ?>)">
                        Lihat Presensi
                    </button>
                </div>
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
        function openModal(kelasId, namaKelas, isKetua) {
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
            xhr.send('kelas_id=' + kelasId + '&is_ketua=' + isKetua);
            
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
            
            var form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            var sesiInput = document.createElement('input');
            sesiInput.name = 'sesi_id';
            sesiInput.value = sesiId;
            form.appendChild(sesiInput);
            
            var statusInput = document.createElement('input');
            statusInput.name = 'status';
            statusInput.value = status;
            form.appendChild(statusInput);
            
            var submitInput = document.createElement('input');
            submitInput.name = 'presensi';
            submitInput.type = 'submit';
            form.appendChild(submitInput);
            
            document.body.appendChild(form);
            form.submit();
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
            
            var sesiInput = document.createElement('input');
            sesiInput.name = 'sesi_id';
            sesiInput.value = sesiId;
            form.appendChild(sesiInput);
            
            var guruInput = document.createElement('input');
            guruInput.name = 'guru_id';
            guruInput.value = guruId;
            form.appendChild(guruInput);
            
            var statusInput = document.createElement('input');
            statusInput.name = 'status_guru';
            statusInput.value = status;
            form.appendChild(statusInput);
            
            var submitInput = document.createElement('input');
            submitInput.name = 'presensi_guru';
            submitInput.type = 'submit';
            form.appendChild(submitInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>