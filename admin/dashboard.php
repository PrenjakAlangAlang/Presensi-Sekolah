<?php
// admin/dashboard.php
include '../config.php';
include '../functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nama_lengkap = $_POST['nama_lengkap'];
        $role = $_POST['role'];
        
        $sql = "INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $username, $password, $nama_lengkap, $role);
        $stmt->execute();
    }
    
    if (isset($_POST['tambah_kelas'])) {
        $nama_kelas = $_POST['nama_kelas'];
        $tingkat = $_POST['tingkat'];
        
        $sql = "INSERT INTO kelas (nama_kelas, tingkat) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $nama_kelas, $tingkat);
        $stmt->execute();
    }
    
    if (isset($_POST['tambah_guru_kelas'])) {
        $guru_id = $_POST['guru_id'];
        $kelas_id = $_POST['kelas_id'];
        $mata_pelajaran = $_POST['mata_pelajaran'];
        
        $sql = "INSERT INTO guru_kelas (guru_id, kelas_id, mata_pelajaran) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $guru_id, $kelas_id, $mata_pelajaran);
        $stmt->execute();
    }
    
    if (isset($_POST['tambah_siswa_kelas'])) {
        $siswa_id = $_POST['siswa_id'];
        $kelas_id = $_POST['kelas_id'];
        $is_ketua = isset($_POST['is_ketua']) ? 1 : 0;
        
        $sql = "INSERT INTO siswa_kelas (siswa_id, kelas_id, is_ketua) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $siswa_id, $kelas_id, $is_ketua);
        $stmt->execute();
    }
    
    if (isset($_POST['tambah_orangtua'])) {
        $orangtua_id = $_POST['orangtua_id'];
        $siswa_id = $_POST['siswa_id'];
        
        $sql = "INSERT INTO orangtua_siswa (orangtua_id, siswa_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $orangtua_id, $siswa_id);
        $stmt->execute();
    }
}

// Get data
$users = $conn->query("SELECT * FROM users");
$kelas = $conn->query("SELECT * FROM kelas");
$guru = $conn->query("SELECT * FROM users WHERE role = 'guru'");
$siswa = $conn->query("SELECT * FROM users WHERE role = 'siswa'");
$orangtua = $conn->query("SELECT * FROM users WHERE role = 'orangtua'");

