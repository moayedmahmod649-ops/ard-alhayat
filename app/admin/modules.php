<?php
declare(strict_types=1);
/**
 * Schema-driven content modules. Each field: [name, type, label_ar, label_en, opts]
 * types: text · textarea · rich · slug · select · bool · number · date · datetime · image · gallery · files · tags · videos · parent · color · url
 * 'pub' = publish control field (published flag, or 'status' for news). Editors (edit level) cannot publish or delete.
 */
function bi(string $base, string $type, string $ar, string $en, array $o = []): array {
    return [[$base . '_ar', $type, $ar . ' (عربي)', $en . ' (Arabic)', $o + ['lang' => 'ar']], [$base . '_en', $type, $ar . ' (إنجليزي)', $en . ' (English)', $o + ['lang' => 'en']]];
}
function seo_fields(): array {
    return array_merge(bi('meta_title', 'text', 'عنوان SEO', 'SEO title', ['max' => 70, 'group' => 'seo']), bi('meta_desc', 'textarea', 'وصف SEO', 'SEO description', ['max' => 170, 'group' => 'seo']));
}
const ICONS = ['leaf', 'wheat', 'olive', 'cow', 'sheep', 'drop', 'sprout', 'greenhouse', 'silo', 'tractor', 'chart', 'hand', 'shield', 'eye', 'target', 'users', 'sun', 'pin'];
function modules(): array {
    static $M = null; if ($M) return $M;
    $icon = ['icon', 'select', 'الأيقونة', 'Icon', ['options' => array_combine(ICONS, ICONS)]];
    $sector = ['sector', 'select', 'القطاع', 'Sector', ['options' => ['agri' => ['زراعي', 'Agriculture'], 'livestock' => ['إنتاج حيواني', 'Livestock']]]];
    $pub = ['published', 'bool', 'منشور', 'Published', ['pub' => true]];
    $sort = ['sort', 'number', 'الترتيب', 'Order', []];
    $M = [
      'slides' => ['table' => 'slides', 'perm' => 'slides', 'title' => ['الشريط الرئيسي', 'Home slider'], 'icon' => 'image', 'cols' => ['title_ar', 'sort', 'published'], 'order' => 'sort, id',
        'fields' => array_merge(bi('title', 'text', 'العنوان', 'Title', ['req' => 'ar', 'max' => 120]), bi('sub', 'textarea', 'النص الفرعي', 'Subtitle', ['max' => 260]), bi('cta', 'text', 'نص الزر', 'Button text', ['max' => 40]),
          [['link', 'url', 'رابط الزر (مثل /activities)', 'Button link (e.g. /activities)', []], ['image', 'image', 'الصورة (1600×900 أو أكبر)', 'Image (1600×900 or larger)', ['kind' => 'cover', 'req' => true]], $sort, $pub])],
      'activities' => ['table' => 'activities', 'perm' => 'activities', 'title' => ['الأنشطة والخدمات', 'Activities & services'], 'icon' => 'sprout', 'cols' => ['title_ar', 'sector', 'featured', 'published'], 'order' => 'sector, sort, id', 'path' => 'activities/',
        'fields' => array_merge(bi('title', 'text', 'العنوان', 'Title', ['req' => 'ar', 'max' => 120]), [['slug', 'slug', 'الرابط المختصر', 'Slug', ['from' => 'title_en']], $sector, $icon],
          bi('summary', 'textarea', 'الملخص', 'Summary', ['max' => 260]), bi('body', 'rich', 'التفاصيل', 'Details', []),
          [['image', 'image', 'صورة الغلاف', 'Cover image', ['kind' => 'cover']], ['gallery', 'gallery', 'معرض الصور', 'Photo gallery', []], ['featured', 'bool', 'يظهر في «أبرز الخدمات» بالرئيسية', 'Show in home highlights', []], $sort, $pub], seo_fields())],
      'projects' => ['table' => 'projects', 'perm' => 'projects', 'title' => ['المشاريع', 'Projects'], 'icon' => 'pin', 'cols' => ['title_ar', 'status', 'featured', 'published'], 'order' => 'sort, id DESC', 'path' => 'projects/',
        'fields' => array_merge(bi('title', 'text', 'العنوان', 'Title', ['req' => 'ar', 'max' => 140]), [['slug', 'slug', 'الرابط المختصر', 'Slug', ['from' => 'title_en']],
          ['status', 'select', 'الحالة', 'Status', ['options' => ['ongoing' => ['جارٍ', 'Ongoing'], 'completed' => ['منفّذ', 'Completed']]]], $sector],
          bi('summary', 'textarea', 'الملخص', 'Summary', ['max' => 300]), bi('body', 'rich', 'التفاصيل', 'Details', []), bi('location', 'text', 'الموقع', 'Location', ['max' => 120]),
          [['map_url', 'url', 'رابط الموقع على الخريطة (https://…)', 'Map link (https://…)', ['abs' => true]], ['year', 'text', 'السنة', 'Year', ['max' => 12]], ['image', 'image', 'صورة الغلاف', 'Cover image', ['kind' => 'cover']], ['gallery', 'gallery', 'معرض الصور', 'Photo gallery', []],
           ['featured', 'bool', 'مميز في الرئيسية', 'Featured on home', []], $sort, $pub], seo_fields())],
      'news' => ['table' => 'news', 'perm' => 'news', 'title' => ['الأخبار والمقالات', 'News & articles'], 'icon' => 'megaphone', 'cols' => ['title_ar', 'category_id', 'publish_at', 'status'], 'order' => 'publish_at DESC, id DESC', 'path' => 'news/', 'search' => ['title_ar', 'title_en', 'tags'],
        'fields' => array_merge(bi('title', 'text', 'العنوان', 'Title', ['req' => 'ar', 'max' => 160]), [['slug', 'slug', 'الرابط المختصر', 'Slug', ['from' => 'title_en']], ['category_id', 'select', 'التصنيف', 'Category', ['options_sql' => 'SELECT id, name_ar, name_en FROM news_categories ORDER BY sort, id', 'null' => true]]],
          bi('excerpt', 'textarea', 'المقتطف', 'Excerpt', ['max' => 300]), bi('body', 'rich', 'نص الخبر', 'Body', []),
          [['tags', 'tags', 'الوسوم (افصل بفاصلة)', 'Tags (comma separated)', ['max' => 200]], ['image', 'image', 'الصورة', 'Image', ['kind' => 'cover']],
           ['status', 'select', 'الحالة', 'Status', ['pub' => true, 'options' => ['draft' => ['مسودة', 'Draft'], 'published' => ['منشور', 'Published'], 'archived' => ['مؤرشف', 'Archived']]]],
           ['publish_at', 'datetime', 'تاريخ ووقت النشر (للجدولة)', 'Publish date & time (scheduling)', []], ['featured', 'bool', 'تثبيت في المقدمة', 'Pin to top', []]], seo_fields())],
      'news_categories' => ['table' => 'news_categories', 'perm' => 'news', 'title' => ['تصنيفات الأخبار', 'News categories'], 'icon' => 'file', 'cols' => ['name_ar', 'name_en', 'sort'], 'order' => 'sort, id', 'parent_nav' => 'news',
        'fields' => array_merge(bi('name', 'text', 'الاسم', 'Name', ['req' => 'ar', 'max' => 60]), [['slug', 'slug', 'الرابط المختصر', 'Slug', ['from' => 'name_en']], $sort])],
      'notices' => ['table' => 'notices', 'perm' => 'notices', 'title' => ['الإعلانات والمناقصات', 'Announcements & tenders'], 'icon' => 'file', 'cols' => ['title_ar', 'kind', 'end_date', 'published'], 'order' => 'id DESC', 'path' => 'notices/',
        'fields' => array_merge(bi('title', 'text', 'العنوان', 'Title', ['req' => 'ar', 'max' => 160]), [['slug', 'slug', 'الرابط المختصر', 'Slug', ['from' => 'title_en']],
          ['kind', 'select', 'النوع', 'Type', ['options' => ['announcement' => ['إعلان رسمي', 'Announcement'], 'tender' => ['مناقصة', 'Tender'], 'job' => ['فرصة عمل', 'Job opportunity']]]], ['ref_no', 'text', 'الرقم المرجعي', 'Reference no.', ['max' => 40]]],
          bi('body', 'rich', 'التفاصيل', 'Details', []),
          [['start_date', 'date', 'تاريخ البداية (يظهر من)', 'Start date (visible from)', []], ['end_date', 'date', 'تاريخ الانتهاء (يُخفى بعده تلقائيًا)', 'End date (auto-hidden after)', []],
           ['attachments', 'files', 'المرفقات (PDF / Word حتى 4 ميغابايت)', 'Attachments (PDF / Word, up to 4 MB)', []], $pub], seo_fields())],
      'albums' => ['table' => 'albums', 'perm' => 'gallery', 'title' => ['معرض الصور والفيديو', 'Photo & video gallery'], 'icon' => 'image', 'cols' => ['title_ar', 'sort', 'published'], 'order' => 'sort, id DESC', 'path' => 'gallery/',
        'fields' => array_merge(bi('title', 'text', 'اسم الألبوم', 'Album title', ['req' => 'ar', 'max' => 120]), [['slug', 'slug', 'الرابط المختصر', 'Slug', ['from' => 'title_en']]], bi('desc', 'textarea', 'الوصف', 'Description', ['max' => 300]),
          [['image', 'image', 'صورة الغلاف', 'Cover image', ['kind' => 'cover']], ['gallery', 'gallery', 'صور الألبوم', 'Album photos', []],
           ['videos', 'videos', 'روابط الفيديو — سطر لكل فيديو: الرابط | الوصف بالعربي | الوصف بالإنجليزي (YouTube أو Vimeo)', 'Video links — one per line: URL | Arabic caption | English caption (YouTube or Vimeo)', []], $sort, $pub])],
      'jobs' => ['table' => 'jobs', 'perm' => 'jobs', 'title' => ['الوظائف', 'Jobs'], 'icon' => 'briefcase', 'cols' => ['title_ar', 'deadline', 'published'], 'order' => 'id DESC', 'path' => 'careers/',
        'fields' => array_merge(bi('title', 'text', 'المسمى الوظيفي', 'Job title', ['req' => 'ar', 'max' => 140]), [['slug', 'slug', 'الرابط المختصر', 'Slug', ['from' => 'title_en']]], bi('dept', 'text', 'القسم', 'Department', ['max' => 100]), bi('location', 'text', 'الموقع', 'Location', ['max' => 100]),
          [['jtype', 'select', 'نوع الدوام', 'Employment type', ['options' => ['full' => ['دوام كامل', 'Full-time'], 'part' => ['دوام جزئي', 'Part-time'], 'contract' => ['عقد', 'Contract']]]]],
          bi('body', 'rich', 'الوصف والمتطلبات', 'Description & requirements', []), [['deadline', 'date', 'آخر موعد للتقديم (يُغلق بعده)', 'Deadline (closes after)', []], $pub])],
      'partners' => ['table' => 'partners', 'perm' => 'partners', 'title' => ['شركاء النجاح', 'Partners'], 'icon' => 'hand', 'cols' => ['name_ar', 'sort', 'published'], 'order' => 'sort, id',
        'fields' => array_merge(bi('name', 'text', 'الاسم', 'Name', ['req' => 'ar', 'max' => 120]), [['url', 'url', 'الموقع الإلكتروني', 'Website', ['abs' => true]], ['image', 'image', 'الشعار (PNG شفاف يُفضّل)', 'Logo (transparent PNG preferred)', ['kind' => 'logo']], $sort, $pub])],
      'stats' => ['table' => 'stats', 'perm' => 'stats', 'title' => ['الإحصائيات', 'Statistics'], 'icon' => 'chart', 'cols' => ['label_ar', 'value', 'published'], 'order' => 'sort, id',
        'fields' => array_merge(bi('label', 'text', 'الوصف', 'Label', ['req' => 'ar', 'max' => 80]), [['value', 'text', 'القيمة (رقم)', 'Value (number)', ['max' => 12, 'req' => true]]], bi('suffix', 'text', 'لاحقة (مثل +)', 'Suffix (e.g. +)', ['max' => 10]),
          [$icon, $sort, $pub])],
      'pages' => ['table' => 'pages', 'perm' => 'pages', 'title' => ['الصفحات الثابتة', 'Static pages'], 'icon' => 'file', 'cols' => ['title_ar', 'slug', 'in_footer', 'published'], 'order' => 'sort, id', 'path' => 'p/', 'locked_slugs' => ['about', 'vision', 'mission'],
        'fields' => array_merge(bi('title', 'text', 'العنوان', 'Title', ['req' => 'ar', 'max' => 120]), [['slug', 'slug', 'الرابط المختصر', 'Slug', ['from' => 'title_en']]], bi('body', 'rich', 'المحتوى', 'Content', []),
          [['in_footer', 'bool', 'رابط في التذييل', 'Link in footer', []], $sort, $pub], seo_fields())],
      'company_values' => ['table' => 'company_values', 'perm' => 'about', 'title' => ['القيم', 'Values'], 'icon' => 'shield', 'cols' => ['title_ar', 'sort', 'published'], 'order' => 'sort, id', 'group' => 'about',
        'fields' => array_merge(bi('title', 'text', 'القيمة', 'Value', ['req' => 'ar', 'max' => 60]), bi('body', 'textarea', 'الوصف', 'Description', ['max' => 240]), [$icon, $sort, $pub])],
      'org_units' => ['table' => 'org_units', 'perm' => 'about', 'title' => ['الهيكل التنظيمي', 'Organisational structure'], 'icon' => 'users', 'cols' => ['name_ar', 'parent_id', 'sort'], 'order' => 'parent_id, sort, id', 'group' => 'about',
        'fields' => array_merge(bi('name', 'text', 'اسم الوحدة', 'Unit name', ['req' => 'ar', 'max' => 100]), [['parent_id', 'parent', 'تتبع لـ', 'Reports to', []], $sort, $pub])],
      'team' => ['table' => 'team', 'perm' => 'about', 'title' => ['فريق الإدارة', 'Management team'], 'icon' => 'users', 'cols' => ['name_ar', 'role_ar', 'published'], 'order' => 'sort, id', 'group' => 'about',
        'fields' => array_merge(bi('name', 'text', 'الاسم', 'Name', ['req' => 'ar', 'max' => 100]), bi('role', 'text', 'المنصب', 'Position', ['max' => 100]), bi('bio', 'textarea', 'نبذة', 'Bio', ['max' => 300]),
          [['image', 'image', 'الصورة الشخصية', 'Photo', ['kind' => 'photo']], $sort, $pub])],
      'menu_items' => ['table' => 'menu_items', 'perm' => 'menus', 'title' => ['القوائم', 'Menus'], 'icon' => 'menu', 'cols' => ['label_ar', 'location', 'url', 'sort'], 'order' => 'location, sort, id',
        'note' => ['إذا كانت قائمة الرأس أو التذييل فارغة تُستخدم القائمة الافتراضية. اكتب الروابط الداخلية بدون اللغة، مثل /projects', 'If the header or footer menu is empty the default menu is used. Write internal links without the language, e.g. /projects'],
        'fields' => array_merge(bi('label', 'text', 'النص', 'Label', ['req' => 'ar', 'max' => 40]), [['url', 'url', 'الرابط', 'Link', ['req' => true]],
          ['location', 'select', 'المكان', 'Location', ['options' => ['header' => ['قائمة الرأس', 'Header menu'], 'footer' => ['روابط التذييل', 'Footer links']]]], $sort, $pub])],
    ];
    return $M;
}
