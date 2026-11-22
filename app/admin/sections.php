<?php
declare(strict_types=1);
function admin_file_download(int $id): never {
    $f = file_row($id); if (!$f) { http_response_code(404); exit('Not found'); }
    $isCv = (bool)q1('SELECT id FROM applications WHERE cv_file = ?', [$id]);
    if ($isCv) need('applications'); elseif (q1('SELECT id FROM media WHERE file_id = ?', [$id])) need('media'); else need('notices');
    if ($isCv) audit('cv_download', 'files', $id, $f['orig_name']);
    download_file($f);
}
function stat_card(string $label, string $value, string $icon, string $href = '', string $cls = ''): string {
    $in = '<span class="sc-ic">' . aicon($icon) . '</span><span class="sc-v">' . e($value) . '</span><span class="sc-l">' . e($label) . '</span>';
    return $href ? '<a class="stat-card ' . $cls . '" href="' . e($href) . '">' . $in . '</a>' : '<div class="stat-card ' . $cls . '">' . $in . '</div>';
}
function page_dashboard(): never {
    $today = (int)qv('SELECT COALESCE(SUM(hits),0) FROM visits WHERE day = ?', [today()]);
    $from = date('Y-m-d', strtotime('-29 days'));
    $d30 = (int)qv('SELECT COALESCE(SUM(hits),0) FROM visits WHERE day >= ?', [$from]);
    $byDay = []; foreach (qall('SELECT day, SUM(hits) AS h FROM visits WHERE day >= ? GROUP BY day', [$from]) as $r) $byDay[$r['day']] = (int)$r['h'];
    $maxD = max([1, ...array_values($byDay)]);
    $top = qall('SELECT path, SUM(hits) AS h FROM visits WHERE day >= ? GROUP BY path ORDER BY h DESC LIMIT 8', [$from]);
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('chart') ?><?= e(ta('dashboard')) ?></h1><p class="muted"><?= e(t('company')) ?></p></div>
<div class="head-actions"><a class="btn btn-ghost" href="<?= e(A('p=help')) ?>"><?= aicon('file') ?><?= e(ta('help')) ?></a>
<form method="post" action="<?= e(A('a=clear_cache')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><?= aicon('history') ?><?= e(ta('clear_cache')) ?></button></form></div></div>
<?php if (seo_noindex()): ?><p class="note note-warn"><?= aicon('eye') ?><?= e(ta('site_hidden')) ?><?php if (can('settings')): ?> <a href="<?= e(A('p=settings')) ?>"><?= e(ta('settings')) ?></a><?php endif ?></p><?php endif ?>
<?php if (!mail_configured() && can('settings')): ?><p class="note"><?= aicon('mail') ?><?= e(ta('mail_not_set')) ?></p><?php endif ?>
<div class="stat-grid">
  <?= stat_card(ta('visits_today'), number_format($today), 'eye') ?>
  <?= stat_card(ta('visits_30'), number_format($d30), 'chart') ?>
  <?php if (can('messages')): ?><?= stat_card(ta('new_messages'), (string)qv("SELECT COUNT(*) FROM messages WHERE status = 'new'"), 'mail', A('p=messages&status=new'), 'sc-gold') ?><?php endif ?>
  <?php if (can('applications')): ?><?= stat_card(ta('new_apps'), (string)qv("SELECT COUNT(*) FROM applications WHERE status = 'new'"), 'briefcase', A('p=applications&status=new'), 'sc-gold') ?><?php endif ?>
  <?php if (can('subscribers')): ?><?= stat_card(ta('subscribers_n'), (string)qv('SELECT COUNT(*) FROM subscribers WHERE active = 1 AND confirmed = 1'), 'users', A('p=subscribers')) ?><?php endif ?>
  <?php if (can('notices')): ?><?= stat_card(ta('live_notices'), (string)qv('SELECT COUNT(*) FROM notices WHERE ' . notice_live_sql(), [today(), today()]), 'file', A('m=notices')) ?><?php endif ?>
</div>
<div class="dash-grid">
<section class="card"><h2 class="h3"><?= e(ta('visits_30')) ?></h2>
  <div class="bars" role="img" aria-label="<?= e(ta('visits_30') . ': ' . $d30) ?>">
  <?php for ($i = 29; $i >= 0; $i--): $d = date('Y-m-d', strtotime("-$i days")); $h = $byDay[$d] ?? 0; ?>
    <span class="bar" style="--h:<?= round($h / $maxD * 100) ?>%" title="<?= e($d . ': ' . $h) ?>"></span>
  <?php endfor ?>
  </div>
  <p class="hint"><?= e(ta(cloud() ? 'visits_note_cloud' : 'visits_note')) ?></p>
</section>
<section class="card"><h2 class="h3"><?= e(ta('top_pages')) ?></h2>
<?php if (!$top): ?><p class="muted"><?= e(ta('none')) ?></p><?php else: ?><ol class="toplist"><?php foreach ($top as $r): ?><li><span dir="ltr"><?= e($r['path']) ?></span><b><?= (int)$r['h'] ?></b></li><?php endforeach ?></ol><?php endif ?>
</section>
<section class="card"><h2 class="h3"><?= e(ta('latest_content')) ?></h2><ul class="latest">
<?php
    $latest = [];
    foreach (['news' => 'title_ar', 'projects' => 'title_ar', 'notices' => 'title_ar', 'activities' => 'title_ar', 'jobs' => 'title_ar'] as $t => $c) {
        $perm = modules()[$t]['perm']; if (!can($perm)) continue;
        foreach (qall("SELECT id, $c AS t, updated_at FROM $t ORDER BY updated_at DESC LIMIT 4") as $r) $latest[] = $r + ['m' => $t];
    }
    usort($latest, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
    foreach (array_slice($latest, 0, 8) as $r): ?>
  <li><a href="<?= e(A('m=' . $r['m'] . '&a=edit&id=' . $r['id'])) ?>"><?= e($r['t']) ?></a><span class="muted"><?= e(al(modules()[$r['m']]['title'])) ?> · <?= e(adate($r['updated_at'])) ?></span></li>
<?php endforeach ?></ul></section>
<?php if (can('audit')): ?>
<section class="card"><h2 class="h3"><?= e(ta('recent_activity')) ?></h2><ul class="latest">
<?php foreach (qall('SELECT * FROM audit_log ORDER BY id DESC LIMIT 8') as $r): ?><li><span><b><?= e($r['username'] ?: '—') ?></b> · <?= e(audit_label($r['action'])) ?> <?= e($r['entity']) ?> <?= e(mb_strimwidth($r['label'], 0, 50, '…')) ?></span><span class="muted"><?= e(adate($r['at'])) ?></span></li><?php endforeach ?>
</ul><p><a href="<?= e(A('p=audit')) ?>"><?= e(ta('audit')) ?></a></p></section>
<section class="card"><h2 class="h3"><?= e(ta('system')) ?></h2><dl class="kv">
  <dt>PHP</dt><dd dir="ltr"><?= e(PHP_VERSION) ?></dd>
  <dt>SQLite</dt><dd dir="ltr"><?= e((string)qv('SELECT sqlite_version()')) ?></dd>
  <dt>WebP</dt><dd><?= function_exists('imagewebp') ? pill(ta('yes'), 'ok') : pill(ta('no'), 'err') ?></dd>
  <dt>SMTP</dt><dd><?= mail_configured() ? pill(ta('yes'), 'ok') : pill(ta('no'), 'warn') ?></dd>
  <dt>HTTPS</dt><dd><?= is_https() ? pill(ta('yes'), 'ok') : pill(ta('no'), 'warn') ?></dd>
</dl></section>
<?php endif ?>
</div>
<?php admin_page(ta('dashboard'), (string)ob_get_clean(), 'dashboard');
}
function audit_label(string $a): string {
    $L = ['create' => ['أضاف', 'created'], 'update' => ['عدّل', 'updated'], 'delete' => ['حذف', 'deleted'], 'login' => ['سجّل الدخول', 'logged in'], 'logout' => ['سجّل الخروج', 'logged out'],
      'password_change' => ['غيّر كلمة المرور', 'changed password'], 'password_reset' => ['أعاد تعيين كلمة المرور', 'reset password'], 'password_reset_request' => ['طلب استعادة كلمة المرور', 'requested a password reset'],
      'reset_issued' => ['أصدر رابط استعادة', 'issued a reset link'], 'export' => ['صدّر', 'exported'], 'settings' => ['عدّل الإعدادات', 'updated settings'], 'backup' => ['أنشأ نسخة احتياطية', 'created a backup'],
      'backup_download' => ['نزّل نسخة احتياطية', 'downloaded a backup'], 'restore' => ['استعاد نسخة احتياطية', 'restored a backup'], 'cache_clear' => ['مسح ذاكرة التخزين', 'cleared cache'], 'status' => ['غيّر الحالة', 'changed status'],
      'cv_download' => ['نزّل سيرة ذاتية', 'downloaded a CV'], 'upload' => ['رفع ملفًا', 'uploaded'], 'test_mail' => ['أرسل رسالة تجريبية', 'sent a test email']];
    return isset($L[$a]) ? al($L[$a]) : $a;
}
/* ---------------- profile ---------------- */
function page_profile(bool $isPost): never {
    $u = current_user(); $err = [];
    if ($isPost) {
        csrf_check();
        $cur = (string)($_POST['cur'] ?? ''); $pw = (string)($_POST['pw'] ?? ''); $pw2 = (string)($_POST['pw2'] ?? '');
        $name = mb_substr(post('display_name'), 0, 80); $email = mb_strtolower(post('email'));
        if ($email !== '' && !valid_email($email)) $err['email'] = ta('bad_url');
        elseif ($email !== '' && q1('SELECT id FROM users WHERE lower(email) = ? AND id <> ?', [$email, $u['id']])) $err['email'] = ta('dup_user');
        if (!password_verify($cur, $u['pass_hash'])) $err['cur'] = ta('pw_wrong');
        if ($pw !== '' || (int)$u['must_change'] === 1) {
            if ($p = password_problem($pw, $u['username'])) $err['pw'] = ta($p);
            elseif ($pw !== $pw2) $err['pw2'] = ta('pw_match');
            elseif (password_verify($pw, $u['pass_hash'])) $err['pw'] = ADMIN_LANG === 'en' ? 'Choose a password different from the current one.' : 'اختر كلمة مرور مختلفة عن الحالية.';
        }
        if (!$err) {
            q('UPDATE users SET display_name = ?, email = ?, admin_lang = ?, updated_at = ? WHERE id = ?', [$name ?: $u['username'], $email, post('admin_lang') === 'en' ? 'en' : 'ar', now_sql(), $u['id']]);
            if ($pw !== '') {
                set_password((int)$u['id'], $pw);
                $_SESSION['sv'] = (int)qv('SELECT session_ver FROM users WHERE id = ?', [$u['id']]); // keep this session, revoke others
                session_regenerate_id(true);
                audit('password_change', 'users', (int)$u['id'], $u['username']);
            } else audit('update', 'users', (int)$u['id'], $u['username']);
            aflash(ta('saved')); redirect(A('p=profile'));
        }
    }
    $fe = fn($k) => isset($err[$k]) ? '<p class="ferr" id="e_' . $k . '">' . e($err[$k]) . '</p>' : '';
    $ai = fn($k) => isset($err[$k]) ? ' aria-invalid="true" aria-describedby="e_' . $k . '"' : '';
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('users') ?><?= e(ta('profile')) ?></h1><p class="muted" dir="ltr"><?= e($u['username']) ?> · <?= e(al(ROLES[$u['role']])) ?></p></div></div>
<?php if ((int)$u['must_change'] === 1): ?><div class="alert alert-warn" role="alert"><?= e(ta('must_change')) ?></div><?php endif ?>
<?php if ($err): ?><div class="alert alert-err" role="alert"><?= e(ta('fix_errors')) ?></div><?php endif ?>
<form method="post" action="<?= e(A('p=profile')) ?>" class="card narrow">
<?= csrf_field() ?><input type="text" name="username" value="<?= e($u['username']) ?>" autocomplete="username" hidden>
<div class="grid-f">
<div class="fld"><label for="dn"><?= e(ta('display_name')) ?></label><input id="dn" name="display_name" maxlength="80" value="<?= e(post('display_name') ?: $u['display_name']) ?>"></div>
<div class="fld"><label for="em"><?= e(ta('email')) ?></label><input id="em" name="email" type="email" dir="ltr" value="<?= e($isPost ? post('email') : $u['email']) ?>"<?= $ai('email') ?>><?= $fe('email') ?></div>
<div class="fld fld-sm"><label for="al"><?= e(ta('admin_lang')) ?></label><select id="al" name="admin_lang"><option value="ar"<?= $u['admin_lang'] !== 'en' ? ' selected' : '' ?>>العربية</option><option value="en"<?= $u['admin_lang'] === 'en' ? ' selected' : '' ?>>English</option></select></div>
<div class="fld"><label for="pw"><?= e(ta('new_password')) ?><?= (int)$u['must_change'] === 1 ? ' <b class="req" aria-hidden="true">*</b>' : '' ?></label><input id="pw" name="pw" type="password" autocomplete="new-password" dir="ltr"<?= (int)$u['must_change'] === 1 ? ' required' : '' ?><?= $ai('pw') ?>><p class="hint"><?= e(ta('pw_short') . ' ' . ta('pw_mix')) ?></p><?= $fe('pw') ?></div>
<div class="fld"><label for="pw2"><?= e(ta('confirm_password')) ?></label><input id="pw2" name="pw2" type="password" autocomplete="new-password" dir="ltr"<?= $ai('pw2') ?>><?= $fe('pw2') ?></div>
<div class="fld"><label for="cur"><?= e(ta('current_password')) ?> <b class="req" aria-hidden="true">*</b></label><input id="cur" name="cur" type="password" autocomplete="current-password" dir="ltr" required<?= $ai('cur') ?>><?= $fe('cur') ?></div>
</div>
<div class="form-actions"><button class="btn" type="submit"><?= aicon('check') ?><?= e(ta('save')) ?></button></div>
</form>
<?php admin_page(ta('profile'), (string)ob_get_clean(), 'profile');
}
/* ---------------- messages ---------------- */
function topic_label(string $t): string { return t('topic_' . $t); }
function page_messages(bool $isPost): never {
    need('messages');
    if ($isPost) {
        csrf_check(); $id = (int)post('id'); $act = post('act');
        $r = q1('SELECT * FROM messages WHERE id = ?', [$id]);
        if ($r && $act === 'delete') { need('messages', PERM_FULL); q('DELETE FROM messages WHERE id = ?', [$id]); audit('delete', 'messages', $id, $r['name']); aflash(ta('deleted')); redirect(A('p=messages')); }
        if ($r && $act === 'update') { need('messages', PERM_EDIT); q('UPDATE messages SET status = ?, note = ?, updated_at = ? WHERE id = ?', [post('status') === 'processed' ? 'processed' : 'new', mb_substr(post('note'), 0, 2000), now_sql(), $id]); audit('status', 'messages', $id, $r['name'] . ' → ' . post('status')); aflash(ta('saved')); redirect(A('p=messages&id=' . $id)); }
        redirect(A('p=messages'));
    }
    [$where, $p] = msg_filters();
    if (get('export') === '1') {
        need('messages', PERM_FULL);
        $rows = qall("SELECT * FROM messages$where ORDER BY id DESC", $p);
        audit('export', 'messages', null, count($rows) . ' rows');
        xlsx_download('messages-' . today() . '.xlsx', ['#', ta('date'), ta('topic'), 'الاسم / Name', ta('email'), 'الهاتف / Phone', 'الجهة / Organisation', 'الموضوع / Subject', 'الرسالة / Message', ta('status'), ta('note')],
          array_map(fn($r) => [$r['id'], $r['created_at'], topic_label($r['topic']), $r['name'], $r['email'], $r['phone'], $r['organisation'], $r['subject'], $r['message'], ta($r['status']), $r['note']], $rows), ADMIN_LANG === 'ar');
    }
    if ($id = (int)get('id')) {
        $r = q1('SELECT * FROM messages WHERE id = ?', [$id]) ?? deny();
        ob_start(); ?>
<div class="page-head"><div><p class="crumb"><a href="<?= e(A('p=messages')) ?>"><?= e(ta('messages')) ?></a></p><h1><?= e($r['subject'] ?: topic_label($r['topic'])) ?></h1></div>
<div class="head-actions"><a class="btn btn-ghost" href="mailto:<?= e($r['email']) ?>?subject=<?= e(rawurlencode('Re: ' . ($r['subject'] ?: topic_label($r['topic'])))) ?>"><?= aicon('mail') ?><?= e(ta('reply_mail')) ?></a></div></div>
<div class="dash-grid">
<section class="card"><dl class="kv">
 <dt><?= e(ta('topic')) ?></dt><dd><?= e(topic_label($r['topic'])) ?></dd><dt><?= e(ta('from')) ?></dt><dd><?= e($r['name']) ?></dd>
 <dt><?= e(ta('email')) ?></dt><dd dir="ltr"><?= e($r['email']) ?></dd><dt><?= e(ADMIN_LANG === 'en' ? 'Phone' : 'الهاتف') ?></dt><dd dir="ltr"><?= e($r['phone'] ?: '—') ?></dd>
 <dt><?= e(ADMIN_LANG === 'en' ? 'Organisation' : 'الجهة') ?></dt><dd><?= e($r['organisation'] ?: '—') ?></dd><dt><?= e(ta('date')) ?></dt><dd><?= e(adate($r['created_at'])) ?></dd>
</dl><div class="msg-body" dir="auto"><?= nl2br(e($r['message'])) ?></div></section>
<section class="card">
<?php if (can('messages', PERM_EDIT)): ?>
<form method="post" action="<?= e(A('p=messages')) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="update">
 <div class="fld"><label for="st"><?= e(ta('status')) ?></label><select id="st" name="status"><option value="new"<?= $r['status'] === 'new' ? ' selected' : '' ?>><?= e(ta('new')) ?></option><option value="processed"<?= $r['status'] === 'processed' ? ' selected' : '' ?>><?= e(ta('processed')) ?></option></select></div>
 <div class="fld"><label for="nt"><?= e(ta('note')) ?></label><textarea id="nt" name="note" rows="5" maxlength="2000"><?= e($r['note']) ?></textarea></div>
 <button class="btn" type="submit"><?= aicon('check') ?><?= e(ta('save')) ?></button>
</form>
<?php endif ?>
<?php if (can('messages', PERM_FULL)): ?><form method="post" action="<?= e(A('p=messages')) ?>" data-confirm="<?= e(ta('confirm_delete')) ?>" class="mt"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="delete"><button class="btn btn-danger" type="submit"><?= aicon('trash') ?><?= e(ta('delete')) ?></button></form><?php endif ?>
</section></div>
<?php admin_page(ta('messages'), (string)ob_get_clean(), 'messages');
    }
    $total = (int)qv("SELECT COUNT(*) FROM messages$where", $p); $per = 30; $pages = max(1, (int)ceil($total / $per)); $pg = clamp_int(get('pg'), 1, $pages, 1);
    $rows = qall("SELECT * FROM messages$where ORDER BY id DESC LIMIT $per OFFSET " . (($pg - 1) * $per), $p);
    $qs = http_build_query(array_filter(['status' => get('status'), 'topic' => get('topic'), 's' => get('s')]));
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('mail') ?><?= e(ta('messages')) ?></h1><p class="muted"><?= $total ?> <?= e(ta('items')) ?></p></div>
<div class="head-actions"><?php if (can('messages', PERM_FULL)): ?><a class="btn btn-ghost" href="<?= e(A('p=messages&export=1' . ($qs ? '&' . $qs : ''))) ?>"><?= aicon('download') ?><?= e(ta('export')) ?></a><?php endif ?></div></div>
<form class="toolbar" method="get" action="/admin/"><input type="hidden" name="p" value="messages">
 <label class="sr" for="fs"><?= e(ta('status')) ?></label><select id="fs" name="status"><option value=""><?= e(ta('all')) ?> — <?= e(ta('status')) ?></option><?php foreach (['new', 'processed'] as $s): ?><option value="<?= $s ?>"<?= get('status') === $s ? ' selected' : '' ?>><?= e(ta($s)) ?></option><?php endforeach ?></select>
 <label class="sr" for="ft"><?= e(ta('topic')) ?></label><select id="ft" name="topic"><option value=""><?= e(ta('all')) ?> — <?= e(ta('topic')) ?></option><?php foreach (['general', 'partner', 'services', 'invest'] as $s): ?><option value="<?= $s ?>"<?= get('topic') === $s ? ' selected' : '' ?>><?= e(topic_label($s)) ?></option><?php endforeach ?></select>
 <label class="sr" for="s"><?= e(ta('search')) ?></label><input id="s" type="search" name="s" value="<?= e(get('s')) ?>" placeholder="<?= e(ta('search')) ?>…">
 <button class="btn btn-ghost" type="submit"><?= aicon('search') ?><?= e(ta('filter')) ?></button>
</form>
<?php if (!$rows): ?><div class="empty"><?= e(ta('none')) ?></div><?php else: ?>
<div class="table-wrap"><table class="tbl"><thead><tr><th scope="col"><?= e(ta('from')) ?></th><th scope="col"><?= e(ta('topic')) ?></th><th scope="col"><?= e(ADMIN_LANG === 'en' ? 'Subject' : 'الموضوع') ?></th><th scope="col"><?= e(ta('date')) ?></th><th scope="col"><?= e(ta('status')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr<?= $r['status'] === 'new' ? ' class="is-new"' : '' ?>><td><a class="strong" href="<?= e(A('p=messages&id=' . $r['id'])) ?>"><?= e($r['name']) ?></a><br><span class="muted small" dir="ltr"><?= e($r['email']) ?></span></td><td><?= e(topic_label($r['topic'])) ?></td><td><?= e(mb_strimwidth($r['subject'] ?: $r['message'], 0, 60, '…')) ?></td><td><?= e(adate($r['created_at'])) ?></td><td><?= pill(ta($r['status']), $r['status'] === 'new' ? 'warn' : 'ok') ?></td></tr><?php endforeach ?>
</tbody></table></div><?= apager($pg, $pages, A('p=messages' . ($qs ? '&' . $qs : ''))) ?>
<?php endif;
    admin_page(ta('messages'), (string)ob_get_clean(), 'messages');
}
function msg_filters(): array {
    $w = []; $p = [];
    if (in_array(get('status'), ['new', 'processed'], true)) { $w[] = 'status = ?'; $p[] = get('status'); }
    if (in_array(get('topic'), ['general', 'partner', 'services', 'invest'], true)) { $w[] = 'topic = ?'; $p[] = get('topic'); }
    if (($s = get('s')) !== '') { $w[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%", "%$s%"); }
    return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
}
/* ---------------- applications ---------------- */
const APP_STATUSES = ['new', 'reviewing', 'shortlisted', 'rejected', 'hired'];
function page_applications(bool $isPost): never {
    need('applications');
    if ($isPost) {
        csrf_check(); $id = (int)post('id'); $r = q1('SELECT * FROM applications WHERE id = ?', [$id]);
        if ($r && post('act') === 'delete') { need('applications', PERM_FULL); q('DELETE FROM applications WHERE id = ?', [$id]); if ($r['cv_file']) delete_file((int)$r['cv_file']); audit('delete', 'applications', $id, $r['name']); aflash(ta('deleted')); redirect(A('p=applications')); }
        if ($r && post('act') === 'status' && in_array(post('status'), APP_STATUSES, true)) { need('applications', PERM_EDIT); q('UPDATE applications SET status = ?, updated_at = ? WHERE id = ?', [post('status'), now_sql(), $id]); audit('status', 'applications', $id, $r['name'] . ' → ' . post('status')); aflash(ta('saved')); }
        redirect(A('p=applications' . ($id ? '&id=' . $id : '')));
    }
    $w = []; $p = [];
    if (in_array(get('status'), APP_STATUSES, true)) { $w[] = 'a.status = ?'; $p[] = get('status'); }
    if (get('job') === '0') $w[] = 'a.job_id IS NULL'; elseif (($j = (int)get('job')) > 0) { $w[] = 'a.job_id = ?'; $p[] = $j; }
    if (valid_date(get('from'))) { $w[] = 'a.created_at >= ?'; $p[] = get('from') . ' 00:00:00'; }
    if (valid_date(get('to'))) { $w[] = 'a.created_at <= ?'; $p[] = get('to') . ' 23:59:59'; }
    if (($s = get('s')) !== '') { $w[] = '(a.name LIKE ? OR a.email LIKE ? OR a.city LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%"); }
    $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
    $base = 'SELECT a.*, j.title_ar AS jt FROM applications a LEFT JOIN jobs j ON j.id = a.job_id';
    if (get('export') === '1') {
        need('applications', PERM_FULL);
        $rows = qall("$base$where ORDER BY a.id DESC", $p); audit('export', 'applications', null, count($rows) . ' rows');
        xlsx_download('applications-' . today() . '.xlsx', ['#', ta('date'), ta('job'), 'الاسم / Name', ta('email'), 'الهاتف / Phone', 'المدينة / City', 'رسالة / Message', ta('status'), ta('cv')],
          array_map(fn($r) => [$r['id'], $r['created_at'], $r['jt'] ?: ($r['job_title'] ?: ta('general_app')), $r['name'], $r['email'], $r['phone'], $r['city'], $r['message'], ta($r['status']), $r['cv_file'] ? 'نعم / yes' : '—'], $rows), ADMIN_LANG === 'ar');
    }
    $sel = fn($r) => '<form method="post" action="' . e(A('p=applications')) . '" class="inline-form">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="hidden" name="act" value="status"><label class="sr" for="as' . $r['id'] . '">' . e(ta('status')) . '</label><select id="as' . $r['id'] . '" name="status" data-autosubmit>'
        . implode('', array_map(fn($s) => '<option value="' . $s . '"' . ($r['status'] === $s ? ' selected' : '') . '>' . e(ta($s)) . '</option>', APP_STATUSES)) . '</select><noscript><button class="btn btn-sm" type="submit">' . e(ta('save')) . '</button></noscript></form>';
    if ($id = (int)get('id')) {
        $r = q1("$base WHERE a.id = ?", [$id]) ?? deny();
        ob_start(); ?>
<div class="page-head"><div><p class="crumb"><a href="<?= e(A('p=applications')) ?>"><?= e(ta('applications')) ?></a></p><h1><?= e($r['name']) ?></h1><p class="muted"><?= e($r['jt'] ?: ($r['job_title'] ?: ta('general_app'))) ?></p></div></div>
<section class="card narrow"><dl class="kv">
 <dt><?= e(ta('email')) ?></dt><dd dir="ltr"><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></dd><dt><?= e(ADMIN_LANG === 'en' ? 'Phone' : 'الهاتف') ?></dt><dd dir="ltr"><?= e($r['phone'] ?: '—') ?></dd>
 <dt><?= e(ADMIN_LANG === 'en' ? 'City' : 'المدينة') ?></dt><dd><?= e($r['city'] ?: '—') ?></dd><dt><?= e(ta('date')) ?></dt><dd><?= e(adate($r['created_at'])) ?></dd>
 <dt><?= e(ta('cv')) ?></dt><dd><?php if ($r['cv_file'] && ($f = file_row((int)$r['cv_file']))): ?><a class="btn btn-sm" href="<?= e(A('a=file&id=' . $f['id'])) ?>"><?= aicon('download') ?><?= e(ta('download')) ?></a> <span class="muted"><?= e($f['orig_name']) ?> · <?= human_bytes((int)$f['bytes']) ?></span><?php else: ?>—<?php endif ?></dd>
 <dt><?= e(ta('status')) ?></dt><dd><?= can('applications', PERM_EDIT) ? $sel($r) : e(ta($r['status'])) ?></dd>
</dl><?php if ($r['message'] !== ''): ?><div class="msg-body" dir="auto"><?= nl2br(e($r['message'])) ?></div><?php endif ?>
<?php if (can('applications', PERM_FULL)): ?><form method="post" action="<?= e(A('p=applications')) ?>" data-confirm="<?= e(ta('confirm_delete')) ?>" class="mt"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="delete"><button class="btn btn-danger" type="submit"><?= aicon('trash') ?><?= e(ta('delete')) ?></button></form><?php endif ?>
</section>
<?php admin_page(ta('applications'), (string)ob_get_clean(), 'applications');
    }
    $total = (int)qv("SELECT COUNT(*) FROM applications a$where", $p); $per = 30; $pages = max(1, (int)ceil($total / $per)); $pg = clamp_int(get('pg'), 1, $pages, 1);
    $rows = qall("$base$where ORDER BY a.id DESC LIMIT $per OFFSET " . (($pg - 1) * $per), $p);
    $qs = http_build_query(array_filter(['status' => get('status'), 'job' => get('job'), 'from' => get('from'), 'to' => get('to'), 's' => get('s')], fn($x) => $x !== ''));
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('briefcase') ?><?= e(ta('applications')) ?></h1><p class="muted"><?= $total ?> <?= e(ta('items')) ?></p></div>
<div class="head-actions"><?php if (can('applications', PERM_FULL)): ?><a class="btn btn-ghost" href="<?= e(A('p=applications&export=1' . ($qs ? '&' . $qs : ''))) ?>"><?= aicon('download') ?><?= e(ta('export')) ?></a><?php endif ?></div></div>
<form class="toolbar" method="get" action="/admin/"><input type="hidden" name="p" value="applications">
 <label class="sr" for="fj"><?= e(ta('job')) ?></label><select id="fj" name="job"><option value=""><?= e(ta('all')) ?> — <?= e(ta('job')) ?></option><option value="0"<?= get('job') === '0' ? ' selected' : '' ?>><?= e(ta('general_app')) ?></option><?php foreach (qall('SELECT id, title_ar, title_en FROM jobs ORDER BY id DESC') as $j): ?><option value="<?= $j['id'] ?>"<?= get('job') === (string)$j['id'] ? ' selected' : '' ?>><?= e(ADMIN_LANG === 'en' && $j['title_en'] ? $j['title_en'] : $j['title_ar']) ?></option><?php endforeach ?></select>
 <label class="sr" for="fs"><?= e(ta('status')) ?></label><select id="fs" name="status"><option value=""><?= e(ta('all')) ?> — <?= e(ta('status')) ?></option><?php foreach (APP_STATUSES as $s): ?><option value="<?= $s ?>"<?= get('status') === $s ? ' selected' : '' ?>><?= e(ta($s)) ?></option><?php endforeach ?></select>
 <label for="ff" class="inl"><?= e(ta('date_from')) ?></label><input id="ff" type="date" name="from" value="<?= e(get('from')) ?>">
 <label for="fto" class="inl"><?= e(ta('date_to')) ?></label><input id="fto" type="date" name="to" value="<?= e(get('to')) ?>">
 <label class="sr" for="s"><?= e(ta('search')) ?></label><input id="s" type="search" name="s" value="<?= e(get('s')) ?>" placeholder="<?= e(ta('search')) ?>…">
 <button class="btn btn-ghost" type="submit"><?= aicon('search') ?><?= e(ta('filter')) ?></button>
</form>
<?php if (!$rows): ?><div class="empty"><?= e(ta('none')) ?></div><?php else: ?>
<div class="table-wrap"><table class="tbl"><thead><tr><th scope="col"><?= e(ADMIN_LANG === 'en' ? 'Applicant' : 'المتقدم') ?></th><th scope="col"><?= e(ta('job')) ?></th><th scope="col"><?= e(ta('date')) ?></th><th scope="col"><?= e(ta('cv')) ?></th><th scope="col"><?= e(ta('status')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr<?= $r['status'] === 'new' ? ' class="is-new"' : '' ?>><td><a class="strong" href="<?= e(A('p=applications&id=' . $r['id'])) ?>"><?= e($r['name']) ?></a><br><span class="muted small" dir="ltr"><?= e($r['email']) ?></span></td><td><?= e($r['jt'] ?: ($r['job_title'] ?: ta('general_app'))) ?></td><td><?= e(adate($r['created_at'])) ?></td>
<td><?php if ($r['cv_file']): ?><a class="btn btn-sm btn-ghost" href="<?= e(A('a=file&id=' . (int)$r['cv_file'])) ?>"><?= aicon('download') ?><span class="sr"><?= e(ta('download') . ' ' . ta('cv')) ?></span></a><?php endif ?></td><td><?= can('applications', PERM_EDIT) ? $sel($r) : e(ta($r['status'])) ?></td></tr><?php endforeach ?>
</tbody></table></div><?= apager($pg, $pages, A('p=applications' . ($qs ? '&' . $qs : ''))) ?>
<?php endif;
    admin_page(ta('applications'), (string)ob_get_clean(), 'applications');
}
/* ---------------- subscribers ---------------- */
function page_subscribers(bool $isPost): never {
    need('subscribers');
    if ($isPost) {
        csrf_check(); $id = (int)post('id'); $r = q1('SELECT * FROM subscribers WHERE id = ?', [$id]);
        if ($r && post('act') === 'delete') { need('subscribers', PERM_FULL); q('DELETE FROM subscribers WHERE id = ?', [$id]); audit('delete', 'subscribers', $id, $r['email']); aflash(ta('deleted')); }
        if ($r && post('act') === 'toggle') { need('subscribers', PERM_EDIT); q('UPDATE subscribers SET active = ?, updated_at = ? WHERE id = ?', [$r['active'] ? 0 : 1, now_sql(), $id]); audit('status', 'subscribers', $id, $r['email']); aflash(ta('saved')); }
        redirect(A('p=subscribers'));
    }
    $w = ''; $p = [];
    if (($s = get('s')) !== '') { $w = ' WHERE email LIKE ?'; $p[] = "%$s%"; }
    if (get('export') === '1') {
        need('subscribers', PERM_FULL);
        $rows = qall("SELECT * FROM subscribers$w ORDER BY id DESC", $p); audit('export', 'subscribers', null, count($rows) . ' rows');
        xlsx_download('subscribers-' . today() . '.xlsx', ['#', ta('email'), 'اللغة / Language', ta('status'), ta('confirmed'), ta('date')],
          array_map(fn($r) => [$r['id'], $r['email'], $r['lang'], $r['active'] ? ta('active') : ta('inactive'), $r['confirmed'] ? ta('yes') : ta('no'), $r['created_at']], $rows), ADMIN_LANG === 'ar');
    }
    $total = (int)qv("SELECT COUNT(*) FROM subscribers$w", $p); $per = 50; $pages = max(1, (int)ceil($total / $per)); $pg = clamp_int(get('pg'), 1, $pages, 1);
    $rows = qall("SELECT * FROM subscribers$w ORDER BY id DESC LIMIT $per OFFSET " . (($pg - 1) * $per), $p);
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('users') ?><?= e(ta('subscribers')) ?></h1><p class="muted"><?= $total ?> <?= e(ta('items')) ?> · <?= e(setting('double_optin') === '1' ? ta('double_optin') : (ADMIN_LANG === 'en' ? 'Single opt-in' : 'اشتراك مباشر')) ?></p></div>
<div class="head-actions"><?php if (can('subscribers', PERM_FULL)): ?><a class="btn btn-ghost" href="<?= e(A('p=subscribers&export=1' . ($s !== '' ? '&s=' . rawurlencode($s) : ''))) ?>"><?= aicon('download') ?><?= e(ta('export')) ?></a><?php endif ?></div></div>
<form class="toolbar" method="get" action="/admin/"><input type="hidden" name="p" value="subscribers"><label class="sr" for="s"><?= e(ta('search')) ?></label><input id="s" type="search" name="s" value="<?= e($s) ?>" placeholder="<?= e(ta('email')) ?>…"><button class="btn btn-ghost" type="submit"><?= aicon('search') ?><?= e(ta('search')) ?></button></form>
<?php if (!$rows): ?><div class="empty"><?= e(ta('none')) ?></div><?php else: ?>
<div class="table-wrap"><table class="tbl"><thead><tr><th scope="col"><?= e(ta('email')) ?></th><th scope="col"><?= e(ta('status')) ?></th><th scope="col"><?= e(ta('date')) ?></th><th scope="col"><?= e(ta('actions')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td dir="ltr" class="ltr-cell"><?= e($r['email']) ?> <span class="muted small"><?= e(strtoupper($r['lang'])) ?></span></td><td><?= $r['active'] ? pill(ta('active'), 'ok') : pill(ta('inactive'), 'muted') ?> <?= $r['confirmed'] ? '' : pill(ta('unconfirmed'), 'warn') ?></td><td><?= e(adate($r['created_at'])) ?></td>
<td class="row-actions"><?php if (can('subscribers', PERM_EDIT)): ?><form method="post" action="<?= e(A('p=subscribers')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="act" value="toggle"><button class="btn btn-sm btn-ghost" type="submit"><?= e($r['active'] ? ta('inactive') : ta('active')) ?></button></form><?php endif ?>
<?php if (can('subscribers', PERM_FULL)): ?><form method="post" action="<?= e(A('p=subscribers')) ?>" data-confirm="<?= e(ta('confirm_delete')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="act" value="delete"><button class="btn btn-sm btn-danger" type="submit"><?= aicon('trash') ?><span class="sr"><?= e(ta('delete')) ?></span></button></form><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div><?= apager($pg, $pages, A('p=subscribers' . ($s !== '' ? '&s=' . rawurlencode($s) : ''))) ?>
<?php endif;
    admin_page(ta('subscribers'), (string)ob_get_clean(), 'subscribers');
}
/* ---------------- audit ---------------- */
function page_audit(): never {
    need('audit');
    $w = []; $p = [];
    if (($s = get('s')) !== '') { $w[] = '(username LIKE ? OR label LIKE ? OR entity LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%"); }
    if (preg_match('/^[a-z_]{2,30}$/', get('act'))) { $w[] = 'action = ?'; $p[] = get('act'); }
    $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
    $total = (int)qv("SELECT COUNT(*) FROM audit_log$where", $p); $per = 50; $pages = max(1, (int)ceil($total / $per)); $pg = clamp_int(get('pg'), 1, $pages, 1);
    $rows = qall("SELECT * FROM audit_log$where ORDER BY id DESC LIMIT $per OFFSET " . (($pg - 1) * $per), $p);
    $acts = array_column(qall('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action');
    ob_start(); ?>
<div class="page-head"><div><h1><?= aicon('eye') ?><?= e(ta('audit')) ?></h1><p class="muted"><?= $total ?> <?= e(ta('items')) ?></p></div></div>
<form class="toolbar" method="get" action="/admin/"><input type="hidden" name="p" value="audit">
<label class="sr" for="fa"><?= e(ta('action')) ?></label><select id="fa" name="act"><option value=""><?= e(ta('all')) ?> — <?= e(ta('action')) ?></option><?php foreach ($acts as $a): ?><option value="<?= e($a) ?>"<?= get('act') === $a ? ' selected' : '' ?>><?= e(audit_label($a)) ?></option><?php endforeach ?></select>
<label class="sr" for="s"><?= e(ta('search')) ?></label><input id="s" type="search" name="s" value="<?= e($s) ?>" placeholder="<?= e(ta('search')) ?>…"><button class="btn btn-ghost" type="submit"><?= aicon('search') ?><?= e(ta('filter')) ?></button></form>
<div class="table-wrap"><table class="tbl"><thead><tr><th scope="col"><?= e(ta('when')) ?></th><th scope="col"><?= e(ta('who')) ?></th><th scope="col"><?= e(ta('action')) ?></th><th scope="col"><?= e(ta('item')) ?></th><th scope="col"><?= e(ta('ip')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e(adate($r['at'])) ?></td><td dir="ltr" class="ltr-cell"><?= e($r['username'] ?: '—') ?></td><td><?= e(audit_label($r['action'])) ?></td><td><span class="muted"><?= e($r['entity']) ?><?= $r['entity_id'] ? ' #' . (int)$r['entity_id'] : '' ?></span> <?= e($r['label']) ?></td><td dir="ltr" class="ltr-cell small"><?= e($r['ip']) ?></td></tr><?php endforeach ?>
</tbody></table></div><?= apager($pg, $pages, A('p=audit' . ($s !== '' ? '&s=' . rawurlencode($s) : '') . (get('act') ? '&act=' . rawurlencode(get('act')) : ''))) ?>
<?php admin_page(ta('audit'), (string)ob_get_clean(), 'audit');
}
