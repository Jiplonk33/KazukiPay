<?php

session_name('laravel_session');


session_start();


header_remove('X-Powered-By'); 


if (!isset($_COOKIE['XSRF-TOKEN'])) {

    $fake_token = bin2hex(random_bytes(16)); 
    
 
    setcookie('XSRF-TOKEN', $fake_token, [
        'expires' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true, 
        'httponly' => false,
        'samesite' => 'Lax'
    ]); 
}
// === AKHIR TAMBAHAN ===

// (Kode Anda selanjutnya...)
require_once __DIR__ . '/config/db.php'; // Selalu butuh koneksi DB // Selalu butuh koneksi DB


$page = $_GET['page'] ?? 'landing';

// Halaman yang bisa diakses publik (tanpa login)
$public_pages = ['landing', 'auth_login', 'auth_register', 'docs_public','privacy_policy', 'terms_of_service']; 

// Daftar semua halaman yang valid di folder /views/
$allowed_pages = [
    'landing', 'auth_login', 'auth_register', 'auth_logout',
    'dashboard', 'projects', 'project_create', 'project_detail', 
    'transactions', 'withdrawals', 'webhook_logs', 'documentation',
    'docs_public', 'privacy_policy', 'terms_of_service'
];

$file_path = __DIR__ . '/views/' . $page . '.php';

// Cek apakah halaman ada di daftar & filenya ada
if (in_array($page, $allowed_pages) && file_exists($file_path)) {
    
    // Cek apakah halaman ini BUTUH LOGIN
    if (!in_array($page, $public_pages)) {
        // Jika halaman BUTUH LOGIN dan user BELUM LOGIN
        if (!isset($_SESSION['user_id'])) {
            // Paksa redirect ke halaman login
            header('Location: index.php?page=auth_login&error=login_required');
            exit;
        }
    }
    
    // Jika aman, tampilkan halamannya
    include $file_path;

}  else {
    // Jika halaman tidak ditemukan, tampilkan 404 publik
    // (Pastikan config/db.php sudah di-include di atas untuk $app_settings)
    include __DIR__ . '/views/404_public.php';
    exit;
}
?>
