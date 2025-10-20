<?php
// guru/dashboard.php
include '../config.php';
include '../functions.php';

if (!isLoggedIn() || !isGuru()) {
    redirect('../login.php');
}

$guru_id = $_SESSION['user_id'];

// Handle buat sesi presensi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_sesi'])) {
    $kelas_id = $_POST['kelas_id'];
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $tanggal = $_POST['tanggal'];
    $waktu_mulai = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];
    $kode_presensi = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
    
    $sql = "INSERT INTO sesi_presensi (guru_id, kelas_id, mata_pelajaran, tanggal, waktu_mulai, waktu_selesai, kode_presensi) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssss", $guru_id, $kelas_id, $mata_pelajaran, $tanggal, $waktu_mulai, $waktu_selesai, $kode_presensi);
    $stmt->execute();
    
    $success = "Sesi presensi berhasil dibuat! Kode: " . $kode_presensi;
}

// Handle tutup sesi presensi by guru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tutup_sesi'])) {
    $sesi_id_close = intval($_POST['sesi_id']);
    if (closeSesi($sesi_id_close, $guru_id)) {
        $success = "Sesi presensi berhasil ditutup.";
    } else {
        $error = "Gagal menutup sesi. Pastikan anda pemilik sesi.";
    }
}

// Get data
$kelas_guru = getKelasByGuru($guru_id);
$sesi_presensi = $conn->query("SELECT sp.*, k.nama_kelas 
                               FROM sesi_presensi sp 
                               JOIN kelas k ON sp.kelas_id = k.id 
                               WHERE sp.guru_id = $guru_id 
                               ORDER BY sp.tanggal DESC, sp.waktu_mulai DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f8f9fa; }
        .header { background: #28a745; color: white; padding: 15px; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .section { margin-bottom: 30px; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .card { background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .card h3 { margin-top: 0; color: #28a745; }
        .card p { margin: 8px 0; color: #666; }
        .btn { display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #218838; }
        .form-group { margin-bottom: 15px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 10% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard Guru</h1>
        <p>Selamat datang, <?php echo $_SESSION['nama_lengkap']; ?> | 
           <a href="../logout.php" style="color: white;">Logout</a></p>
    </div>
    
    <div class="container">
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error" style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
            <?php endif; ?>

        <!-- Daftar Kelas yang Diampu -->
        <div class="section">
            <h2>Kelas yang Diampu</h2>
            <div class="card-container">
                <?php while ($kelas = $kelas_guru->fetch_assoc()): ?>
                <div class="card">
                    <h3><?php echo $kelas['nama_kelas']; ?></h3>
                    <p><strong>Mata Pelajaran:</strong> <?php echo $kelas['mata_pelajaran']; ?></p>
                    <p><strong>Tingkat:</strong> <?php echo $kelas['tingkat']; ?></p>
                    <button class="btn" onclick="openModal(<?php echo $kelas['id']; ?>, '<?php echo $kelas['nama_kelas']; ?>', '<?php echo $kelas['mata_pelajaran']; ?>')">
                        Buka Presensi
                    </button>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- Daftar Sesi Presensi -->
        <div class="section">
            <h2>Riwayat Sesi Presensi</h2>
            <div class="card-container">
                <?php while ($sesi = $sesi_presensi->fetch_assoc()): ?>
                <div class="card">
                    <h3><?php echo $sesi['nama_kelas'] . ' - ' . $sesi['mata_pelajaran']; ?></h3>
                    <p><strong>Tanggal:</strong> <?php echo $sesi['tanggal']; ?></p>
                    <p><strong>Waktu:</strong> <?php echo $sesi['waktu_mulai'] . ' - ' . $sesi['waktu_selesai']; ?></p>
                    <p><strong>Status:</strong> <span style="color: <?php echo $sesi['status'] == 'aktif' ? '#28a745' : '#6c757d'; ?>">
                        <?php echo $sesi['status']; ?>
                    </span></p>
                    <?php if ($sesi['kode_presensi']): ?>
                    <p><strong>Kode:</strong> <?php echo $sesi['kode_presensi']; ?></p>
                    <?php endif; ?>
                    <div style="margin-top:12px;">
                        <button class="btn" onclick="openPresensiModal(<?php echo $sesi['id']; ?>)">Lihat Presensi</button>
                        <?php if ($sesi['status'] == 'aktif'): ?>
                        <form method="POST" style="display:inline-block; margin-left:8px;" onsubmit="return confirm('Tutup sesi ini?');">
                            <input type="hidden" name="sesi_id" value="<?php echo $sesi['id']; ?>">
                            <button type="submit" name="tutup_sesi" class="btn" style="background:#dc3545;">Tutup Sesi</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Modal untuk buat sesi presensi -->
    <div id="presensiModal" class="modal">
        <div class="modal-content">
            <h2>Buat Sesi Presensi</h2>
            <form method="POST" id="presensiForm">
                <input type="hidden" name="kelas_id" id="modal_kelas_id">
                <input type="hidden" name="mata_pelajaran" id="modal_mata_pelajaran">
                
                <div class="form-group">
                    <label>Kelas:</label>
                    <input type="text" id="modal_nama_kelas" readonly>
                </div>
                
                <div class="form-group">
                    <label>Mata Pelajaran:</label>
                    <input type="text" id="modal_mp" readonly>
                </div>
                
                <div class="form-group">
                    <label>Tanggal:</label>
                    <input type="date" name="tanggal" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Waktu Mulai:</label>
                    <input type="time" name="waktu_mulai" required value="<?php echo date('H:i'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Waktu Selesai:</label>
                    <input type="time" name="waktu_selesai" required value="<?php echo date('H:i', strtotime('+1 hour')); ?>">
                </div>
                
                <button type="submit" name="buat_sesi" class="btn">Buat Sesi Presensi</button>
                <button type="button" class="btn" style="background: #6c757d;" onclick="closeModal()">Batal</button>
            </form>
        </div>
    </div>

    <script>
        // Open attendance modal for a sesi
        function openPresensiModal(sesiId) {
            // Fetch attendance list from server
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'get_presensi_siswa.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    // show in a modal
                    var modalHtml = '<div class="modal" id="attModal" style="display:block; position:fixed; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">';
                    modalHtml += '<div style="background:white; max-width:700px; margin:6% auto; padding:20px; border-radius:8px;">';
                    modalHtml += '<h3>Daftar Presensi</h3>';
                    modalHtml += xhr.responseText;
                    modalHtml += '<div style="text-align:right; margin-top:12px;"><button onclick="closeAttModal()" class="btn">Tutup</button></div>';
                    modalHtml += '</div></div>';
                    var div = document.createElement('div');
                    div.id = 'attModalWrapper';
                    div.innerHTML = modalHtml;
                    document.body.appendChild(div);
                }
            };
            xhr.send('sesi_id=' + encodeURIComponent(sesiId));
        }

        function closeAttModal() {
            var w = document.getElementById('attModalWrapper');
            if (w) document.body.removeChild(w);
        }
        function openModal(kelasId, namaKelas, mataPelajaran) {
            document.getElementById('modal_kelas_id').value = kelasId;
            document.getElementById('modal_nama_kelas').value = namaKelas;
            document.getElementById('modal_mata_pelajaran').value = mataPelajaran;
            document.getElementById('modal_mp').value = mataPelajaran;
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
    </script>
</body>
</html>