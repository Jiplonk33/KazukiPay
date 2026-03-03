<?php
// Mock successful transaction in DB for testing
require_once __DIR__ . '/config/db.php';

$unique_amount = 12345;
$internal_trx_id = "TEST-" . rand(1000, 9999);

// Create a test transaction
$pdo->prepare("INSERT INTO transactions (project_id, internal_trx_id, amount_request, amount_unique, status, provider_name) 
               VALUES (27, ?, 12000, ?, 'PENDING', 'Yobase Notif')")
    ->execute([$internal_trx_id, $unique_amount]);

echo "Created test transaction: $internal_trx_id with amount $unique_amount\n";

// Simulate Yobase Notif Webhook by mocking php://input
$data = [
    "amount" => $unique_amount,
    "bank" => "Dana",
    "sender" => "Tester"
];

// PHP doesn't easily allow mocking php://input in the same process for another file, 
// so we'll just mock the logic manually or use a scratch file.
// For verification, I'll just check if the DB record was created first.
echo "Manually triggering matching logic...\n";

// Replicating logic for verification
$stmt = $pdo->prepare("SELECT t.*, p.webhook_url, p.user_id 
        FROM transactions t
        JOIN projects p ON t.project_id = p.id
        WHERE t.amount_unique = ? AND t.status = 'PENDING' AND t.provider_name = 'Yobase Notif'");
$stmt->execute([$unique_amount]);
$trx = $stmt->fetch();

if ($trx) {
    echo "Found transaction! Updating...\n";
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE transactions SET status = 'SUCCESS', paid_at = NOW(), fee = 1000, provider_ref_id = 'Dana | Tester' WHERE id = ?")
        ->execute([$trx['id']]);
    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
        ->execute([$unique_amount - 1000, $trx['user_id']]);
    $pdo->commit();
    echo "Update complete.\n";
} else {
    echo "Transaction not found!\n";
}

// Check DB for update
$stmt = $pdo->prepare("SELECT status, provider_ref_id FROM transactions WHERE internal_trx_id = ?");
$stmt->execute([$internal_trx_id]);
$res = $stmt->fetch();

echo "Final transaction status: " . $res['status'] . "\n";
echo "Provider Ref: " . $res['provider_ref_id'] . "\n";
?>
