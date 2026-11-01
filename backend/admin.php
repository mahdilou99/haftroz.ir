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
    <form method="POST" enctype="multipart/form-data">
        <input type="password" name="password" placeholder="رمز عبور" required style="padding:10px; width:200px; background:#222; border:1px solid gold; color:white;"><br><br>
        <button type="submit" name="login" style="padding:10px 20px; background:gold; color:black; border:none; cursor:pointer; font-weight:bold;">ورود</button>
    </form>
    <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
</body></html>
<?php exit; }

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-create tracking and settings tables if not exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `setting_key` VARCHAR(100) PRIMARY KEY,
        `setting_value` TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_installs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `device_id` VARCHAR(100) NOT NULL UNIQUE,
        `app_version` VARCHAR(50) DEFAULT '1.0.0',
        `ip_address` VARCHAR(50) DEFAULT NULL,
        `open_count` INT DEFAULT 1,
        `first_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`device_id`),
        INDEX (`last_seen`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_events` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `device_id` VARCHAR(100) NOT NULL,
        `event_type` VARCHAR(50) NOT NULL,
        `event_label` VARCHAR(255) DEFAULT NULL,
        `ip_address` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`device_id`),
        INDEX (`event_type`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ======================================
    // 1. عملیات مربوط به تنظیمات و تبلیغات
    // ======================================
    if (isset($_POST['save_ad'])) {
        $ad_code = $_POST['ad_code'] ?? '';
        $ad_title = $_POST['ad_title'] ?? '';
        $ad_target_url = $_POST['ad_target_url'] ?? '';
        $ad_active = isset($_POST['ad_active']) ? '1' : '0';

        $banner_image = null;
        if (isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $bName = 'ad_' . time() . '.' . $ext;
                $dest = __DIR__ . '/uploads/ads/' . $bName;
                if (!is_dir(__DIR__ . '/uploads/ads')) mkdir(__DIR__ . '/uploads/ads', 0775, true);
                if (move_uploaded_file($_FILES['banner_file']['tmp_name'], $dest)) {
                    $banner_image = 'uploads/ads/' . $bName;
                }
            }
        } elseif (!empty($_POST['ad_banner_image_url'])) {
            $banner_image = trim($_POST['ad_banner_image_url']);
        }

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['ad_code', $ad_code, $ad_code]);
        $stmt->execute(['ad_title', $ad_title, $ad_title]);
        $stmt->execute(['ad_target_url', $ad_target_url, $ad_target_url]);
        $stmt->execute(['ad_active', $ad_active, $ad_active]);
        if ($banner_image !== null) {
            $stmt->execute(['ad_banner_image', $banner_image, $banner_image]);
        }
        $msg = "تنظیمات تبلیغات با موفقیت ذخیره شد.";
    }

    // ======================================
    // 2. عملیات مربوط به صداها
    // ======================================
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
    // 3. عملیات مربوط به داستان‌ها
    // ======================================
    try { $pdo->exec("ALTER TABLE stories ADD COLUMN image_url VARCHAR(255) NULL"); } catch(Exception $e) {}

    if (isset($_POST['add_story'])) {
        $title = $_POST['title'];
        $content = $_POST['content_text'];
        $order = (int)$_POST['order_index'];
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imgName = time() . '_' . rand(100, 999) . '.' . $ext;
            $dest = __DIR__ . '/uploads/stories/' . $imgName;
            if (!is_dir(__DIR__ . '/uploads/stories')) mkdir(__DIR__ . '/uploads/stories', 0775, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) $image_url = 'uploads/stories/' . $imgName;
        }
        $stmt = $pdo->prepare("INSERT INTO stories (category_name, title, content_text, order_index, image_url) VALUES ('c1', ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $order, $image_url]);
        header("Location: admin.php?tab=stories&msg=added"); exit;
    }

    if (isset($_POST['edit_story'])) {
        $id = (int)$_POST['story_id'];
        $title = $_POST['title'];
        $content = $_POST['content_text'];
        $order = (int)$_POST['order_index'];
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imgName = time() . '_' . rand(100, 999) . '.' . $ext;
            $dest = __DIR__ . '/uploads/stories/' . $imgName;
            if (!is_dir(__DIR__ . '/uploads/stories')) mkdir(__DIR__ . '/uploads/stories', 0775, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) $image_url = 'uploads/stories/' . $imgName;
        }
        if ($image_url) {
            $stmt = $pdo->prepare("UPDATE stories SET title = ?, content_text = ?, order_index = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$title, $content, $order, $image_url, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE stories SET title = ?, content_text = ?, order_index = ? WHERE id = ?");
            $stmt->execute([$title, $content, $order, $id]);
        }
        header("Location: admin.php?tab=stories&msg=edited"); exit;
    }

    if (isset($_GET['del_story'])) {
        $id = (int)$_GET['del_story'];
        $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?"); 
        $stmt->execute([$id]);
        header("Location: admin.php?tab=stories&msg=deleted"); exit;
    }

    // ======================================
    // خواندن اطلاعات برای نمایش در تب‌ها
    // ======================================
    $recordings = $pdo->query("SELECT r.*, u.name as user_name FROM recordings r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $current_ad = $settings['ad_code'] ?? '';
    $ad_title = $settings['ad_title'] ?? '';
    $ad_banner_image = $settings['ad_banner_image'] ?? '';
    $ad_target_url = $settings['ad_target_url'] ?? '';
    $ad_active = isset($settings['ad_active']) ? ($settings['ad_active'] === '1') : true;

    try {
        $stories = $pdo->query("SELECT * FROM stories ORDER BY order_index ASC, id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stories = $pdo->query("SELECT * FROM stories ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    }

    // واکشی آمار اپلیکیشن (Analytics)
    $statInstalls = (int)$pdo->query("SELECT COUNT(*) FROM app_installs")->fetchColumn();
    $statOpens = (int)$pdo->query("SELECT COALESCE(SUM(open_count), 0) FROM app_installs")->fetchColumn();
    $statToday = (int)$pdo->query("SELECT COUNT(*) FROM app_installs WHERE DATE(last_seen) = CURDATE()")->fetchColumn();
    $statAdClicks = (int)$pdo->query("SELECT COUNT(*) FROM app_events WHERE event_type = 'ad_click'")->fetchColumn();
    $statRecordStarts = (int)$pdo->query("SELECT COUNT(*) FROM app_events WHERE event_type = 'record_start'")->fetchColumn();
    $statShares = (int)$pdo->query("SELECT COUNT(*) FROM app_events WHERE event_type = 'share_click'")->fetchColumn();

    $topStories = $pdo->query("SELECT event_label, COUNT(*) as views FROM app_events WHERE event_type = 'story_view' AND event_label != '' GROUP BY event_label ORDER BY views DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $recentEvents = $pdo->query("SELECT * FROM app_events ORDER BY id DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { die("DB Error: " . $e->getMessage()); }

$activeTab = $_GET['tab'] ?? 'analytics';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>پنل مدیریت هفت روز</title>
    <style>
        body { font-family: Tahoma, sans-serif; padding: 20px; background: #121212; color: white; margin: 0; line-height: 1.6; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        
        /* تب‌ها */
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-btn { background: #333; color: #fff; border: none; padding: 12px 24px; cursor: pointer; border-radius: 8px 8px 0 0; font-weight: bold; font-family: inherit; font-size: 14px; transition: 0.2s; }
        .tab-btn:hover { background: #444; }
        .tab-btn.active { background: gold; color: #000; }
        .tab-content { display: none; background: #1e1e1e; padding: 25px; border-radius: 0 8px 8px 8px; border: 1px solid #333; }
        .tab-content.active { display: block; }
        
        table { width: 100%; border-collapse: collapse; background: #1e1e1e; margin-bottom: 30px; }
        th, td { border: 1px solid #444; padding: 10px; text-align: center; }
        th { background: #000; color: gold; font-weight: bold; }
        
        .btn { padding: 7px 14px; color: #fff; text-decoration: none; border-radius: 5px; margin: 2px; display: inline-block; border:none; cursor:pointer; font-family: inherit; font-size: 13px; font-weight: bold; }
        .btn-red { background: #b30000; }
        .btn-gold { background: linear-gradient(45deg, #FFD700, #DAA520); color: #000; font-weight: bold; }
        .btn-green { background: #27ae60; }
        .btn-orange { background: #f39c12; }
        .btn-blue { background: #2980b9; }

        input, textarea, select { width: 100%; padding: 10px; background: #0a0a0a; color: #fff; border: 1px solid #444; font-family: Tahoma; box-sizing: border-box; border-radius: 6px; }
        input:focus, textarea:focus { border-color: gold; outline: none; }
        label { display: block; margin-top: 15px; margin-bottom: 6px; color: #ddd; font-weight: bold; text-align: right; }
        
        /* کارت‌های آماری */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 30px; }
        .stat-card { background: #262626; border: 1px solid #444; border-radius: 12px; padding: 20px; text-align: center; }
        .stat-card.primary { border-color: gold; }
        .stat-val { font-size: 32px; font-weight: bold; color: gold; margin-top: 10px; }
        .stat-label { font-size: 14px; color: #bbb; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 100; overflow-y: auto; }
        .modal-content { background: #1e1e1e; margin: 4% auto; padding: 25px; width: 65%; border-radius: 12px; border: 1px solid gold; max-width: 700px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="color:gold; margin:0;">پنل مدیریت اپلیکیشن هفت روز</h1>
        <a href="?logout=1" class="btn btn-red" style="padding:10px 20px;">خروج از پنل</a>
    </div>

    <?php if(isset($msg)) echo "<p style='background:#1b5e20; color:#fff; padding:12px; border-radius:6px; font-weight:bold;'>$msg</p>"; ?>
    <?php if(isset($_GET['msg'])) echo "<p style='background:#f57f17; color:#000; padding:12px; border-radius:6px; font-weight:bold;'>عملیات با موفقیت ثبت شد.</p>"; ?>

    <!-- منوی تب‌ها -->
    <div class="tabs">
        <button class="tab-btn <?php echo $activeTab == 'analytics' ? 'active' : ''; ?>" onclick="openTab('analytics')">📊 آمار و تحلیل کاربران (کانتر)</button>
        <button class="tab-btn <?php echo $activeTab == 'settings' ? 'active' : ''; ?>" onclick="openTab('settings')">💰 تنظیمات و مدیریت تبلیغات</button>
        <button class="tab-btn <?php echo $activeTab == 'recordings' ? 'active' : ''; ?>" onclick="openTab('recordings')">🎙️ صداهای ارسالی کاربران</button>
        <button class="tab-btn <?php echo $activeTab == 'stories' ? 'active' : ''; ?>" onclick="openTab('stories')">📖 مدیریت داستان‌ها (محتوا)</button>
    </div>

    <!-- بخش آمار و کانتر -->
    <div id="analytics" class="tab-content <?php echo $activeTab == 'analytics' ? 'active' : ''; ?>">
        <h2 style="color:gold; margin-top:0;">📊 آمار و رهگیری زنده اپلیکیشن</h2>
        <p style="color:#aaa;">این آمار مستقیماً از کاربران اپلیکیشن ثبت می‌شود و کاملاً بدون نیاز به فیلترشکن و دقیق کار می‌کند.</p>

        <div class="stat-grid">
            <div class="stat-card primary">
                <div class="stat-label">👥 تعداد نصب‌های یکتا (کاربران کل)</div>
                <div class="stat-val"><?php echo number_format($statInstalls); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🚀 کل دفعات باز شدن اپلیکیشن</div>
                <div class="stat-val" style="color:#4fc3f7;"><?php echo number_format($statOpens); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">📅 کاربران فعال امروز</div>
                <div class="stat-val" style="color:#81c784;"><?php echo number_format($statToday); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🖱️ کلیک روی تبلیغات</div>
                <div class="stat-val" style="color:#ffb74d;"><?php echo number_format($statAdClicks); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🎙️ شروع ضبط صدا</div>
                <div class="stat-val" style="color:#ba68c8;"><?php echo number_format($statRecordStarts); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🔗 اشتراک‌گذاری برنامه</div>
                <div class="stat-val" style="color:#4db6ac;"><?php echo number_format($statShares); ?></div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h3 style="color:gold;">🏆 ۱۰ داستان پرخواننده‌تر و محبوب</h3>
                <table>
                    <tr><th width="40">#</th><th>عنوان داستان</th><th width="80">بازدید</th></tr>
                    <?php if(empty($topStories)): ?>
                        <tr><td colspan="3" style="color:#888;">هنوز آماری ثبت نشده است.</td></tr>
                    <?php else: foreach($topStories as $idx => $ts): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td style="text-align:right; padding-right:15px;"><?php echo htmlspecialchars($ts['event_label']); ?></td>
                            <td><strong style="color:gold;"><?php echo number_format($ts['views']); ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </table>
            </div>

            <div>
                <h3 style="color:gold;">🕒 آخرین تعاملات و رویدادهای زنده</h3>
                <table>
                    <tr><th>دستگاه</th><th>رویداد</th><th>جزئیات</th><th>زمان</th></tr>
                    <?php if(empty($recentEvents)): ?>
                        <tr><td colspan="4" style="color:#888;">هیچ فعالیتی ثبت نشده است.</td></tr>
                    <?php else: foreach($recentEvents as $re): ?>
                        <tr>
                            <td dir="ltr" style="font-size:11px; color:#aaa;"><?php echo substr($re['device_id'], 0, 10); ?>...</td>
                            <td><span style="background:#333; padding:2px 8px; border-radius:4px; font-size:12px;"><?php echo htmlspecialchars($re['event_type']); ?></span></td>
                            <td style="text-align:right; font-size:12px;"><?php echo htmlspecialchars($re['event_label']); ?></td>
                            <td dir="ltr" style="font-size:11px; color:#888;"><?php echo $re['created_at']; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- بخش تنظیمات و تبلیغات -->
    <div id="settings" class="tab-content <?php echo $activeTab == 'settings' ? 'active' : ''; ?>">
        <h2 style="color:gold; margin-top:0;">💰 مدیریت تبلیغات هوشمند و درآمدزایی</h2>
        <p style="color:#aaa;">شما می‌توانید بنر تبلیغاتی اختصاصی (اسپانسری، افیلیت دیجی‌کالا، کانال تلگرام یا پیج اینستاگرام) یا اسکریپت‌های تبلیغاتی دیگر را در اینجا تنظیم کنید. این بنر در صفحه اصلی و پایین داستان‌ها نمایش داده می‌شود.</p>
        
        <form method="POST" enctype="multipart/form-data" style="max-width:700px; background:#262626; padding:20px; border-radius:10px; border:1px solid #444;">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                <input type="checkbox" name="ad_active" value="1" <?php echo $ad_active ? 'checked' : ''; ?> style="width:20px; height:20px;">
                <span style="color:gold; font-size:16px;">نمایش فعال بنر تبلیغاتی در اپلیکیشن</span>
            </label>

            <label>عنوان جذاب تبلیغ (مثال: تخفیف ویژه پاییزی / عضویت در کانال قصه‌گویی):</label>
            <input type="text" name="ad_title" value="<?php echo htmlspecialchars($ad_title); ?>" placeholder="عنوان نمایشی بنر">

            <label>لینک مقصد کلیک (URL به همراه https://):</label>
            <input type="url" name="ad_target_url" value="<?php echo htmlspecialchars($ad_target_url); ?>" placeholder="https://t.me/yourchannel یا لینک دلخواه">

            <label>آپلود عکس بنر تبلیغاتی (اختیاری):</label>
            <input type="file" name="banner_file" accept="image/*">
            <?php if(!empty($ad_banner_image)): ?>
                <div style="margin-top:10px;">
                    <span style="font-size:12px; color:#aaa;">بنر فعلی:</span><br>
                    <img src="/api/<?php echo $ad_banner_image; ?>" style="max-width:300px; max-height:100px; border-radius:8px; margin-top:5px; border:1px solid #555;">
                </div>
            <?php endif; ?>

            <label>یا آدرس اینترنتی عکس بنر (اگر فایل آپلود نمی‌کنید):</label>
            <input type="url" name="ad_banner_image_url" value="<?php echo htmlspecialchars($ad_banner_image); ?>" placeholder="https://example.com/banner.png">

            <label>کد یا متن مکمل تبلیغات (اختیاری):</label>
            <textarea name="ad_code" style="height:100px; direction:ltr;"><?php echo htmlspecialchars($current_ad); ?></textarea>

            <br><br>
            <button type="submit" name="save_ad" class="btn btn-gold" style="padding:12px 24px; font-size:15px; width:100%;">ذخیره و به‌روزرسانی تبلیغات در اپ</button>
        </form>
    </div>

    <!-- بخش صداها -->
    <div id="recordings" class="tab-content <?php echo $activeTab == 'recordings' ? 'active' : ''; ?>">
        <h2 style="color:gold; margin-top:0;">🎙️ صداهای ضبط شده توسط کاربران</h2>
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

    <!-- بخش داستان‌ها -->
    <div id="stories" class="tab-content <?php echo $activeTab == 'stories' ? 'active' : ''; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="color:gold; margin-top:0;">📖 لیست داستان‌های اپلیکیشن</h2>
            <button class="btn btn-green" style="padding:10px 18px;" onclick="openModal('addStoryModal')">+ افزودن داستان جدید</button>
        </div>
        <p style="color:#aaa;">عددی که در بخش «ترتیب» وارد می‌کنید، جایگاه داستان در اپلیکیشن را مشخص می‌کند (شماره کمتر = بالاتر).</p>
        
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

    <!-- مودال افزودن داستان -->
    <div id="addStoryModal" class="modal">
        <div class="modal-content">
            <h2 style="color:gold; margin-top:0;">افزودن داستان جدید</h2>
            <form method="POST" enctype="multipart/form-data">
                <label>عنوان داستان</label>
                <input type="text" name="title" required>
                
                <label>ترتیب نمایش</label>
                <input type="number" name="order_index" value="10" required>
                
                <label>عکس داستان (اختیاری)</label>
                <input type="file" name="image" accept="image/*">
                
                <label>متن داستان</label>
                <textarea name="content_text" rows="12" required></textarea>
                
                <div style="margin-top:20px; text-align:left;">
                    <button type="button" class="btn btn-red" onclick="closeModal('addStoryModal')">انصراف</button>
                    <button type="submit" name="add_story" class="btn btn-green">ثبت داستان</button>
                </div>
            </form>
        </div>
    </div>

    <!-- مودال ویرایش داستان -->
    <div id="editStoryModal" class="modal">
        <div class="modal-content">
            <h2 style="color:gold; margin-top:0;">ویرایش داستان</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="story_id" id="edit_id">
                <label>عنوان داستان</label>
                <input type="text" name="title" id="edit_title" required>
                
                <label>ترتیب نمایش</label>
                <input type="number" name="order_index" id="edit_order" required>
                
                <label>عکس داستان (اختیاری)</label>
                <input type="file" name="image" accept="image/*">
                
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
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
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
