<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 12;
    $offset = ($page - 1) * $limit;

    // فقط فایل‌هایی که ادمین تایید کرده شمرده می‌شوند
    $countStmt = $pdo->query("SELECT COUNT(*) FROM recordings WHERE is_approved = 1");
    $total = (int)$countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);

    // واکشی فقط صداهای تایید شده به همراه نام داستان و نام کاربر
    $stmt = $pdo->prepare("
        SELECT 
            r.id, 
            r.story_id, 
            COALESCE(u.name, 'کاربر مهمان') as user_name, 
            COALESCE(s.title, CONCAT('قصه شماره ', r.story_id)) as story_title, 
            r.file_path, 
            r.created_at 
        FROM recordings r 
        LEFT JOIN users u ON r.user_id = u.id 
        LEFT JOIN stories s ON r.story_id = s.id 
        WHERE r.is_approved = 1 
        ORDER BY r.id DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $recordings,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => max(1, $totalPages),
            'total_items' => $total
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
}
?>