// Get relational data
$guru_kelas = $conn->query("SELECT gk.*, u.nama_lengkap as nama_guru, k.nama_kelas 
                           FROM guru_kelas gk 
                           JOIN users u ON gk.guru_id = u.id 
                           JOIN kelas k ON gk.kelas_id = k.id");
$siswa_kelas = $conn->query("SELECT sk.*, u.nama_lengkap as nama_siswa, k.nama_kelas 
                            FROM siswa_kelas sk 
                            JOIN users u ON sk.siswa_id = u.id 
                            JOIN kelas k ON sk.kelas_id = k.id");
$orangtua_siswa = $conn->query("SELECT os.*, o.nama_lengkap as nama_orangtua, s.nama_lengkap as nama_siswa 
                               FROM orangtua_siswa os 
                               JOIN users o ON os.orangtua_id = o.id 
                               JOIN users s ON os.siswa_id = s.id");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background: #343a40; color: white; padding: 15px; }
        .container { padding: 20px; }
        .section { margin-bottom: 30px; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .form-group { margin-bottom: 15px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        button { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        .tab-container { margin-bottom: 20px; }
        .tab { display: inline-block; padding: 10px 20px; background: #f8f9fa; cursor: pointer; border: 1px solid #ddd; }
        .tab.active { background: #007bff; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard Admin</h1>
        <p>Selamat datang, <?php echo $_SESSION['nama_lengkap']; ?> | 
           <a href="../logout.php" style="color: white;">Logout</a></p>
    </div>
    
    <div class="container">
        <div class="tab-container">
            <div class="tab active" onclick="showTab('users')">Manajemen User</div>
            <div class="tab" onclick="showTab('kelas')">Manajemen Kelas</div>
            <div class="tab" onclick="showTab('guru-kelas')">Guru & Kelas</div>
            <div class="tab" onclick="showTab('siswa-kelas')">Siswa & Kelas</div>
            <div class="tab" onclick="showTab('orangtua')">Orang Tua & Siswa</div>
        </div>

        <!-- Tab Manajemen User -->
        <div id="users" class="tab-content active">
            <div class="section">
                <h2>Tambah User</h2>
                <form method="POST">
                    <div class="form-group">
                        <input type="text" name="username" placeholder="Username" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="nama_lengkap" placeholder="Nama Lengkap" required>
                    </div>
                    <div class="form-group">
                        <select name="role" required>
                            <option value="">Pilih Role</option>
                            <option value="admin">Admin</option>
                            <option value="guru">Guru</option>
                            <option value="siswa">Siswa</option>
                            <option value="orangtua">Orang Tua</option>
                        </select>
                    </div>
                    <button type="submit" name="tambah_user">Tambah User</button>
                </form>
            </div>
            
            <div class="section">
                <h2>Daftar User</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['nama_lengkap']; ?></td>
                            <td><?php echo $user['role']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Manajemen Kelas -->
        <div id="kelas" class="tab-content">
            <div class="section">
                <h2>Tambah Kelas</h2>
                <form method="POST">
                    <div class="form-group">
                        <input type="text" name="nama_kelas" placeholder="Nama Kelas" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="tingkat" placeholder="Tingkat (e.g., X, XI, XII)" required>
                    </div>
                    <button type="submit" name="tambah_kelas">Tambah Kelas</button>
                </form>
            </div>
            
            <div class="section">
                <h2>Daftar Kelas</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Kelas</th>
                            <th>Tingkat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($kelas_item = $kelas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $kelas_item['id']; ?></td>
                            <td><?php echo $kelas_item['nama_kelas']; ?></td>
                            <td><?php echo $kelas_item['tingkat']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Guru & Kelas -->
        <div id="guru-kelas" class="tab-content">
            <div class="section">
                <h2>Assign Guru ke Kelas</h2>
                <form method="POST">
                    <div class="form-group">
                        <select name="guru_id" required>
                            <option value="">Pilih Guru</option>
                            <?php while ($guru_item = $guru->fetch_assoc()): ?>
                            <option value="<?php echo $guru_item['id']; ?>">
                                <?php echo $guru_item['nama_lengkap']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="kelas_id" required>
                            <option value="">Pilih Kelas</option>
                            <?php 
                            $kelas->data_seek(0); // Reset pointer
                            while ($kelas_item = $kelas->fetch_assoc()): ?>
                            <option value="<?php echo $kelas_item['id']; ?>">
                                <?php echo $kelas_item['nama_kelas']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="text" name="mata_pelajaran" placeholder="Mata Pelajaran" required>
                    </div>
                    <button type="submit" name="tambah_guru_kelas">Assign Guru</button>
                </form>
            </div>
            
            <div class="section">
                <h2>Daftar Guru & Kelas</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Guru</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($gk = $guru_kelas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $gk['nama_guru']; ?></td>
                            <td><?php echo $gk['nama_kelas']; ?></td>
                            <td><?php echo $gk['mata_pelajaran']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Siswa & Kelas -->
        <div id="siswa-kelas" class="tab-content">
            <div class="section">
                <h2>Assign Siswa ke Kelas</h2>
                <form method="POST">
                    <div class="form-group">
                        <select name="siswa_id" required>
                            <option value="">Pilih Siswa</option>
                            <?php while ($siswa_item = $siswa->fetch_assoc()): ?>
                            <option value="<?php echo $siswa_item['id']; ?>">
                                <?php echo $siswa_item['nama_lengkap']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="kelas_id" required>
                            <option value="">Pilih Kelas</option>
                            <?php 
                            $kelas->data_seek(0); // Reset pointer
                            while ($kelas_item = $kelas->fetch_assoc()): ?>
                            <option value="<?php echo $kelas_item['id']; ?>">
                                <?php echo $kelas_item['nama_kelas']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_ketua"> Jadikan Ketua Kelas
                        </label>
                    </div>
                    <button type="submit" name="tambah_siswa_kelas">Assign Siswa</button>
                </form>
            </div>
            
            <div class="section">
                <h2>Daftar Siswa & Kelas</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Ketua Kelas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sk = $siswa_kelas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $sk['nama_siswa']; ?></td>
                            <td><?php echo $sk['nama_kelas']; ?></td>
                            <td><?php echo $sk['is_ketua'] ? 'Ya' : 'Tidak'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Orang Tua & Siswa -->
        <div id="orangtua" class="tab-content">
            <div class="section">
                <h2>Hubungkan Orang Tua dengan Siswa</h2>
                <form method="POST">
                    <div class="form-group">
                        <select name="orangtua_id" required>
                            <option value="">Pilih Orang Tua</option>
                            <?php while ($ortu_item = $orangtua->fetch_assoc()): ?>
                            <option value="<?php echo $ortu_item['id']; ?>">
                                <?php echo $ortu_item['nama_lengkap']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="siswa_id" required>
                            <option value="">Pilih Siswa</option>
                            <?php 
                            $siswa->data_seek(0); // Reset pointer
                            while ($siswa_item = $siswa->fetch_assoc()): ?>
                            <option value="<?php echo $siswa_item['id']; ?>">
                                <?php echo $siswa_item['nama_lengkap']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" name="tambah_orangtua">Hubungkan</button>
                </form>
            </div>
            
            <div class="section">
                <h2>Daftar Orang Tua & Siswa</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Orang Tua</th>
                            <th>Siswa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($os = $orangtua_siswa->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $os['nama_orangtua']; ?></td>
                            <td><?php echo $os['nama_siswa']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show selected tab content and activate tab
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>