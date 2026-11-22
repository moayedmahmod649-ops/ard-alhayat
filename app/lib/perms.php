<?php
declare(strict_types=1);
/**
 * Permission levels per admin section: 0 none · 1 view · 2 edit (create/update drafts; cannot publish or delete) · 3 full (publish, delete, export).
 * Four ready roles (from the proposal) + per-user overrides stored as JSON in users.perms.
 */
const PERM_NONE = 0, PERM_VIEW = 1, PERM_EDIT = 2, PERM_FULL = 3;
const SECTIONS = [
    // key => [ar, en, group]
    'slides'     => ['الشريط الرئيسي', 'Home slider', 'content'],
    'pages'      => ['الصفحات الثابتة', 'Static pages', 'content'],
    'about'      => ['من نحن: القيم والهيكل والفريق', 'About: values, structure, team', 'content'],
    'stats'      => ['الإحصائيات', 'Statistics', 'content'],
    'activities' => ['الأنشطة والخدمات', 'Activities & services', 'content'],
    'projects'   => ['المشاريع', 'Projects', 'content'],
    'news'       => ['الأخبار والمقالات', 'News & articles', 'content'],
    'notices'    => ['الإعلانات والمناقصات', 'Announcements & tenders', 'content'],
    'gallery'    => ['معرض الصور والفيديو', 'Photo & video gallery', 'content'],
    'partners'   => ['شركاء النجاح', 'Partners', 'content'],
    'media'      => ['مكتبة الوسائط', 'Media library', 'content'],
    'menus'      => ['القوائم والتذييل', 'Menus & footer', 'content'],
    'jobs'       => ['الوظائف', 'Jobs', 'hr'],
    'applications'=> ['طلبات التوظيف', 'Job applications', 'hr'],
    'messages'   => ['رسائل اتصل بنا', 'Contact messages', 'interaction'],
    'subscribers'=> ['القائمة البريدية', 'Newsletter', 'interaction'],
    'users'      => ['المستخدمون والصلاحيات', 'Users & permissions', 'system'],
    'audit'      => ['سجل النشاط', 'Audit log', 'system'],
    'settings'   => ['الإعدادات العامة', 'General settings', 'system'],
    'backup'     => ['النسخ الاحتياطي', 'Backup & restore', 'system'],
];
const ROLES = [
    'admin'   => ['مدير النظام', 'System administrator'],
    'content' => ['مدير المحتوى', 'Content manager'],
    'editor'  => ['محرر', 'Editor'],
    'hr'      => ['مسؤول الموارد البشرية', 'HR officer'],
];
function role_defaults(string $role): array {
    $p = array_fill_keys(array_keys(SECTIONS), PERM_NONE);
    foreach (SECTIONS as $k => $s) {
        switch ($role) {
            case 'admin': $p[$k] = PERM_FULL; break;
            case 'content':
                if ($s[2] === 'content' || $s[2] === 'interaction') $p[$k] = PERM_FULL;
                if ($k === 'jobs') $p[$k] = PERM_VIEW;
                break;
            case 'editor':
                if ($s[2] === 'content' && $k !== 'menus') $p[$k] = PERM_EDIT;
                break;
            case 'hr':
                if ($s[2] === 'hr') $p[$k] = PERM_FULL;
                if ($k === 'notices') $p[$k] = PERM_EDIT; // job notices
                if ($k === 'media') $p[$k] = PERM_EDIT;
                break;
        }
    }
    return $p;
}
function user_perms(array $u): array {
    $p = role_defaults($u['role']);
    if ($u['role'] === 'admin') return $p; // admins cannot be locked out by overrides
    foreach (json_list((string)$u['perms']) as $k => $lvl) if (isset($p[$k])) $p[$k] = max(0, min(3, (int)$lvl));
    return $p;
}
function can(string $section, int $level = PERM_VIEW): bool {
    $u = current_user();
    if (!$u) return false;
    return (user_perms($u)[$section] ?? 0) >= $level;
}
