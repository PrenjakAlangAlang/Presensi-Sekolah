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
    // Determine a compatible value for the `status` column to avoid data truncation
    // When a teacher closes a session we mark it as 'selesai'
    $desired = 'selesai';
    $statusToSet = $desired;

    // Try to read enum values for the status column (if any)
    $enumVals = [];
    $colRes = $conn->query("SHOW COLUMNS FROM `sesi_presensi` LIKE 'status'");
    if ($colRes && $col = $colRes->fetch_assoc()) {
        $type = $col['Type']; // e.g. enum('aktif','ditutup') or varchar(20)
        if (strpos($type, "enum(") === 0) {
            // parse enum values
            $vals = substr($type, 5, -1); // remove "enum(" and ")"
            // split by comma and trim quotes
            $parts = explode(',', $vals);
            foreach ($parts as $p) {
                $enumVals[] = trim($p, " '\"");
            }
        }
    }

    $isNumericType = false;
    if (!empty($enumVals)) {
        if (in_array($desired, $enumVals)) {
            $statusToSet = $desired;
        } else {
            // try some reasonable alternatives if the DB uses a different wording
            $candidates = ['tutup', 'closed', 'nonaktif', 'inactive', '0', '1'];
            $found = false;
            foreach ($candidates as $c) {
                if (in_array($c, $enumVals)) {
                    $statusToSet = $c;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // fallback: use the first enum value (safer than forcing an invalid value)
                $statusToSet = $enumVals[0];
            }
        }
    }

    // If column type is numeric (int, tinyint, etc.), set numeric value and mark as numeric bind
    if (isset($type) && preg_match('/int|decimal|float|double/i', $type)) {
        $isNumericType = true;
        // Use 0 for closed; if your schema uses different mapping adjust here
        $statusToSet = 0;
    }

    // Determine bind types dynamically
    if ($isNumericType) {
        $statusBind = 'i';
    } else {
        $statusBind = 's';
    }

    if ($guru_id) {
        $sql = "UPDATE sesi_presensi SET status = ? WHERE id = ? AND guru_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) return false;
        $bindTypes = $statusBind . 'ii';
        $stmt->bind_param($bindTypes, $statusToSet, $sesi_id, $guru_id);
    } else {
        $sql = "UPDATE sesi_presensi SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) return false;
        $bindTypes = $statusBind . 'i';
        $stmt->bind_param($bindTypes, $statusToSet, $sesi_id);
    }

    $ok = $stmt->execute();

    // If update succeeded and we set the session to closed, notify parents of students
    // who did NOT perform presensi for this sesi.
    if ($ok) {
        // Determine the kelas_id for this sesi
        $k_sql = $conn->prepare("SELECT kelas_id FROM sesi_presensi WHERE id = ? LIMIT 1");
        if ($k_sql) {
            $k_sql->bind_param('i', $sesi_id);
            $k_sql->execute();
            $k_res = $k_sql->get_result();
            if ($k_res && $k_row = $k_res->fetch_assoc()) {
                $kelas_id = $k_row['kelas_id'];

                // Get all siswa in the kelas
                $s_sql = $conn->prepare("SELECT siswa_id FROM siswa_kelas WHERE kelas_id = ?");
                if ($s_sql) {
                    $s_sql->bind_param('i', $kelas_id);
                    $s_sql->execute();
                    $s_res = $s_sql->get_result();
                    while ($s_row = $s_res->fetch_assoc()) {
                        $siswa_id = $s_row['siswa_id'];

                        // Check if the student has a presensi record for this sesi
                        $p_check = $conn->prepare("SELECT 1 FROM presensi_siswa WHERE sesi_id = ? AND siswa_id = ? LIMIT 1");
                        if ($p_check) {
                            $p_check->bind_param('ii', $sesi_id, $siswa_id);
                            $p_check->execute();
                            $p_check_res = $p_check->get_result();
                            $has_presensi = ($p_check_res && $p_check_res->num_rows > 0);
                            $p_check->close();
                        } else {
                            $has_presensi = true; // be conservative if check fails
                        }

                        if (!$has_presensi) {
                            // Get parents for this student
                            $o_sql = $conn->prepare("SELECT orangtua_id FROM orangtua_siswa WHERE siswa_id = ?");
                            if ($o_sql) {
                                $o_sql->bind_param('i', $siswa_id);
                                $o_sql->execute();
                                $o_res = $o_sql->get_result();
                                while ($o_row = $o_res->fetch_assoc()) {
                                    $orangtua_id = $o_row['orangtua_id'];
                                    // Insert notification - status_absen set to 'alpha' for no-presensi
                                    $ins = $conn->prepare("INSERT INTO notifikasi_absen (orangtua_id, siswa_id, sesi_id, status_absen, tanggal) VALUES (?, ?, ?, ?, CURDATE())");
                                    if ($ins) {
                                        $status_absen = 'alpha';
                                        $ins->bind_param('iiis', $orangtua_id, $siswa_id, $sesi_id, $status_absen);
                                        $ins->execute();
                                        $ins->close();
                                    }
                                }
                                $o_sql->close();
                            }
                        }
                    }
                    $s_sql->close();
                }
            }
            $k_sql->close();
        }
    }

    return $ok;
}
?>