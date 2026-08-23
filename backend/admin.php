<?php
session_start();
// بارگذاری تنظیمات و رمزهای عبور از فایل config
require_once 'config.php';

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

    // ======================================
    // 1. عملیات مربوط به صداها و تنظیمات عمومی
    // ======================================
    
    if (isset($_POST['save_ad'])) {
        $ad_code = $_POST['ad_code'];
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ad_code', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$ad_code, $ad_code]);
        $msg = "تبلیغات ذخیره شد.";
    }
    
    function sendUserEmail($email, $name, $status) {
        if (empty($email)) return;
        $name = $name ?: 'کاربر';
        $subject = ($status === 'approved') ? "تایید قصه شما در سایت هفت روز" : "رد قصه شما در سایت هفت روز";
        
        if ($status === 'approved') {
            $message = "سلام $name عزیز،\n\nتبریک! فایل صوتی شما توسط ادمین تایید شد و هم‌اکنون در سایت هفت روز قرار گرفت.\nاز مشارکت شما در دنیای قصه‌ها سپاسگزاریم.\n\nتیم پشتیبانی هفت روز";
        } else {
            $message = "سلام $name عزیز،\n\nفایل صوتی ارسالی شما با توجه به شاخصه‌های اصلی سایت مورد تایید مدیریت قرار نگرفت و متاسفانه رد شد.\nلطفاً بخش راهنمای ارسال صدا را با دقت بخوانید و با توجه به راهنمای سایت اقدام به ارسال صدای خود کنید.\n\nتیم پشتیبانی هفت روز";
        }
        
        $headers = "From: noreply@haftroz.ir\r\nContent-Type: text/plain; charset=utf-8";
        @mail($email, $subject, $message, $headers);
    }

    if (isset($_GET['del_rec'])) {
        $id = (int)$_GET['del_rec'];
        $stmt = $pdo->prepare("SELECT r.file_path, u.name, u.email FROM recordings r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($info) {
            $filePath = __DIR__ . '/' . ltrim($info['file_path'], '/');
            if (file_exists($filePath) && is_file($filePath)) @unlink($filePath);
            sendUserEmail($info['email'], $info['name'], 'rejected');
        }

        $stmt = $pdo->prepare("DELETE FROM recordings WHERE id = ?"); $stmt->execute([$id]);
        header("Location: admin.php?tab=recordings"); exit;
    }
    
    if (isset($_GET['approve_rec'])) {
        $id = (int)$_GET['approve_rec'];
        $stmt = $pdo->prepare("SELECT u.name, u.email FROM recordings r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE recordings SET is_approved = 1 WHERE id = ?"); $stmt->execute([$id]);
        
        if ($info) sendUserEmail($info['email'], $info['name'], 'approved');
        header("Location: admin.php?tab=recordings"); exit;
    }
    
    if (isset($_GET['reject_rec'])) {
        $stmt = $pdo->prepare("UPDATE recordings SET is_approved = 0 WHERE id = ?"); $stmt->execute([$_GET['reject_rec']]);
        header("Location: admin.php?tab=recordings"); exit;
    }

    // ======================================
    // 2. عملیات مربوط به داستان‌ها (متن)
    // ======================================
    
    if (isset($_POST['add_story'])) {
        $title = $_POST['title'];
        $content = $_POST['content_text'];
        $order = (int)$_POST['order_index'];
        $stmt = $pdo->prepare("INSERT INTO stories (category_name, title, content_text, order_index) VALUES ('c1', ?, ?, ?)");
        $stmt->execute([$title, $content, $order]);
        header("Location: admin.php?tab=stories&msg=added"); exit;
    }

    if (isset($_POST['edit_story'])) {
        $id = (int)$_POST['story_id'];
        $title = $_POST['title'];
        $content = $_POST['content_text'];
        $order = (int)$_POST['order_index'];
        $stmt = $pdo->prepare("UPDATE stories SET title = ?, content_text = ?, order_index = ? WHERE id = ?");
        $stmt->execute([$title, $content, $order, $id]);
        header("Location: admin.php?tab=stories&msg=edited"); exit;
    }

    if (isset($_GET['del_story'])) {
        $id = (int)$_GET['del_story'];
        $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?"); 
        $stmt->execute([$id]);
        header("Location: admin.php?tab=stories&msg=deleted"); exit;
    }

    // دریافت داده‌ها برای نمایش
    $ad_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'ad_code'");
    $current_ad = $ad_stmt->fetchColumn() ?: '';

    $recordings = $pdo->query("SELECT r.*, u.name as user_name FROM recordings r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    // تلاش برای واکشی داستان‌ها با در نظر گرفتن امکان عدم وجود order_index
    try {
        $stories = $pdo->query("SELECT * FROM stories ORDER BY order_index ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // اگر ستون نبود موقتا بدون آن لود کن تا کاربر بره setup_stories رو اجرا کنه
        $stories = $pdo->query("SELECT * FROM stories ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) { die("DB Error: " . $e->getMessage()); }

$activeTab = $_GET['tab'] ?? 'recordings';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>پنل مدیریت</title>
    <style>
        body { font-family: Tahoma; padding: 20px; background: #121212; color: white; margin: 0; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        
        /* تب‌ها */
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn { background: #333; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px 5px 0 0; font-weight: bold; }
        .tab-btn.active { background: gold; color: #000; }
        .tab-content { display: none; background: #1e1e1e; padding: 20px; border-radius: 0 5px 5px 5px; }
        .tab-content.active { display: block; }
        
        table { width: 100%; border-collapse: collapse; background: #1e1e1e; margin-bottom: 30px; }
        th, td { border: 1px solid #444; padding: 8px; text-align: center; }
        th { background: #000; color: gold; }
        
        .btn { padding: 5px 10px; color: #fff; text-decoration: none; border-radius: 3px; margin: 2px; display: inline-block; border:none; cursor:pointer; font-family: inherit;}
        .btn-red { background: #b30000; }
        .btn-gold { background: linear-gradient(45deg, #FFD700, #DAA520); color: #000; font-weight: bold; }
        .btn-green { background: #27ae60; }
        .btn-orange { background: #f39c12; }
        .btn-blue { background: #2980b9; }

        input, textarea { width: 100%; padding: 8px; background: #000; color: #fff; border: 1px solid #444; font-family: Tahoma; box-sizing: border-box; }
        label { display: block; margin-top: 10px; margin-bottom: 5px; color: #aaa; text-align: right; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100; }
        .modal-content { background: #1e1e1e; margin: 5% auto; padding: 20px; width: 60%; border-radius: 10px; border: 1px solid gold; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="color:gold; margin:0;">پنل مدیریت سایت هفت روز</h1>
        <a href="?logout=1" class="btn btn-red" style="padding:10px 20px;">خروج از پنل</a>
    </div>

    <?php if(isset($msg)) echo "<p style='color:green; font-weight:bold;'>$msg</p>"; ?>
    <?php if(isset($_GET['msg'])) echo "<p style='color:gold; font-weight:bold;'>عملیات با موفقیت انجام شد.</p>"; ?>

    <!-- منوی تب‌ها -->
    <div class="tabs">
        <button class="tab-btn <?php echo $activeTab == 'recordings' ? 'active' : ''; ?>" onclick="openTab('recordings')">صداهای ارسالی کاربران</button>
        <button class="tab-btn <?php echo $activeTab == 'stories' ? 'active' : ''; ?>" onclick="openTab('stories')">مدیریت داستان‌ها (محتوا)</button>
        <button class="tab-btn <?php echo $activeTab == 'settings' ? 'active' : ''; ?>" onclick="openTab('settings')">تنظیمات و تبلیغات</button>
    </div>

    <!-- بخش 1: صداها -->
    <div id="recordings" class="tab-content <?php echo $activeTab == 'recordings' ? 'active' : ''; ?>">
        <h2 style="color:gold;">صداهای ضبط شده توسط کاربران</h2>
        <table>
            <tr><th>ID</th><th>شناسه قصه</th><th>کاربر</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr>
            <?php foreach($recordings as $r): ?>
            <tr>
                <td><?php echo $r['id']; ?></td>
                <td><?php echo $r['story_id']; ?></td>
                <td><?php echo htmlspecialchars($r['user_name'] ?? 'مهمان'); ?></td>
                <td style="color: <?php echo $r['is_approved'] ? '#27ae60' : '#f39c12'; ?>;">
                    <?php echo $r['is_approved'] ? 'تایید شده ✔' : 'در انتظار ⏳'; ?>
                </td>
                <td dir="ltr"><?php echo $r['created_at']; ?></td>
                <td>
                    <audio controls src="/api/<?php echo $r['file_path']; ?>" style="height:30px; width:200px;"></audio><br>
                    <?php if($r['is_approved']): ?>
                        <a href="?reject_rec=<?php echo $r['id']; ?>" class="btn btn-orange">لغو تایید</a>
                    <?php else: ?>
                        <a href="?approve_rec=<?php echo $r['id']; ?>" class="btn btn-green">تایید ارسال</a>
                    <?php endif; ?>
                    <a href="?del_rec=<?php echo $r['id']; ?>" class="btn btn-red" onclick="return confirm('مطمئن هستید؟')">حذف و اخطار</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- بخش 2: داستان‌ها -->
    <div id="stories" class="tab-content <?php echo $activeTab == 'stories' ? 'active' : ''; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="color:gold;">لیست داستان‌های اپلیکیشن</h2>
            <button class="btn btn-green" style="padding:10px;" onclick="openModal('addStoryModal')">+ افزودن داستان جدید</button>
        </div>
        <p style="color:#aaa;">شما می‌توانید داستان‌ها را از اینجا مدیریت کنید. عددی که در بخش «ترتیب» وارد می‌کنید، جایگاه نمایش داستان در اپلیکیشن را مشخص می‌کند (شماره کمتر = بالاتر).</p>
        
        <table>
            <tr>
                <th width="50">ID</th>
                <th width="50">ترتیب</th>
                <th>عنوان داستان</th>
                <th width="150">عملیات</th>
            </tr>
            <?php foreach($stories as $s): ?>
            <tr>
                <td><?php echo $s['id']; ?></td>
                <td><strong><?php echo $s['order_index'] ?? 0; ?></strong></td>
                <td style="text-align:right; padding-right:15px;"><?php echo htmlspecialchars($s['title']); ?></td>
                <td>
                    <button class="btn btn-blue" onclick='openEditModal(<?php echo json_encode($s); ?>)'>ویرایش</button>
                    <a href="?del_story=<?php echo $s['id']; ?>" class="btn btn-red" onclick="return confirm('از حذف این داستان کاملا مطمئن هستید؟')">حذف</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- بخش 3: تنظیمات -->
    <div id="settings" class="tab-content <?php echo $activeTab == 'settings' ? 'active' : ''; ?>">
        <h2 style="color:gold;">تنظیمات و کدهای تبلیغاتی</h2>
        <form method="POST" style="max-width:600px;">
            <label>کد تبلیغات تپسل / عدد:</label>
            <textarea name="ad_code" style="height:150px; direction:ltr;"><?php echo htmlspecialchars($current_ad); ?></textarea><br><br>
            <button type="submit" name="save_ad" class="btn btn-gold" style="padding:10px 20px;">ذخیره کد تبلیغات</button>
        </form>
    </div>

    <!-- مودال افزودن داستان -->
    <div id="addStoryModal" class="modal">
        <div class="modal-content">
            <h2 style="color:gold; margin-top:0;">افزودن داستان جدید</h2>
            <form method="POST">
                <label>عنوان داستان</label>
                <input type="text" name="title" required>
                
                <label>ترتیب نمایش (مثلا 1 برای نمایش در ابتدا)</label>
                <input type="number" name="order_index" value="10" required>
                
                <label>متن داستان</label>
                <textarea name="content_text" rows="12" required></textarea>
                
                <div style="margin-top:20px; text-align:left;">
                    <button type="button" class="btn btn-red" onclick="closeModal('addStoryModal')">انصراف</button>
                    <button type="submit" name="add_story" class="btn btn-green">ذخیره داستان</button>
                </div>
            </form>
        </div>
    </div>

    <!-- مودال ویرایش داستان -->
    <div id="editStoryModal" class="modal">
        <div class="modal-content">
            <h2 style="color:gold; margin-top:0;">ویرایش داستان</h2>
            <form method="POST">
                <input type="hidden" name="story_id" id="edit_id">
                <label>عنوان داستان</label>
                <input type="text" name="title" id="edit_title" required>
                
                <label>ترتیب نمایش</label>
                <input type="number" name="order_index" id="edit_order" required>
                
                <label>متن داستان</label>
                <textarea name="content_text" id="edit_content" rows="12" required></textarea>
                
                <div style="margin-top:20px; text-align:left;">
                    <button type="button" class="btn btn-red" onclick="closeModal('editStoryModal')">انصراف</button>
                    <button type="submit" name="edit_story" class="btn btn-blue">ثبت تغییرات</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openEditModal(story) {
            document.getElementById('edit_id').value = story.id;
            document.getElementById('edit_title').value = story.title;
            document.getElementById('edit_order').value = story.order_index || 0;
            document.getElementById('edit_content').value = story.content_text;
            openModal('editStoryModal');
        }
    </script>
</body>
</html>
