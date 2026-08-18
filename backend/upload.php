<?php
header('Content-Type: application/json');
require_once __DIR__ . '/logger.php';

$db_host = 'localhost';
$db_user = 'haftroz_user'; 
$db_pass = 'YOUR_DB_PASSWORD'; 
$db_name = 'haftroz_db';

$botToken = "YOUR_TELEGRAM_BOT_TOKEN"; 
$chatId = "YOUR_TELEGRAM_CHAT_ID"; 

$logger->info("Upload request started", ['post_data' => $_POST, 'files' => $_FILES]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['audio']['tmp_name'];
        
        $raw_story_id = isset($_POST['story_id']) ? $_POST['story_id'] : '0';
        $story_id = (int)str_replace('s', '', $raw_story_id);
        
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $story_title = isset($_POST['story_name']) ? $_POST['story_name'] : 'داستان_نامشخص';
        
        $safe_title = str_replace(' ', '_', $story_title);
        $safe_title = preg_replace('/[\\\\\/:\*\?"<>\|]/', '', $safe_title);
        $newFileName = $safe_title . '_' . $user_id . '_' . time() . '.m4a';

        $uploadFileDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0775, true);
        }

        $dest_path = $uploadFileDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $logger->info("File successfully moved", ['target_path' => $dest_path]);
            
            try {
                $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $pdo->prepare("INSERT INTO recordings (story_id, user_id, file_path) VALUES (?, ?, ?)");
                $stmt->execute([$story_id, $user_id, 'uploads/' . $newFileName]);
                $logger->info("Database insert successful", ['story_id' => $story_id, 'user_id' => $user_id]);

            } catch (PDOException $e) {
                $logger->error("Database error in upload.php", ['error' => $e->getMessage()]);
            }

            $caption = "🎙 یک صدای جدید دریافت شد!\n\n" .
                       "نام داستان: " . $story_title . "\n" .
                       "شناسه داستان: " . $story_id . "\n" .
                       "آیدی کاربر: " . $user_id;

            $telegramUrl = "https://api.telegram.org/bot" . $botToken . "/sendAudio";
            $cFile = new CURLFile($dest_path, 'audio/mp4', $newFileName);

            $postFields = array(
                'chat_id' => $chatId,
                'audio' => $cFile,
                'caption' => $caption
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $telegramUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                $logger->error("Telegram curl error", ['curl_error' => curl_error($ch)]);
            } else {
                $logger->info("Telegram response", ['response' => $response]);
            }
            curl_close($ch);

            echo json_encode(['success' => true, 'message' => 'File uploaded and sent to Telegram']);
        } else {
            $logger->error("Failed to move uploaded file");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error moving uploaded file.']);
        }
    } else {
        $upload_error = isset($_FILES['audio']['error']) ? $_FILES['audio']['error'] : 'No file uploaded';
        $logger->error("No audio file found or upload error", ['upload_error' => $upload_error]);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No audio file uploaded or upload error.']);
    }
} else {
    $logger->warning("Invalid request method", ['method' => $_SERVER['REQUEST_METHOD']]);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
