<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // واکشی داستان‌ها بر اساس ترتیب تعیین شده در پنل مدیریت
    $stmt = $pdo->query("SELECT id, category_name as categoryId, title, content_text as content FROM stories ORDER BY order_index ASC, id DESC");
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تبدیل IDها به رشته (برای سازگاری با مدل فلاتر)
    foreach ($stories as &$story) {
        $story['id'] = (string)$story['id'];
    }

    echo json_encode([
        'success' => true,
        'data' => $stories
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'خطا در ارتباط با سرور'
    ]);
}
?>
