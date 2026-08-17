<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db_host = 'localhost';
$db_name = 'haftroz_db';
$db_user = 'haftroz_user';
$db_pass = 'YOUR_PASSWORD';

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the 20 most recent recordings
    $stmt = $pdo->query("SELECT id, story_id, device_id as user_name, file_path, created_at FROM recordings ORDER BY created_at DESC LIMIT 20");
    $recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $recordings
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>
