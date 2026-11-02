<?php
header('Content-Type: application/json');
require_once __DIR__ . '/logger.php';
$logger = new AppLogger();

require_once __DIR__ . '/config.php';
$botToken = $telegram_bot_token;
$chatId = $telegram_chat_id; 

$logger->info("Upload request started", ['post_data' => $_POST, 'files' => $_FILES]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['audio']['tmp_name'];
        
        $raw_story_id = isset($_POST['story_id']) ? $_POST['story_id'] : '0';
        $story_id = (int)str_replace('s', '', $raw_story_id);
        
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $story_title = isset($_POST['story_name']) ? trim($_POST['story_name']) : '';
        $user_name = isset($_POST['user_name']) ? trim($_POST['user_name']) : '';

        // Query database to fetch title and user details if missing or empty
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if (empty($story_title) && $story_id > 0) {
                $stmt = $pdo->prepare("SELECT title FROM stories WHERE id = ?");
                $stmt->execute([$story_id]);
                $storyRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($storyRow && !empty($storyRow['title'])) {
                    $story_title = $storyRow['title'];
                }
            }

            if (empty($user_name) && $user_id > 0) {
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($userRow && !empty($userRow['name'])) {
                    $user_name = $userRow['name'];
                }
            }
        } catch (Exception $e) {
            $logger->error("Database check error in upload.php", ['error' => $e->getMessage()]);
        }

        if (empty($story_title)) {
            $story_title = ($story_id > 0) ? "داستان شماره $story_id" : "داستان نامشخص";
        }
        if (empty($user_name)) {
            $user_name = ($user_id > 0) ? "کاربر شماره $user_id" : "کاربر مهمان";
        }

        $safe_title = preg_replace('/[\\\\\/:\*\?"<>\|]/', '', str_replace(' ', '_', $story_title));
        $safe_user = preg_replace('/[\\\\\/:\*\?"<>\|]/', '', str_replace(' ', '_', $user_name));
        $newFileName = $safe_title . '_' . $safe_user . '_' . time() . '.m4a';

        $uploadFileDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0775, true);
        }

        $dest_path = $uploadFileDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $logger->info("File successfully moved", ['target_path' => $dest_path]);
            
            try {
                if (!isset($pdo)) {
                    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                }

                $stmt = $pdo->prepare("INSERT INTO recordings (story_id, user_id, file_path, is_approved) VALUES (?, ?, ?, 0)");
                $stmt->execute([$story_id, $user_id, 'uploads/' . $newFileName]);
                $logger->info("Database insert successful", ['story_id' => $story_id, 'user_id' => $user_id]);

            } catch (PDOException $e) {
                $logger->error("Database error in upload.php", ['error' => $e->getMessage()]);
            }

            $caption = "🎙 یک صدای جدید دریافت شد!\n\n" .
                       "📖 نام داستان: " . $story_title . "\n" .
                       "🆔 شناسه داستان: " . $story_id . "\n" .
                       "👤 نام راوی: " . $user_name . "\n" .
                       "🔢 کد کاربر: " . ($user_id > 0 ? $user_id : 'مهمان') . "\n" .
                       "⏰ زمان ارسال: " . date('Y-m-d H:i:s');

            $telegramUrl = "https://api.telegram.org/bot" . $botToken . "/sendAudio";
            $cFile = new CURLFile($dest_path, 'audio/mp4', $newFileName);

            $postFields = array(
                'chat_id' => $chatId,
                'audio' => $cFile,
                'caption' => $caption,
                'title' => $story_title,
                'performer' => $user_name
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
?>
