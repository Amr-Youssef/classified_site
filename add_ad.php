<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$categories = get_categories();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
    $new_parent_name = trim($_POST['new_parent_name'] ?? '');
    $new_sub_name = trim($_POST['new_sub_name'] ?? '');
    $price = isset($_POST['price']) ? (float) $_POST['price'] : 0;
    $condition = $_POST['condition'] ?? '';
    $location = trim($_POST['location'] ?? '');
    // خصائص مرنة بحسب القسم
    $car_make = trim($_POST['car_make'] ?? '');
    $car_model = trim($_POST['car_model'] ?? '');
    $car_year = trim($_POST['car_year'] ?? '');
    $device_brand = trim($_POST['device_brand'] ?? '');
    $device_model = trim($_POST['device_model'] ?? '');
    // التحقق من الحقول الأساسية
    if (!$title || !$description || !$category_id) {
        $error = 'يرجى إدخال جميع الحقول المطلوبة.';
    } else {
        // منطق إنشاء الأقسام الذكي: إذا زود المستخدم أسماء جديدة، قم بإنشائها مع منع التكرار حسب الأب
        if ($new_parent_name !== '') {
            // أنشئ أو اجلب القسم الرئيسي
            $parent_id = get_or_create_category($new_parent_name, null);
            if ($parent_id) {
                $category_id = $parent_id;
            }
        } else {
            // إن لم يحدد اسم رئيسي جديد، يمكن أن يكون قد اختار parent_category من القائمة
            $parent_id = isset($_POST['parent_category']) && is_numeric($_POST['parent_category']) ? (int)$_POST['parent_category'] : null;
        }

        if ($new_sub_name !== '') {
            // أنشئ أو اجلب الفرعي تحت الأب المحدد/المنشأ الآن
            if (!isset($parent_id)) {
                // إذا لم يُحدد أب، استخدم category_id الحالي كأب إذا كان أعلى
                $parent_id = $category_id ?: null;
            }
            $sub_id = get_or_create_category($new_sub_name, $parent_id);
            if ($sub_id) {
                $category_id = $sub_id;
            }
        } else {
            // إن لم يحدد فرعي جديد، استخدم اختيار sub_category إن وجد
            if (empty($category_id)) {
                $sc = isset($_POST['sub_category']) && is_numeric($_POST['sub_category']) ? (int)$_POST['sub_category'] : 0;
                if ($sc) $category_id = $sc;
                elseif (!empty($parent_id)) $category_id = (int)$parent_id;
            }
        }

        // التحقق من حد الإعلانات اليومية (3 إعلانات كحد أقصى)
        $todayCount = get_user_ads_count_today($_SESSION['user_id']);
        if ($todayCount >= 3) {
            $error = 'لقد تجاوزت الحد اليومي لنشر الإعلانات (3 إعلانات يومياً). حاول غداً.';
        } else {
    // حفظ الإعلان في قاعدة البيانات
    // اقتباس اسم العمود المحجوز `condition` لتجنب أخطاء بناء الجملة
    $stmt = $pdo->prepare('INSERT INTO ads (user_id, category_id, title, description, price, location, `condition`, created_at) VALUES (:user_id, :category_id, :title, :description, :price, :location, :condition, NOW())');
        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'category_id' => $category_id,
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'location' => $location,
            'condition' => $condition
        ]);
        $ad_id = $pdo->lastInsertId();
        // حفظ خصائص متقدمة حسب القسم الأعلى (سيارات/أجهزة)
        $advanced_type = '';
        if ($category_id) {
            $path = get_category_path((int)$category_id);
            $top = $path ? $path[0] : null;
            if ($top) {
                if ((int)$top['id'] === 1) { $advanced_type = 'cars'; }
                elseif ((int)$top['id'] === 9) { $advanced_type = 'devices'; }
            }
        }
        if ($advanced_type === 'cars') {
            $attrs = [];
            if ($car_make) $attrs['car_make'] = $car_make;
            if ($car_model) $attrs['car_model'] = $car_model;
            if ($car_year) $attrs['car_year'] = $car_year;
            if (!empty($attrs)) save_ad_attributes((int)$ad_id, $attrs, true);
        } elseif ($advanced_type === 'devices') {
            $attrs = [];
            if ($device_brand) $attrs['device_brand'] = $device_brand;
            if ($device_model) $attrs['device_model'] = $device_model;
            if (!empty($attrs)) save_ad_attributes((int)$ad_id, $attrs, true);
        }
        // معالجة رفع الصور
        if (!empty($_FILES['images']['name'][0])) {
            $upload_dir = 'uploads/' . $ad_id;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                $tmp_name = $_FILES['images']['tmp_name'][$i];
                $name = basename($_FILES['images']['name'][$i]);
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                // السماح فقط بامتدادات الصور
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array(strtolower($ext), $allowed)) {
                    $new_name = uniqid() . '.' . $ext;
                    $target = $upload_dir . '/' . $new_name;
                    if (move_uploaded_file($tmp_name, $target)) {
                        // حفظ مسار الصورة في جدول الصور
                        $stmtImg = $pdo->prepare('INSERT INTO ad_images (ad_id, image_path) VALUES (:ad_id, :image_path)');
                        $stmtImg->execute([
                            'ad_id' => $ad_id,
                            'image_path' => $target
                        ]);
                    }
                }
            }
        }
        $success = 'تم نشر الإعلان بنجاح!';
    }
    }
}
?>
<?php require_once __DIR__ . '/header.php'; ?>
<h2>إضافة إعلان جديد</h2>
<?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <p><a href="ad_details.php?id=<?php echo $ad_id; ?>">عرض الإعلان</a></p>
<?php else: ?>
    <form method="POST" action="add_ad.php" enctype="multipart/form-data" class="add-ad-form form-modern" id="addAdForm">
        <label for="title">عنوان الإعلان:</label>
        <input type="text" name="title" id="title" class="input-modern" required>
        <label for="description">الوصف:</label>
        <textarea name="description" id="description" rows="5" class="input-modern" required></textarea>
    <label for="parent_category">القسم الرئيسي:</label>
    <select name="parent_category" id="parent_category" class="input-modern">
            <option value="">اختر القسم الرئيسي</option>
            <?php foreach ($categories as $cat): ?>
                <?php if (empty($cat['parent_id'])): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    <div class="hint">أو أضف قسماً رئيسياً جديداً:</div>
    <input type="text" name="new_parent_name" id="new_parent_name" class="input-modern" placeholder="أدخل اسم قسم رئيسي جديد (اختياري)">

    <label for="sub_category">القسم الفرعي (اختياري):</label>
    <select name="sub_category" id="sub_category" class="input-modern">
            <option value="">اختر فرعاً (أو اتركه لاختيار الرئيسي)</option>
        </select>
    <div class="hint">أو أضف قسماً فرعياً جديداً:</div>
    <input type="text" name="new_sub_name" id="new_sub_name" class="input-modern" placeholder="أدخل اسم قسم فرعي جديد (اختياري)">
        <!-- حقل مخفي لإرسال category_id الفعلية -->
        <input type="hidden" name="category_id" id="category_id" value="">
        <label for="price">السعر (اختياري):</label>
        <input type="number" step="0.01" name="price" id="price" class="input-modern">
        <label for="location">الموقع (اختياري):</label>
        <input type="text" name="location" id="location" class="input-modern">
        <label for="condition">حالة الإعلان:</label>
        <select name="condition" id="condition" class="input-modern">
            <option value="new">جديد</option>
            <option value="used">مستعمل</option>
        </select>
        <!-- حقول خصائص متقدمة تُعرض ديناميكياً -->
        <div id="advancedFields" style="display:none;">
            <div data-type="cars" style="display:none;">
                <label>الشركة المصنعة:</label>
                <input type="text" name="car_make" class="input-modern" placeholder="مثال: تويوتا">
                <label>الموديل:</label>
                <input type="text" name="car_model" class="input-modern" placeholder="مثال: كورولا">
                <label>سنة التصنيع:</label>
                <input type="number" name="car_year" class="input-modern" placeholder="مثال: 2018">
            </div>
            <div data-type="devices" style="display:none;">
                <label>العلامة التجارية:</label>
                <input type="text" name="device_brand" class="input-modern" placeholder="مثال: آبل">
                <label>الموديل:</label>
                <input type="text" name="device_model" class="input-modern" placeholder="مثال: آيفون 13">
            </div>
        </div>
        <label for="images">تحميل صور (يمكن اختيار عدة صور):</label>
        <input type="file" name="images[]" id="images" multiple accept="image/*" class="input-modern">
        <button type="submit" class="btn-modern">نشر الإعلان</button>
    </form>
    <script>
        // جمع الأقسام لتحويلها إلى خريطة parent -> children
        const categories = <?php echo json_encode($categories, JSON_HEX_TAG); ?>;
        const childrenMap = {};
        categories.forEach(c => {
            const pid = c.parent_id || 0;
            if (!childrenMap[pid]) childrenMap[pid] = [];
            childrenMap[pid].push(c);
        });

        const parentSelect = document.getElementById('parent_category');
        const subSelect = document.getElementById('sub_category');
    const hiddenCat = document.getElementById('category_id');
    const advWrap = document.getElementById('advancedFields');
    const advCars = advWrap.querySelector('[data-type="cars"]');
    const advDevices = advWrap.querySelector('[data-type="devices"]');

        function fillSub(parentId) {
            // مسح الفرعية
            subSelect.innerHTML = '<option value="">اختر فرعاً (أو اتركه لاختيار الرئيسي)</option>';
            if (childrenMap[parentId]) {
                childrenMap[parentId].forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    subSelect.appendChild(opt);
                });
            }
            // تحديث hidden
            hiddenCat.value = parentId || '';
            updateAdvancedFields(parentId);
        }

        function getTopCategoryId(catId) {
            catId = Number(catId) || 0;
            if (!catId) return 0;
            // اصعد للأعلى حتى الجذر
            const map = new Map(categories.map(c => [c.id, c]));
            let cur = map.get(catId);
            while (cur && cur.parent_id) {
                cur = map.get(cur.parent_id);
            }
            return cur ? cur.id : catId;
        }

        function updateAdvancedFields(selectedId) {
            const topId = getTopCategoryId(selectedId);
            // 1 = سيارات, 9 = أجهزة بحسب seed
            advWrap.style.display = 'block';
            advCars.style.display = (topId === 1) ? 'block' : 'none';
            advDevices.style.display = (topId === 9) ? 'block' : 'none';
            if (advCars.style.display === 'none' && advDevices.style.display === 'none') {
                advWrap.style.display = 'none';
            }
        }

    parentSelect.addEventListener('change', e => {
            const pid = e.target.value || 0;
            fillSub(pid);
        });

        subSelect.addEventListener('change', e => {
            // إذا اختير فرعي، استخدمه كـ category_id
            hiddenCat.value = e.target.value || parentSelect.value || '';
            updateAdvancedFields(hiddenCat.value);
        });

        // عند إرسال النموذج، تأكد من وجود category_id (أو وجود أسماء جديدة لتوليده)
        document.getElementById('addAdForm').addEventListener('submit', function(e) {
            const newParent = document.getElementById('new_parent_name').value.trim();
            const newSub = document.getElementById('new_sub_name').value.trim();
            if (!hiddenCat.value && !newParent && !newSub) {
                e.preventDefault();
                alert('يرجى اختيار قسم (أو إدخال قسم جديد) للإعلان.');
            }
        });

        // تهيئة مبدئية
    if (parentSelect.value) { fillSub(parentSelect.value); }
    </script>
<?php endif; ?>
<?php
require_once __DIR__ . '/footer.php';