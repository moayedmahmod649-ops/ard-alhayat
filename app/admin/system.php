<?php
declare(strict_types=1);
/* System pages: users & permissions, settings, backup/restore, media library, user guide. */

function en(): bool { return ADMIN_LANG === 'en'; }
function active_admins(?int $except = null): int {
    return (int)qv("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1" . ($except ? ' AND id <> ' . (int)$except : ''));
}

/* ---------------- users & permissions ---------------- */
function page_users(bool $isPost): never {
    need('users');
    $me = current_user();
    $err = []; $issued = '';
    if ($isPost) {
        csrf_check(); need('users', PERM_FULL);
        if ($me['role'] !== 'admin') deny(); // only real system administrators may change accounts, roles or permissions
        $act = post('act'); $id = (int)post('id');
        db()->exec('BEGIN IMMEDIATE'); // serialise last-admin checks with the mutation
        register_shutdown_function(function () { try { if (db()->inTransaction()) db()->exec('COMMIT'); } catch (Throwable) {} });
        $r = $id ? q1('SELECT * FROM users WHERE id = ?', [$id]) : null;
        if ($act === 'delete' && $r) {
            if ($id === (int)$me['id']) { aflash(ta('cant_self'), 'err'); redirect(A('p=users')); }
            if ($r['role'] === 'admin' && active_admins($id) < 1) { aflash(ta('last_admin'), 'err'); redirect(A('p=users')); }
            q('DELETE FROM users WHERE id = ?', [$id]); audit('delete', 'users', $id, $r['username']); aflash(ta('deleted')); redirect(A('p=users'));
        }
        if ($act === 'reset' && $r) {
            $tok = create_reset_token($id, (int)$me['id']);
            audit('reset_issued', 'users', $id, $r['username']);
            $issued = base_url() . '/admin/?a=reset&t=' . $tok;
            $_GET['id'] = (string)$id;
        }
        if ($act === 'save') {
            $username = mb_strtolower(post('username')); $email = mb_strtolower(post('email'));
            $role = isset(ROLES[post('role')]) ? post('role') : 'editor'; $active = post_bool('active');
            if (!$r && !preg_match('/^[a-z0-9._-]{3,32}$/', $username)) $err['username'] = ta('bad_username');
            if (!$r && q1('SELECT id FROM users WHERE username = ?', [$username])) $err['username'] = ta('dup_user');
            if ($email !== '' && !valid_email($email)) $err['email'] = ta('bad_url');
            elseif ($email !== '' && q1('SELECT id FROM users WHERE lower(email) = ? AND id <> ?', [$email, $id])) $err['email'] = ta('dup_user');
            if ($r && $id === (int)$me['id'] && (!$active || $role !== 'admin') && $me['role'] === 'admin') $err['role'] = ta('cant_self');
            if ($r && $r['role'] === 'admin' && ($role !== 'admin' || !$active) && active_admins($id) < 1) $err['role'] = ta('last_admin');
            $pw = (string)($_POST['pw'] ?? '');
            if (!$r || $pw !== '') { if ($p = password_problem($pw, $username ?: (string)($r['username'] ?? ''))) $err['pw'] = ta($p); }
            $perms = [];
            foreach (SECTIONS as $k => $_) { $v = $_POST['perm'][$k] ?? ''; if (is_string($v) && $v !== '' && ctype_digit($v)) $perms[$k] = max(0, min(3, (int)$v)); }
            if (!$err) {
                $pj = $perms ? json_encode($perms) : '';
                $name = mb_substr(post('display_name'), 0, 80);
                if ($r) {
                    q('UPDATE users SET display_name = ?, email = ?, role = ?, perms = ?, active = ?, updated_at = ?' . (!$active ? ', session_ver = session_ver + 1' : '') . ' WHERE id = ?', [$name ?: $r['username'], $email, $role, $pj, $active, now_sql(), $id]);
                    if ($pw !== '') { set_password($id, $pw); q('UPDATE users SET must_change = 1 WHERE id = ?', [$id]); }
                    audit('update', 'users', $id, $r['username']);
                } else {
                    q('INSERT INTO users (username, email, display_name, role, perms, active, pass_hash, must_change) VALUES (?,?,?,?,?,?,?,1)', [$username, $email, $name ?: $username, $role, $pj, $active, password_hash($pw, PASSWORD_DEFAULT)]);
                    $id = (int)db()->lastInsertId(); audit('create', 'users', $id, $username);
                }
                aflash(ta('saved')); redirect(A('p=users'));
            }
            $_GET['id'] = $r ? (string)$id : 'new';
        }
    }
    $gid = get('id');
    if ($gid !== '') {
        $r = $gid === 'new' ? null : q1('SELECT * FROM users WHERE id = ?', [(int)$gid]);
        if ($gid !== 'new' && !$r) { http_response_code(404); admin_page('404', '<div class="empty">404</div>', 'users'); }
        $v = fn($k, $d = '') => $isPost && post('act') === 'save' ? post($k, $d) : (string)($r[$k] ?? $d);
        $fe = fn($k) => isset($err[$k]) ? '<p class="ferr" id="e_' . $k . '">' . e($err[$k]) . '</p>' : '';
        $ai = fn($k) => isset($err[$k]) ? ' aria-invalid="true" aria-describedby="e_' . $k . '"' : '';
        $cur = $r ? json_list((string)$r['perms']) : [];
        $role = $v('role', 'editor'); $defs = role_defaults($role);
        $ro = !can('users', PERM_FULL) || $me['role'] !== 'admin' ? ' disabled' : '';
        ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('shield') ?><?= e($r ? $r['username'] : (en() ? 'New user' : 'مستخدم جديد')) ?></h1><p class="muted"><?= e(ta('users')) ?></p></div>
<div class="head-actions"><a class="btn btn-ghost" href="<?= e(A('p=users')) ?>"><?= e(ta('back')) ?></a></div></div>
<?php if ($issued): ?><div class="alert alert-ok" role="status"><?= e(ta('reset_issued')) ?><br><code class="copy" dir="ltr"><?= e($issued) ?></code></div><?php endif ?>
<?php if ($err): ?><div class="alert alert-err" role="alert"><?= e(ta('fix_errors')) ?></div><?php endif ?>
<form method="post" action="<?= e(A('p=users')) ?>" class="edit-form">
<?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
<section class="card"><h2 class="h3"><?= e(ta('general')) ?></h2><div class="grid-f">
 <div class="fld"><label for="un"><?= e(en() ? 'Username' : 'اسم المستخدم') ?> <b class="req" aria-hidden="true">*</b></label><input id="un" name="username" dir="ltr" autocomplete="off" value="<?= e($v('username')) ?>"<?= $r ? ' readonly' : ' required' ?><?= $ai('username') ?><?= $ro ?>><?= $fe('username') ?></div>
 <div class="fld"><label for="dn"><?= e(ta('display_name')) ?></label><input id="dn" name="display_name" maxlength="80" value="<?= e($v('display_name')) ?>"<?= $ro ?>></div>
 <div class="fld"><label for="em"><?= e(ta('email')) ?></label><input id="em" name="email" type="email" dir="ltr" value="<?= e($v('email')) ?>"<?= $ai('email') ?><?= $ro ?>><?= $fe('email') ?><p class="hint"><?= e(en() ? 'Needed for “forgot password” emails.' : 'مطلوب لرسائل «نسيت كلمة المرور».') ?></p></div>
 <div class="fld fld-sm"><label for="rl"><?= e(ta('role')) ?></label><select id="rl" name="role"<?= $ai('role') ?><?= $ro ?>><?php foreach (ROLES as $k => $lab): ?><option value="<?= $k ?>"<?= $role === $k ? ' selected' : '' ?>><?= e(al($lab)) ?></option><?php endforeach ?></select><?= $fe('role') ?></div>
 <div class="fld"><label for="pw"><?= e($r ? ta('new_password') : ta('initial_pw')) ?><?= $r ? '' : ' <b class="req" aria-hidden="true">*</b>' ?></label><input id="pw" name="pw" type="password" dir="ltr" autocomplete="new-password"<?= $r ? '' : ' required' ?><?= $ai('pw') ?><?= $ro ?>><p class="hint"><?= e(ta('pw_short') . ' ' . ta('pw_mix') . ($r ? ' ' . (en() ? 'Leave empty to keep. The user must change it at next login.' : 'اتركه فارغًا للإبقاء عليها. سيُطلب من المستخدم تغييرها عند الدخول.') : '')) ?></p><?= $fe('pw') ?></div>
 <div class="fld fld-check"><input type="hidden" name="active" value="0"><input id="ac" type="checkbox" name="active" value="1"<?= ($r ? (int)$r['active'] : 1) ? ' checked' : '' ?><?= $ro ?>><label for="ac"><?= e(ta('user_active')) ?></label></div>
</div></section>
<section class="card"><h2 class="h3"><?= e(ta('perm_override')) ?></h2>
<p class="hint"><?= e(en() ? 'System administrators always have full access. For other roles, choose a level per section or keep the role default.' : 'مدير النظام يملك صلاحيات كاملة دائمًا. للأدوار الأخرى اختر مستوى لكل قسم أو اترك «حسب الدور».') ?></p>
<div class="table-wrap"><table class="tbl perm-tbl"><thead><tr><th scope="col"><?= e(en() ? 'Section' : 'القسم') ?></th><th scope="col"><?= e(ta('role_default')) ?></th><th scope="col"><?= e(en() ? 'Override' : 'تخصيص') ?></th></tr></thead><tbody>
<?php foreach (SECTIONS as $k => $s): $o = $cur[$k] ?? ''; ?><tr><th scope="row"><?= e(al($s)) ?></th><td><?= pill(ta('p' . $defs[$k]), $defs[$k] >= 3 ? 'ok' : ($defs[$k] ? 'warn' : 'muted')) ?></td>
<td><label class="sr" for="pm_<?= $k ?>"><?= e(al($s)) ?></label><select id="pm_<?= $k ?>" name="perm[<?= $k ?>]"<?= $ro ?>><option value=""><?= e(ta('role_default')) ?></option><?php for ($i = 0; $i <= 3; $i++): ?><option value="<?= $i ?>"<?= $o !== '' && (int)$o === $i ? ' selected' : '' ?>><?= e(ta('p' . $i)) ?></option><?php endfor ?></select></td></tr><?php endforeach ?>
</tbody></table></div></section>
<?php if (!$ro): ?><div class="form-actions"><button class="btn" type="submit"><?= aicon('check') ?><?= e(ta('save')) ?></button></div><?php endif ?>
</form>
<?php if ($r && !$ro): ?>
<div class="card stack"><form method="post" action="<?= e(A('p=users')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="act" value="reset"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-ghost" type="submit"><?= aicon('link') ?><?= e(ta('issue_reset')) ?></button></form>
<?php if ((int)$r['id'] !== (int)$me['id']): ?><form method="post" action="<?= e(A('p=users')) ?>" class="inline-form" data-confirm="<?= e(ta('confirm_delete')) ?>"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger" type="submit"><?= aicon('trash') ?><?= e(ta('delete')) ?></button></form><?php endif ?></div>
<?php endif;
        admin_page(ta('users'), (string)ob_get_clean(), 'users');
    }
    $rows = qall('SELECT * FROM users ORDER BY role, username');
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('shield') ?><?= e(ta('users')) ?></h1><p class="muted"><?= count($rows) ?> <?= e(ta('items')) ?></p></div>
<div class="head-actions"><?php if (can('users', PERM_FULL) && current_user()['role'] === 'admin'): ?><a class="btn" href="<?= e(A('p=users&id=new')) ?>"><?= aicon('users') ?><?= e(ta('add')) ?></a><?php endif ?></div></div>
<div class="role-cards"><?php foreach (ROLES as $k => $lab): $d = role_defaults($k); ?><div class="card role-card"><h2 class="h3"><?= e(al($lab)) ?></h2><p class="muted small"><?= e(implode(' · ', array_map(fn($s) => al(SECTIONS[$s]), array_keys(array_filter($d, fn($x) => $x >= PERM_EDIT))))) ?: '—' ?></p></div><?php endforeach ?></div>
<div class="table-wrap"><table class="tbl"><thead><tr><th scope="col"><?= e(en() ? 'User' : 'المستخدم') ?></th><th scope="col"><?= e(ta('role')) ?></th><th scope="col"><?= e(ta('status')) ?></th><th scope="col"><?= e(ta('last_login')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><a class="strong" href="<?= e(A('p=users&id=' . $r['id'])) ?>"><?= e($r['display_name'] ?: $r['username']) ?></a><br><span class="muted small" dir="ltr"><?= e($r['username']) ?><?= $r['email'] ? ' · ' . e($r['email']) : '' ?></span></td>
<td><?= e(al(ROLES[$r['role']] ?? [$r['role']])) ?><?= $r['perms'] !== '' && $r['perms'] !== '[]' ? ' ' . pill(en() ? 'custom' : 'مخصص', 'warn') : '' ?></td>
<td><?= (int)$r['active'] ? pill(ta('active'), 'ok') : pill(ta('inactive'), 'muted') ?><?= (int)$r['must_change'] ? ' ' . pill(en() ? 'must change password' : 'يجب تغيير كلمة المرور', 'warn') : '' ?></td><td><?= e(adate($r['last_login'])) ?></td></tr><?php endforeach ?>
</tbody></table></div>
<?php admin_page(ta('users'), (string)ob_get_clean(), 'users');
}

/* ---------------- settings ---------------- */
function settings_schema(): array {
    $b = fn($ar, $en) => en() ? $en : $ar;
    return [
      's_company' => [['company_name_ar', 'text', $b('اسم الشركة (عربي)', 'Company name (Arabic)'), 'ar'], ['company_name_en', 'text', $b('اسم الشركة (إنجليزي)', 'Company name (English)'), 'en'],
                      ['footer_about_ar', 'textarea', $b('نبذة التذييل (عربي)', 'Footer blurb (Arabic)'), 'ar'], ['footer_about_en', 'textarea', $b('نبذة التذييل (إنجليزي)', 'Footer blurb (English)'), 'en']],
      's_home'    => [['home_about_title_ar', 'text', $b('العنوان (عربي)', 'Title (Arabic)'), 'ar'], ['home_about_title_en', 'text', $b('العنوان (إنجليزي)', 'Title (English)'), 'en'], ['home_about_ar', 'textarea', $b('النص (عربي)', 'Text (Arabic)'), 'ar'], ['home_about_en', 'textarea', $b('النص (إنجليزي)', 'Text (English)'), 'en']],
      's_contact' => [['phone', 'text', $b('الهاتف', 'Phone'), 'ltr'], ['whatsapp', 'text', 'WhatsApp', 'ltr'], ['email', 'email', $b('البريد العام', 'Public email'), 'ltr'],
                      ['address_ar', 'text', $b('العنوان (عربي)', 'Address (Arabic)'), 'ar'], ['address_en', 'text', $b('العنوان (إنجليزي)', 'Address (English)'), 'en'],
                      ['hours_ar', 'text', $b('ساعات العمل (عربي)', 'Working hours (Arabic)'), 'ar'], ['hours_en', 'text', $b('ساعات العمل (إنجليزي)', 'Working hours (English)'), 'en'],
                      ['map_embed', 'url', $b('رابط الخريطة', 'Map link'), 'ltr', ta('map_help')], ['notify_email', 'email', $b('بريد إشعارات الرسائل', 'Notification email (messages)'), 'ltr'], ['hr_email', 'email', $b('بريد الموارد البشرية', 'HR email (applications)'), 'ltr']],
      's_social'  => [['social_facebook', 'url', 'Facebook', 'ltr'], ['social_instagram', 'url', 'Instagram', 'ltr'], ['social_x', 'url', 'X', 'ltr'], ['social_linkedin', 'url', 'LinkedIn', 'ltr'], ['social_youtube', 'url', 'YouTube', 'ltr']],
      's_seo'     => [['seo_title_ar', 'text', $b('العنوان الافتراضي (عربي)', 'Default title (Arabic)'), 'ar'], ['seo_title_en', 'text', $b('العنوان الافتراضي (إنجليزي)', 'Default title (English)'), 'en'], ['seo_desc_ar', 'textarea', $b('الوصف الافتراضي (عربي)', 'Default description (Arabic)'), 'ar'], ['seo_desc_en', 'textarea', $b('الوصف الافتراضي (إنجليزي)', 'Default description (English)'), 'en'], ['noindex', 'bool', ta('noindex')], ['double_optin', 'bool', ta('double_optin')]],
      's_tracking'=> [['ga_id', 'text', 'Google Analytics 4', 'ltr', ta('ga_help')]],
      's_mail'    => [['mail_from', 'email', $b('بريد المرسل', 'From address'), 'ltr'], ['mail_from_name', 'text', $b('اسم المرسل', 'From name')], ['smtp_host', 'text', 'SMTP host', 'ltr'], ['smtp_port', 'text', 'SMTP port', 'ltr'],
                      ['smtp_secure', 'select', $b('التشفير', 'Encryption'), ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None']], ['smtp_user', 'text', 'SMTP user', 'ltr'], ['smtp_pass', 'secret', 'SMTP password', 'ltr', ta('smtp_pass_keep')]],
      's_brand'   => [['logo_header', 'logo', $b('شعار الترويسة', 'Header emblem')], ['logo_footer', 'logo', $b('شعار التذييل (فاتح)', 'Footer logo (light)')], ['brand_primary', 'color', $b('اللون الأساسي', 'Primary colour')], ['brand_accent', 'color', $b('اللون الذهبي', 'Accent colour')]],
    ];
}
function page_settings(bool $isPost): never {
    need('settings');
    $S = settings_schema(); $err = [];
    if ($isPost) {
        csrf_check(); need('settings', PERM_FULL);
        if (post('act') === 'test_mail') {
            $ok = send_mail((string)current_user()['email'], 'Test — أرض الحياة', "رسالة تجريبية من لوحة التحكم.\nTest email from the admin panel.");
            audit('test_mail'); aflash($ok ? ta('mail_ok') : ta('mail_fail'), $ok ? 'ok' : 'err'); redirect(A('p=settings'));
        }
        $vals = [];
        if (current_user()['role'] !== 'admin') unset($S['s_mail']); // SMTP endpoint + credentials: system administrators only
        foreach ($S as $fields) foreach ($fields as $f) {
            [$k, $type] = $f;
            switch ($type) {
                case 'bool': $vals[$k] = post_bool($k) ? '1' : '0'; break;
                case 'email': $v = mb_strtolower(post($k)); if ($v !== '' && !valid_email($v)) $err[$k] = ta('bad_url'); $vals[$k] = $v; break;
                case 'url': $v = post($k);
                    if ($v !== '' && $k === 'map_embed' && !str_starts_with($v, 'https://www.google.com/maps/embed?')) $err[$k] = ta('bad_url');
                    elseif ($v !== '' && $k !== 'map_embed' && safe_url($v, false) === '') $err[$k] = ta('bad_url');
                    $vals[$k] = $v; break;
                case 'color': $v = post($k); if ($v !== '' && !valid_hex($v)) $err[$k] = ta('bad_url'); $vals[$k] = $v; break;
                case 'select': $vals[$k] = isset($f[3][post($k)]) ? post($k) : array_key_first($f[3]); break;
                case 'secret':
                    if (post($k) !== '') $vals[$k] = post($k);
                    elseif (setting($k) !== '' && (post('smtp_host') !== setting('smtp_host') || post('smtp_user') !== setting('smtp_user'))) { $vals[$k] = ''; } // endpoint changed → stored password is not reused
                    break;
                case 'logo':
                    if (post($k . '__remove') === '1') $vals[$k] = '';
                    try { if ($p = handle_image_upload($k . '__file', 'logo', 'logo')) $vals[$k] = $p; } catch (RuntimeException $ex) { $err[$k] = $ex->getMessage(); }
                    break;
                default: $v = mb_substr(post($k), 0, $type === 'textarea' ? 1200 : 200);
                    if ($k === 'ga_id' && $v !== '' && !preg_match('/^G-[A-Z0-9]{4,12}$/', $v)) $err[$k] = ta('bad_url');
                    $vals[$k] = $v;
            }
        }
        if (!$err) {
            foreach ($vals as $k => $v) set_setting($k, $v);
            page_cache_clear(); audit('settings'); aflash(ta('saved')); redirect(A('p=settings'));
        }
    }
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('settings') ?><?= e(ta('settings')) ?></h1><p class="muted"><?= e(t('company')) ?></p></div>
<div class="head-actions"><?php if (can('settings', PERM_FULL)): ?><form method="post" action="<?= e(A('p=settings')) ?>"><?= csrf_field() ?><input type="hidden" name="act" value="test_mail"><button class="btn btn-ghost" type="submit"><?= aicon('mail') ?><?= e(ta('test_mail')) ?></button></form><?php endif ?></div></div>
<?php if ($err): ?><div class="alert alert-err" role="alert"><?= e(ta('fix_errors')) ?></div><?php endif ?>
<?php if (!mail_configured()): ?><p class="note"><?= aicon('mail') ?><?= e(ta('mail_not_set')) ?></p><?php endif ?>
<form method="post" action="<?= e(A('p=settings')) ?>" enctype="multipart/form-data" class="edit-form" data-autosave="settings">
<?= csrf_field() ?>
<?php $ro = can('settings', PERM_FULL) ? '' : ' disabled';
    if (current_user()['role'] !== 'admin') unset($S['s_mail']);
    foreach ($S as $sec => $fields): ?>
<section class="card"><h2 class="h3"><?= e(ta($sec)) ?></h2><div class="grid-f">
<?php foreach ($fields as $f): [$k, $type, $lab] = $f; $id = 's_' . $k; $val = $isPost && $type !== 'secret' && $type !== 'logo' ? post($k) : setting($k);
      $hint = is_string($f[4] ?? null) ? '<p class="hint">' . e($f[4]) . '</p>' : '';
      $dirA = ($f[3] ?? '') === 'ar' ? ' lang="ar" dir="rtl"' : ((($f[3] ?? '') === 'en' || ($f[3] ?? '') === 'ltr') ? ' dir="ltr"' : '');
      $er = isset($err[$k]) ? '<p class="ferr" id="' . $id . '_e">' . e($err[$k]) . '</p>' : ''; $ai = isset($err[$k]) ? ' aria-invalid="true" aria-describedby="' . $id . '_e"' : '';
      $L = '<label for="' . $id . '">' . e($lab) . '</label>';
      switch ($type) {
        case 'textarea': echo "<div class=\"fld fld-wide\">$L<textarea id=\"$id\" name=\"$k\" rows=\"3\"$dirA$ai$ro>" . e($val) . "</textarea>$hint$er</div>"; break;
        case 'bool': echo "<div class=\"fld fld-check fld-wide\"><input type=\"hidden\" name=\"$k\" value=\"0\"><input id=\"$id\" type=\"checkbox\" name=\"$k\" value=\"1\"" . (setting($k, $k === 'noindex' ? '1' : '0') === '1' ? ' checked' : '') . "$ro>$L</div>"; break;
        case 'select': echo "<div class=\"fld fld-sm\">$L<select id=\"$id\" name=\"$k\"$ro>"; foreach ($f[3] as $ok => $ol) echo '<option value="' . e($ok) . '"' . (($val ?: 'tls') === $ok ? ' selected' : '') . '>' . e($ol) . '</option>'; echo '</select></div>'; break;
        case 'secret': echo "<div class=\"fld\">$L<input id=\"$id\" name=\"$k\" type=\"password\" dir=\"ltr\" autocomplete=\"new-password\" placeholder=\"" . (setting($k) !== '' ? '••••••••' : '') . "\"$ro>$hint</div>"; break;
        case 'color': echo "<div class=\"fld fld-sm\">$L<input id=\"$id\" name=\"$k\" type=\"text\" dir=\"ltr\" pattern=\"#[0-9a-fA-F]{6}\" placeholder=\"" . ($k === 'brand_primary' ? '#0F2A12' : '#C8892A') . "\" value=\"" . e($val) . "\"$ai$ro><span class=\"swatch\" style=\"background:" . e(valid_hex($val) ? $val : ($k === 'brand_primary' ? '#0F2A12' : '#C8892A')) . "\"></span>$er</div>"; break;
        case 'logo': echo "<div class=\"fld fld-image\"><span class=\"lbl\">" . e($lab) . "</span><figure class=\"cur-img logo-prev" . ($k === 'logo_footer' ? ' on-dark' : '') . "\"><img src=\"" . e($val ?: ($k === 'logo_footer' ? '/assets/img/footer-logo-light.webp' : '/assets/img/brand-mark.webp')) . "\" alt=\"\"></figure>"
            . ($val ? "<div class=\"fld-check\"><input type=\"checkbox\" id=\"{$id}_rm\" name=\"{$k}__remove\" value=\"1\"$ro><label for=\"{$id}_rm\">" . e(en() ? 'Use the default logo' : 'استخدام الشعار الافتراضي') . '</label></div>' : '')
            . "<label class=\"file-btn\" for=\"$id\">" . aicon('image') . e(ta('upload_new')) . "</label><input class=\"file-in\" id=\"$id\" name=\"{$k}__file\" type=\"file\" accept=\"image/png,image/webp,image/jpeg\"$ai$ro>$er</div>"; break;
        default: $it = $type === 'email' ? 'email' : 'text'; echo "<div class=\"fld\">$L<input id=\"$id\" name=\"$k\" type=\"$it\" value=\"" . e($val) . "\"$dirA$ai$ro>$hint$er</div>";
      }
    endforeach ?>
</div></section>
<?php endforeach ?>
<?php if (!$ro): ?><div class="form-actions"><button class="btn" type="submit"><?= aicon('check') ?><?= e(ta('save')) ?></button></div><?php endif ?>
</form>
<?php admin_page(ta('settings'), (string)ob_get_clean(), 'settings');
}

/* ---------------- backup & restore ---------------- */
function backup_dir(): string { global $CONFIG; $d = $CONFIG['backup_dir']; if (!is_dir($d)) mkdir($d, 0750, true); if (!is_file("$d/.htaccess")) @file_put_contents("$d/.htaccess", "Require all denied\n"); return $d; }
function make_backup(string $tag = 'manual'): string {
    global $CONFIG;
    if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive missing');
    $dir = backup_dir(); $name = 'backup-' . date('Ymd-His') . '-' . $tag . '-' . bin2hex(random_bytes(3)) . '.zip';
    $tmpDb = $dir . '/.snap-' . bin2hex(random_bytes(6)) . '.sqlite';
    db()->exec('VACUUM INTO ' . db()->quote($tmpDb));
    $t = new PDO('sqlite:' . $tmpDb); $t->exec("UPDATE settings SET v = '' WHERE k IN ('smtp_pass')"); $t->exec('VACUUM'); $t = null; // never ship SMTP secrets in backups
    $z = new ZipArchive(); if ($z->open("$dir/$name", ZipArchive::CREATE) !== true) throw new RuntimeException('zip');
    $z->addFile($tmpDb, 'db.sqlite');
    foreach (['uploads' => $CONFIG['uploads_dir'], 'files' => $CONFIG['files_dir']] as $pre => $src) if (is_dir($src))
        foreach (scandir($src) as $f) if ($f[0] !== '.' && is_file("$src/$f")) $z->addFile("$src/$f", "$pre/$f");
    $man = ['app' => 'ardalhayat', 'created' => now_sql(), 'v' => (int)db()->query('PRAGMA user_version')->fetchColumn(), 'db' => hash_file('sha256', $tmpDb)];
    $man['sig'] = hash_hmac('sha256', json_encode($man), app_secret()); // restore only accepts backups made by this installation
    $z->addFromString('manifest.json', json_encode($man));
    $z->close(); @unlink($tmpDb); @chmod("$dir/$name", 0640);
    return $name;
}
function backups_list(): array {
    $d = backup_dir(); $out = [];
    foreach (glob("$d/backup-*.zip") ?: [] as $f) $out[] = ['name' => basename($f), 'size' => filesize($f), 'at' => filemtime($f)];
    usort($out, fn($a, $b) => $b['at'] <=> $a['at']);
    return $out;
}
function valid_backup_name(string $n): bool { return (bool)preg_match('/^backup-\d{8}-\d{6}-[a-z]+-[a-f0-9]{6}\.zip$/', $n); }
function restore_backup(string $zipPath): void {
    global $CONFIG;
    $z = new ZipArchive(); if ($z->open($zipPath) !== true) throw new RuntimeException('zip');
    $man = json_decode((string)$z->getFromName('manifest.json'), true);
    if (!is_array($man) || ($man['app'] ?? '') !== 'ardalhayat' || !is_string($man['sig'] ?? null)) throw new RuntimeException('bad');
    $sig = $man['sig']; unset($man['sig']);
    if (!hash_equals(hash_hmac('sha256', json_encode($man), app_secret()), $sig)) throw new RuntimeException('bad');
    $st = $z->statName('db.sqlite');
    if (!$st || $st['size'] > 200 * 1048576) throw new RuntimeException('bad');
    $dbBytes = $z->getFromName('db.sqlite');
    if (!$dbBytes || !str_starts_with($dbBytes, "SQLite format 3\0") || !hash_equals((string)($man['db'] ?? ''), hash('sha256', $dbBytes))) throw new RuntimeException('bad');
    // stage everything first; nothing live is touched until all checks pass
    $stage = dirname($CONFIG['db_path']) . '/.restore-' . bin2hex(random_bytes(6)); mkdir($stage, 0750); mkdir("$stage/uploads"); mkdir("$stage/files");
    $clean = function () use ($stage) { foreach (['uploads', 'files'] as $d) foreach (glob("$stage/$d/*") ?: [] as $f) @unlink($f); @rmdir("$stage/uploads"); @rmdir("$stage/files"); @unlink("$stage/db.sqlite"); @rmdir($stage); };
    try {
        file_put_contents("$stage/db.sqlite", $dbBytes);
        $t = new PDO('sqlite:' . "$stage/db.sqlite");
        $ok = $t->query('PRAGMA integrity_check')->fetchColumn() === 'ok'
           && (int)$t->query('PRAGMA user_version')->fetchColumn() === (int)db()->query('PRAGMA user_version')->fetchColumn()
           && (int)$t->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn() > 0;
        if (!$ok) throw new RuntimeException('bad');
        // keep this server's secrets (SMTP password, signing key) and revoke every session
        $keep = qall("SELECT k, v FROM settings WHERE k IN ('smtp_pass', 'app_secret')");
        $up = $t->prepare('INSERT OR REPLACE INTO settings (k, v) VALUES (?, ?)'); foreach ($keep as $kv) $up->execute([$kv['k'], $kv['v']]);
        $t->exec('UPDATE users SET session_ver = session_ver + 1');
        $t->exec("INSERT INTO audit_log (user_id, username, action, entity, label, ip, at) VALUES (NULL, " . $t->quote((string)current_user()['username']) . ", 'restore', 'backup', " . $t->quote((string)($man['created'] ?? '')) . ", " . $t->quote(client_ip()) . ", " . $t->quote(now_sql()) . ")");
        $t->exec('PRAGMA journal_mode = DELETE'); $t = null;
        $total = 0;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = (string)$z->getNameIndex($i); $sz = (int)($z->statIndex($i)['size'] ?? 0); $total += $sz;
            if ($sz > 20 * 1048576 || $total > 1024 * 1048576) throw new RuntimeException('bad');
            if (preg_match('#^uploads/([a-z]+-[a-f0-9]{16}(-[mt])?\.webp)$#', $n, $m)) {
                $bytes = (string)$z->getFromIndex($i); $info = @getimagesizefromstring($bytes);
                if (!$info || $info['mime'] !== 'image/webp') continue; // only real WebP images
                file_put_contents("$stage/uploads/{$m[1]}", $bytes);
            } elseif (preg_match('#^files/([a-f0-9]{32})$#', $n, $m)) {
                $bytes = (string)$z->getFromIndex($i);
                $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                if (!in_array($mime, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream', 'application/CDFV2'], true)) continue;
                file_put_contents("$stage/files/{$m[1]}", $bytes);
            }
        }
        $z->close();
        if (cloud()) {
            // serverless: images and documents go to Blob, then the restored database replaces the shared copy
            foreach (glob("$stage/uploads/*") ?: [] as $f) { try { cloud_push_image($f); } catch (Throwable) {} }
            foreach (glob("$stage/files/*") ?: [] as $f) { try { cloud_put_doc(basename($f), (string)file_get_contents($f)); } catch (Throwable) {} @unlink($f); }
            $bytes = (string)file_get_contents("$stage/db.sqlite");
            db_close(); cloud_install_db($CONFIG['db_path'], $bytes); cloud_mark_dirty();
            return;
        }
        make_backup('prerestore');
        // switch: flush the live WAL, then swap the DB file and move staged files in
        db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        foreach (glob("$stage/uploads/*") ?: [] as $f) rename($f, $CONFIG['uploads_dir'] . '/' . basename($f));
        foreach (glob("$stage/files/*") ?: [] as $f) { rename($f, $CONFIG['files_dir'] . '/' . basename($f)); @chmod($CONFIG['files_dir'] . '/' . basename($f), 0640); }
        if (!rename("$stage/db.sqlite", $CONFIG['db_path'])) throw new RuntimeException('bad');
        foreach (['-wal', '-shm'] as $x) @unlink($CONFIG['db_path'] . $x);
    } finally { $clean(); }
    page_cache_clear();
}
function page_backup(bool $isPost): never {
    need('backup');
    if (($dl = get('dl')) !== '') {
        need('backup', PERM_FULL);
        if (!valid_backup_name($dl) || !is_file(backup_dir() . "/$dl")) { http_response_code(404); exit('Not found'); }
        audit('backup_download', 'backup', null, $dl);
        header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . $dl . '"'); header('Content-Length: ' . filesize(backup_dir() . "/$dl"));
        readfile(backup_dir() . "/$dl"); exit;
    }
    if ($isPost) {
        csrf_check(); need('backup', PERM_FULL);
        $act = post('act');
        try {
            if ($act === 'make' && cloud()) {
                // no lasting disk here: hand the archive straight to the browser
                $n = make_backup(); audit('backup', 'backup', null, $n); $p = backup_dir() . "/$n";
                header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . $n . '"'); header('Cache-Control: no-store');
                echo file_get_contents($p); @unlink($p); exit;
            }
            if ($act === 'make') { $n = make_backup(); audit('backup', 'backup', null, $n); aflash(ta('saved')); }
            if ($act === 'delete' && valid_backup_name(post('name'))) { @unlink(backup_dir() . '/' . post('name')); audit('delete', 'backup', null, post('name')); aflash(ta('deleted')); }
            if ($act === 'restore') {
                if (current_user()['role'] !== 'admin' || !password_verify((string)($_POST['cur'] ?? ''), (string)current_user()['pass_hash'])) { aflash(ta('pw_wrong'), 'err'); redirect(A('p=backup')); }
                $f = $_FILES['bk'] ?? null;
                if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || (!is_uploaded_file($f['tmp_name']) && !getenv('AH_ALLOW_LOCAL_UPLOAD')) || $f['size'] > 512 * 1048576) throw new RuntimeException('bad');
                if ((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/zip') throw new RuntimeException('bad');
                restore_backup($f['tmp_name']);
                $_SESSION = []; session_regenerate_id(true); $_SESSION['flash'] = ['m' => ta('restore_ok'), 't' => 'ok']; redirect(A());
            }
        } catch (RuntimeException) { aflash($act === 'restore' ? ta('restore_bad') : ta('mail_fail'), 'err'); }
        redirect(A('p=backup'));
    }
    $list = backups_list(); $full = can('backup', PERM_FULL);
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('download') ?><?= e(ta('backup')) ?></h1><p class="muted"><?= e(ta('backup_help')) ?></p><?php if (cloud()): ?><p class="muted"><?= e(ta('backup_cloud')) ?></p><?php endif ?></div>
<div class="head-actions"><?php if ($full): ?><form method="post" action="<?= e(A('p=backup')) ?>"><?= csrf_field() ?><input type="hidden" name="act" value="make"><button class="btn" type="submit"><?= aicon('download') ?><?= e(ta('make_backup')) ?></button></form><?php endif ?></div></div>
<div class="dash-grid">
<section class="card"><h2 class="h3"><?= e(ta('backups')) ?></h2>
<?php if (!$list): ?><p class="muted"><?= e(ta('none')) ?></p><?php else: ?><div class="table-wrap"><table class="tbl"><thead><tr><th scope="col"><?= e(ta('file')) ?></th><th scope="col"><?= e(ta('size')) ?></th><th scope="col"><?= e(ta('actions')) ?></th></tr></thead><tbody>
<?php foreach ($list as $b): ?><tr><td dir="ltr" class="ltr-cell small"><?= e($b['name']) ?><br><span class="muted"><?= e(date('Y-m-d H:i', $b['at'])) ?></span></td><td><?= human_bytes((int)$b['size']) ?></td>
<td class="row-actions"><?php if ($full): ?><a class="btn btn-sm btn-ghost" href="<?= e(A('p=backup&dl=' . rawurlencode($b['name']))) ?>"><?= aicon('download') ?><span class="sr"><?= e(ta('download')) ?></span></a>
<form method="post" action="<?= e(A('p=backup')) ?>" data-confirm="<?= e(ta('confirm_delete')) ?>"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="name" value="<?= e($b['name']) ?>"><button class="btn btn-sm btn-danger" type="submit"><?= aicon('trash') ?><span class="sr"><?= e(ta('delete')) ?></span></button></form><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div><?php endif ?></section>
<?php if ($full && current_user()['role'] === 'admin'): ?><section class="card"><h2 class="h3"><?= e(ta('restore')) ?></h2><p class="note note-warn"><?= e(ta('restore_help')) ?></p>
<form method="post" action="<?= e(A('p=backup')) ?>" enctype="multipart/form-data" data-confirm="<?= e(ta('restore_help')) ?>"><?= csrf_field() ?><input type="hidden" name="act" value="restore">
<div class="fld"><label for="bk"><?= e(ta('file')) ?> (.zip)</label><input id="bk" name="bk" type="file" accept=".zip,application/zip" required></div>
<div class="fld"><label for="rcur"><?= e(ta('current_password')) ?> <b class="req" aria-hidden="true">*</b></label><input id="rcur" name="cur" type="password" dir="ltr" autocomplete="current-password" required></div>
<p class="hint"><?= e(en() ? 'Only backups created by this installation are accepted. SMTP passwords are never stored in backups. Everyone is signed out after a restore.' : 'تُقبل فقط النسخ التي أنشأها هذا الموقع. لا تُحفظ كلمة مرور SMTP في النسخ. يُسجَّل خروج الجميع بعد الاستعادة.') ?></p>
<div class="form-actions"><button class="btn btn-danger" type="submit"><?= aicon('download') ?><?= e(ta('restore')) ?></button></div></form></section><?php endif ?>
</div>
<?php admin_page(ta('backup'), (string)ob_get_clean(), 'backup');
}

/* ---------------- media library ---------------- */
function page_media(bool $isPost): never {
    need('media');
    if ($isPost) {
        csrf_check();
        $act = post('act');
        if ($act === 'upload') {
            need('media', PERM_EDIT); $n = 0; $errs = [];
            foreach (upload_entries('up') as $f) {
                try {
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                        $path = store_image(image_from_upload($f, 'photo'), 'photo', 'media');
                        $bytes = (int)($GLOBALS['LAST_IMAGE_BYTES'] ?? @filesize(PUBLIC_DIR . $path));
                        q('INSERT INTO media (kind, path, title, alt_ar, alt_en, bytes) VALUES (?,?,?,?,?,?)', ['image', $path, mb_substr((string)$f['name'], 0, 120), post('alt_ar'), post('alt_en'), $bytes]);
                    } else {
                        $fid = store_document($f, 'public');
                        q('INSERT INTO media (kind, file_id, title, bytes) VALUES (?,?,?,?)', ['doc', $fid, mb_substr((string)$f['name'], 0, 120), (int)$f['size']]);
                    }
                    audit('upload', 'media', (int)db()->lastInsertId(), (string)$f['name']); $n++;
                } catch (RuntimeException $ex) { $errs[] = $f['name'] . ': ' . (in_array($ex->getMessage(), ['type', 'size', 'upload'], true) ? ta('doc_bad') : $ex->getMessage()); }
            }
            aflash($errs ? implode(' · ', $errs) : ta('saved'), $errs ? 'err' : 'ok'); redirect(A('p=media'));
        }
        $id = (int)post('id'); $r = q1('SELECT * FROM media WHERE id = ?', [$id]);
        if ($r && $act === 'alt') { need('media', PERM_EDIT); q('UPDATE media SET alt_ar = ?, alt_en = ?, updated_at = ? WHERE id = ?', [mb_substr(post('alt_ar'), 0, 200), mb_substr(post('alt_en'), 0, 200), now_sql(), $id]); audit('update', 'media', $id, $r['title']); aflash(ta('saved')); }
        if ($r && $act === 'delete') { need('media', PERM_FULL); if ($r['path']) delete_upload($r['path']); if ($r['file_id']) delete_file((int)$r['file_id']); q('DELETE FROM media WHERE id = ?', [$id]); audit('delete', 'media', $id, $r['title']); aflash(ta('deleted')); }
        redirect(A('p=media'));
    }
    $kind = in_array(get('kind'), ['image', 'doc'], true) ? get('kind') : '';
    $total = (int)qv('SELECT COUNT(*) FROM media' . ($kind ? ' WHERE kind = ?' : ''), $kind ? [$kind] : []);
    $per = 40; $pages = max(1, (int)ceil($total / $per)); $pg = clamp_int(get('pg'), 1, $pages, 1);
    $rows = qall('SELECT * FROM media' . ($kind ? ' WHERE kind = ?' : '') . " ORDER BY id DESC LIMIT $per OFFSET " . (($pg - 1) * $per), $kind ? [$kind] : []);
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('image') ?><?= e(ta('media')) ?></h1><p class="muted"><?= $total ?> <?= e(ta('items')) ?> · <?= e(ta('media_help')) ?></p></div></div>
<?php if (can('media', PERM_EDIT)): ?><form class="card upload-card" method="post" action="<?= e(A('p=media')) ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="act" value="upload">
<div class="grid-f"><div class="fld fld-wide"><label class="file-btn file-drop" for="up"><?= aicon('image') ?><?= e(ta('upload')) ?> — JPG · PNG · WebP · PDF · DOC/DOCX</label><input class="file-in" id="up" name="up[]" type="file" multiple accept="image/jpeg,image/png,image/webp,.pdf,.doc,.docx" required></div>
<div class="fld"><label for="aa"><?= e(ta('alt')) ?> (AR)</label><input id="aa" name="alt_ar" lang="ar" dir="rtl" maxlength="200"></div><div class="fld"><label for="ae"><?= e(ta('alt')) ?> (EN)</label><input id="ae" name="alt_en" dir="ltr" maxlength="200"></div></div>
<div class="form-actions"><button class="btn" type="submit"><?= aicon('check') ?><?= e(ta('upload')) ?></button></div></form><?php endif ?>
<div class="toolbar"><a class="btn btn-ghost<?= $kind === '' ? ' is-on' : '' ?>" href="<?= e(A('p=media')) ?>"><?= e(ta('all')) ?></a><a class="btn btn-ghost<?= $kind === 'image' ? ' is-on' : '' ?>" href="<?= e(A('p=media&kind=image')) ?>"><?= aicon('image') ?><?= e(en() ? 'Images' : 'الصور') ?></a><a class="btn btn-ghost<?= $kind === 'doc' ? ' is-on' : '' ?>" href="<?= e(A('p=media&kind=doc')) ?>"><?= aicon('file') ?><?= e(en() ? 'Documents' : 'المستندات') ?></a></div>
<?php if (!$rows): ?><div class="empty"><?= e(ta('none')) ?></div><?php else: ?><ul class="media-grid">
<?php foreach ($rows as $r): $f = $r['file_id'] ? file_row((int)$r['file_id']) : null; $link = $r['kind'] === 'image' ? base_url() . $r['path'] : ($f ? base_url() . '/files/' . $f['token'] : ''); ?>
<li class="media-item card">
<?php if ($r['kind'] === 'image'): ?><img src="<?= e(img_variant($r['path'], 't')) ?>" alt="<?= e($r['alt_ar']) ?>" loading="lazy" width="300" height="225"><?php else: ?><div class="doc-ic"><?= aicon('file') ?><span dir="ltr"><?= e(strtoupper(pathinfo((string)($f['orig_name'] ?? ''), PATHINFO_EXTENSION))) ?></span></div><?php endif ?>
<p class="small strong" dir="auto"><?= e($r['title']) ?></p><p class="muted small"><?= human_bytes((int)$r['bytes']) ?> · <?= e(adate($r['created_at'])) ?></p>
<?php if ($link): ?><div class="copy-row"><label class="sr" for="u<?= $r['id'] ?>"><?= e(ta('copy_url')) ?></label><input id="u<?= $r['id'] ?>" class="copy-src" dir="ltr" readonly value="<?= e($link) ?>"><button type="button" class="btn btn-sm btn-ghost" data-copy="u<?= $r['id'] ?>" data-done="<?= e(ta('copied')) ?>"><?= aicon('link') ?><?= e(ta('copy_url')) ?></button></div><?php endif ?>
<?php if ($r['kind'] === 'image' && can('media', PERM_EDIT)): ?><details><summary><?= e(ta('alt')) ?></summary><form method="post" action="<?= e(A('p=media')) ?>"><?= csrf_field() ?><input type="hidden" name="act" value="alt"><input type="hidden" name="id" value="<?= $r['id'] ?>">
<label for="a1<?= $r['id'] ?>">AR</label><input id="a1<?= $r['id'] ?>" name="alt_ar" lang="ar" dir="rtl" value="<?= e($r['alt_ar']) ?>"><label for="a2<?= $r['id'] ?>">EN</label><input id="a2<?= $r['id'] ?>" name="alt_en" dir="ltr" value="<?= e($r['alt_en']) ?>"><button class="btn btn-sm" type="submit"><?= e(ta('save')) ?></button></form></details><?php endif ?>
<?php if (can('media', PERM_FULL)): ?><form method="post" action="<?= e(A('p=media')) ?>" data-confirm="<?= e(ta('confirm_delete')) ?>"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger" type="submit"><?= aicon('trash') ?><?= e(ta('delete')) ?></button></form><?php endif ?>
</li><?php endforeach ?></ul><?= apager($pg, $pages, A('p=media' . ($kind ? '&kind=' . $kind : ''))) ?><?php endif ?>
<?php admin_page(ta('media'), (string)ob_get_clean(), 'media');
}

/* ---------------- user guide (Arabic first, per proposal deliverable) ---------------- */
function page_help(): never {
    $ar = [
      ['البدء', ['سجّل الدخول من /admin/ ثم غيّر كلمة المرور المؤقتة من «الملف الشخصي».', 'بدّل لغة اللوحة من الزر في الأعلى. كل محتوى له حقول عربية وإنجليزية في تبويبين.', 'زر «عرض الموقع» يفتح الموقع في نافذة جديدة. زر «معاينة» في أي عنصر يعرض المسودة قبل نشرها.']],
      ['المحتوى', ['الشريط الرئيسي: صور الرئيسية المتحركة مع العنوان والزر.', 'الأنشطة والخدمات: صفحة لكل نشاط زراعي أو حيواني مع صور ومعرض.', 'المشاريع: الحالة (جارٍ/منفّذ)، الموقع، المعرض.', 'الأخبار: التصنيف والوسوم وتاريخ النشر (جدولة) والحالة: مسودة / منشور / مؤرشف.', 'الإعلانات والمناقصات: تاريخ بداية ونهاية ومرفقات PDF/Word، وتختفي تلقائيًا بعد تاريخ الانتهاء.', 'معرض الصور والفيديو: ألبومات صور وروابط YouTube/Vimeo.', 'الصفحات الثابتة، الإحصائيات، القيم، الهيكل التنظيمي، فريق الإدارة، شركاء النجاح، القوائم والتذييل.']],
      ['التفاعل', ['رسائل «اتصل بنا» تصل إلى «الرسائل» بحالة جديد/تمت المعالجة، ويُرسل إشعار بريدي إن ضُبط SMTP.', 'طلبات التوظيف مع السير الذاتية: التصفية حسب الوظيفة والحالة والتاريخ والتصدير إلى Excel.', 'القائمة البريدية: المشتركون مع التصدير إلى Excel.']],
      ['النظام', ['المستخدمون: أربعة أدوار جاهزة (مدير النظام، مدير المحتوى، محرر، مسؤول الموارد البشرية) مع تخصيص الصلاحيات لكل قسم.', 'سجل النشاط يوضح من فعل ماذا ومتى.', 'الإعدادات: بيانات الشركة والشعار والألوان وSEO والبريد وأكواد التتبع. خيار «إخفاء الموقع عن محركات البحث» مفعّل حتى الإطلاق.', 'النسخ الاحتياطي: أنشئ نسخة وحمّلها واحفظها خارج الخادم بانتظام. الاستعادة تنشئ نسخة تلقائية أولًا.']],
      ['قبل الإطلاق', ['راجع النصوص الابتدائية وعدّلها بالمحتوى المعتمد، واستبدل الصور بصور الشركة.', 'العناصر غير المنشورة (المشاريع، الوظائف، الإعلانات، الفريق، الشركاء، الإحصائيات) جاهزة كمسودات: عدّلها ثم فعّل «منشور».', 'أدخل بيانات الاتصال وروابط التواصل.', 'اضبط SMTP وأرسل رسالة تجريبية، ثم ألغِ «إخفاء الموقع عن محركات البحث».']],
    ];
    $en = [
      ['Getting started', ['Log in at /admin/ and change the temporary password from “Profile”.', 'Switch the panel language from the top bar. Every item has Arabic and English tabs.', '“Preview” on any item shows a draft before it is published.']],
      ['Content', ['Home slider, activities & services, projects, news (categories, tags, scheduling, draft/published/archived), announcements & tenders (start/end dates, attachments, auto-hide), gallery, static pages, stats, values, org structure, team, partners, menus & footer.']],
      ['Interaction', ['Contact messages (new/processed + email notification), job applications with CVs (filter + Excel export), newsletter subscribers (Excel export).']],
      ['System', ['Users with 4 ready roles and per-section overrides, audit log, settings (company, logo, colours, SEO, SMTP, tracking), backup & restore.']],
      ['Before launch', ['Review the starting text and replace it with approved content and company photos.', 'Unpublished items (projects, jobs, notices, team, partners, stats) are ready as drafts: edit them, then tick “Published”.', 'Enter contact details, configure SMTP, then turn off “Hide the site from search engines”.']],
    ];
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('file') ?><?= e(ta('help')) ?></h1><p class="muted"><?= e(t('company')) ?></p></div></div>
<div class="guide"><?php foreach (en() ? $en : $ar as [$h, $items]): ?><section class="card"><h2 class="h3"><?= e($h) ?></h2><ul><?php foreach ($items as $i): ?><li><?= e($i) ?></li><?php endforeach ?></ul></section><?php endforeach ?></div>
<?php admin_page(ta('help'), (string)ob_get_clean(), 'dashboard');
}
