<?php
header('Content-Type: application/json');

// REPLACE WITH YOUR TELEGRAM BOT TOKEN AND CHAT ID BEFORE DEPLOYING
$botToken = 'YOUR_TELEGRAM_BOT_TOKEN'; 
$chatId = 'YOUR_TELEGRAM_CHAT_ID';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['name']) || empty($data['contact']) || empty($data['message'])) {
    echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص است']);
    exit;
}

$text = "📬 پیام جدید از فرم تماس سایت هفت روز\n\n👤 نام: {$data['name']}\n📞 تماس: {$data['contact']}\n📝 پیام: {$data['message']}";
$url = "https://api.telegram.org/bot$botToken/sendMessage";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['chat_id' => $chatId, 'text' => $text]));
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
