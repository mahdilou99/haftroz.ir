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

    // Ensure settings table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `setting_key` VARCHAR(100) PRIMARY KEY,
        `setting_value` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settingsRaw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $ad_code = $settingsRaw['ad_code'] ?? '';
    $ad_banner_image = $settingsRaw['ad_banner_image'] ?? '';
    $ad_target_url = $settingsRaw['ad_target_url'] ?? '';
    $ad_title = $settingsRaw['ad_title'] ?? '';
    $ad_active = isset($settingsRaw['ad_active']) ? ($settingsRaw['ad_active'] === '1' || $settingsRaw['ad_active'] === 'true') : (!empty($ad_code) || !empty($ad_banner_image));

    echo json_encode([
        'success' => true,
        'ad_code' => $ad_code,
        'ad_banner_image' => $ad_banner_image,
        'ad_target_url' => $ad_target_url,
        'ad_title' => $ad_title,
        'ad_active' => $ad_active
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
