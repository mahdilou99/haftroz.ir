<?php
session_start();
$db_host = 'localhost'; $db_name = 'haftroz_db'; $db_user = 'haftroz_user'; $db_pass = 'YOUR_DB_PASSWORD';
$admin_pass = 'YOUR_ADMIN_PASSWORD';

if (isset($_POST['login'])) {
    if ($_POST['password'] === $admin_pass) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "رمز عبور اشتباه است";
    }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

if (!isset($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>ورود مدیریت</title></head>
<body style="font-family:Tahoma; text-align:center; margin-top:100px; background:#121212; color:white;">
    <h2 style="color:gold;">ورود به پنل مدیریت هفت روز</h2>
    <form method="POST">
        <input type="password" name="password" placeholder="رمز عبور" required style="padding:10px; width:200px; background:#222; border:1px solid gold; color:white;"><br><br>
        <button type="submit" name="login" style="padding:10px 20px; background:gold; color:black; border:none; cursor:pointer; font-weight:bold;">ورود</button>
    </form>
    <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
</body></html>
<?php exit; }

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_POST['save_ad'])) {
        $ad_code = $_POST['ad_code'];
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ad_code', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$ad_code, $ad_code]);
        $msg = "کد تبلیغات با موفقیت ذخیره شد.";
    }
    if (isset($_GET['del_rec'])) {
        $stmt = $pdo->prepare("DELETE FROM recordings WHERE id = ?"); $stmt->execute([$_GET['del_rec']]);
        header("Location: admin.php"); exit;
    }
    if (isset($_GET['del_user'])) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?"); $stmt->execute([$_GET['del_user']]);
        header("Location: admin.php"); exit;
    }

    $ad_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'ad_code'");
    $current_ad = $ad_stmt->fetchColumn() ?: '';

    $users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $recordings = $pdo->query("SELECT r.*, u.name as user_name FROM recordings r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { die("DB Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>پنل مدیریت</title>
<style>body{font-family:Tahoma; padding:20px; background:#121212; color:white;} table{width:100%; border-collapse:collapse; background:#1e1e1e; margin-bottom:30px;} th,td{border:1px solid #444; padding:8px; text-align:center;} th{background:#000; color:gold;} .btn{padding:5px 10px; color:#fff; text-decoration:none; border-radius:3px;} .btn-red{background:#b30000;} .btn-gold{background:linear-gradient(45deg, #FFD700, #DAA520); border:none; padding:10px 20px; color:#000; font-weight:bold; cursor:pointer;}</style>
</head><body>
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1 style="color:gold;">پنل مدیریت هفت روز</h1>
        <a href="?logout=1" class="btn btn-red">خروج</a>
    </div>
    <?php if(isset($msg)) echo "<p style='color:green; font-weight:bold;'>$msg</p>"; ?>
    <h2 style="color:gold;">تنظیمات تبلیغات اپلیکیشن</h2>
    <form method="POST" style="background:#1e1e1e; padding:20px; border-radius:5px; border:1px solid #333;">
        <p>متن یا کد تبلیغات را وارد کنید (در صورت خالی بودن، باکس تبلیغات مخفی می‌شود):</p>
        <textarea name="ad_code" style="width:100%; height:100px; padding:10px; direction:ltr; background:#000; color:white; border:1px solid #444;"><?php echo htmlspecialchars($current_ad); ?></textarea><br><br>
        <button type="submit" name="save_ad" class="btn-gold">ذخیره تبلیغات</button>
    </form>
    <h2 style="color:gold;">لیست قصه‌های ضبط شده</h2>
    <table>
        <tr><th>ID</th><th>شناسه داستان</th><th>کاربر</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr>
        <?php foreach($recordings as $r): ?>
        <tr>
            <td><?php echo $r['id']; ?></td>
            <td><?php echo $r['story_id']; ?></td>
            <td><?php echo htmlspecialchars($r['user_name'] ?? 'مهمان'); ?></td>
            <td><?php echo $r['is_approved'] ? 'تایید شده' : 'در انتظار'; ?></td>
            <td><?php echo $r['created_at']; ?></td>
            <td>
                <audio controls src="/api/<?php echo $r['file_path']; ?>" style="height:30px;"></audio><br><br>
                <a href="?del_rec=<?php echo $r['id']; ?>" class="btn btn-red" onclick="return confirm('مطمئنید؟')">حذف</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body></html>
