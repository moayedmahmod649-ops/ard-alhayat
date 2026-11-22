<?php
declare(strict_types=1);
/**
 * Installer (CLI): php app/seed.php [--reset]
 * Creates the database, an admin account with a random password (written to ../CREDENTIALS.txt, outside the web root),
 * and starter content. Nothing factual is invented: items that need the company's real details (projects, jobs, notices,
 * team, stats) are created as unpublished drafts, ready to be completed and published from the admin panel.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/bootstrap.php';
if (in_array('--reset', $argv, true)) {
    foreach ([$CONFIG['db_path'], $CONFIG['db_path'] . '-wal', $CONFIG['db_path'] . '-shm'] as $f) if (is_file($f)) unlink($f);
    foreach (glob($CONFIG['files_dir'] . '/*') ?: [] as $f) if (is_file($f) && basename($f) !== '.htaccess') unlink($f);
    page_cache_clear();
}
if ((int)qv('SELECT COUNT(*) FROM users') > 0) { fwrite(STDERR, "Already installed. Use --reset to wipe and re-seed.\n"); exit(1); }
define('LANG', 'ar');
$S = '';
$SE = '';
$ins = function (string $table, array $row) { $k = array_keys($row); q("INSERT INTO $table (" . implode(',', $k) . ') VALUES (' . implode(',', array_fill(0, count($k), '?')) . ')', array_values($row)); return (int)db()->lastInsertId(); };

// --- admin ---
$pw = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Kq'), '=') . '7a';
$ins('users', ['username' => 'admin', 'email' => '', 'display_name' => 'مدير النظام', 'role' => 'admin', 'pass_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change' => 1]);
$cred = dirname(APP_DIR) . '/CREDENTIALS.txt';
file_put_contents($cred, "Ard Al-Hayat CMS — first login\n\nURL: /admin/\nUsername: admin\nPassword: $pw\n\nYou will be asked to change this password at first login.\nDELETE THIS FILE after the first login. Never upload it to the web root.\n");
@chmod($cred, 0600);

// --- settings ---
foreach ([
    'company_name_ar' => 'شركة أرض الحياة للتنمية الزراعية والإنتاج الحيواني المساهمة', 'company_name_en' => 'Ard Al-Hayat Agricultural Development & Livestock Production JSC',
    'seo_title_ar' => 'شركة أرض الحياة للتنمية الزراعية والإنتاج الحيواني', 'seo_title_en' => 'Ard Al-Hayat — Agricultural Development & Livestock Production',
    'seo_desc_ar' => 'شركة أرض الحياة للتنمية الزراعية والإنتاج الحيواني المساهمة — شركة ليبية تعمل في قطاعي التنمية الزراعية والإنتاج الحيواني.',
    'seo_desc_en' => 'Ard Al-Hayat Agricultural Development & Livestock Production JSC — a Libyan company working in agricultural development and livestock production.',
    'footer_about_ar' => 'شركة مساهمة ليبية تعمل في قطاعي التنمية الزراعية والإنتاج الحيواني، وتتواصل مع العملاء والشركاء والمستثمرين محليًا ودوليًا.',
    'footer_about_en' => 'A Libyan joint-stock company working in agricultural development and livestock production, connecting with clients, partners and investors locally and internationally.',
    'home_about_title_ar' => 'جذور في الأرض، ورؤية للمستقبل', 'home_about_title_en' => 'Rooted in the land, looking to the future',
    'home_about_ar' => 'تعمل شركة أرض الحياة في قطاعي التنمية الزراعية والإنتاج الحيواني، وتسعى إلى تقديم صورة احترافية موثوقة عن أنشطتها ومشاريعها، ودعم تواصلها مع العملاء والشركاء والمستثمرين.',
    'home_about_en' => 'Ard Al-Hayat works in agricultural development and livestock production, presenting its activities and projects professionally and supporting its communication with clients, partners and investors.',
    'noindex' => '1', 'double_optin' => '0', 'mail_from_name' => 'أرض الحياة',
    'brand_primary' => '#0F2A12', 'brand_accent' => '#C8892A',
] as $k => $v) set_setting($k, $v);

// --- pages ---
$ins('pages', ['slug' => 'about', 'title_ar' => 'من نحن', 'title_en' => 'Who we are', 'sort' => 1,
  'body_ar' => '<p>شركة أرض الحياة للتنمية الزراعية والإنتاج الحيواني شركة مساهمة ليبية تعمل في قطاعي <strong>التنمية الزراعية</strong> و<strong>الإنتاج الحيواني</strong>.</p><p>تسعى الشركة إلى تقديم صورة احترافية موثوقة عن أنشطتها ومشاريعها، ونشر أخبارها وإعلاناتها، واستقبال استفسارات العملاء وطلبات الشراكة والتوظيف، ومخاطبة جمهور محلي ودولي.</p>' . $S,
  'body_en' => '<p>Ard Al-Hayat Agricultural Development & Livestock Production is a Libyan joint-stock company working in <strong>agricultural development</strong> and <strong>livestock production</strong>.</p><p>The company presents its activities and projects professionally, publishes its news and announcements, receives client enquiries, partnership and job applications, and speaks to local and international audiences.</p>' . $SE]);
$ins('pages', ['slug' => 'vision', 'title_ar' => 'رؤيتنا', 'title_en' => 'Our vision', 'sort' => 2,
  'body_ar' => '<p>أن نسهم في تنمية القطاع الزراعي والإنتاج الحيواني في ليبيا، بممارسات حديثة ومستدامة وجودة يمكن الاعتماد عليها.</p>', 'body_en' => '<p>To contribute to the growth of agriculture and livestock production in Libya through modern, sustainable practice and dependable quality.</p>']);
$ins('pages', ['slug' => 'mission', 'title_ar' => 'رسالتنا', 'title_en' => 'Our mission', 'sort' => 3,
  'body_ar' => '<p>تطوير الأراضي الزراعية والثروة الحيوانية بأساليب مدروسة، وبناء شراكات محلية ودولية تخدم الأمن الغذائي والمجتمع.</p>', 'body_en' => '<p>To develop farmland and livestock with well-planned methods, and to build local and international partnerships that serve food security and the community.</p>']);
$ins('pages', ['slug' => 'privacy', 'title_ar' => 'سياسة الخصوصية', 'title_en' => 'Privacy policy', 'in_footer' => 1, 'sort' => 9,
  'body_ar' => '<h2>البيانات التي نجمعها</h2><p>نجمع البيانات التي تُرسلها عبر نماذج الموقع فقط (التواصل، التوظيف، القائمة البريدية) لغرض الرد على طلبك.</p><h2>ملفات السيرة الذاتية</h2><p>تُحفظ في مكان غير متاح للعامة ولا يطّلع عليها إلا المخوَّلون.</p><h2>إلغاء الاشتراك</h2><p>يمكنك إلغاء الاشتراك في القائمة البريدية في أي وقت عبر الرابط المرفق بكل رسالة.</p>' . '<p class="sample-inline">مسودة — تحتاج إلى مراجعة قانونية واعتماد من الشركة.</p>',
  'body_en' => '<h2>Data we collect</h2><p>We only collect what you send through the site forms (contact, careers, newsletter) to respond to your request.</p><h2>CV files</h2><p>Stored in a non-public location and accessed only by authorised staff.</p><h2>Unsubscribing</h2><p>You can unsubscribe from the newsletter at any time using the link in every message.</p><p class="sample-inline">Draft — requires legal review and company approval.</p>']);

// --- slides ---
$ins('slides', ['sort' => 1, 'image' => '/assets/img/hero-olive.webp', 'link' => '/activities',
  'title_ar' => 'أرض الحياة… تنمية زراعية وإنتاج حيواني', 'title_en' => 'Ard Al-Hayat: agricultural development & livestock production',
  'sub_ar' => 'شركة مساهمة ليبية تعمل في قطاعي التنمية الزراعية والإنتاج الحيواني، وتخاطب عملاءها وشركاءها ومستثمريها بلغتين.', 'sub_en' => 'A Libyan joint-stock company in agricultural development and livestock production, speaking to clients, partners and investors in two languages.',
  'cta_ar' => 'استكشف أنشطتنا', 'cta_en' => 'Explore our activities']);
$ins('slides', ['sort' => 2, 'image' => '/assets/img/hero-pivot.webp', 'link' => '/projects',
  'title_ar' => 'مشاريع زراعية منفّذة وجارية', 'title_en' => 'Completed and ongoing agricultural projects',
  'sub_ar' => 'تعرّف على مشاريع الشركة بالصور والتفاصيل والمواقع.', 'sub_en' => 'Discover the company\'s projects with photos, details and locations.', 'cta_ar' => 'مشاريعنا', 'cta_en' => 'Our projects']);
$ins('slides', ['sort' => 3, 'image' => '/assets/img/hero-cattle.webp', 'link' => '/contact?topic=partner',
  'title_ar' => 'شراكات تنمو مع الأرض', 'title_en' => 'Partnerships that grow with the land',
  'sub_ar' => 'نرحّب بطلبات الشراكة والتعاون مع الجهات المحلية والدولية.', 'sub_en' => 'We welcome partnership and cooperation requests from local and international organisations.', 'cta_ar' => 'طلب شراكة', 'cta_en' => 'Partnership request']);

// --- stats (unpublished until the company enters approved figures) ---
foreach ([['هكتار من الأراضي الزراعية', 'hectares of farmland', 'wheat'], ['رأس من الثروة الحيوانية', 'head of livestock', 'cow'], ['مشروع منفّذ وجارٍ', 'completed & ongoing projects', 'chart'], ['شريك نجاح', 'partners in success', 'hand']] as $i => [$ar, $en, $ic])
    $ins('stats', ['label_ar' => $ar, 'label_en' => $en, 'value' => '', 'suffix_ar' => '+', 'suffix_en' => '+', 'icon' => $ic, 'is_sample' => 0, 'published' => 0, 'sort' => $i]);

// --- values ---
foreach ([['الاستدامة', 'Sustainability', 'sprout', 'حماية الأرض والمياه لأجيال قادمة.', 'Protecting land and water for generations to come.'],
          ['الجودة', 'Quality', 'shield', 'معايير واضحة في الزراعة والإنتاج الحيواني.', 'Clear standards in farming and livestock production.'],
          ['الشفافية', 'Transparency', 'eye', 'إعلانات ومناقصات واضحة ومواعيد معلنة.', 'Clear announcements, tenders and published deadlines.'],
          ['الشراكة', 'Partnership', 'hand', 'تعاون مع الشركاء والمستثمرين والمجتمع.', 'Cooperation with partners, investors and the community.']] as $i => [$ar, $en, $ic, $bar, $ben])
    $ins('company_values', ['title_ar' => $ar, 'title_en' => $en, 'icon' => $ic, 'body_ar' => $bar, 'body_en' => $ben, 'sort' => $i]);

// --- org structure (typical units; edit to match the company) ---
$ga = $ins('org_units', ['name_ar' => 'الجمعية العمومية', 'name_en' => 'General Assembly', 'sort' => 1]);
$bd = $ins('org_units', ['name_ar' => 'مجلس الإدارة', 'name_en' => 'Board of Directors', 'parent_id' => $ga, 'sort' => 1]);
$gm = $ins('org_units', ['name_ar' => 'الإدارة العامة', 'name_en' => 'General Management', 'parent_id' => $bd, 'sort' => 1]);
foreach ([['إدارة الأنشطة الزراعية', 'Agricultural Activities'], ['إدارة الإنتاج الحيواني', 'Livestock Production'], ['إدارة المشاريع', 'Projects'], ['الشؤون الإدارية والمالية', 'Administration & Finance'], ['الموارد البشرية', 'Human Resources']] as $i => [$ar, $en])
    $ins('org_units', ['name_ar' => $ar, 'name_en' => $en, 'parent_id' => $gm, 'sort' => $i]);

// --- team (roles only, unpublished until names and photos are added) ---
foreach ([['رئيس مجلس الإدارة', 'Chairman of the Board'], ['المدير العام', 'General Manager'], ['مدير الأنشطة الزراعية', 'Head of Agricultural Activities'], ['مدير الإنتاج الحيواني', 'Head of Livestock Production']] as $i => [$ar, $en])
    $ins('team', ['name_ar' => $ar, 'name_en' => $en, 'role_ar' => $ar, 'role_en' => $en, 'is_sample' => 0, 'published' => 0, 'sort' => $i]);

// --- activities (the two sectors named in the proposal; short neutral descriptions) ---
$acts = [
  ['field-crops', 'agri', 'wheat', '/assets/img/wheat.webp', 'المحاصيل الحقلية والحبوب', 'Field crops & grains', 'زراعة القمح والشعير والمحاصيل الحقلية.', 'Wheat, barley and other field crops.', 1],
  ['olives-orchards', 'agri', 'olive', '/assets/img/olive.webp', 'الزيتون والبساتين', 'Olives & orchards', 'بساتين الزيتون والأشجار المثمرة.', 'Olive groves and fruit orchards.', 1],
  ['greenhouses', 'agri', 'greenhouse', '/assets/img/greenhouse.webp', 'الزراعة المحمية', 'Protected agriculture', 'البيوت المحمية لإنتاج الخضروات.', 'Greenhouses for vegetable production.', 1],
  ['nurseries-irrigation', 'agri', 'drop', '/assets/img/nursery.webp', 'المشاتل والري الحديث', 'Nurseries & modern irrigation', 'إنتاج الشتلات وأنظمة الري الحديثة.', 'Seedling production and modern irrigation systems.', 0],
  ['cattle-dairy', 'livestock', 'cow', '/assets/img/dairy.webp', 'الأبقار وإنتاج الألبان', 'Cattle & dairy', 'تربية الأبقار ورعايتها وإنتاج الألبان.', 'Cattle husbandry, care and dairy production.', 1],
  ['sheep-goats', 'livestock', 'sheep', '/assets/img/sheep.webp', 'الأغنام والماعز', 'Sheep & goats', 'تربية الأغنام والماعز في المراعي.', 'Sheep and goat husbandry on pasture.', 1],
  ['feed', 'livestock', 'silo', '/assets/img/silos.webp', 'الأعلاف والتخزين', 'Feed & storage', 'إنتاج الأعلاف وتخزين الحبوب.', 'Feed production and grain storage.', 1],
];
foreach ($acts as $i => [$slug, $sec, $ic, $img, $tar, $ten, $sar, $sen, $feat])
    $ins('activities', ['slug' => $slug, 'sector' => $sec, 'icon' => $ic, 'image' => $img, 'title_ar' => $tar, 'title_en' => $ten, 'summary_ar' => $sar, 'summary_en' => $sen, 'featured' => $feat, 'sort' => $i,
      'body_ar' => "<p>$sar</p>",
      'body_en' => "<p>$sen</p>",
      'gallery' => json_encode([['src' => $img, 'ar' => $tar, 'en' => $ten]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

// --- projects (unpublished drafts: the company confirms real projects before publishing) ---
foreach ([
  ['pivot-irrigation', 'ongoing', 'agri', '/assets/img/hero-pivot.webp', 'مشروع الري المحوري للحبوب', 'Centre-pivot irrigation for grains', 'مشروع ري محوري لزراعة الحبوب.', 'A centre-pivot irrigation project for grain crops.', 1],
  ['olive-nursery', 'completed', 'agri', '/assets/img/nursery.webp', 'مشتل شتلات الزيتون', 'Olive seedling nursery', 'مشتل لإنتاج شتلات الزيتون.', 'A nursery producing olive seedlings.', 0],
  ['dairy-farm', 'ongoing', 'livestock', '/assets/img/dairy.webp', 'تطوير مزرعة الأبقار', 'Dairy farm development', 'تطوير مزرعة لتربية الأبقار وإنتاج الألبان.', 'Developing a cattle and dairy farm.', 0],
] as $i => [$slug, $st, $sec, $img, $tar, $ten, $sar, $sen, $feat])
    $ins('projects', ['slug' => $slug, 'status' => $st, 'sector' => $sec, 'image' => $img, 'title_ar' => $tar, 'title_en' => $ten, 'summary_ar' => $sar, 'summary_en' => $sen, 'featured' => $feat, 'sort' => $i, 'is_sample' => 0, 'published' => 0,
      'location_ar' => '', 'location_en' => '', 'year' => '',
      'body_ar' => "<p>$sar</p>", 'body_en' => "<p>$sen</p>",
      'gallery' => json_encode([['src' => $img, 'ar' => $tar, 'en' => $ten], ['src' => '/assets/img/irrigation.webp', 'ar' => 'الري الحديث', 'en' => 'Modern irrigation']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

// --- news ---
$cats = [];
foreach ([['company', 'أخبار الشركة', 'Company news'], ['agriculture', 'الزراعة', 'Agriculture'], ['livestock', 'الإنتاج الحيواني', 'Livestock'], ['events', 'فعاليات', 'Events']] as $i => [$s, $ar, $en])
    $cats[$s] = $ins('news_categories', ['slug' => $s, 'name_ar' => $ar, 'name_en' => $en, 'sort' => $i]);
foreach ([
  ['new-website', 'company', '/assets/img/office.webp', 'إطلاق الموقع الإلكتروني الجديد للشركة', 'The company launches its new website', 'موقع ثنائي اللغة يعرّف بأنشطة الشركة ومشاريعها وأخبارها.', 'A bilingual website presenting the company\'s activities, projects and news.', 'موقع,إطلاق', 2],
  ['harvest-season', 'agriculture', '/assets/img/harvest.webp', 'الاستعداد لموسم الحصاد', 'Preparing for the harvest season', 'تستعد فرق العمل لموسم الحصاد.', 'Teams prepare for the harvest season.', 'حصاد,زيتون', 6],
  ['livestock-care', 'livestock', '/assets/img/hero-cattle.webp', 'رعاية الثروة الحيوانية', 'Caring for livestock', 'عن رعاية الثروة الحيوانية وصحتها.', 'On livestock care and animal health.', 'ماشية,رعاية', 10],
] as [$slug, $cat, $img, $tar, $ten, $ear, $een, $tags, $daysAgo])
    $ins('news', ['slug' => $slug, 'category_id' => $cats[$cat], 'image' => $img, 'title_ar' => $tar, 'title_en' => $ten, 'excerpt_ar' => $ear, 'excerpt_en' => $een, 'tags' => $tags, 'status' => $slug === 'new-website' ? 'published' : 'draft', 'is_sample' => 0,
      'publish_at' => date('Y-m-d H:i:s', time() - $daysAgo * 86400),
      'body_ar' => $slug === 'new-website' ? "<p>$ear</p><p>يضمّ الموقع صفحات للتعريف بالشركة وأنشطتها في التنمية الزراعية والإنتاج الحيواني، وأقسامًا للمشاريع والأخبار والإعلانات والمناقصات والوظائف، ونماذج للتواصل وطلبات الشراكة والتوظيف.</p>" : "<p>$ear</p>",
      'body_en' => $slug === 'new-website' ? "<p>$een</p><p>It introduces the company and its work in agricultural development and livestock production, with sections for projects, news, announcements and tenders, careers, and forms for enquiries, partnership requests and job applications.</p>" : "<p>$een</p>"]);

// --- notices (unpublished drafts; the company adds real references, dates and attachments) ---
$ins('notices', ['slug' => 'tender-agri-inputs', 'kind' => 'tender', 'ref_no' => '', 'is_sample' => 0, 'published' => 0, 'start_date' => today(), 'end_date' => date('Y-m-d', time() + 30 * 86400),
  'title_ar' => 'مناقصة توريد مستلزمات زراعية', 'title_en' => 'Tender: supply of agricultural inputs',
  'body_ar' => '<p>تعلن الشركة عن طرح مناقصة لتوريد مستلزمات زراعية. تتوفر الشروط والمواصفات في المرفقات.</p>', 'body_en' => '<p>The company invites bids for the supply of agricultural inputs. Terms and specifications are in the attachments.</p>',
  'attachments' => '']);
$ins('notices', ['slug' => 'official-announcement', 'kind' => 'announcement', 'is_sample' => 0, 'published' => 0, 'start_date' => today(), 'end_date' => date('Y-m-d', time() + 30 * 86400),
  'title_ar' => 'إعلان رسمي', 'title_en' => 'Official announcement',
  'body_ar' => '<p>نص الإعلان الرسمي.</p>', 'body_en' => '<p>Announcement text.</p>']);

// --- jobs (unpublished drafts) ---
$ins('jobs', ['slug' => 'agricultural-engineer', 'is_sample' => 0, 'published' => 0, 'jtype' => 'full', 'deadline' => date('Y-m-d', time() + 30 * 86400),
  'title_ar' => 'مهندس زراعي', 'title_en' => 'Agricultural engineer', 'dept_ar' => 'إدارة الأنشطة الزراعية', 'dept_en' => 'Agricultural Activities',
  'body_ar' => '<h2>المهام</h2><ul><li>متابعة الأعمال الزراعية في الحقول والمشاتل.</li></ul><h2>المتطلبات</h2><ul><li>مؤهل في الهندسة الزراعية.</li></ul>',
  'body_en' => '<h2>Responsibilities</h2><ul><li>Supervising field and nursery work.</li></ul><h2>Requirements</h2><ul><li>A degree in agricultural engineering.</li></ul>']);
$ins('jobs', ['slug' => 'veterinarian', 'is_sample' => 0, 'published' => 0, 'jtype' => 'contract', 'deadline' => '',
  'title_ar' => 'طبيب بيطري', 'title_en' => 'Veterinarian', 'dept_ar' => 'إدارة الإنتاج الحيواني', 'dept_en' => 'Livestock Production',
  'body_ar' => '<h2>المهام</h2><ul><li>الرعاية البيطرية للقطعان.</li></ul>', 'body_en' => '<h2>Responsibilities</h2><ul><li>Veterinary care for the herds.</li></ul>']);

// --- gallery ---
$g = fn(array $l) => json_encode(array_map(fn($x) => ['src' => "/assets/img/{$x[0]}.webp", 'ar' => $x[1], 'en' => $x[2]], $l), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ins('albums', ['slug' => 'fields-farms', 'sort' => 1, 'image' => '/assets/img/wheat.webp', 'title_ar' => 'الحقول والمزارع', 'title_en' => 'Fields & farms', 'desc_ar' => 'مشاهد من الحقول والمزارع.', 'desc_en' => 'Scenes from fields and farms.',
  'gallery' => $g([['wheat', 'حقول القمح', 'Wheat fields'], ['olive', 'أغصان الزيتون', 'Olive branches'], ['hero-pivot', 'الري المحوري', 'Centre-pivot irrigation'], ['greenhouse', 'البيوت المحمية', 'Greenhouses'], ['nursery', 'مشتل الزيتون', 'Olive nursery'], ['irrigation', 'الري الحديث', 'Modern irrigation'], ['palms', 'النخيل', 'Date palms'], ['harvest', 'موسم الزيتون', 'Olive season']])]);
$ins('albums', ['slug' => 'livestock', 'sort' => 2, 'image' => '/assets/img/hero-cattle.webp', 'title_ar' => 'الإنتاج الحيواني', 'title_en' => 'Livestock production', 'desc_ar' => 'مشاهد من الإنتاج الحيواني.', 'desc_en' => 'Scenes from livestock production.',
  'gallery' => $g([['hero-cattle', 'المراعي', 'Pastures'], ['dairy', 'الأبقار', 'Dairy cattle'], ['sheep', 'الأغنام', 'Sheep'], ['silos', 'صوامع الأعلاف', 'Feed silos'], ['hay', 'الأعلاف', 'Hay'], ['careers', 'ورشة المزرعة', 'Farm workshop']])]);

// --- partners: none until the company supplies real names and logos ---

echo "Installed. Admin credentials written to $cred\n";
