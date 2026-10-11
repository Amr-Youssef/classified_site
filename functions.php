<?php
// مجموعة من الدوال المساعدة للموقع
require_once __DIR__ . '/config.php';

/**
 * التحقق ما إذا كان المستخدم يمتلك صلاحيات الإدارة.
 * يعتمد ذلك على المصفوفة $admin_users المحددة في config.php.
 * @param int $user_id
 * @return bool
 */
function is_admin_user($user_id) {
    global $pdo;
    // إذا لم يتم توفير معرّف أو كان فارغاً، فليس مديراً
    if (empty($user_id)) {
        return false;
    }
    // إذا كانت قيمة is_admin مخزنة في الجلسة، استخدمها مباشرةً
    if (isset($_SESSION['is_admin'])) {
        return ((int)$_SESSION['is_admin']) === 1;
    }
    // جلب الحالة من قاعدة البيانات عند الحاجة
    $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = :id');
    $stmt->execute(['id' => (int)$user_id]);
    $isAdmin = $stmt->fetchColumn();
    return ((int)$isAdmin) === 1;
}

/**
 * تطبيع رقم الهاتف لحفظه والبحث عنه بشكل موحد.
 * يحذف كل الأحرف غير الرقمية ويُعيد سلسلة من الأرقام فقط.
 * @param string $phone
 * @return string
 */
function normalize_phone($phone) {
    $p = trim((string)$phone);
    if ($p === '') return '';
    // إزالة كل شيء ما عدا الأرقام
    $normalized = preg_replace('/\D+/', '', $p);
    return $normalized;
}

/**
 * صياغة رقم الجوال للعرض: تضيف بادئة + على الرقم المطبع.
 * إذا كان الرقم فارغًا تعيد سلسلة فارغة.
 * @param string $phone
 * @return string
 */
function format_phone_display($phone, $forTel = false) {
    $p = normalize_phone($phone);
    if ($p === '') return '';

    // إزالة بادئة 00 إن وُجدت
    if (substr($p, 0, 2) === '00') {
        $p = substr($p, 2);
    }

    $country = '';
    $national = '';

    // إذا كان الرقم يبدأ بـ966 (مع رمز الدولة)
    if (substr($p, 0, 3) === '966') {
        $country = '966';
        $national = substr($p, 3);
    }
    // لو كان رقماً محلياً يبدأ بصفر بطول 10 (مثال: 0544205314)
    elseif (strlen($p) === 10 && $p[0] === '0') {
        $country = '966';
        $national = substr($p, 1);
    }
    // لو كان رقماً محلياً بدون الصفر (9 أرقام يبدأ بـ5)
    elseif (strlen($p) === 9 && $p[0] === '5') {
        $country = '966';
        $national = $p;
    }
    // غير ذلك، نُعامل الرقم كدولي كما هو
    else {
        $tel = '+' . $p;
        if ($forTel) return $tel;
        return $tel;
    }

    // canonical tel form (no spaces)
    $tel = '+' . $country . $national;
    if ($forTel) return $tel;

    // عرض مُنسَّق لرقم السعودية: +966 54 420 5314 (تقسيم national إلى 2-3-4)
    $n = $national;
    $p1 = substr($n, 0, 2);
    $p2 = substr($n, 2, 3);
    $p3 = substr($n, 5);
    $display = '+' . $country;
    if ($p1 !== false && $p1 !== '') $display .= ' ' . $p1;
    if ($p2 !== false && $p2 !== '') $display .= ' ' . $p2;
    if ($p3 !== false && $p3 !== '') $display .= ' ' . $p3;
    return $display;
}

/**
 * التحقق من بيانات تسجيل الدخول للمستخدم (باستخدام البريد أو رقم الجوال).
 *
 * @param string $identifier (email or phone)
 * @param string $password
 * @return array|null
 */
