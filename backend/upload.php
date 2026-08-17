<?php
header('Content-Type: application/json');

// Database configuration
$db_host = 'localhost';
$db_name = 'haftroz_db';
$db_user = 'root';
$db_pass = ''; // Set your MariaDB password here
$upload_dir = __DIR__ . '/uploads/';

// Ensure uploads directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Load Telegram Config if exists
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// Ensure method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// Validate inputs
if (!isset($_POST['story_id']) || !isset($_FILES['audio'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: story_id, audio']);
    exit;
}

$story_id = (int)$_POST['story_id'];
$device_id = isset($_POST['device_id']) ? $_POST['device_id'] : 'unknown';
$audio_file = $_FILES['audio'];

// Check upload errors
if ($audio_file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $audio_file['error']]);
    exit;
}

// Generate unique safe file name
$file_ext = pathinfo($audio_file['name'], PATHINFO_EXTENSION);
if (empty($file_ext)) {
    $file_ext = 'm4a'; // default audio format
}
$new_filename = time() . '_' . uniqid() . '.' . $file_ext;
$target_path = $upload_dir . $new_filename;

// Move file
if (!move_uploaded_file($audio_file['tmp_name'], $target_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file on the server.']);
    exit;
}

// Insert record into MariaDB
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("INSERT INTO recordings (story_id, device_id, file_path) VALUES (?, ?, ?)");
    $relative_path = 'uploads/' . $new_filename;
    $stmt->execute([$story_id, $device_id, $relative_path]);

    // Send to Telegram
    if (isset($telegram_token) && isset($telegram_chat_id)) {
        $story_title = isset($_POST['story_title']) ? $_POST['story_title'] : "بدون عنوان";
        $telegram_url = "https://api.telegram.org/bot" . $telegram_token . "/sendAudio";
        
        $cfile = new CURLFile($target_path, mime_content_type($target_path), $new_filename);
        $caption = "🎙 یک قصه جدید ضبط شد!\n\nنام داستان: " . $story_title . "\nشناسه داستان: " . $story_id;

        $post_fields = [
            'chat_id' => $telegram_chat_id,
            'audio' => $cfile,
            'caption' => $caption
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $telegram_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Execute, but ignore errors so the API response to the app isn't blocked
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Recording successfully uploaded and saved.',
        'data' => [
            'id' => $pdo->lastInsertId(),
            'file_path' => $relative_path
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    // Note: Do not expose actual SQL errors in production. Log them instead.
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>
