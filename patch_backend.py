import re

def patch_admin():
    with open('backend/admin.php', 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Add image_url to DB if not exists
    if "image_url VARCHAR(255)" not in content:
        content = content.replace(
            "// 2. عملیات مربوط به داستان‌ها (متن)",
            "try { $pdo->exec(\"ALTER TABLE stories ADD COLUMN image_url VARCHAR(255) NULL\"); } catch(Exception $e) {}\n    // 2. عملیات مربوط به داستان‌ها (متن)"
        )

    # 2. Update add_story query
    if "INSERT INTO stories (category_name, title, content_text, order_index, image_url)" not in content:
        old_add = """    if (isset($_POST['add_story'])) {
        $title = $_POST['title'];
        $content = $_POST['content_text'];
        $order = (int)$_POST['order_index'];
        $stmt = $pdo->prepare("INSERT INTO stories (category_name, title, content_text, order_index) VALUES ('c1', ?, ?, ?)");
        $stmt->execute([$title, $content, $order]);
        header("Location: admin.php?tab=stories&msg=added"); exit;
    }"""
        new_add = """    if (isset($_POST['add_story'])) {
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
    }"""
        content = content.replace(old_add, new_add)

    # 3. Update edit_story query
    if "UPDATE stories SET title = ?, content_text = ?, order_index = ?, image_url = COALESCE(?, image_url)" not in content:
        old_edit = """    if (isset($_POST['edit_story'])) {
        $id = (int)$_POST['story_id'];
        $title = $_POST['title'];
        $content = $_POST['content_text'];
        $order = (int)$_POST['order_index'];
        $stmt = $pdo->prepare("UPDATE stories SET title = ?, content_text = ?, order_index = ? WHERE id = ?");
        $stmt->execute([$title, $content, $order, $id]);
        header("Location: admin.php?tab=stories&msg=edited"); exit;
    }"""
        new_edit = """    if (isset($_POST['edit_story'])) {
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
    }"""
        content = content.replace(old_edit, new_edit)

    # 4. Update HTML form to enctype="multipart/form-data" and add file input (add modal)
    content = content.replace('<form method="POST">', '<form method="POST" enctype="multipart/form-data">')
    
    add_file_input = """<label>ترتیب نمایش</label>
                <input type="number" name="order_index" value="10" required>
                
                <label>عکس داستان (اختیاری)</label>
                <input type="file" name="image" accept="image/*">
                """
    content = re.sub(r'<label>ترتیب نمایش.*?</label>\s*<input type="number" name="order_index" value="10" required>', add_file_input, content, flags=re.DOTALL)

    # 5. Update HTML form in edit modal
    edit_file_input = """<label>ترتیب نمایش</label>
                <input type="number" name="order_index" id="edit_order" required>
                
                <label>عکس داستان (اختیاری - اگر عکس جدیدی انتخاب کنید جایگزین قبلی می‌شود)</label>
                <input type="file" name="image" accept="image/*">
                """
    content = re.sub(r'<label>ترتیب نمایش.*?</label>\s*<input type="number" name="order_index" id="edit_order" required>', edit_file_input, content, flags=re.DOTALL)

    with open('backend/admin.php', 'w', encoding='utf-8') as f:
        f.write(content)

def patch_get_stories():
    with open('backend/get_stories.php', 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Just selecting everything handles image_url, but we should make sure the flutter app knows the full URL
    # Or flutter can prepend the baseUrl.
    # We don't need to patch get_stories.php if it uses SELECT * FROM stories.
    # Let's verify get_stories.php uses SELECT *

    pass

if __name__ == '__main__':
    patch_admin()
