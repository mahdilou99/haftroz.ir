<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents(__DIR__ . '/../update_all.sql');
    if ($sql) {
        $pdo->exec($sql);
        echo "<h2 style='color:green;'>آپدیت دیتابیس (کل ۷۳۳ داستان) با موفقیت انجام شد! 🎉</h2>";
        echo "<p>داستان‌ها ویرایش، لحن‌سازی، فیلتر و بر اساس زیبایی مرتب شدند. لطفاً این فایل را پس از اجرا از روی سرور پاک کنید.</p>";
    } else {
        echo "<h2 style='color:red;'>فایل SQL پیدا نشد!</h2>";
    }
} catch (Exception $e) {
    echo "خطا در آپدیت دیتابیس: " . $e->getMessage();
}
