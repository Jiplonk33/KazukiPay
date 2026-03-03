<?php
// File: api.php
// Matikan output error HTML agar tidak merusak format JSON
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle Pre-flight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 1. Load Database & Settings (Wajib Urutan Ini)
    require_once __DIR__ . '/config/db.php';       // Memuat $pdo dan $app_settings
    require_once __DIR__ . '/config/settings.php'; // Memuat konstanta (PAYGOLD_*, dll)
    require_once __DIR__ . '/controllers/webhook_controller.php'; 

    // 2. Validasi API Key
    $api_key = $_GET['apikey'] ?? '';
    if (empty($api_key)) {
        throw new Exception("API Key wajib diisi");
    }

    $stmt = $pdo->prepare("SELECT * FROM projects WHERE api_key = ? AND is_active = 1");
    $stmt->execute([$api_key]);
    $project = $stmt->fetch();

    if (!$project) {
        throw new Exception("API Key tidak valid atau proyek tidak aktif");
    }

    $action = $_GET['action'] ?? null;

    // ===================================
    // ACTION: CREATE PAYMENT
    // ===================================
    if ($action == "createpayment") {
        $amount = (int)($_GET['amount'] ?? 0);
        if ($amount <= 0) throw new Exception("Nominal tidak valid");

        // Logika kode unik
        if ($amount < 3000) {
            $unique = rand(100, 499); 
        } else {
            $unique = rand(100, 999); 
        }
        $unique_amount = $amount + $unique;
        $internal_trx_id = "YO-" . strtoupper(substr(md5(uniqid()), 0, 8));

        $payment_method = $app_settings['PAYMENT_METHOD'] ?? 'QRIS';
        
        if ($payment_method == 'EWALLET') {
            // MODE EWALLET MANUAL (LISTENER HP)
            $mysql_expired_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $qris_url = null;
            $provider_name = 'Yobase Notif';
            
            $instructions = [
                "Gopay" => $app_settings['EWALLET_GOPAY'] ?? '',
                "ShopeePay" => $app_settings['EWALLET_SHOPEEPAY'] ?? '',
                "Dana" => $app_settings['EWALLET_DANA'] ?? '',
                "OVO" => $app_settings['EWALLET_OVO'] ?? ''
            ];
            // Filter empty instructions
            $instructions = array_filter($instructions);

            $res_data = [
                "provider" => "YoGateway",
                "status" => true,
                "result" => [
                    "trxid" => $internal_trx_id, 
                    "method" => "EWALLET",
                    "nominal" => $unique_amount,
                    "unique_code" => $unique,
                    "expired" => $mysql_expired_time,
                    "instructions" => $instructions
                ]
            ];
        } else {
            // MODE QRIS OTOMATIS (PAYGOLD)
            $url = PAYGOLD_BASE . "/createpayment?apikey=" . PAYGOLD_APIKEY . "&amount=$unique_amount&codeqr=" . urlencode(PAYGOLD_CODEQR);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $res = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($res, true);

            if (!$data || !isset($data["status"]) || !$data["status"]) {
                $msg = $data['message'] ?? "Gagal membuat QRIS di provider utama.";
                throw new Exception($msg);
            }

            $mysql_expired_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
            if (isset($data['result']['expired'])) {
                $dt = new DateTime($data['result']['expired']);
                $mysql_expired_time = $dt->format('Y-m-d H:i:s');
            }
            $qris_url = $data['result']['imageqris']['url'];
            $provider_name = 'PayGold';

            $res_data = [
                "provider" => "YoGateway",
                "status" => true,
                "result" => [
                    "trxid" => $internal_trx_id, 
                    "method" => "QRIS",
                    "nominal" => $unique_amount,
                    "expired" => $mysql_expired_time,
                    "qris_image" => $qris_url
                ]
            ];
        }

        // Insert ke DB
        $sql = "INSERT INTO transactions (project_id, internal_trx_id, amount_request, amount_unique, status, qris_url, expired_at, provider_name) 
                VALUES (?, ?, ?, ?, 'PENDING', ?, ?, ?)";
        $stmt_insert = $pdo->prepare($sql);
        $stmt_insert->execute([
            $project['id'],
            $internal_trx_id,
            $amount,
            $unique_amount,
            $qris_url,
            $mysql_expired_time,
            $provider_name
        ]);

        echo json_encode($res_data, JSON_PRETTY_PRINT);
        exit;
    }

    // ===================================
    // ACTION: CHECK STATUS
    // ===================================
    if ($action == "checkstatus") {
        $trxid = $_GET['trxid'] ?? '';
        if (empty($trxid)) throw new Exception("trxid wajib diisi");

        // Cari transaksi
        $sql = "SELECT t.*, p.webhook_url, p.user_id 
                FROM transactions t
                JOIN projects p ON t.project_id = p.id
                WHERE t.internal_trx_id = ? AND t.project_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$trxid, $project['id']]);
        $trx = $stmt->fetch();

        if (!$trx) throw new Exception("Transaksi tidak ditemukan");

        // Jika sudah sukses, langsung return
        if ($trx['status'] == 'SUCCESS') {
            echo json_encode([
                "provider" => "YoGateway",
                "status" => true,
                "result" => [
                    "trxid" => $trx['internal_trx_id'],
                    "amount" => (int)$trx['amount_unique'],
                    "status" => "SUCCESS",
                    "qris_image" => $trx['qris_url']
                ]
            ], JSON_PRETTY_PRINT);
            exit;
        }

        // Cek Mutasi ke Provider
        $url = PAYGOLD_BASE . "/mutasiqr?apikey=" . PAYGOLD_APIKEY . "&username=" . PAYGOLD_USERNAME . "&token=" . urlencode(PAYGOLD_TOKEN);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $mutasi = json_decode($res, true);

        $found = false;
        if ($mutasi && isset($mutasi["status"]) && $mutasi["status"]) {
            foreach ($mutasi["result"] as $m) {
                // Bersihkan format angka (misal: 10.500 -> 10500)
                $kredit = preg_replace('/[^0-9]/', '', $m["kredit"]);
                if ((int)$kredit == (int)$trx["amount_unique"]) {
                    $found = true;
                    break;
                }
            }
        }

        if ($found) {
            $pdo->beginTransaction();
            
            // Ambil fee dari settings database
            $fee_amount = (int)($app_settings['PLATFORM_FEE_FIXED'] ?? 500);
            $net_amount = $trx['amount_unique'] - $fee_amount;

            // Update Transaksi
            $sql_update_trx = "UPDATE transactions SET status = 'SUCCESS', paid_at = NOW(), fee = ? WHERE id = ?";
            $pdo->prepare($sql_update_trx)->execute([$fee_amount, $trx['id']]);

            // Update Saldo User
            $sql_update_user = "UPDATE users SET balance = balance + ? WHERE id = ?";
            $pdo->prepare($sql_update_user)->execute([$net_amount, $trx['user_id']]);

            $pdo->commit();

            // Kirim Webhook
            if (!empty($trx['webhook_url'])) {
                $trx_data = $trx;
                $trx_data['status'] = 'SUCCESS';
                $trx_data['paid_at'] = date('Y-m-d H:i:s');
                send_webhook($pdo, $trx_data, $trx['webhook_url']);
            }
            
            $final_status = "SUCCESS";
        } else {
            $final_status = "PENDING";
        }

        echo json_encode([
            "provider" => "YoGateway",
            "status" => true,
            "result" => [
                "trxid" => $trx['internal_trx_id'],
                "amount" => (int)$trx['amount_unique'],
                "status" => $final_status,
                "qris_image" => $trx['qris_url']
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }

    throw new Exception("Action tidak valid");

} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
?>