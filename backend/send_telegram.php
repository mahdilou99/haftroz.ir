<?php
header('Content-Type: application/json');

// بارگذاری توکن تلگرام از فایل تنظیمات
require_once 'config.php';

// مقادیر $telegram_bot_token و $telegram_chat_id باید در config.php باشند

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['name']) || empty($data['contact']) || empty($data['message'])) {
    echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص است']);
    exit;
}

$text = "📬 پیام جدید از فرم تماس سایت هفت روز\n\n👤 نام: {$data['name']}\n📞 تماس: {$data['contact']}\n📝 پیام: {$data['message']}";
$url = "https://api.telegram.org/bot$telegram_bot_token/sendMessage";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['chat_id' => $telegram_chat_id, 'text' => $text]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result && isset($result['ok']) && $result['ok']) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'خطا در ارتباط با تلگرام']);
}
?>
