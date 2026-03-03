
<?php
// ============================================================================
// 1. KONFIGURASI & KONEKSI API
// ============================================================================
$apiKey = '5HAD2KfNrQYDimLMiLfWlGnGShzjdCBgPKcX60eCrghG8cdDMckQwH1b326ohOrDGIwodjIfkvccfa97bVN50niFHHHtCBnrY3Oi'; // <--- GANTI API KEY
$apiUrl = 'https://atlantich2h.com/layanan/price_list';

function fetchAtlanticData($url, $apiKey) {
    $ch = curl_init();
    $params = ['api_key' => $apiKey, 'type' => 'prabayar'];
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Ambil Data
$response = fetchAtlanticData($apiUrl, $apiKey);
$semuaProduk = $response['data'] ?? [];

// ============================================================================
// 2. LOGIKA SMART SORTING & GROUPING (MEMBERSIHKAN DATA)
// ============================================================================

// Struktur Penampung Data
$kategoriUtama = [
    'games'   => [],
    'pulsa'   => [],
    'lainnya' => [] // E-Wallet, PLN, Voucher
];

// Helper untuk membersihkan nama Provider agar tidak duplikat
// Contoh: "Telkomsel Data" & "Telkomsel Promo" -> Jadi "TELKOMSEL"
function cleanProviderName($name) {
    $name = strtoupper($name);
    $removes = [' DATA', ' PAKET', ' REGULER', ' PROMO', ' TRANSFER', ' MASA AKTIF'];
    return trim(str_replace($removes, '', $name));
}

foreach ($semuaProduk as $item) {
    if (empty($item['provider'])) continue;
    if ($item['status'] == 'empty') continue; // Opsi: Skip produk gangguan

    // Deteksi Jenis Kategori Berdasarkan String
    $catRaw = strtolower($item['category']);
    $typeRaw = strtolower($item['type']);
    $provRaw = strtolower($item['provider']);

    $targetGroup = 'lainnya'; // Default

    // Logika Pemisahan
    if (strpos($catRaw, 'game') !== false || strpos($typeRaw, 'game') !== false) {
        $targetGroup = 'games';
    } elseif (strpos($catRaw, 'pulsa') !== false || strpos($catRaw, 'data') !== false || strpos($provRaw, 'telkomsel') !== false || strpos($provRaw, 'indosat') !== false || strpos($provRaw, 'xl') !== false || strpos($provRaw, 'axis') !== false || strpos($provRaw, 'tri') !== false || strpos($provRaw, 'smartfren') !== false) {
        $targetGroup = 'pulsa';
    }

    // Normalisasi Nama Provider (Grouping Card)
    $cleanProvider = cleanProviderName($item['provider']);

    // Setup Induk Provider
    if (!isset($kategoriUtama[$targetGroup][$cleanProvider])) {
        // Fallback Image Generator
        $img = !empty($item['img_url']) ? $item['img_url'] : "https://ui-avatars.com/api/?name=".urlencode($cleanProvider)."&background=1e293b&color=fff&size=200&font-size=0.33";
        
        $kategoriUtama[$targetGroup][$cleanProvider] = [
            'name' => $cleanProvider,
            'image' => $img,
            'variants' => []
        ];
    }

    // Masukkan Item ke Varian
    // Kelompokkan lagi berdasarkan Type/Category sub-nya (misal: "Umum", "Internet Sakti")
    $subKategori = $item['type'] ?? $item['category'];
    $kategoriUtama[$targetGroup][$cleanProvider]['variants'][$subKategori][] = $item;
}

// Urutkan Provider A-Z di setiap kategori utama
foreach ($kategoriUtama as $key => $val) {
    ksort($kategoriUtama[$key]);
}

// Convert ke JSON untuk JS
$jsonData = json_encode($kategoriUtama);
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlantic Shop - Top Up Termurah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-dark: #0f172a;    /* 60% */
            --bg-card: #1e293b;    /* 30% */
            --accent: #8b5cf6;     /* 10% */
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-dark); color: #fff; }
        
        /* Tab Animation */
        .tab-btn { transition: all 0.3s; border-bottom: 2px solid transparent; opacity: 0.6; }
        .tab-btn.active { border-color: var(--accent); color: var(--accent); opacity: 1; font-weight: 700; }
        
        /* Card Hover */
        .provider-card { transition: transform 0.2s, border-color 0.2s; }
        .provider-card:hover { transform: translateY(-5px); border-color: var(--accent); }
        
        /* Hide Scrollbar */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <nav class="bg-[#1e293b]/90 backdrop-blur border-b border-slate-700 sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <a href="#" class="flex items-center gap-2 font-bold text-xl">
                <i class="fas fa-bolt text-violet-500"></i> Atlantic<span class="text-violet-500">Pedia</span>
            </a>
            <div class="relative hidden md:block w-1/3">
                <input type="text" id="globalSearch" placeholder="Cari Game / Provider..." class="w-full bg-[#0f172a] border border-slate-600 rounded-full py-2 pl-10 pr-4 text-sm focus:border-violet-500 outline-none transition">
                <i class="fas fa-search absolute left-4 top-2.5 text-slate-500"></i>
            </div>
            <button class="bg-violet-600 px-5 py-2 rounded-full text-sm font-bold shadow-lg shadow-violet-500/30 hover:bg-violet-700 transition">Masuk</button>
        </div>
    </nav>

    <header class="container mx-auto px-4 py-8">
        <div class="bg-gradient-to-r from-violet-900 to-slate-900 rounded-2xl p-8 relative overflow-hidden border border-slate-700 shadow-2xl">
            <div class="relative z-10 max-w-2xl">
                <h1 class="text-3xl md:text-4xl font-extrabold mb-2 leading-tight">Top Up Game & Pulsa <br><span class="text-violet-400">Termurah & Otomatis</span></h1>
                <p class="text-slate-400 text-sm md:text-base mb-6">Layanan aktif 24 jam. Proses detik, harga distributor.</p>
            </div>
            <div class="absolute -right-10 -bottom-20 w-64 h-64 bg-violet-600/20 blur-3xl rounded-full"></div>
        </div>
    </header>

    <div class="sticky top-[72px] z-30 bg-[#0f172a]/95 backdrop-blur py-2 border-b border-slate-800">
        <div class="container mx-auto px-4 flex gap-6 overflow-x-auto no-scrollbar">
            <button onclick="switchTab('games')" class="tab-btn active text-sm md:text-base pb-2 whitespace-nowrap" id="btn-games">
                <i class="fas fa-gamepad mr-2"></i> Games
            </button>
            <button onclick="switchTab('pulsa')" class="tab-btn text-sm md:text-base pb-2 whitespace-nowrap" id="btn-pulsa">
                <i class="fas fa-mobile-alt mr-2"></i> Pulsa & Data
            </button>
            <button onclick="switchTab('lainnya')" class="tab-btn text-sm md:text-base pb-2 whitespace-nowrap" id="btn-lainnya">
                <i class="fas fa-wallet mr-2"></i> Voucher & Lainnya
            </button>
        </div>
    </div>

    <main class="container mx-auto px-4 py-8 flex-grow min-h-[500px]">
        
        <div class="md:hidden mb-6 relative">
            <input type="text" id="mobileSearch" placeholder="Cari layanan..." class="w-full bg-[#1e293b] border border-slate-700 rounded-xl p-3 pl-10 text-sm outline-none focus:border-violet-500">
            <i class="fas fa-search absolute left-3.5 top-3.5 text-slate-500"></i>
        </div>

        <div id="gridContainer" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3 md:gap-5">
            </div>

        <div id="emptyState" class="hidden text-center py-20">
            <div class="inline-block p-4 rounded-full bg-slate-800 mb-3"><i class="fas fa-ghost text-2xl text-slate-500"></i></div>
            <p class="text-slate-500">Layanan tidak ditemukan.</p>
        </div>

    </main>

    <footer class="bg-[#0b1120] border-t border-slate-800 py-8 text-center">
        <p class="text-slate-500 text-sm">&copy; 2025 Atlantic Pedia Partner. All Rights Reserved.</p>
    </footer>

    <div id="modal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="absolute bottom-0 md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 w-full md:w-[600px] h-[85vh] md:h-auto md:max-h-[90vh] bg-[#1e293b] md:rounded-2xl rounded-t-2xl shadow-2xl flex flex-col border border-slate-700">
            
            <div class="p-5 border-b border-slate-700 flex justify-between items-center bg-[#1e293b] md:rounded-t-2xl z-10">
                <div class="flex items-center gap-3">
                    <img id="mImg" src="" class="w-10 h-10 rounded-lg bg-slate-800 object-cover">
                    <div>
                        <h3 id="mTitle" class="font-bold text-lg leading-tight">Loading...</h3>
                        <p class="text-xs text-violet-400 font-semibold">Pilih Nominal</p>
                    </div>
                </div>
                <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition"><i class="fas fa-times"></i></button>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-6" id="mBody">
                <div class="bg-[#0f172a] p-4 rounded-xl border border-slate-700">
                    <label class="block text-xs font-bold text-slate-400 mb-2 uppercase">Target (No HP / User ID)</label>
                    <input type="text" placeholder="Contoh: 08123456789" class="w-full bg-[#1e293b] border border-slate-600 rounded-lg px-4 py-3 text-white text-sm focus:border-violet-500 outline-none transition placeholder-slate-600">
                </div>

                <div id="mItems"></div>
            </div>

            <div class="p-4 bg-[#0f172a] border-t border-slate-800 md:rounded-b-2xl flex justify-between items-center">
                <div>
                    <span class="text-xs text-slate-500 block">Total Bayar</span>
                    <span id="mTotal" class="text-xl font-bold text-violet-400">Rp 0</span>
                </div>
                <button class="bg-violet-600 hover:bg-violet-700 text-white px-8 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-violet-900/20 transition">Beli</button>
            </div>
        </div>
    </div>

    <script>
        // Data dari PHP
        const rawData = <?= $jsonData ?>;
        
        let currentTab = 'games';
        const grid = document.getElementById('gridContainer');
        const emptyState = document.getElementById('emptyState');

        // Helper: Format Rupiah
        const rp = (n) => new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', minimumFractionDigits:0}).format(n);

        // 1. RENDER GRID UTAMA
        function renderGrid(filterText = '') {
            const data = rawData[currentTab]; // Ambil data sesuai tab aktif
            grid.innerHTML = '';
            
            let count = 0;
            const search = filterText.toLowerCase();

            if (!data || Object.keys(data).length === 0) {
                grid.classList.add('hidden');
                emptyState.classList.remove('hidden');
                return;
            }

            for (const [key, provider] of Object.entries(data)) {
                if (filterText && !provider.name.toLowerCase().includes(search)) continue;

                const card = `
                    <div onclick="openModal('${currentTab}', '${key}')" class="provider-card bg-[#1e293b] border border-slate-700/50 rounded-xl overflow-hidden cursor-pointer group">
                        <div class="aspect-square bg-slate-800 relative">
                            <img src="${provider.image}" class="w-full h-full object-cover transition duration-500 group-hover:scale-110" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#1e293b] to-transparent opacity-60"></div>
                        </div>
                        <div class="p-3">
                            <h3 class="font-bold text-xs md:text-sm text-center truncate group-hover:text-violet-400 transition">${provider.name}</h3>
                        </div>
                    </div>
                `;
                grid.innerHTML += card;
                count++;
            }

            if (count === 0) {
                grid.classList.add('hidden');
                emptyState.classList.remove('hidden');
            } else {
                grid.classList.remove('hidden');
                emptyState.classList.add('hidden');
            }
        }

        // 2. SWITCH TAB SYSTEM
        function switchTab(tabName) {
            currentTab = tabName;
            
            // Update Tombol Aktif
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-'+tabName).classList.add('active');
            
            renderGrid();
        }

        // 3. SEARCH LISTENER
        const handleSearch = (e) => renderGrid(e.target.value);
        document.getElementById('globalSearch').addEventListener('keyup', handleSearch);
        document.getElementById('mobileSearch').addEventListener('keyup', handleSearch);

        // 4. MODAL SYSTEM
        const modal = document.getElementById('modal');
        
        function openModal(category, providerKey) {
            const data = rawData[category][providerKey];
            if(!data) return;

            document.getElementById('mTitle').innerText = data.name;
            document.getElementById('mImg').src = data.image;
            document.getElementById('mTotal').innerText = 'Rp 0';

            const itemsContainer = document.getElementById('mItems');
            itemsContainer.innerHTML = '';

            // Loop Sub-Category (Misal: Umum, Internet Sakti)
            for (const [subCat, items] of Object.entries(data.variants)) {
                let html = `
                    <div class="mb-4">
                        <h4 class="text-xs font-bold text-slate-400 uppercase mb-3 border-l-2 border-violet-500 pl-2">${subCat}</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                `;

                // Urutkan item berdasarkan harga terendah
                items.sort((a, b) => a.price - b.price);

                items.forEach(item => {
                    const isReady = item.status === 'available';
                    const css = isReady ? 'cursor-pointer hover:border-violet-500 hover:bg-slate-800' : 'opacity-50 cursor-not-allowed grayscale';
                    
                    // Bersihkan nama produk di tampilan
                    let cleanName = item.name.replace(data.name, '').trim(); 
                    if(cleanName === '') cleanName = item.code; // Fallback jika nama kosong

                    html += `
                        <div class="bg-[#1e293b] border border-slate-700 p-3 rounded-lg relative transition group ${css}"
                             onclick="selectItem(this, ${item.price}, ${isReady})">
                            <div class="flex justify-between items-start">
                                <span class="text-sm font-semibold text-slate-200 line-clamp-2 leading-snug">${cleanName}</span>
                                ${!isReady ? '<span class="text-[10px] bg-red-900/50 text-red-200 px-1.5 rounded">Habis</span>' : ''}
                            </div>
                            <div class="mt-2 text-violet-400 font-bold text-sm">${rp(item.price)}</div>
                            
                            <div class="check-icon hidden absolute top-2 right-2 w-5 h-5 bg-violet-600 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-[10px] text-white"></i>
                            </div>
                        </div>
                    `;
                });

                html += `</div></div>`;
                itemsContainer.innerHTML += html;
            }

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function selectItem(el, price, isReady) {
            if(!isReady) return;

            // Reset Selection
            document.querySelectorAll('#mItems > div > div > div').forEach(div => {
                div.classList.remove('border-violet-500', 'bg-slate-800', 'ring-1', 'ring-violet-500');
                div.querySelector('.check-icon').classList.add('hidden');
            });

            // Set Active
            el.classList.add('border-violet-500', 'bg-slate-800', 'ring-1', 'ring-violet-500');
            el.querySelector('.check-icon').classList.remove('hidden');

            document.getElementById('mTotal').innerText = rp(price);
        }

        // Init First Load
        renderGrid();

    </script>
</body>
</html>