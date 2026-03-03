<?php
require_once __DIR__ . '/config/db.php';

$settings = [
    'PAYMENT_METHOD' => 'QRIS',
    'EWALLET_GOPAY' => '',
    'EWALLET_SHOPEEPAY' => '',
    'EWALLET_DANA' => '',
    'EWALLET_OVO' => ''
];

$sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = setting_value"; // Don't overwrite if exists
$stmt = $pdo->prepare($sql);

foreach ($settings as $key => $value) {
    $stmt->execute([$key, $value]);
    echo "Ensured setting: $key\n";
}
?>
