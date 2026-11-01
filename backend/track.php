<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-create tracking tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_installs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `device_id` VARCHAR(100) NOT NULL UNIQUE,
        `app_version` VARCHAR(50) DEFAULT '1.0.0',
        `ip_address` VARCHAR(50) DEFAULT NULL,
        `open_count` INT DEFAULT 1,
        `first_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`device_id`),
        INDEX (`last_seen`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_events` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `device_id` VARCHAR(100) NOT NULL,
        `event_type` VARCHAR(50) NOT NULL,
        `event_label` VARCHAR(255) DEFAULT NULL,
        `ip_address` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`device_id`),
        INDEX (`event_type`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Read payload
    $rawInput = file_get_contents('php://input');
    $data = [];
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $data = $json;
        }
    }
    if (empty($data)) {
        $data = $_POST ?: $_GET;
    }

    $device_id = trim($data['device_id'] ?? '');
    $event_type = trim($data['event_type'] ?? 'app_open');
    $event_label = trim($data['event_label'] ?? '');
    $app_version = trim($data['app_version'] ?? '1.0.0');

    if (empty($device_id)) {
        echo json_encode(['success' => false, 'message' => 'device_id is required']);
        exit;
    }

    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

    // If app_open, record or increment install/open count
    if ($event_type === 'app_open') {
        $stmt = $pdo->prepare("INSERT INTO app_installs (device_id, app_version, ip_address, open_count, first_seen, last_seen)
            VALUES (?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                open_count = open_count + 1,
                last_seen = NOW(),
                app_version = VALUES(app_version),
                ip_address = VALUES(ip_address)");
        $stmt->execute([$device_id, $app_version, $ip]);
    }

    // Record the specific event
    $stmt = $pdo->prepare("INSERT INTO app_events (device_id, event_type, event_label, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$device_id, $event_type, $event_label, $ip]);

    echo json_encode([
        'success' => true,
        'recorded' => true
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
