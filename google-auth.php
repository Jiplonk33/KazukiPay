<?php
// File: google-auth.php
session_name('laravel_session');
session_start();

// === PERBAIKAN: Load DB Dulu agar settings.php bisa baca database ===
require_once __DIR__ . '/config/db.php'; 
require_once __DIR__ . '/config/settings.php'; 
// ===================================================================
$params = [
    'response_type' => 'code',
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    
    // === PERBAIKAN DI SINI ===
    'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
    // === AKHIR PERBAIKAN ===
    
    'access_type' => 'offline',
    'prompt' => 'select_account'
];

// === PERBAIKAN DI SINI ===
// Buat URL otentikasi
$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
// === AKHIR PERBAIKAN ===

// Arahkan user ke URL tersebut
header('Location: ' . $auth_url);
exit;
?>