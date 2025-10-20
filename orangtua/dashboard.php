<?php
// orangtua/dashboard.php
include '../config.php';
include '../functions.php';

if (!isLoggedIn() || !isOrangTua()) {
    redirect('../login.php');
}

$orangtua_id = $_SESSION['user_id'];

// Get anak-anak dari orang tua ini
$anak_sql = "SELECT u.* FROM users u 
            JOIN orangtua_siswa os ON u.id = os.siswa_id 
            WHERE os.orangtua_id = $orangtua_id";
$anak_result = $conn->query($anak_sql);

// Collect anak rows to reuse (so we can query presensi for only these children)
$anak_rows = [];
if ($anak_result) {
    while ($r = $anak_result->fetch_assoc()) {
        $anak_rows[] = $r;
    }
}

// Build presensi list for these children (recent attendance notifications)
$presensi_result = null;
if (count($anak_rows) > 0) {
    $ids = implode(',', array_map('intval', array_column($anak_rows, 'id')));
    $presensi_sql = "SELECT ps.*, sp.mata_pelajaran, k.nama_kelas, sp.tanggal as sesi_tanggal 
                     FROM presensi_siswa ps 
                     JOIN sesi_presensi sp ON ps.sesi_id = sp.id 
                     JOIN kelas k ON sp.kelas_id = k.id 
                     WHERE ps.siswa_id IN ($ids) 
                     ORDER BY ps.waktu_presensi DESC 
                     LIMIT 200";
    $presensi_result = $conn->query($presensi_sql);
}

// Get notifikasi absen
$notifikasi_sql = "SELECT na.*, u.nama_lengkap as nama_siswa, sp.mata_pelajaran, k.nama_kelas 
                  FROM notifikasi_absen na 
                  JOIN users u ON na.siswa_id = u.id 
                  JOIN sesi_presensi sp ON na.sesi_id = sp.id 
                  JOIN kelas k ON sp.kelas_id = k.id 
                  WHERE na.orangtua_id = $orangtua_id 
                  ORDER BY na.created_at DESC";
$notifikasi_result = $conn->query($notifikasi_sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Orang Tua</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background: #ffc107; color: black; padding: 15px; }
        .container { padding: 20px; }
        .section { margin-bottom: 30px; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .notif-unread { background-color: #fff3cd; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard Orang Tua</h1>
        <p>Selamat datang, <?php echo $_SESSION['nama_lengkap']; ?> | 
           <a href="../logout.php" style="color: black;">Logout</a></p>
    </div>
    
    <div class="container">
        <!-- Daftar Anak -->
        <div class="section">
            <h2>Daftar Anak</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                    </tr>
                </thead>
                <tbody>
                            <?php foreach ($anak_rows as $anak): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($anak['nama_lengkap']); ?></td>
                                <td><?php echo htmlspecialchars($anak['username']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Notifikasi Kehadiran (hanya anak dari orang tua ini) -->
        <div class="section">
            <h2>Notifikasi Kehadiran Anak</h2>
            <?php if ($presensi_result && $presensi_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Sesi</th>
                            <th>Anak</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Status</th>
                            <th>Waktu Presensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($p = $presensi_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['sesi_tanggal']); ?></td>
                            <td><?php
                                // find nama from our anak_rows
                                $nama = '';
                                foreach ($anak_rows as $a) {
                                    if ($a['id'] == $p['siswa_id']) { $nama = $a['nama_lengkap']; break; }
                                }
                                echo htmlspecialchars($nama);
                            ?></td>
                            <td><?php echo htmlspecialchars($p['nama_kelas']); ?></td>
                            <td><?php echo htmlspecialchars($p['mata_pelajaran']); ?></td>
                            <td><?php echo htmlspecialchars($p['status']); ?></td>
                            <td><?php echo htmlspecialchars($p['waktu_presensi']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Tidak ada catatan kehadiran untuk anak Anda.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>