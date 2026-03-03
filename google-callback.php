<?php
// File: google-callback.php (Versi Manual Tanpa Composer)

// === PERBAIKAN DI SINI ===
session_name('laravel_session');
session_start();
// === AKHIR PERBAIKAN ===
require_once __DIR__ . '/config/db.php';     // Butuh $pdo
require_once __DIR__ . '/config/settings.php'; // Butuh Kunci API

try {
    // 1. Pastikan kita dapat 'code' dari Google
    if (!isset($_GET['code'])) {
        throw new Exception("Tidak ada kode otorisasi dari Google.");
    }

    // 2. Tukar 'code' dengan 'access token' (Pakai cURL POST)
    $token_url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'code' => $_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Jika Anda online (bukan localhost), HAPUS baris SSL_VERIFYPEER di bawah ini
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_data = json_decode($response, true);
    if (isset($token_data['error'])) {
        throw new Exception("Gagal mendapatkan token: " . $token_data['error_description']);
    }
    $access_token = $token_data['access_token'];


    // 3. Ambil info profil user dari Google (Pakai cURL GET)
    $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch_user = curl_init();
    curl_setopt($ch_user, CURLOPT_URL, $user_info_url);
    curl_setopt($ch_user, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_user, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    // curl_setopt($ch_user, CURLOPT_SSL_VERIFYPEER, false); // Jika perlu
    $user_response = curl_exec($ch_user);
    curl_close($ch_user);

    $user_info = json_decode($user_response, true);
    if (isset($user_info['error'])) {
        throw new Exception("Gagal mendapatkan info user: " . $user_info['error']['message']);
    }

    $google_id = $user_info['id'];
    $email = $user_info['email'];
    $name = $user_info['name'] ?? null; // Ambil nama, bisa jadi null
    $avatar = $user_info['picture'];

    // 4. Logika Database (Cari atau Buat User)
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->execute([$google_id]);
    $user = $stmt->fetch();

    if ($user) {
        // --- KASUS 1: User SUDAH ADA (Login) ---
        $user_id = $user['id'];
        
    } else {
        // --- KASUS 2: User BELUM ADA ---
        $stmt_email = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt_email->execute([$email]);
        $user_by_email = $stmt_email->fetch();
        
        if ($user_by_email) {
            // --- KASUS 2a: Email ada -> Link-kan akun ---
            $sql = "UPDATE users SET google_id = ?, avatar = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$google_id, $avatar, $user_by_email['id']]);
            $user_id = $user_by_email['id'];
        } else {
            // --- KASUS 2b: User baru -> Buat akun baru ---
            
            // === PERBAIKAN: Fallback jika nama Google kosong ===
            $username_base = $name;
            if (empty($username_base)) {
                // Ambil bagian email sebelum @
                $username_base = explode('@', $email)[0];
                // Bersihkan karakter non-alfanumerik
                $username_base = preg_replace('/[^a-zA-Z0-9]/', '', $username_base);
            }
            // === AKHIR PERBAIKAN ===

            $username = $username_base;
            $stmt_check_username = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
            $stmt_check_username->execute([$username]);
            
            // Cek duplikat
            if ($stmt_check_username->fetch()) {
                $username = $username_base . rand(100, 999); // Tambah angka acak
            }

            // Ingat: password_hash dibuat NULL
            $sql = "INSERT INTO users (username, email, google_id, avatar, password_hash) 
                    VALUES (?, ?, ?, ?, NULL)";
            $stmt_insert = $pdo->prepare($sql);
            $stmt_insert->execute([$username, $email, $google_id, $avatar]);
            $user_id = $pdo->lastInsertId();
        }
    }
    
    // 5. Buat Session (Login)
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    // Ambil ulang username untuk session
    $stmt_username = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt_username->execute([$user_id]);
    $_SESSION['username'] = $stmt_username->fetchColumn();

    // 6. Arahkan ke Dashboard
    header('Location: index.php?page=dashboard');
    exit;

} catch (Exception $e) {
    // Jika terjadi error, kembalikan ke halaman login
    header('Location: index.php?page=auth_login&error=google_failed&msg=' . urlencode($e->getMessage()));
    exit;
}
?>