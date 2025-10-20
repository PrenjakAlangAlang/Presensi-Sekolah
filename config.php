<?php
// config.php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'presensi_sekolah';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

session_start();
?>