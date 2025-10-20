<?php
// functions.php
include 'config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function isAdmin() {
    return getUserRole() === 'admin';
}

function isGuru() {
    return getUserRole() === 'guru';
}

function isSiswa() {
    return getUserRole() === 'siswa';
}

function isOrangTua() {
    return getUserRole() === 'orangtua';
}

function getKelasByGuru($guru_id) {
    global $conn;
    $sql = "SELECT k.*, gk.mata_pelajaran 
            FROM kelas k 
            JOIN guru_kelas gk ON k.id = gk.kelas_id 
            WHERE gk.guru_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guru_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getSiswaByKelas($kelas_id) {
    global $conn;
    $sql = "SELECT u.*, sk.is_ketua 
            FROM users u 
            JOIN siswa_kelas sk ON u.id = sk.siswa_id 
            WHERE sk.kelas_id = ? AND u.role = 'siswa'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getKetuaKelas($kelas_id) {
    global $conn;
    $sql = "SELECT u.* 
            FROM users u 
            JOIN siswa_kelas sk ON u.id = sk.siswa_id 
            WHERE sk.kelas_id = ? AND sk.is_ketua = TRUE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Ambil daftar presensi untuk sebuah sesi (join dengan data siswa)
function getPresensiBySesi($sesi_id) {
    global $conn;
    $sql = "SELECT ps.*, u.nama_lengkap, u.username 
            FROM presensi_siswa ps 
            JOIN users u ON ps.siswa_id = u.id 
            WHERE ps.sesi_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sesi_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Tutup sesi presensi (opsional verifikasi guru)
function closeSesi($sesi_id, $guru_id = null) {
    global $conn;
    if ($guru_id) {
        $sql = "UPDATE sesi_presensi SET status = 'ditutup' WHERE id = ? AND guru_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $sesi_id, $guru_id);
    } else {
        $sql = "UPDATE sesi_presensi SET status = 'ditutup' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $sesi_id);
    }
    return $stmt->execute();
}
?>