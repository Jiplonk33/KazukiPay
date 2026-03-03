<?php
// File: hash.php
// Utility sederhana untuk generate & verify password hash (BCRYPT)

$result_hash = "";
$verify_status = "";
$verify_message = "";

// 1. LOGIKA GENERATE HASH
if (isset($_POST['generate'])) {
    $password = $_POST['password_gen'];
    if (!empty($password)) {
        // Gunakan PASSWORD_BCRYPT sesuai standar proyek ini
        $result_hash = password_hash($password, PASSWORD_BCRYPT);
    }
}

// 2. LOGIKA VERIFIKASI HASH
if (isset($_POST['verify'])) {
    $password = $_POST['password_ver'];
    $hash = $_POST['hash_ver'];
    
    if (!empty($password) && !empty($hash)) {
        if (password_verify($password, $hash)) {
            $verify_status = "success";
            $verify_message = "✅ COCOK! Password sesuai dengan Hash tersebut.";
        } else {
            $verify_status = "error";
            $verify_message = "❌ TIDAK COCOK! Password salah atau Hash berbeda.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Tool - YoGateway</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center py-10 px-4">

    <div class="max-w-4xl w-full grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="bg-white border border-gray-200 rounded-2xl shadow-lg p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600">
                    <iconify-icon icon="mdi:key-plus" class="text-2xl"></iconify-icon>
                </div>
                <h2 class="text-xl font-bold text-gray-800">Generate Hash</h2>
            </div>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password Plain Text</label>
                    <input type="text" name="password_gen" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Masukkan password..." required>
                </div>
                <button type="submit" name="generate" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition">
                    Buat Hash
                </button>
            </form>

            <?php if ($result_hash): ?>
            <div class="mt-6">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Hasil (BCRYPT)</label>
                <div class="relative">
                    <textarea id="hashResult" class="w-full bg-slate-900 text-green-400 font-mono text-sm p-3 rounded-lg break-all h-24" readonly><?php echo $result_hash; ?></textarea>
                    <button onclick="copyHash()" class="absolute top-2 right-2 bg-white/10 hover:bg-white/20 text-white px-2 py-1 rounded text-xs">
                        Copy
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-2">Copy kode di atas dan paste ke kolom <code>password_hash</code> di database (tabel users/admins).</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-lg p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600">
                    <iconify-icon icon="mdi:shield-check" class="text-2xl"></iconify-icon>
                </div>
                <h2 class="text-xl font-bold text-gray-800">Verify Hash</h2>
            </div>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password Plain Text</label>
                    <input type="text" name="password_ver" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:outline-none" placeholder="Cth: 123456" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hash dari Database</label>
                    <input type="text" name="hash_ver" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:outline-none font-mono text-sm" placeholder="$2y$10$..." required>
                </div>
                <button type="submit" name="verify" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition">
                    Cek Kecocokan
                </button>
            </form>

            <?php if ($verify_message): ?>
            <div class="mt-6 p-4 rounded-lg <?php echo ($verify_status == 'success') ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                <p class="font-bold flex items-center gap-2">
                    <?php echo $verify_message; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        function copyHash() {
            var copyText = document.getElementById("hashResult");
            copyText.select();
            copyText.setSelectionRange(0, 99999); 
            navigator.clipboard.writeText(copyText.value);
            alert("Hash berhasil disalin!");
        }
    </script>
</body>
</html>