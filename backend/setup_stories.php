<?php
header('Content-Type: text/plain; charset=utf-8');
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. اضافه کردن ستون order_index در صورت عدم وجود
    try {
        $pdo->exec("ALTER TABLE stories ADD COLUMN order_index INT DEFAULT 0");
        echo "ستون order_index با موفقیت اضافه شد.\n";
    } catch (Exception $e) {
        echo "ستون order_index از قبل وجود دارد.\n";
    }

    // 2. بررسی اینکه آیا جدول stories خالی است؟
    $stmt = $pdo->query("SELECT COUNT(*) FROM stories");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        echo "جدول داستان‌ها خالی است، در حال خواندن داستان‌ها از فایل JSON...\n";
        
        $jsonString = file_get_contents(__DIR__ . '/stories.json');
        $stories = json_decode($jsonString, true);
        
        if ($stories && is_array($stories)) {
            $insertStmt = $pdo->prepare("INSERT INTO stories (category_name, title, content_text, order_index) VALUES (?, ?, ?, ?)");
            
            $inserted = 0;
            foreach ($stories as $story) {
                $insertStmt->execute([
                    $story['categoryId'], 
                    $story['title'], 
                    $story['content_text'], 
                    $story['order_index']
                ]);
                $inserted++;
            }
            echo "تعداد $inserted داستان با موفقیت وارد دیتابیس شد!\n";
        } else {
            echo "خطا در خواندن فایل stories.json.\n";
        }
    } else {
        echo "جدول داستان‌ها از قبل شامل دیتا می‌باشد ($count داستان یافت شد).\n";
    }

    echo "\nعملیات با موفقیت انجام شد. می‌توانید این فایل را پاک کنید.";

} catch (Exception $e) {
    echo "خطا در ارتباط با دیتابیس:\n" . $e->getMessage();
}
?>
