<?php
header('Content-Type: application/json');

require_once __DIR__ . '/logger.php';
$logger = new AppLogger();

// Database configuration
$db_host = 'localhost';
$db_name = 'haftroz_db';
$db_user = 'haftroz_user';
$db_pass = 'YOUR_DB_PASSWORD';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $logger->warning('login.php accessed with non-POST method');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

$google_id = $_POST['google_id'] ?? null;
$email = $_POST['email'] ?? null;
$name = $_POST['name'] ?? null;

if (!$google_id || !$email) {
    http_response_code(400);
    $logger->error('login.php missing required fields', ['google_id' => $google_id, 'email' => $email]);
    echo json_encode(['success' => false, 'message' => 'Missing google_id or email']);
    exit;
}

$logger->info("Login attempt", ['email' => $email, 'name' => $name]);

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE google_id = ? OR email = ? LIMIT 1");
    $stmt->execute([$google_id, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user_id = $user['id'];
        $logger->info("User logged in successfully (Existing)", ['user_id' => $user_id]);
    } else {
        // Create new user
        $stmt = $pdo->prepare("INSERT INTO users (google_id, email, name) VALUES (?, ?, ?)");
        $stmt->execute([$google_id, $email, $name]);
        $user_id = $pdo->lastInsertId();
        $logger->info("User registered successfully (New)", ['user_id' => $user_id]);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $user_id,
            'name' => $name,
            'email' => $email
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    $logger->error("Database error during login", ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>
