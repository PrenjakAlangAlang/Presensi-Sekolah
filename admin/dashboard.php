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

    // Update handlers for edit actions
    if (isset($_POST['update_user'])) {
        $id = intval($_POST['id']);
        $username = $_POST['username'];
        $nama_lengkap = $_POST['nama_lengkap'];
        $role = $_POST['role'];
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username = ?, password = ?, nama_lengkap = ?, role = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $username, $password, $nama_lengkap, $role, $id);
        } else {
            $sql = "UPDATE users SET username = ?, nama_lengkap = ?, role = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $username, $nama_lengkap, $role, $id);
        }
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }

    if (isset($_POST['update_kelas'])) {
        $id = intval($_POST['id']);
        $nama_kelas = $_POST['nama_kelas'];
        $tingkat = $_POST['tingkat'];
        $sql = "UPDATE kelas SET nama_kelas = ?, tingkat = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $nama_kelas, $tingkat, $id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }

    if (isset($_POST['update_guru_kelas'])) {
        $id = intval($_POST['id']);
        $guru_id = intval($_POST['guru_id']);
        $kelas_id = intval($_POST['kelas_id']);
        $mata_pelajaran = $_POST['mata_pelajaran'];
        $sql = "UPDATE guru_kelas SET guru_id = ?, kelas_id = ?, mata_pelajaran = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisi", $guru_id, $kelas_id, $mata_pelajaran, $id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }

    if (isset($_POST['update_siswa_kelas'])) {
        $id = intval($_POST['id']);
        $siswa_id = intval($_POST['siswa_id']);
        $kelas_id = intval($_POST['kelas_id']);
        $is_ketua = isset($_POST['is_ketua']) ? 1 : 0;
        $sql = "UPDATE siswa_kelas SET siswa_id = ?, kelas_id = ?, is_ketua = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $siswa_id, $kelas_id, $is_ketua, $id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }

    if (isset($_POST['update_orangtua'])) {
        $id = intval($_POST['id']);
        $orangtua_id = intval($_POST['orangtua_id']);
        $siswa_id = intval($_POST['siswa_id']);
        $sql = "UPDATE orangtua_siswa SET orangtua_id = ?, siswa_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $orangtua_id, $siswa_id, $id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    }
}

// If an edit is requested, fetch the record for prefill
$editType = $_GET['type'] ?? null;
$editId = isset($_GET['action']) && $_GET['action'] === 'edit' ? intval($_GET['id'] ?? 0) : 0;
$editItem = null;
if ($editType && $editId) {
    if ($editType === 'users') {
        $s = $conn->prepare("SELECT id, username, nama_lengkap, role FROM users WHERE id = ? LIMIT 1");
        $s->bind_param('i', $editId);
        $s->execute();
        $editItem = $s->get_result()->fetch_assoc();
    } elseif ($editType === 'kelas') {
        $s = $conn->prepare("SELECT id, nama_kelas, tingkat FROM kelas WHERE id = ? LIMIT 1");
        $s->bind_param('i', $editId);
        $s->execute();
        $editItem = $s->get_result()->fetch_assoc();
    } elseif ($editType === 'guru-kelas') {
        $s = $conn->prepare("SELECT id, guru_id, kelas_id, mata_pelajaran FROM guru_kelas WHERE id = ? LIMIT 1");
        $s->bind_param('i', $editId);
        $s->execute();
        $editItem = $s->get_result()->fetch_assoc();
    } elseif ($editType === 'siswa-kelas') {
        $s = $conn->prepare("SELECT id, siswa_id, kelas_id, is_ketua FROM siswa_kelas WHERE id = ? LIMIT 1");
        $s->bind_param('i', $editId);
        $s->execute();
        $editItem = $s->get_result()->fetch_assoc();
    } elseif ($editType === 'orangtua') {
        $s = $conn->prepare("SELECT id, orangtua_id, siswa_id FROM orangtua_siswa WHERE id = ? LIMIT 1");
        $s->bind_param('i', $editId);
        $s->execute();
        $editItem = $s->get_result()->fetch_assoc();
    }
}

// Handle delete actions (simple GET-based delete with confirmation on client)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['type']) && isset($_GET['id'])) {
    $delType = $_GET['type'];
    $delId = intval($_GET['id']);
    switch ($delType) {
        case 'users':
            $d = $conn->prepare("DELETE FROM users WHERE id = ?");
            $d->bind_param('i', $delId);
            $d->execute();
            break;
        case 'kelas':
            $d = $conn->prepare("DELETE FROM kelas WHERE id = ?");
            $d->bind_param('i', $delId);
            $d->execute();
            break;
        case 'guru-kelas':
            $d = $conn->prepare("DELETE FROM guru_kelas WHERE id = ?");
            $d->bind_param('i', $delId);
            $d->execute();
            break;
        case 'siswa-kelas':
            $d = $conn->prepare("DELETE FROM siswa_kelas WHERE id = ?");
            $d->bind_param('i', $delId);
            $d->execute();
            break;
        case 'orangtua':
            $d = $conn->prepare("DELETE FROM orangtua_siswa WHERE id = ?");
            $d->bind_param('i', $delId);
            $d->execute();
            break;
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit();
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
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['nama_lengkap']; ?></td>
                            <td><?php echo $user['role']; ?></td>
                            <td>
                                <a href="?action=edit&type=users&id=<?php echo $user['id']; ?>">Edit</a>
                                &nbsp;|&nbsp;
                                <a href="?action=delete&type=users&id=<?php echo $user['id']; ?>" onclick="return confirm('Hapus user ini?');">Hapus</a>
                            </td>
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
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($kelas_item = $kelas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $kelas_item['id']; ?></td>
                            <td><?php echo $kelas_item['nama_kelas']; ?></td>
                            <td><?php echo $kelas_item['tingkat']; ?></td>
                            <td>
                                <a href="?action=edit&type=kelas&id=<?php echo $kelas_item['id']; ?>">Edit</a>
                                &nbsp;|&nbsp;
                                <a href="?action=delete&type=kelas&id=<?php echo $kelas_item['id']; ?>" onclick="return confirm('Hapus kelas ini?');">Hapus</a>
                            </td>
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
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($gk = $guru_kelas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $gk['nama_guru']; ?></td>
                            <td><?php echo $gk['nama_kelas']; ?></td>
                            <td><?php echo $gk['mata_pelajaran']; ?></td>
                            <td>
                                <a href="?action=edit&type=guru-kelas&id=<?php echo $gk['id']; ?>">Edit</a>
                                &nbsp;|&nbsp;
                                <a href="?action=delete&type=guru-kelas&id=<?php echo $gk['id']; ?>" onclick="return confirm('Hapus relasi guru-kelas ini?');">Hapus</a>
                            </td>
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
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sk = $siswa_kelas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $sk['nama_siswa']; ?></td>
                            <td><?php echo $sk['nama_kelas']; ?></td>
                            <td><?php echo $sk['is_ketua'] ? 'Ya' : 'Tidak'; ?></td>
                            <td>
                                <a href="?action=edit&type=siswa-kelas&id=<?php echo $sk['id']; ?>">Edit</a>
                                &nbsp;|&nbsp;
                                <a href="?action=delete&type=siswa-kelas&id=<?php echo $sk['id']; ?>" onclick="return confirm('Hapus relasi siswa-kelas ini?');">Hapus</a>
                            </td>
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
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($os = $orangtua_siswa->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $os['nama_orangtua']; ?></td>
                            <td><?php echo $os['nama_siswa']; ?></td>
                            <td>
                                <a href="?action=edit&type=orangtua&id=<?php echo $os['id']; ?>">Edit</a>
                                &nbsp;|&nbsp;
                                <a href="?action=delete&type=orangtua&id=<?php echo $os['id']; ?>" onclick="return confirm('Hapus relasi orangtua-siswa ini?');">Hapus</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php if ($editItem): ?>
    <div class="container" style="margin-top:20px;">
        <div class="section">
            <h2>Edit <?php echo htmlspecialchars($editType); ?></h2>
            <?php if ($editType === 'users'): ?>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
                <div class="form-group"><input type="text" name="username" value="<?php echo htmlspecialchars($editItem['username']); ?>" required></div>
                <div class="form-group"><input type="password" name="password" placeholder="(Kosongkan jika tidak ingin mengganti)"></div>
                <div class="form-group"><input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($editItem['nama_lengkap']); ?>" required></div>
                <div class="form-group">
                    <select name="role" required>
                        <option value="admin" <?php echo $editItem['role']==='admin'?'selected':''; ?>>Admin</option>
                        <option value="guru" <?php echo $editItem['role']==='guru'?'selected':''; ?>>Guru</option>
                        <option value="siswa" <?php echo $editItem['role']==='siswa'?'selected':''; ?>>Siswa</option>
                        <option value="orangtua" <?php echo $editItem['role']==='orangtua'?'selected':''; ?>>Orang Tua</option>
                    </select>
                </div>
                <button type="submit" name="update_user">Simpan Perubahan</button>
            </form>
            <?php elseif ($editType === 'kelas'): ?>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
                <div class="form-group"><input type="text" name="nama_kelas" value="<?php echo htmlspecialchars($editItem['nama_kelas']); ?>" required></div>
                <div class="form-group"><input type="text" name="tingkat" value="<?php echo htmlspecialchars($editItem['tingkat']); ?>" required></div>
                <button type="submit" name="update_kelas">Simpan Perubahan</button>
            </form>
            <?php elseif ($editType === 'guru-kelas'): ?>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
                <div class="form-group">
                    <select name="guru_id" required>
                        <?php $guru->data_seek(0); while($g = $guru->fetch_assoc()): ?>
                        <option value="<?php echo $g['id']; ?>" <?php echo $g['id']==$editItem['guru_id']?'selected':''; ?>><?php echo $g['nama_lengkap']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <?php $kelas->data_seek(0); ?>
                    <select name="kelas_id" required>
                        <?php while($k = $kelas->fetch_assoc()): ?>
                        <option value="<?php echo $k['id']; ?>" <?php echo $k['id']==$editItem['kelas_id']?'selected':''; ?>><?php echo $k['nama_kelas']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group"><input type="text" name="mata_pelajaran" value="<?php echo htmlspecialchars($editItem['mata_pelajaran']); ?>" required></div>
                <button type="submit" name="update_guru_kelas">Simpan Perubahan</button>
            </form>
            <?php elseif ($editType === 'siswa-kelas'): ?>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
                <div class="form-group">
                    <?php $siswa->data_seek(0); ?>
                    <select name="siswa_id" required>
                        <?php while($s = $siswa->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $s['id']==$editItem['siswa_id']?'selected':''; ?>><?php echo $s['nama_lengkap']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <?php $kelas->data_seek(0); ?>
                    <select name="kelas_id" required>
                        <?php while($k = $kelas->fetch_assoc()): ?>
                        <option value="<?php echo $k['id']; ?>" <?php echo $k['id']==$editItem['kelas_id']?'selected':''; ?>><?php echo $k['nama_kelas']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group"><label><input type="checkbox" name="is_ketua" <?php echo $editItem['is_ketua'] ? 'checked' : ''; ?>> Jadikan Ketua</label></div>
                <button type="submit" name="update_siswa_kelas">Simpan Perubahan</button>
            </form>
            <?php elseif ($editType === 'orangtua'): ?>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
                <div class="form-group">
                    <?php $orangtua->data_seek(0); ?>
                    <select name="orangtua_id" required>
                        <?php while($o = $orangtua->fetch_assoc()): ?>
                        <option value="<?php echo $o['id']; ?>" <?php echo $o['id']==$editItem['orangtua_id']?'selected':''; ?>><?php echo $o['nama_lengkap']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <?php $siswa->data_seek(0); ?>
                    <select name="siswa_id" required>
                        <?php while($s = $siswa->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $s['id']==$editItem['siswa_id']?'selected':''; ?>><?php echo $s['nama_lengkap']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" name="update_orangtua">Simpan Perubahan</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
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