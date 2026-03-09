<?php
/**
 * Test which version of the code is being executed
 * This will create a log file to prove which code ran
 */

$logFile = __DIR__ . '/../../test_code_version.log';

// Log that this new test file was called
file_put_contents($logFile, date('Y-m-d H:i:s') . " - TEST FILE CALLED\n", FILE_APPEND);

// Now include the actual file and see what happens
$actualFile = __DIR__ . '/submit_late_fee_payment.php';
$content = file_get_contents($actualFile);

if (strpos($content, 'INSERT INTO late_fee_payments') !== false) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - File contains: INSERT INTO late_fee_payments (CORRECT)\n", FILE_APPEND);
    echo json_encode([
        'success' => true,
        'message' => 'File contains correct code',
        'code_version' => 'NEW (late_fee_payments)',
        'file_size' => strlen($content),
        'file_path' => $actualFile
    ]);
} else {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - File contains: INSERT INTO payments (OLD)\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => 'File contains OLD code',
        'code_version' => 'OLD (payments)',
        'file_size' => strlen($content),
        'file_path' => $actualFile
    ]);
}
?>