function authenticate_user($identifier, $password) {
    global $pdo;
    // إذا تضمن المعرف علامة @ فاعتبره بريدًا إلكترونيًا، وإلا اعتبره رقم هاتف
    if (strpos($identifier, '@') !== false) {
        $stmt = $pdo->prepare('SELECT id, password, is_admin FROM users WHERE email = :ident LIMIT 1');
        $stmt->execute(['ident' => $identifier]);
    } else {
        $phone = normalize_phone($identifier);
        $stmt = $pdo->prepare('SELECT id, password, is_admin FROM users WHERE phone = :phone LIMIT 1');
        $stmt->execute(['phone' => $phone]);
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return null;
}

/**
 * إنشاء رمز "تذكرني" آمن وتخزينه وإرجاع قيمة الكوكيز (selector|validator)
 */
function remember_me_issue($user_id, $days = 30) {
    global $pdo;
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $hash = hash('sha256', $validator);
    $expires = date('Y-m-d H:i:s', time() + (max(1, (int)$days) * 86400));
    $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (:uid, :sel, :val, :exp)');
    $stmt->execute(['uid' => (int)$user_id, 'sel' => $selector, 'val' => $hash, 'exp' => $expires]);
    return $selector . '|' . $validator;
}

/**
 * محاولة تسجيل دخول تلقائي عبر كوكيز التذكر
 */
function try_auto_login_from_cookie() {
    global $pdo;
    if (!empty($_SESSION['user_id'])) return; // already logged in
    $cookie = $_COOKIE['remember_me'] ?? '';
    if (!$cookie || strpos($cookie, '|') === false) return;
    list($selector, $validator) = explode('|', $cookie, 2);
    $selector = preg_replace('/[^a-f0-9]/', '', strtolower($selector));
    $validator = preg_replace('/[^a-f0-9]/', '', strtolower($validator));
    if ($selector === '' || $validator === '') return;
    $stmt = $pdo->prepare('SELECT user_id, validator_hash, expires_at FROM remember_tokens WHERE selector = :sel LIMIT 1');
    $stmt->execute(['sel' => $selector]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;
    if (strtotime($row['expires_at']) < time()) {
        // expired, cleanup
        $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :sel')->execute(['sel' => $selector]);
        return;
    }
    $calc = hash('sha256', $validator);
    if (!hash_equals($row['validator_hash'], $calc)) {
        // possible theft; revoke this selector
        $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :sel')->execute(['sel' => $selector]);
        return;
    }
    // success: log in the user
    $_SESSION['user_id'] = (int)$row['user_id'];
    // prefetch is_admin
    $_SESSION['is_admin'] = is_admin_user($_SESSION['user_id']) ? 1 : 0;
    // rotate token (one-time use)
    $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :sel')->execute(['sel' => $selector]);
    $cookieVal = remember_me_issue($_SESSION['user_id']);
    // re-set cookie below in config bootstrap hook
    set_remember_cookie($cookieVal);
}

/**
 * ضبط الكوكيز بشكل آمن للمتصفح (HTTPOnly)
 */
function set_remember_cookie($value, $days = 30) {
    $params = session_get_cookie_params();
    $expire = time() + (max(1, (int)$days) * 86400);
    // ملاحظة: Secure=true يعمل فقط عبر HTTPS؛ في البيئة المحلية قد لا يكون مفعلًا
    setcookie('remember_me', $value, [
        'expires' => $expire,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * مسح جميع رموز التذكر للمستخدم ومسح الكوكيز
 */
function forget_me($user_id = null) {
    global $pdo;
    if ($user_id) {
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :uid');
        $stmt->execute(['uid' => (int)$user_id]);
    }
    setcookie('remember_me', '', time() - 3600, '/');
}

/**
 * إنشاء حساب مستخدم جديد.
 *
 * @param string $username
 * @param string $email
 * @param string $password
 * @return bool|string إرجاع true في حالة النجاح أو رسالة خطأ في حالة الفشل
 */
function create_user($username, $email, $password, $phone = '', $avatar = '') {
    global $pdo;
    // التحقق من وجود البريد مسبقاً
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        return 'البريد الإلكتروني مستخدم مسبقاً.';
    }
    // تطبيع وفحص رقم الهاتف إذا وُفر، نخزنه NULL إذا لم يُزوَّد
    $phoneNorm = null;
    if (!empty($phone)) {
        $phoneNorm = normalize_phone($phone);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = :phone');
        $stmt->execute(['phone' => $phoneNorm]);
        if ($stmt->fetch()) {
            return 'رقم الجوال مستخدم مسبقاً.';
        }
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    // إنشاء مستخدم جديد مع تعيين is_admin = 0 افتراضياً
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, phone, avatar, is_admin, created_at) VALUES (:username, :email, :password, :phone, :avatar, :is_admin, NOW())');
    $result = $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password' => $hash,
        'phone' => $phoneNorm,
        'avatar' => $avatar,
        'is_admin' => 0
    ]);
    return $result;
}

/**
 * تحديث ملف المستخدم (الاسم، الهاتف، الصورة)
 */
function update_user_profile($user_id, $username, $phone, $avatarPath = '') {
    global $pdo;
    // تطبيع رقم الهاتف قبل الحفظ
    $phoneNorm = normalize_phone($phone);
    if ($avatarPath !== '') {
        $stmt = $pdo->prepare('UPDATE users SET username = :username, phone = :phone, avatar = :avatar WHERE id = :id');
        return $stmt->execute(['username' => $username, 'phone' => $phoneNorm, 'avatar' => $avatarPath, 'id' => $user_id]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET username = :username, phone = :phone WHERE id = :id');
        return $stmt->execute(['username' => $username, 'phone' => $phoneNorm, 'id' => $user_id]);
    }
}

/**
 * جلب قائمة الأقسام (مع العنوان والأب) من قاعدة البيانات.
 *
 * @return array
 */
function get_categories() {
    global $pdo;
    $stmt = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY parent_id, id');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * جلب قسم بالاسم ضمن نفس الأب (حساس للأب وليس لحالة الأحرف)
 */
function get_category_by_name_under_parent($name, $parent_id = null) {
    global $pdo;
    $name = trim((string)$name);
    if ($name === '') return null;
    if ($parent_id === '' || $parent_id === false) $parent_id = null;
    if ($parent_id === 0) $parent_id = null;
    if ($parent_id === null) {
        $stmt = $pdo->prepare('SELECT id, parent_id, name FROM categories WHERE parent_id IS NULL AND name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
    } else {
        $stmt = $pdo->prepare('SELECT id, parent_id, name FROM categories WHERE parent_id = :pid AND name = :name LIMIT 1');
        $stmt->execute(['pid' => (int)$parent_id, 'name' => $name]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * الحصول أو الإنشاء لقسم باسم محدد تحت أب محدد مع ضمان عدم التكرار لكل أب.
 * يعيد معرّف القسم الذي تم إيجاده أو إنشاؤه.
 */
function get_or_create_category($name, $parent_id = null) {
    global $pdo;
    $name = trim((string)$name);
    if ($name === '') return 0;
    if ($parent_id === '' || $parent_id === false) $parent_id = null;
    if ($parent_id === 0) $parent_id = null;

    // أولاً حاول الجلب
    $existing = get_category_by_name_under_parent($name, $parent_id);
    if ($existing) return (int)$existing['id'];

    // إن لم يوجد، أنشئه
    if ($parent_id === null) {
        $stmt = $pdo->prepare('INSERT INTO categories (parent_id, name) VALUES (NULL, :name)');
        try {
            $stmt->execute(['name' => $name]);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            // في حال وجود فهرس فريد وتنافس، حاول الجلب مجدداً
            $existing = get_category_by_name_under_parent($name, null);
            return $existing ? (int)$existing['id'] : 0;
        }
    } else {
        $stmt = $pdo->prepare('INSERT INTO categories (parent_id, name) VALUES (:pid, :name)');
        try {
            $stmt->execute(['pid' => (int)$parent_id, 'name' => $name]);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            $existing = get_category_by_name_under_parent($name, $parent_id);
            return $existing ? (int)$existing['id'] : 0;
        }
    }
}

/**
 * اقتراح مواضيع فرعية ذكية لقسم أب معيّن.
 * ترجع مصفوفة بعناصر تحمل نوع العنصر (category/attribute) والعرض والعدد.
 * - أولاً: تُرجع الأقسام الفرعية مرتبة بعدد الإعلانات.
 * - ثانياً (إذا لم يوجد أقسام): تُستدل من ad_attributes الأكثر شيوعاً ضمن إعلانات الأب.
 */
function get_smart_subtopics($parent_id, $limit = 10) {
    global $pdo;
    // كاش بسيط على مستوى الطلب لتقليل الاستعلامات المتكررة
    static $cache = [];
    $parent_id = (int)$parent_id;
    $limit = max(1, (int)$limit);
    $cacheKey = $parent_id . ':' . $limit;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    $out = [];
    // 1) الأقسام الفرعية مرتبة بعدد الإعلانات
    $sql = 'SELECT c.id, c.name, COUNT(a.id) AS ads_count
            FROM categories c
            LEFT JOIN ads a ON a.category_id = c.id
            WHERE c.parent_id = :pid
            GROUP BY c.id, c.name
            ORDER BY ads_count DESC, c.id ASC
            LIMIT :lim';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':pid', $parent_id, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if ((int)$r['ads_count'] > 0) {
            $item = [
                'type' => 'category',
                'id' => (int)$r['id'],
                'label' => $r['name'],
                'count' => (int)$r['ads_count'],
            ];
            // توافق مع واجهات قديمة تتوقع مفاتيح name/cnt
            $item['name'] = $item['label'];
            $item['cnt'] = $item['count'];
            $out[] = $item;
        }
    }
    if (!empty($out)) {
        // خزّن في الكاش وأعد
        $cache[$cacheKey] = $out;
        return $out;
    }

    // 2) إن لم توجد أقسام حقيقية، استدل من خصائص الإعلانات الأكثر شيوعاً
    // اجمع الإعلانات التي تقع مباشرة تحت الأب أو تحت فروعه المباشرة
    $attrSql = 'SELECT aa.attr_key, aa.attr_value, COUNT(*) AS cnt
                FROM ad_attributes aa
                JOIN ads a ON aa.ad_id = a.id
                WHERE a.category_id IN (
                    SELECT id FROM categories WHERE parent_id = :pid
                ) OR a.category_id = :pid
                GROUP BY aa.attr_key, aa.attr_value
                ORDER BY cnt DESC
                LIMIT :lim';
    $stmt2 = $pdo->prepare($attrSql);
    $stmt2->bindValue(':pid', $parent_id, PDO::PARAM_INT);
    $stmt2->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt2->execute();
    $attrs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($attrs as $a) {
        $label = $a['attr_value'];
        if ($label === '' || $label === null) continue;
        $item = [
            'type' => 'attribute',
            'key' => $a['attr_key'],
            'value' => $a['attr_value'],
            'label' => $label,
            'count' => (int)$a['cnt'],
        ];
        // توافق مع واجهات قديمة
        $item['name'] = $item['label'];
        $item['cnt'] = $item['count'];
        $out[] = $item;
    }
    // خزّن في الكاش وأعد
    $cache[$cacheKey] = $out;
    return $out;
}

/**
 * جلب إعلانات باستخدام فلترة بسيطة.
 *
 * @param array $filters
 * @return array
 */
function get_ads($filters = []) {
    global $pdo;
    $sql = 'SELECT ads.*, users.username, categories.name AS category_name FROM ads
            JOIN users ON ads.user_id = users.id
            JOIN categories ON ads.category_id = categories.id
            WHERE 1';
    $params = [];
    // فلتر حسب الكلمات المفتاحية
    if (!empty($filters['keyword'])) {
        $sql .= ' AND (
            ads.title LIKE :keyword
            OR ads.description LIKE :keyword
            OR categories.name LIKE :keyword
            OR ads.location LIKE :keyword
            OR EXISTS (
                SELECT 1 FROM ad_attributes aa
                WHERE aa.ad_id = ads.id AND aa.attr_value LIKE :keyword
            )
        )';
        $params['keyword'] = '%' . $filters['keyword'] . '%';
    }
    // فلتر حسب القسم
    if (!empty($filters['category_id'])) {
        $sql .= ' AND ads.category_id = :category_id';
        $params['category_id'] = $filters['category_id'];
    }
    // فلتر حسب السعر الأدنى والأعلى
    if (!empty($filters['min_price'])) {
        $sql .= ' AND ads.price >= :min_price';
        $params['min_price'] = $filters['min_price'];
    }
    if (!empty($filters['max_price'])) {
        $sql .= ' AND ads.price <= :max_price';
        $params['max_price'] = $filters['max_price'];
    }
    // حالة الإعلان (جديد/مستعمل)
    if (!empty($filters['condition'])) {
    // اقتباس اسم العمود المحجوز لمنع أخطاء SQL
    $sql .= ' AND ads.`condition` = :condition';
        $params['condition'] = $filters['condition'];
    }

    // الفلترة حسب المدينة/الموقع
    if (!empty($filters['city'])) {
        // مطابقة دقيقة أو شبه جزئية حسب المدخل
        $sql .= ' AND (ads.location = :city OR ads.location LIKE :city_like)';
        $params['city'] = $filters['city'];
        $params['city_like'] = '%' . $filters['city'] . '%';
    }

    // فلاتر مخصصة حسب القسم باستخدام جدول خصائص مرن ad_attributes
    // سيارات: الشركة، الموديل، سنة من/إلى
    if (!empty($filters['car_make'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes aa1 WHERE aa1.ad_id = ads.id AND aa1.attr_key = 'car_make' AND aa1.attr_value LIKE :car_make)";
        $params['car_make'] = '%' . $filters['car_make'] . '%';
    }
    if (!empty($filters['car_model'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes aa2 WHERE aa2.ad_id = ads.id AND aa2.attr_key = 'car_model' AND aa2.attr_value LIKE :car_model)";
        $params['car_model'] = '%' . $filters['car_model'] . '%';
    }
    if (!empty($filters['car_year_from'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes aaf WHERE aaf.ad_id = ads.id AND aaf.attr_key = 'car_year' AND CAST(aaf.attr_value AS UNSIGNED) >= :car_year_from)";
        $params['car_year_from'] = (int)$filters['car_year_from'];
    }
    if (!empty($filters['car_year_to'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes aat WHERE aat.ad_id = ads.id AND aat.attr_key = 'car_year' AND CAST(aat.attr_value AS UNSIGNED) <= :car_year_to)";
        $params['car_year_to'] = (int)$filters['car_year_to'];
    }

    // أجهزة: العلامة التجارية، الموديل
    if (!empty($filters['device_brand'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes dab WHERE dab.ad_id = ads.id AND dab.attr_key = 'device_brand' AND dab.attr_value LIKE :device_brand)";
        $params['device_brand'] = '%' . $filters['device_brand'] . '%';
    }
    if (!empty($filters['device_model'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes dam WHERE dam.ad_id = ads.id AND dam.attr_key = 'device_model' AND dam.attr_value LIKE :device_model)";
        $params['device_model'] = '%' . $filters['device_model'] . '%';
    }
    // الترتيب
    $orderBy = 'ads.created_at DESC';
    if (!empty($filters['sort'])) {
        switch ($filters['sort']) {
            case 'price_asc':
                $orderBy = 'ads.price ASC, ads.created_at DESC';
                break;
            case 'price_desc':
                $orderBy = 'ads.price DESC, ads.created_at DESC';
                break;
            case 'oldest':
                $orderBy = 'ads.created_at ASC';
                break;
            case 'newest':
            default:
                $orderBy = 'ads.created_at DESC';
        }
    }
    $sql .= ' ORDER BY ' . $orderBy;

    // ترقيم الصفحات (LIMIT/OFFSET)
    if (!empty($filters['per_page'])) {
        $perPage = max(1, (int)$filters['per_page']);
        $page = !empty($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $offset = ($page - 1) * $perPage;
        // إدراج القيم الصحيحة مباشرة لتجنب مشاكل bind في LIMIT/OFFSET
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * عدّ عدد الإعلانات المطابقة للفلاتر (لاستخدامه في ترقيم الصفحات)
 */
function count_ads($filters = []) {
    global $pdo;
    $sql = 'SELECT COUNT(*) FROM ads
            JOIN users ON ads.user_id = users.id
            JOIN categories ON ads.category_id = categories.id
            WHERE 1';
    $params = [];
    if (!empty($filters['keyword'])) {
        $sql .= ' AND (
            ads.title LIKE :keyword
            OR ads.description LIKE :keyword
            OR categories.name LIKE :keyword
            OR ads.location LIKE :keyword
            OR EXISTS (
                SELECT 1 FROM ad_attributes aa
                WHERE aa.ad_id = ads.id AND aa.attr_value LIKE :keyword
            )
        )';
        $params['keyword'] = '%' . $filters['keyword'] . '%';
    }
    if (!empty($filters['category_id'])) {
        $sql .= ' AND ads.category_id = :category_id';
        $params['category_id'] = $filters['category_id'];
    }
    if (!empty($filters['min_price'])) {
        $sql .= ' AND ads.price >= :min_price';
        $params['min_price'] = $filters['min_price'];
    }
    if (!empty($filters['max_price'])) {
        $sql .= ' AND ads.price <= :max_price';
        $params['max_price'] = $filters['max_price'];
    }
    if (!empty($filters['condition'])) {
        $sql .= ' AND ads.`condition` = :condition';
        $params['condition'] = $filters['condition'];
    }
    if (!empty($filters['city'])) {
        $sql .= ' AND (ads.location = :city OR ads.location LIKE :city_like)';
        $params['city'] = $filters['city'];
        $params['city_like'] = '%' . $filters['city'] . '%';
    }
    if (!empty($filters['car_make'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes aa1 WHERE aa1.ad_id = ads.id AND aa1.attr_key = 'car_make' AND aa1.attr_value LIKE :car_make)";
        $params['car_make'] = '%' . $filters['car_make'] . '%';
    }
    if (!empty($filters['car_model'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes aa2 WHERE aa2.ad_id = ads.id AND aa2.attr_key = 'car_model' AND aa2.attr_value LIKE :car_model)";
        $params['car_model'] = '%' . $filters['car_model'] . '%';
    }
    if (!empty($filters['car_year_from'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes aaf WHERE aaf.ad_id = ads.id AND aaf.attr_key = 'car_year' AND CAST(aaf.attr_value AS UNSIGNED) >= :car_year_from)";
        $params['car_year_from'] = (int)$filters['car_year_from'];
    }
    if (!empty($filters['car_year_to'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes aat WHERE aat.ad_id = ads.id AND aat.attr_key = 'car_year' AND CAST(aat.attr_value AS UNSIGNED) <= :car_year_to)";
        $params['car_year_to'] = (int)$filters['car_year_to'];
    }
    if (!empty($filters['device_brand'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes dab WHERE dab.ad_id = ads.id AND dab.attr_key = 'device_brand' AND dab.attr_value LIKE :device_brand)";
        $params['device_brand'] = '%' . $filters['device_brand'] . '%';
    }
    if (!empty($filters['device_model'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM ad_attributes dam WHERE dam.ad_id = ads.id AND dam.attr_key = 'device_model' AND dam.attr_value LIKE :device_model)";
        $params['device_model'] = '%' . $filters['device_model'] . '%';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/**
 * تجزئة الكلمات إلى رموز بسيطة (تفصل على الفراغات وتزيل الرموز الشائعة)
 */
function tokenize_keywords($q) {
    $q = trim((string)$q);
    if ($q === '') return [];
    // استبدل الفواصل والواصفات بمسافات ثم قسّم
    $q = preg_replace('/[\,\.;\-\_\+\|\(\)\[\]\{\}]+/u', ' ', $q);
    $parts = preg_split('/\s+/u', $q);
    // تجاهل الكلمات القصيرة جداً
    $tokens = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && mb_strlen($p) >= 2) $tokens[] = $p;
    }
    return array_slice($tokens, 0, 8); // حد أقصى 8 رموز
}

/**
 * بحث متقدم للإعلانات: يُطبّق AND على الرموز، وكل رمز يُطابق أيّاً من الحقول.
 */
function search_ads_advanced($keyword, $extraFilters = []) {
    global $pdo;
    $tokens = tokenize_keywords($keyword);
    if (empty($tokens)) return [];
    $sql = 'SELECT ads.*, users.username, categories.name AS category_name FROM ads
            JOIN users ON ads.user_id = users.id
            JOIN categories ON ads.category_id = categories.id
            WHERE 1';
    $params = [];
    // طبّق فلاتر إضافية (مماثلة لـ get_ads)
    if (!empty($extraFilters['category_id'])) {
        $sql .= ' AND ads.category_id = :category_id';
        $params['category_id'] = (int)$extraFilters['category_id'];
    }
    if (!empty($extraFilters['min_price'])) {
        $sql .= ' AND ads.price >= :min_price';
        $params['min_price'] = $extraFilters['min_price'];
    }
    if (!empty($extraFilters['max_price'])) {
        $sql .= ' AND ads.price <= :max_price';
        $params['max_price'] = $extraFilters['max_price'];
    }
    if (!empty($extraFilters['city'])) {
        $sql .= ' AND (ads.location = :city OR ads.location LIKE :city_like)';
        $params['city'] = $extraFilters['city'];
        $params['city_like'] = '%' . $extraFilters['city'] . '%';
    }
    // شروط الرموز (AND عبر الرموز)؛ لكل رمز OR عبر الحقول
    $i = 0;
    foreach ($tokens as $t) {
        $i++;
        $k = ":t$i";
        $params[$k] = '%' . $t . '%';
        $sql .= " AND (ads.title LIKE $k OR ads.description LIKE $k OR categories.name LIKE $k OR ads.location LIKE $k OR EXISTS (SELECT 1 FROM ad_attributes aa WHERE aa.ad_id = ads.id AND aa.attr_value LIKE $k))";
    }
    $sql .= ' ORDER BY ads.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * بحث عن الأقسام بالاسم مع عدّاد الإعلانات داخلها
 */
function search_categories($keyword, $limit = 10) {
    global $pdo;
    $tokens = tokenize_keywords($keyword);
    if (empty($tokens)) return [];
    $sql = 'SELECT c.id, c.parent_id, c.name, COUNT(a.id) AS ads_count
            FROM categories c
            LEFT JOIN ads a ON a.category_id = c.id
            WHERE 1';
    $params = [];
    $i = 0;
    foreach ($tokens as $t) {
        $i++;
        $k = ":w$i";
        $params[$k] = '%' . $t . '%';
        $sql .= " AND c.name LIKE $k"; // AND عبر الرموز لتحسين الدقّة
    }
    $sql .= ' GROUP BY c.id, c.parent_id, c.name ORDER BY ads_count DESC, c.name ASC LIMIT :lim';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v, PDO::PARAM_STR); }
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * جلب قائمة المدن/المواقع المميزة من الإعلانات لعرضها في الفلاتر.
 * @return array
 */
function get_distinct_cities() {
    global $pdo;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $stmt = $pdo->query("SELECT DISTINCT TRIM(location) AS city FROM ads WHERE location IS NOT NULL AND TRIM(location) <> '' ORDER BY city ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // دمج قائمة المدن القياسية مع الموجودة في الإعلانات
    $catalog = get_saudi_cities();
    $all = array_merge($catalog, is_array($rows) ? $rows : []);
    // تنظيف وترتيب
    $unique = [];
    foreach ($all as $c) {
        $c = trim((string)$c);
        if ($c === '') continue;
        if (!in_array($c, $unique, true)) $unique[] = $c;
    }
    // ترتيب طبيعي؛ قد لا يكون مثالياً للأحرف العربية لكن يعطي ترتيباً ثابتاً
    sort($unique, SORT_NATURAL);
    $cache = $unique;
    return $cache;
}

/**
 * قائمة قياسية لمدن المملكة العربية السعودية (أسماء شائعة بالعربية).
 * يمكن توسيعها لاحقاً بسهولة. مرتبة تقريبياً أبجدياً.
 */
function get_saudi_cities() {
    // ملاحظة: هذه ليست حصرية لكل المحافظات والقرى، لكنها تغطي معظم المدن المعروفة
    static $cities = null;
    if ($cities !== null) return $cities;
    $cities = [
        // الرياض والمنطقة الوسطى
        'الرياض','الخرج','الدرعية','ضرما','حوطة بني تميم','الأفلاج','وادي الدواسر','السليل','الزلفي','الغاط','المجمعة','شقراء','ثادق','حريملاء','رماح','القويعية','الدوادمي','عفيف','مرات','الدلم',
        // مكة المكرمة والطائف
        'مكة المكرمة','جدة','الطائف','رابغ','خليص','الجموم','الليث','القنفذة','أضم','بحرة',
        // المدينة المنورة وينبع
        'المدينة المنورة','ينبع','العلا','خيبر','بدر','الحناكية','المهد','وادي الفرع',
        // الشرقية
        'الدمام','الخبر','الظهران','القطيف','صفوى','سيهات','عنك','تاروت','رأس تنورة','الجبيل','الخفجي','قرية العليا','النعيرية','بقيق','الأحساء','الهفوف','المبرز','العمران','العيون','حفر الباطن','القيصومة','الرقعي',
        // القصيم
        'بريدة','عنيزة','الرس','البكيرية','البدائع','الأسياح','عقلة الصقور','ضرية','المذنب','رياض الخبراء','عيون الجواء',
        // عسير
        'أبها','خميس مشيط','بيشة','النماص','تنومة','بلقرن','محايل عسير','رجال المع','سراة عبيدة','ظهران الجنوب','تثليث',
        // جازان
        'جازان','صبيا','أبو عريش','صامطة','الدرب','بيش','العارضة','الريث','العيدابي','فرسان','ضمد','الدائر',
        // نجران
        'نجران','شرورة','حبونا','بدر الجنوب','ثجر',
        // الباحة
        'الباحة','بلجرشي','المندق','المخواة','قلوة','العقيق','غامد الزناد','الحجرة',
        // تبوك
        'تبوك','الوجه','أملج','ضباء','تيماء','حقل','البدع',
        // حائل
        'حائل','بقعاء','الغزالة','الشنان','السليمي','الحائط','الشملي','موقق','سميراء',
        // الجوف
        'سكاكا','القريات','دومة الجندل','طبرجل',
        // الحدود الشمالية
        'عرعر','رفحاء','طريف','العويقيلة',
        // مناطق أخرى وشائعة
        'ينبع الصناعية','أبو ظبي' /* placeholder accidental? */
    ];
    // إزالة أي قيم خاطئة إن وجدت
    $cities = array_values(array_filter($cities, function($c){ return trim((string)$c) !== ''; }));
    return $cities;
}

/**
 * إرجاع المدن مرتبة حسب عدد الإعلانات (تنازلياً)، مع دمج الكتالوج لمدن بدون إعلانات.
 * تعيد مصفوفة من عناصر مثل: [ 'city' => 'الرياض', 'count' => 123 ]
 */
function get_cities_with_counts($includeCatalog = true) {
    global $pdo;
    static $cache = [];
    $key = $includeCatalog ? 'withCatalog' : 'noCatalog';
    if (isset($cache[$key])) return $cache[$key];

    // عدّ الإعلانات لكل مدينة موجودة فعلاً في الإعلانات
    $sql = "SELECT TRIM(location) AS city, COUNT(*) AS cnt
            FROM ads
            WHERE location IS NOT NULL AND TRIM(location) <> ''
            GROUP BY TRIM(location)";
    $rows = [];
    try {
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $rows = [];
    }
    $map = [];
    foreach ($rows as $r) {
        $city = trim((string)$r['city']);
        if ($city === '') continue;
        $map[$city] = (int)$r['cnt'];
    }
    if ($includeCatalog) {
        foreach (get_saudi_cities() as $c) {
            if (!isset($map[$c])) $map[$c] = 0;
        }
    }
    // حول الخريطة إلى قائمة مرتبة
    $out = [];
    foreach ($map as $city => $cnt) { $out[] = ['city' => $city, 'count' => (int)$cnt]; }
    usort($out, function($a, $b){
        if ($a['count'] === $b['count']) return strnatcasecmp($a['city'], $b['city']);
        return ($a['count'] > $b['count']) ? -1 : 1;
    });
    $cache[$key] = $out;
    return $out;
}

/**
 * جلب إعلان واحد بالتفصيل.
 *
 * @param int $ad_id
 * @return array|false
 */
function get_ad($ad_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT ads.*, users.username, users.email, users.phone, categories.name AS category_name
                            FROM ads
                            JOIN users ON ads.user_id = users.id
                            JOIN categories ON ads.category_id = categories.id
                            WHERE ads.id = :ad_id');
    $stmt->execute(['ad_id' => $ad_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * هل الإعلان مميز حالياً؟ يتحقق من premium_until إن وُجدت أو من is_premium كحقل قديم.
 * @param array $ad صف إعلان كامل أو جزئي يحتوي أعمدة ads.*
 */
function is_ad_premium_active(array $ad): bool {
    // أولوية لـ premium_until إذا كانت موجودة ومتاحة
    if (array_key_exists('premium_until', $ad) && !empty($ad['premium_until'])) {
        return (strtotime((string)$ad['premium_until']) > time());
    }
    // توافق قديم: is_premium = 1
    return !empty($ad['is_premium']);
}

/**
 * جلب الإعلانات المميزة للواجهة الرئيسية مع توافق للأعمدة القديمة.
 * @param int $limit
 * @return array
 */
function get_featured_ads(int $limit = 8): array {
    global $pdo;
    $limit = max(1, (int)$limit);
    // حاول استخدام premium_until إن كانت موجودة؛ وإلا ارجع إلى is_premium فقط
    try {
        $sql = "SELECT ads.*, users.username, categories.name AS category_name
                FROM ads
                JOIN users ON ads.user_id = users.id
                JOIN categories ON ads.category_id = categories.id
                WHERE (ads.premium_until IS NOT NULL AND ads.premium_until > NOW())
                   OR ads.is_premium = 1
                ORDER BY COALESCE(ads.premium_until, ads.created_at) DESC
                LIMIT :lim";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        // fallback لغياب العمود
        try {
            $stmt = $pdo->prepare("SELECT ads.*, users.username, categories.name AS category_name
                                     FROM ads JOIN users ON ads.user_id = users.id
                                              JOIN categories ON ads.category_id = categories.id
                                     WHERE ads.is_premium = 1
                                     ORDER BY ads.created_at DESC
                                     LIMIT :lim");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e2) {
            return [];
        }
    }
}

/**
 * ترقية إعلان إلى مميز لعدد معين من الأيام (بدون دمج دفع حقيقي حالياً).
 * يسمح بالتمديد إذا كان مميزاً بالفعل.
 */
function promote_ad(int $ad_id, int $user_id, int $days = 7): bool {
    global $pdo;
    $days = max(1, $days);
    // تحقق أن المستخدم يمتلك الإعلان
    $stmt = $pdo->prepare('SELECT user_id FROM ads WHERE id = :id');
    $stmt->execute(['id' => $ad_id]);
    $owner = (int)($stmt->fetchColumn() ?: 0);
    if ($owner !== (int)$user_id) return false;

    // حاول تحديث premium_until إذا كان العمود موجوداً
    try {
        $sql = "UPDATE ads
                SET is_premium = 1,
                    premium_until = CASE WHEN premium_until IS NOT NULL AND premium_until > NOW()
                                         THEN DATE_ADD(premium_until, INTERVAL :days DAY)
                                         ELSE DATE_ADD(NOW(), INTERVAL :days DAY)
                                    END
                WHERE id = :id";
        $stmt2 = $pdo->prepare($sql);
        $stmt2->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt2->bindValue(':id', $ad_id, PDO::PARAM_INT);
        return $stmt2->execute();
    } catch (Exception $e) {
        // fallback: اضبط is_premium فقط
        $stmt3 = $pdo->prepare('UPDATE ads SET is_premium = 1 WHERE id = :id');
        return $stmt3->execute(['id' => $ad_id]);
    }
}

/**
 * تسعير مرن حسب الأيام.
 */
function get_promotion_price_sar(int $days): float {
    switch ((int)$days) {
        case 7: return (float)PROMO_PRICE_7;
        case 14: return (float)PROMO_PRICE_14;
        case 30: return (float)PROMO_PRICE_30;
        default:
            // تسعير تناسبي بسيط لمدد أخرى
            $perDay = (float)PROMO_PRICE_7 / 7.0;
            return round(max(1, $days) * $perDay, 2);
    }
}

/**
 * إنشاء سجل ترقية بانتظار الدفع.
 */
function create_promotion(int $ad_id, int $user_id, int $days, string $provider): int {
    global $pdo;
    $price = get_promotion_price_sar($days);
    $stmt = $pdo->prepare('INSERT INTO promotions (ad_id, user_id, days, price, provider, status) VALUES (:ad, :uid, :days, :price, :prov, "pending")');
    $stmt->execute(['ad' => $ad_id, 'uid' => $user_id, 'days' => $days, 'price' => $price, 'prov' => $provider]);
    return (int)$pdo->lastInsertId();
}

function get_promotion_by_id(int $promo_id): ?array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM promotions WHERE id = :id');
    $stmt->execute(['id' => $promo_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * وسم الترقية كمسددة وتفعيل الإعلان وفق الأيام المسجلة.
 */
function mark_promotion_paid(int $promo_id, string $provider_ref = ''): bool {
    global $pdo;
    $promo = get_promotion_by_id($promo_id);
    if (!$promo) return false;
    if ($promo['status'] === 'paid') return true;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE promotions SET status = "paid", paid_at = NOW(), provider_ref = :ref WHERE id = :id');
        $stmt->execute(['ref' => $provider_ref, 'id' => $promo_id]);
        // فعّل الإعلان
        $days = (int)$promo['days'];
        $ad_id = (int)$promo['ad_id'];
        // owner guard غير مطلوب هنا لأنه يتم من خلال الدفع/الطلب
        $sql = "UPDATE ads
                SET is_premium = 1,
                    premium_until = CASE WHEN premium_until IS NOT NULL AND premium_until > NOW()
                                         THEN DATE_ADD(premium_until, INTERVAL :days DAY)
                                         ELSE DATE_ADD(NOW(), INTERVAL :days DAY)
                                    END
                WHERE id = :id";
        $st2 = $pdo->prepare($sql);
        $st2->bindValue(':days', $days, PDO::PARAM_INT);
        $st2->bindValue(':id', $ad_id, PDO::PARAM_INT);
        $st2->execute();
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * بدء عملية الدفع وإرجاع رابط/مُعرّف الدفع لإعادة التوجيه.
 * يدعم: mock, moyasar
 */
function start_payment_for_promotion(int $promo_id): array {
    $provider = PAYMENT_PROVIDER;
    switch ($provider) {
        case 'moyasar':
            return start_payment_moyasar($promo_id);
        case 'paytabs':
            // سنستخدم صفحة checkout للتعامل مع إنشاء الطلب وإعادة التوجيه
            return ['ok' => true, 'redirect' => 'payment_checkout.php?provider=paytabs&id=' . $promo_id];
        case 'mock':
        default:
            // في الوضع التجريبي، نعتبر الدفع ناجحاً فوراً
            mark_promotion_paid($promo_id, 'mock:' . uniqid());
            return ['ok' => true, 'redirect' => 'promotion_success.php?id=' . $promo_id];
    }
}

function start_payment_moyasar(int $promo_id): array {
    global $pdo;
    $promo = get_promotion_by_id($promo_id);
    if (!$promo) return ['ok' => false, 'error' => 'promotion not found'];
    $amountHalalas = (int)round(((float)$promo['price']) * 100);
    $desc = 'تمييز إعلان #' . $promo['ad_id'] . ' لمدة ' . $promo['days'] . ' يوم';
    // صفحة الدفع تُنشئ نموذج Moyasar وتهيئ callback مع promo_id

    // بديل سهل: صفحة وسيطة تحتوي نموذج مويصر جاهز للدفع
    return [
        'ok' => true,
        'redirect' => 'payment_checkout.php?provider=moyasar&id=' . $promo_id
    ];
}

/** PayTabs: مساعد لاختيار عنوان API حسب الإقليم */
function paytabs_api_base(): string {
    $region = strtoupper((string)PAYTABS_REGION);
    // المنصّة العالمية الافتراضية
    $base = 'https://secure-global.paytabs.com';
    if ($region === 'SA') { $base = 'https://secure.paytabs.com'; }
    return rtrim($base, '/');
}

/** تخزين مرجع المزوّد عند إنشاء الطلب */
function set_promotion_provider_ref(int $promo_id, string $ref): void {
    global $pdo;
    if ($promo_id <= 0 || $ref === '') return;
    try {
        $stmt = $pdo->prepare('UPDATE promotions SET provider_ref = :r WHERE id = :id');
        $stmt->execute(['r' => $ref, 'id' => $promo_id]);
    } catch (Throwable $e) { /* ignore */ }
}

/** PayTabs: الاستعلام عن معاملة للتحقق من الحالة */
function paytabs_query_tran(string $tran_ref): ?array {
    if (!$tran_ref || !PAYTABS_SERVER_KEY || !PAYTABS_PROFILE_ID) return null;
    $url = paytabs_api_base() . '/payment/query';
    $payload = json_encode([
        'profile_id' => PAYTABS_PROFILE_ID,
        'tran_ref'   => $tran_ref,
    ], JSON_UNESCAPED_UNICODE);
    $opts = [ 'http' => [
        'method' => 'POST',
        'header' => "Authorization: " . PAYTABS_SERVER_KEY . "\r\n" .
                    "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 15
    ]];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    return is_array($data) ? $data : null;
}

/**
 * تحقق الدفع من موفّر محدد (يُستدعى من صفحة callback)
 */
function verify_payment_callback(string $gateway, array $payload, array $query): array {
    switch ($gateway) {
        case 'moyasar':
            // نتوقع معرّف الترقية عبر query[id] و token/session من مويصر عبر POST
            $promo_id = (int)($query['promo_id'] ?? ($query['id'] ?? 0));
            if ($promo_id <= 0) return ['ok' => false, 'error' => 'bad id'];
            // في تكامل مبسّط، نثق بنجاح الدفع عند العودة من صفحة checkout إذا status=paid في query
            if (($query['status'] ?? '') === 'paid' || ($payload['status'] ?? '') === 'paid') {
                $ref = (string)($query['id'] ?? ($query['payment_id'] ?? ($payload['id'] ?? 'moyasar')));
                $ok = mark_promotion_paid($promo_id, $ref);
                return ['ok' => $ok];
            }
            return ['ok' => false, 'error' => 'not paid'];
        case 'paytabs':
            $promo_id = (int)($query['promo_id'] ?? ($payload['promo_id'] ?? 0));
            $tran_ref = (string)($query['tran_ref'] ?? ($payload['tran_ref'] ?? ''));
            if ($promo_id <= 0 || $tran_ref === '') return ['ok' => false, 'error' => 'bad params'];
            $data = paytabs_query_tran($tran_ref);
            if (!$data) return ['ok' => false, 'error' => 'verify failed'];
            // النجاح عندما تكون الحالة مصادَقة/مدفوعة
            $status = (string)($data['payment_result']['response_status'] ?? '');
            $success = ($status === 'A' || strtolower((string)($data['success'] ?? '')) === 'true');
            if ($success) {
                $ok = mark_promotion_paid($promo_id, $tran_ref);
                return ['ok' => $ok];
            }
            return ['ok' => false, 'error' => 'not paid'];
        case 'mock':
        default:
            return ['ok' => false, 'error' => 'unsupported'];
    }
}

/**
 * جلب صور إعلان معين.
 *
 * @param int $ad_id
 * @return array
 */
function get_ad_images($ad_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, image_path FROM ad_images WHERE ad_id = :ad_id');
    $stmt->execute(['ad_id' => $ad_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * عدد الإعلانات التي نشرها المستخدم خلال اليوم الحالي
 * @param int $user_id
 * @return int
 */
function get_user_ads_count_today($user_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ads WHERE user_id = :user_id AND DATE(created_at) = CURDATE()');
    $stmt->execute(['user_id' => $user_id]);
    return (int) $stmt->fetchColumn();
}

/**
 * إضافة تعليق لإعلان
 * @param int $ad_id
 * @param int $user_id
 * @param string $content
 * @return bool
 */
function add_comment($ad_id, $user_id, $content) {
    global $pdo;
    /*
     * عند إضافة تعليق جديد كنا نقوم بتخزينه كتعليق غير مُعتمد (approved = 0).
     * لاحظنا من خلال تجربة المستخدم أن التعليقات لا تظهر مباشرة بعد الإضافة مما يربك المستخدمين،
     * لذا قمنا بتعديل المنطق ليتم اعتماد التعليق مباشرةً افتراضيًا (approved = 1).
     * لا يزال بإمكان المشرفين أو مالك الإعلان إزالة التعليقات غير المناسبة من خلال لوحة الإدارة.
     */
    $stmt = $pdo->prepare('INSERT INTO comments (ad_id, user_id, content, approved, created_at) VALUES (:ad_id, :user_id, :content, 1, NOW())');
    $res = $stmt->execute(['ad_id' => $ad_id, 'user_id' => $user_id, 'content' => $content]);
    if ($res) {
        // إرسال إشعار لصاحب الإعلان بوجود تعليق جديد
        // يمكن استخدام send_notification() لتخزين إشعار للمستخدم في جدول الإشعارات
        // هنا نستخدمها لإبلاغ المعلن حتى لو كان التعليق معتمدًا فوراً
        try {
            // جلب صاحب الإعلان
            $stmt2 = $pdo->prepare('SELECT user_id FROM ads WHERE id = :ad_id');
            $stmt2->execute(['ad_id' => $ad_id]);
            $owner = $stmt2->fetchColumn();
            if ($owner && $owner != $user_id) {
                send_notification($owner, 'لديك تعليق جديد على إعلانك');
            }
        } catch (Exception $e) {
            // تجاهل أي خطأ في الإشعارات للحفاظ على سير العملية
        }
    }
    return $res;
}

/**
 * الحصول على تعليقات الإعلان مع اسم المعلق
 * @param int $ad_id
 * @return array
 */
function get_ad_comments($ad_id, $page = 1, $perPage = 20, $includeUnapproved = false) {
    global $pdo;
    $offset = max(0, ($page - 1)) * $perPage;
    if ($includeUnapproved) {
        $stmt = $pdo->prepare('SELECT comments.*, users.username FROM comments JOIN users ON comments.user_id = users.id WHERE comments.ad_id = :ad_id AND comments.deleted_at IS NULL ORDER BY comments.created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':ad_id', $ad_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare('SELECT comments.*, users.username FROM comments JOIN users ON comments.user_id = users.id WHERE comments.ad_id = :ad_id AND comments.approved = 1 AND comments.deleted_at IS NULL ORDER BY comments.created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':ad_id', $ad_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * جلب التعليقات المعلقة للمراجعة
 */
function get_pending_comments($limit = 50) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT comments.*, users.username, ads.title FROM comments JOIN users ON comments.user_id = users.id JOIN ads ON comments.ad_id = ads.id WHERE comments.approved = 0 AND comments.deleted_at IS NULL ORDER BY comments.created_at ASC LIMIT :limit');
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * قبول تعليق (approve)
 */
function approve_comment($comment_id) {
    global $pdo;
    $stmt = $pdo->prepare('UPDATE comments SET approved = 1 WHERE id = :id');
    return $stmt->execute(['id' => $comment_id]);
}

/**
 * تعديل تعليق (يحق لصاحبه)
 */
function edit_comment($comment_id, $user_id, $newContent) {
    global $pdo;
    $stmt = $pdo->prepare('UPDATE comments SET content = :content, edited_at = NOW() WHERE id = :id AND user_id = :user_id');
    return $stmt->execute(['content' => $newContent, 'id' => $comment_id, 'user_id' => $user_id]);
}

/**
 * حذف تعليق - حذف منطقي (soft delete)
 */
function delete_comment($comment_id, $user_id = null) {
    global $pdo;
    if ($user_id) {
        $stmt = $pdo->prepare('UPDATE comments SET deleted_at = NOW() WHERE id = :id AND user_id = :user_id');
        return $stmt->execute(['id' => $comment_id, 'user_id' => $user_id]);
    } else {
        // حذف بواسطة المشرف
        $stmt = $pdo->prepare('UPDATE comments SET deleted_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $comment_id]);
    }
}

/**
 * وظيفة بسيطة لإرسال إشعار (مكان احتياطي، يمكن توصيلها بآلية حقيقية لاحقاً)
 */
function send_notification($user_id, $content) {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, content, created_at) VALUES (:user_id, :content, NOW())');
    return $stmt->execute(['user_id' => $user_id, 'content' => $content]);
}

/**
 * حفظ رسالة بين المستخدمين.
 *
 * @param int $from_user
 * @param int $to_user
 * @param int $ad_id
 * @param string $message
 * @return bool
 */
function send_message($from_user, $to_user, $ad_id, $message) {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO messages (from_user_id, to_user_id, ad_id, content, created_at)
                           VALUES (:from_user_id, :to_user_id, :ad_id, :content, NOW())');
    return $stmt->execute([
        'from_user_id' => $from_user,
        'to_user_id' => $to_user,
        'ad_id' => $ad_id,
        'content' => $message
    ]);
}

/**
 * حفظ مرفقات رسالة بعد إنشاء الرسالة.
 * @param int $message_id
 * @param string $file_path
 * @param string $mime
 * @param int $size
 */
function save_message_attachment($message_id, $file_path, $mime = '', $size = 0) {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO message_attachments (message_id, file_path, mime_type, file_size, created_at)
                           VALUES (:message_id, :file_path, :mime_type, :file_size, NOW())');
    return $stmt->execute([
        'message_id' => $message_id,
        'file_path'  => $file_path,
        'mime_type'  => $mime,
        'file_size'  => (int)$size
    ]);
}

/**
 * جلب مرفقات رسالة.
 */
function get_message_attachments($message_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, file_path, mime_type, file_size FROM message_attachments WHERE message_id = :mid ORDER BY id ASC');
    $stmt->execute(['mid' => $message_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * جلب رسائل المحادثة لإعلان محدد مع المرفقات.
 */
function get_conversation_messages_with_attachments($user_id, $other_user_id, $ad_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE ((from_user_id = :user_id AND to_user_id = :other_user_id) OR (from_user_id = :other_user_id AND to_user_id = :user_id)) AND ad_id = :ad_id ORDER BY created_at ASC');
    $stmt->execute(['user_id' => $user_id, 'other_user_id' => $other_user_id, 'ad_id' => $ad_id]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($msgs as &$m) {
        $m['attachments'] = get_message_attachments((int)$m['id']);
    }
    return $msgs;
}

/**
 * جلب رسائل المحادثة عبر جميع الإعلانات مع المرفقات.
 */
function get_user_conversation_messages_with_attachments($user_id, $other_user_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM messages
                            WHERE (from_user_id = :uid AND to_user_id = :other)
                               OR (from_user_id = :other AND to_user_id = :uid)
                            ORDER BY created_at ASC');
    $stmt->execute(['uid' => $user_id, 'other' => $other_user_id]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($msgs as &$m) {
        $m['attachments'] = get_message_attachments((int)$m['id']);
    }
    return $msgs;
}

/**
 * إضافة إعلان إلى المفضلة.
 *
 * @param int $user_id
 * @param int $ad_id
 * @return bool
 */
function add_favorite($user_id, $ad_id) {
    global $pdo;
    // التأكد من عدم وجوده مسبقاً
    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = :user_id AND ad_id = :ad_id');
    $stmt->execute([
        'user_id' => $user_id,
        'ad_id' => $ad_id
    ]);
    if ($stmt->fetch()) {
        return false;
    }
    $stmt = $pdo->prepare('INSERT INTO favorites (user_id, ad_id, created_at) VALUES (:user_id, :ad_id, NOW())');
    return $stmt->execute([
        'user_id' => $user_id,
        'ad_id' => $ad_id
    ]);
}

/**
 * الحصول على قائمة الإعلانات المفضلة للمستخدم.
 *
 * @param int $user_id
 * @return array
 */
function get_user_favorites($user_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT ads.*, categories.name AS category_name
                            FROM favorites
                            JOIN ads ON favorites.ad_id = ads.id
                            JOIN categories ON ads.category_id = categories.id
                            WHERE favorites.user_id = :user_id
                            ORDER BY favorites.created_at DESC');
    $stmt->execute(['user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * الحصول على قائمة الرسائل للمستخدم.
 *
 * @param int $user_id
 * @return array
 */
function get_user_messages($user_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT messages.*, ads.title AS ad_title, users.username AS sender_name
                            FROM messages
                            JOIN ads ON messages.ad_id = ads.id
                            JOIN users ON messages.from_user_id = users.id
                            WHERE messages.to_user_id = :user_id
                            ORDER BY messages.created_at DESC');
    $stmt->execute(['user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * جلب مسار القسم من الجذر إلى القسم الحالي (لإنشاء breadcrumbs)
 *
 * @param int $category_id
 * @return array مصفوفة من الأقسام بترتيب من الجذر إلى الهدف
 */
function get_category_path($category_id) {
    global $pdo;
    $path = [];
    $current = $category_id;
    while ($current) {
        $stmt = $pdo->prepare('SELECT id, parent_id, name FROM categories WHERE id = :id');
        $stmt->execute(['id' => $current]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) break;
        $path[] = $row;
        // توقف عند الجذر (parent_id NULL أو 0)
        if (empty($row['parent_id'])) break;
        $current = $row['parent_id'];
    }
    return array_reverse($path);
}

/**
 * جلب قائمة المحادثات للمستخدم.
 * تُرجع مصفوفة تحتوي على معرّف الإعلان، ومعرّف الطرف الآخر، واسم الطرف الآخر، وعنوان الإعلان،
 * وأحدث رسالة وتاريخها، لتكوين قائمة بالمحادثات.
 *
 * @param int $user_id
 * @return array
 */
function get_conversations($user_id) {
    global $pdo;
    $user_id = (int)$user_id;
    $conversations = [];
    // الحصول على قائمة المحادثات (الإعلان + الطرف الآخر) مع آخر وقت
    $sql = 'SELECT m.ad_id,
                   CASE WHEN m.from_user_id = :uid THEN m.to_user_id ELSE m.from_user_id END AS other_id,
                   MAX(m.created_at) AS last_time
            FROM messages m
            WHERE (m.from_user_id = :uid OR m.to_user_id = :uid)
            GROUP BY m.ad_id, other_id
            ORDER BY last_time DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $ad_id = (int)$row['ad_id'];
        $other_id = (int)$row['other_id'];
        // جلب اسم الطرف الآخر
        $stmt2 = $pdo->prepare('SELECT username FROM users WHERE id = :id');
        $stmt2->execute(['id' => $other_id]);
        $other_username = $stmt2->fetchColumn();
        // جلب عنوان الإعلان
        $stmt3 = $pdo->prepare('SELECT title FROM ads WHERE id = :id');
        $stmt3->execute(['id' => $ad_id]);
        $ad_title = $stmt3->fetchColumn();
        // جلب آخر رسالة
        $stmt4 = $pdo->prepare('SELECT content, created_at FROM messages
                                 WHERE ((from_user_id = :uid AND to_user_id = :other) OR (from_user_id = :other AND to_user_id = :uid))
                                       AND ad_id = :ad
                                 ORDER BY created_at DESC
                                 LIMIT 1');
        $stmt4->execute(['uid' => $user_id, 'other' => $other_id, 'ad' => $ad_id]);
        $lastMessage = $stmt4->fetch(PDO::FETCH_ASSOC);
        $conversations[] = [
            'ad_id'         => $ad_id,
            'other_user_id' => $other_id,
            'other_username'=> $other_username ?: 'مستخدم مجهول',
            'ad_title'      => $ad_title ?: '',
            'last_message'  => $lastMessage['content'] ?? '',
            'last_time'     => $lastMessage['created_at'] ?? $row['last_time']
        ];
    }
    return $conversations;
}

/**
 * جلب قائمة بالمستخدمين الذين تحادث معهم المستخدم الحالي (بدون تمييز للإعلانات).
 * سيتم تجميع المحادثات حسب المستخدم الآخر وإرجاع آخر رسالة وتوقيتها.
 *
 * @param int $user_id
 * @return array
 */
function get_user_conversations($user_id) {
    global $pdo;
    $user_id = (int)$user_id;
    $conversations = [];
    // الحصول على معرفات المستخدمين الآخرين مع آخر وقت رسالة
    $sql = 'SELECT CASE WHEN from_user_id = :uid THEN to_user_id ELSE from_user_id END AS other_id,
                   MAX(created_at) AS last_time
            FROM messages
            WHERE from_user_id = :uid OR to_user_id = :uid
            GROUP BY other_id
            ORDER BY last_time DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $other_id = (int)$row['other_id'];
        // جلب اسم الطرف الآخر
        $stmt2 = $pdo->prepare('SELECT username FROM users WHERE id = :id');
        $stmt2->execute(['id' => $other_id]);
        $other_username = $stmt2->fetchColumn();
        // جلب آخر رسالة بين المستخدمين بغض النظر عن الإعلان
        $stmt3 = $pdo->prepare('SELECT content, created_at FROM messages
                                 WHERE (from_user_id = :uid AND to_user_id = :other_id)
                                    OR (from_user_id = :other_id AND to_user_id = :uid)
                                 ORDER BY created_at DESC
                                 LIMIT 1');
        $stmt3->execute(['uid' => $user_id, 'other_id' => $other_id]);
        $lastMessage = $stmt3->fetch(PDO::FETCH_ASSOC);
        $conversations[] = [
            'other_user_id'  => $other_id,
            'other_username' => $other_username ?: 'مستخدم مجهول',
            'last_message'   => $lastMessage['content'] ?? '',
            'last_time'      => $lastMessage['created_at'] ?? $row['last_time']
        ];
    }
    return $conversations;
}

/**
 * جلب جميع رسائل المحادثة بين مستخدمين عبر كل الإعلانات.
 *
 * @param int $user_id
 * @param int $other_user_id
 * @return array
 */
function get_user_conversation_messages($user_id, $other_user_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM messages
                            WHERE (from_user_id = :uid AND to_user_id = :other)
                               OR (from_user_id = :other AND to_user_id = :uid)
                            ORDER BY created_at ASC');
    $stmt->execute(['uid' => $user_id, 'other' => $other_user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * جلب جميع رسائل محادثة معينة بين مستخدمين لإعلان محدد.
 *
 * @param int $user_id
 * @param int $other_user_id
 * @param int $ad_id
 * @return array
 */
function get_conversation_messages($user_id, $other_user_id, $ad_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE ((from_user_id = :user_id AND to_user_id = :other_user_id) OR (from_user_id = :other_user_id AND to_user_id = :user_id)) AND ad_id = :ad_id ORDER BY created_at ASC');
    $stmt->execute(['user_id' => $user_id, 'other_user_id' => $other_user_id, 'ad_id' => $ad_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * جلب مستخدم بواسطة المعرّف
 */
function get_user_by_id($id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * إنشاء رمز تحقق البريد وإرساله للمستخدم
 */
function create_email_verification_token($user_id, $ttlSeconds = 3*24*3600) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + max(300, (int)$ttlSeconds));
    try {
        $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (:uid, :token, :exp)');
        $stmt->execute(['uid' => (int)$user_id, 'token' => $token, 'exp' => $expires]);
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02' || stripos($e->getMessage(), 'doesn\'t exist') !== false) {
            // أنشئ الجدول ثم أعد المحاولة
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_verifications (\n                id INT AUTO_INCREMENT PRIMARY KEY,\n                user_id INT NOT NULL,\n                token CHAR(64) NOT NULL,\n                expires_at DATETIME NOT NULL,\n                used_at DATETIME DEFAULT NULL,\n                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n                INDEX idx_user_created (user_id, created_at),\n                UNIQUE KEY uniq_token (token),\n                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (:uid, :token, :exp)');
            $stmt->execute(['uid' => (int)$user_id, 'token' => $token, 'exp' => $expires]);
        } else {
            throw $e;
        }
    }
    return $token;
}

function build_absolute_url($path) {
    $base = SITE_BASE_URL;
    if (!$base) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function send_email($to, $subject, $html, $text = '') {
    // مزود بريد مرن: mock | mail | sendgrid (stub)
    $provider = EMAIL_PROVIDER;
    if ($provider === 'mock') return true;
    if ($provider === 'file') {
        // اكتب الرسالة كملف .eml لبيئة التطوير
        $dir = MAIL_STORE_DIR;
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $eml = 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM . ">\r\n" .
               'To: <' . $to . ">\r\n" .
               'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n" .
               'Date: ' . $date . "\r\n" .
               "MIME-Version: 1.0\r\n" .
               "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
               $html . "\r\n";
        $name = date('Ymd_His') . '_' . md5($to . $subject) . '.eml';
        $ok = @file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $eml) !== false;
        return $ok;
    }
    if ($provider === 'smtp') {
        // حاول استخدام PHPMailer إن كان متوفراً على النظام، وإلا استخدم مسار بديل بسيط
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->Port = SMTP_PORT ?: 587;
                $mail->SMTPAuth = true;
                // Debug in non-production environments
                if ((getenv('APP_ENV') ?: '') !== 'production') {
                    $mail->SMTPDebug = 2; // show client/server messages
                    $mail->Debugoutput = function($str) { error_log('[PHPMailer] ' . trim($str)); };
                }
                if (strtolower(SMTP_ENCRYPTION ?: 'tls') === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = $html;
                $mail->AltBody = $text ?: strip_tags($html);
                $mail->send();
                return true;
            } catch (\Throwable $e) {
                // إذا فشل PHPMailer سنسقط إلى المسار البديل أدناه
            }
        }
        // Fallback بسيط باستخدام mail() أو SMTP بدائي عبر fsockopen إذا تعذر استخدام PHPMailer
        // 1) جرّب mail()
        $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n" .
                   "MIME-Version: 1.0\r\n" .
                   "Content-Type: text/html; charset=UTF-8\r\n";
        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
        if ($sent) return true;
        // 2) SMTP مبسّط يدعم STARTTLS و SSL
        try {
            $host = SMTP_HOST; $port = SMTP_PORT ?: 587; $user = SMTP_USERNAME; $pass = SMTP_PASSWORD; $enc = strtolower(SMTP_ENCRYPTION ?: 'tls');
            $target = ($enc === 'ssl' ? 'ssl://' . $host : $host);
            $sock = @fsockopen($target, $port, $errno, $errstr, 15);
            if (!$sock) return false;
            $read = function() use ($sock){ $data=''; while(($l = fgets($sock, 515)) !== false){ $data.=$l; if (preg_match('/^\d{3} /',$l)) break; } return $data; };
            $send = function($cmd) use ($sock){ fwrite($sock, $cmd."\r\n"); };
            $resp = $read(); if (strpos($resp, '220') !== 0) { fclose($sock); return false; }
            $send('EHLO localhost'); $resp = $read(); if (strpos($resp, '250') !== 0) { fclose($sock); return false; }
            if ($enc === 'tls') {
                $send('STARTTLS'); $resp = $read(); if (strpos($resp, '220') !== 0) { fclose($sock); return false; }
                if (!function_exists('stream_socket_enable_crypto') || !@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($sock); return false; }
                $send('EHLO localhost'); $resp = $read(); if (strpos($resp, '250') !== 0) { fclose($sock); return false; }
            }
            $send('AUTH LOGIN'); $resp = $read(); if (strpos($resp, '334') !== 0) { fclose($sock); return false; }
            $send(base64_encode($user)); $resp = $read(); if (strpos($resp, '334') !== 0) { fclose($sock); return false; }
            $send(base64_encode($pass)); $resp = $read(); if (strpos($resp, '235') !== 0) { fclose($sock); return false; }
            $send('MAIL FROM:<'.EMAIL_FROM.'>'); $resp = $read(); if (strpos($resp, '250') !== 0) { fclose($sock); return false; }
            $send('RCPT TO:<'.$to.'>'); $resp = $read(); if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) { fclose($sock); return false; }
            $send('DATA'); $resp = $read(); if (strpos($resp, '354') !== 0) { fclose($sock); return false; }
            $date = gmdate('D, d M Y H:i:s \G\M\T');
            $msg = 'From: '.EMAIL_FROM_NAME.' <'.EMAIL_FROM.">\r\n".
                   'To: <'.$to.">\r\n".
                   'Subject: =?UTF-8?B?'.base64_encode($subject)."?=\r\n".
                   'Date: '.$date."\r\n".
                   'Message-ID: <'.uniqid('', true).'@'.parse_url(SITE_BASE_URL, PHP_URL_HOST).">\r\n".
                   "MIME-Version: 1.0\r\n".
                   "Content-Type: text/html; charset=UTF-8\r\n".
                   "Content-Transfer-Encoding: 8bit\r\n\r\n".
                   $html."\r\n.\r\n";
            fwrite($sock, $msg);
            $resp = $read(); if (strpos($resp, '250') !== 0) { fclose($sock); return false; }
            $send('QUIT');
            fclose($sock);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
    if ($provider === 'mail') {
        $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n" .
                   "MIME-Version: 1.0\r\n" .
                   "Content-Type: text/html; charset=UTF-8\r\n";
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
    }
    if ($provider === 'sendgrid') {
        $key = getenv('SENDGRID_API_KEY');
        if (!$key) return false;
        $payload = json_encode([
            'personalizations' => [[ 'to' => [[ 'email' => $to ]] ]],
            'from' => [ 'email' => EMAIL_FROM, 'name' => EMAIL_FROM_NAME ],
            'subject' => $subject,
            'content' => [ ['type' => 'text/plain', 'value' => ($text ?: strip_tags($html))], ['type' => 'text/html', 'value' => $html] ]
        ], JSON_UNESCAPED_UNICODE);
        $opts = [ 'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer $key\r\nContent-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10
        ]];
        $ctx = stream_context_create($opts);
        $resp = @file_get_contents('https://api.sendgrid.com/v3/mail/send', false, $ctx);
        return $resp !== false;
    }
    if ($provider === 'brevo') {
        $apiKey = BREVO_API_KEY;
        if (!$apiKey) return false;
        $payload = json_encode([
            'sender' => [ 'email' => EMAIL_FROM, 'name' => EMAIL_FROM_NAME ],
            'to' => [[ 'email' => $to ]],
            'subject' => $subject,
            'htmlContent' => $html,
            'textContent' => $text ?: strip_tags($html)
        ], JSON_UNESCAPED_UNICODE);
        $opts = [ 'http' => [
            'method' => 'POST',
            'header' => "api-key: $apiKey\r\nContent-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10
        ]];
        $ctx = stream_context_create($opts);
        $resp = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $ctx);
        return $resp !== false;
    }
    return false;
}

function send_email_verification($user_id, $email, $redirect = 'profile.php') {
    $token = create_email_verification_token($user_id);
    $qs = 'token=' . urlencode($token);
    if ($redirect) { $qs .= '&redirect=' . urlencode($redirect); }
    $url = build_absolute_url('verify_email.php?' . $qs);
    $subject = 'تفعيل بريدك الإلكتروني';
    $html = '<p>مرحباً،</p><p>لتفعيل بريدك الإلكتروني في سوق الإعلانات، يرجى الضغط على الرابط التالي:</p>' .
            '<p><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a></p>' .
            '<p>الرابط صالح لعدة أيام.</p>';
    $text = "لتفعيل بريدك الإلكتروني، افتح الرابط التالي: $url";
    return send_email($email, $subject, $html, $text);
}

function verify_email_token($token) {
    global $pdo;
    $token = preg_replace('/[^a-f0-9]/', '', strtolower((string)$token));
    if ($token === '') return false;
    $stmt = $pdo->prepare('SELECT id, user_id, expires_at, used_at FROM email_verifications WHERE token = :t LIMIT 1');
    $stmt->execute(['t' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if ($row['used_at'] !== null) return false;
    if (strtotime($row['expires_at']) < time()) return false;
    // علّم الرمز كمستخدم وفعّل البريد
    $pdo->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = :id')->execute(['id' => (int)$row['id']]);
    try {
        $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = :uid')->execute(['uid' => (int)$row['user_id']]);
    } catch (PDOException $e) {
        // fallback إنشاء العمود إذا لم يكن موجوداً (للترقيات الجزئية)
        if ($e->getCode() === '42S22' || stripos($e->getMessage(), 'Unknown column') !== false) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
            $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = :uid')->execute(['uid' => (int)$row['user_id']]);
        } else {
            return false;
        }
    }
    return true;
}

/**
 * إنشاء كود OTP وتخزينه وإرجاعه.
 * @param int $user_id
 * @param int $ttlSeconds
 */
function create_phone_otp($user_id, $ttlSeconds = 300) {
    global $pdo;
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + max(60, (int)$ttlSeconds));
    $stmt = $pdo->prepare('INSERT INTO otp_codes (user_id, code, expires_at) VALUES (:uid, :code, :exp)');
    $stmt->execute(['uid' => (int)$user_id, 'code' => $code, 'exp' => $expires]);
    return $code;
}

/**
 * جلب آخر OTP للمستخدم (غير مستخدم) لإدارة إعادة الإرسال/التبريد
 */
function get_last_phone_otp($user_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, code, expires_at, used_at, created_at FROM otp_codes WHERE user_id = :uid ORDER BY id DESC LIMIT 1');
    $stmt->execute(['uid' => (int)$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * هل يسمح بإعادة إرسال OTP الآن وفق فترة تبريد بالثواني
 */
function can_resend_phone_otp($user_id, $cooldownSeconds = 60) {
    $last = get_last_phone_otp($user_id);
    if (!$last) return true;
    $created = strtotime($last['created_at'] ?? '');
    if (!$created) return true;
    return (time() - $created) >= max(5, (int)$cooldownSeconds);
}

/**
 * إرسال كود OTP للجوال - نقطة تكامل لاحقة مع مزود SMS
 * حالياً: تُعيد true وتُسجّل الكود في السجل لأغراض التطوير
 */
function send_phone_otp($phone, $code) {
    // تحويل الرقم إلى صيغة دولية للاستخدام مع موفري SMS
    $to = format_phone_display($phone, true); // tel canonical
    $text = "رمز التحقق لحراج نجد: $code";

    // في بيئة التطوير على localhost، لا نرسل فعلياً
    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1']);
    if ($isLocal && (SMS_PROVIDER === 'mock')) {
        return true;
    }

    try {
        switch (SMS_PROVIDER) {
            case 'twilio':
                // إرسال مبسط عبر REST بدون SDK
                if (!TWILIO_ACCOUNT_SID || !TWILIO_AUTH_TOKEN || !TWILIO_FROM) return false;
                $sid = urlencode(TWILIO_ACCOUNT_SID);
                $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
                $data = http_build_query(['To' => $to, 'From' => TWILIO_FROM, 'Body' => $text]);
                $opts = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Authorization: Basic " . base64_encode(TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN) . "\r\n" .
                                    "Content-Type: application/x-www-form-urlencoded\r\n",
                        'content' => $data,
                        'timeout' => 10
                    ]
                ];
                $ctx = stream_context_create($opts);
                $resp = @file_get_contents($url, false, $ctx);
                return $resp !== false;
            case 'unifonic':
                if (!UNIFONIC_APP_SID || !UNIFONIC_SENDER) return false;
                $url = 'https://el.cloud.unifonic.com/rest/SMS/messages';
                $payload = http_build_query([
                    'AppSid' => UNIFONIC_APP_SID,
                    'Body' => $text,
                    'Recipient' => $to,
                    'SenderID' => UNIFONIC_SENDER
                ]);
                $opts = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                        'content' => $payload,
                        'timeout' => 10
                    ]
                ];
                $ctx = stream_context_create($opts);
                $resp = @file_get_contents($url, false, $ctx);
                return $resp !== false;
            case 'mock':
            default:
                return true;
        }
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * التحقق من كود OTP وتحديث حالة التحقق للمستخدم
 */
function verify_phone_otp($user_id, $code) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, code, expires_at, used_at FROM otp_codes WHERE user_id = :uid ORDER BY id DESC LIMIT 1');
    $stmt->execute(['uid' => (int)$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if ($row['used_at'] !== null) return false;
    if (strtotime($row['expires_at']) < time()) return false;
    if (trim($row['code']) !== trim($code)) return false;
    // علّم الكود كمستخدم
    $pdo->prepare('UPDATE otp_codes SET used_at = NOW() WHERE id = :id')->execute(['id' => (int)$row['id']]);
    // علّم المستخدم كمتحقق هاتفه (تأكد من وجود العمود أولاً)
    try {
        $pdo->prepare('UPDATE users SET phone_verified = 1 WHERE id = :id')->execute(['id' => (int)$user_id]);
    } catch (PDOException $e) {
        // 42S22: عمود غير موجود
        if ($e->getCode() === '42S22' || stripos($e->getMessage(), 'Unknown column') !== false) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN phone_verified TINYINT(1) DEFAULT 0");
                $pdo->prepare('UPDATE users SET phone_verified = 1 WHERE id = :id')->execute(['id' => (int)$user_id]);
            } catch (Exception $e2) {
                // تجاهل الفشل في التعديل على المخطط لكن اعتبر التحقق فاشلاً
                return false;
            }
        } else {
            return false;
        }
    }
    return true;
}

/**
 * حفظ خصائص مرنة للإعلان في جدول ad_attributes.
 * @param int $ad_id
 * @param array $attributes [key => value]
 * @param bool $clearExistingKeys إذا true يحذف نفس المفاتيح قبل الإدراج
 * @return void
 */
function save_ad_attributes($ad_id, array $attributes, $clearExistingKeys = true) {
    global $pdo;
    $ad_id = (int)$ad_id;
    if ($ad_id <= 0) return;
    // نظّف القيم الفارغة
    $attrs = [];
    foreach ($attributes as $k => $v) {
        $k = trim((string)$k);
        $v = trim((string)$v);
        if ($k === '' || $v === '') continue;
        $attrs[$k] = $v;
    }
    if (empty($attrs)) return;

    if ($clearExistingKeys) {
        $keys = array_keys($attrs);
        // حضّر استعلام حذف للمفاتيح نفسها فقط
        $in = implode(',', array_fill(0, count($keys), '?'));
        $stmtDel = $pdo->prepare("DELETE FROM ad_attributes WHERE ad_id = ? AND attr_key IN ($in)");
        $params = array_merge([$ad_id], $keys);
        $stmtDel->execute($params);
    }
    $stmt = $pdo->prepare('INSERT INTO ad_attributes (ad_id, attr_key, attr_value, created_at) VALUES (:ad_id, :k, :v, NOW())');
    foreach ($attrs as $k => $v) {
        $stmt->execute(['ad_id' => $ad_id, 'k' => $k, 'v' => $v]);
    }
}
