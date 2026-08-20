<?php
header('Content-Type: application/json');
$db_host = 'localhost'; $db_name = 'haftroz_db'; $db_user = 'haftroz_user'; $db_pass = 'Ahmad110110';
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'ad_code'");
    $ad_code = $stmt->fetchColumn() ?: '';
    echo json_encode(['success' => true, 'ad_code' => $ad_code]);
} catch (Exception $e) { echo json_encode(['success' => false]); }
?>