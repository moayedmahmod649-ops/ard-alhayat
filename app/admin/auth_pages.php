<?php
declare(strict_types=1);
function auth_shell(string $title, string $inner): never {
    ob_start(); ?>
<div class="auth">
  <div class="auth-card">
    <img class="auth-logo" src="/assets/img/brand-lockup.webp" alt="<?= e(t('company')) ?>" width="200" height="168">
    <h1><?= e($title) ?></h1>
    <?= $inner ?>
    <form class="auth-lang" method="post" action="<?= e(A('a=alang')) ?>"><?= csrf_field() ?><button class="linkish" type="submit" lang="<?= ADMIN_LANG === 'ar' ? 'en' : 'ar' ?>"><?= aicon('globe') ?><?= e(ta('switch_lang')) ?></button></form>
  </div>
</div>
<?php admin_page($title, (string)ob_get_clean());
}
function page_login(string $err = '', string $user = ''): never {
    $f = aflash();
    $h = ($f ? '<div class="alert alert-' . e($f['t']) . '" role="status">' . e($f['m']) . '</div>' : '') . ($err ? '<div class="alert alert-err" role="alert">' . e($err) . '</div>' : '')
      . '<form method="post" action="' . e(A('a=login')) . '" class="stack">' . csrf_field()
      . '<div class="fld"><label for="u">' . e(ta('username')) . '</label><input id="u" name="username" autocomplete="username" dir="ltr" required value="' . e($user) . '" autofocus></div>'
      . '<div class="fld"><label for="pw">' . e(ta('password')) . '</label><input id="pw" name="password" type="password" autocomplete="current-password" dir="ltr" required></div>'
      . '<button class="btn btn-block" type="submit">' . e(ta('login')) . '</button></form>'
      . '<p class="center"><a href="' . e(A('a=forgot')) . '">' . e(ta('forgot')) . '</a></p>';
    if ($err) http_response_code(401);
    auth_shell(ta('login'), $h);
}
function do_login(): never {
    csrf_check();
    $user = mb_substr(post('username'), 0, 190); $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (login_throttled($user)) { usleep(300000); page_login(ta('login_throttle'), $user); }
    if (!attempt_login($user, $pw)) { usleep(300000); page_login(ta('login_fail'), $user); }
    audit('login', 'users', (int)$_SESSION['uid'], mb_strtolower($user));
    redirect(A());
}
function page_forgot(bool $isPost): never {
    if ($isPost) {
        csrf_check();
        $id = mb_strtolower(mb_substr(post('username'), 0, 190));
        if (!form_throttled('pwreset', 5, 3600)) {
            form_hit('pwreset');
            $row = q1("SELECT * FROM users WHERE (username = ? OR (email <> '' AND lower(email) = ?)) AND active = 1", [$id, $id]);
            if ($row && valid_email($row['email'])) {
                $tok = create_reset_token((int)$row['id']);
                $link = base_url() . '/admin/?a=reset&t=' . $tok;
                send_mail($row['email'], 'استعادة كلمة المرور / Password reset — أرض الحياة',
                  "مرحبًا {$row['display_name']},\n\nلتعيين كلمة مرور جديدة للوحة التحكم افتح الرابط التالي (صالح 30 دقيقة ولمرة واحدة):\n$link\n\nTo set a new admin password open the link above (valid for 30 minutes, single use).\nإذا لم تطلب ذلك فتجاهل هذه الرسالة. / If you did not request this, ignore this email.\n");
                q("INSERT INTO audit_log (user_id, username, action, entity, entity_id, label, ip, at) VALUES (?,?,?,?,?,?,?,?)", [$row['id'], $row['username'], 'password_reset_request', 'users', $row['id'], '', client_ip(), now_sql()]);
            }
        }
        aflash(ta('forgot_done'));
        redirect(A('a=forgot'));
    }
    $f = aflash();
    $h = ($f ? '<div class="alert alert-ok" role="status">' . e($f['m']) . '</div>' : '')
      . '<form method="post" action="' . e(A('a=forgot')) . '" class="stack">' . csrf_field()
      . '<div class="fld"><label for="u">' . e(ta('username')) . '</label><input id="u" name="username" dir="ltr" required></div>'
      . '<button class="btn btn-block" type="submit">' . e(ta('forgot_send')) . '</button></form>'
      . '<p class="hint">' . e(ADMIN_LANG === 'en' ? 'No email on the account, or email not set up yet? Ask the system administrator to issue a reset link from Users.' : 'لا يوجد بريد على الحساب أو لم يُضبط البريد بعد؟ اطلب من مدير النظام إصدار رابط استعادة من صفحة المستخدمين.') . '</p>'
      . '<p class="center"><a href="' . e(A()) . '">' . e(ta('back')) . '</a></p>';
    auth_shell(ta('forgot_title'), $h);
}
function page_reset(bool $isPost): never {
    $tok = $isPost ? post('t') : get('t');
    $rs = find_reset($tok);
    if (!$rs) auth_shell(ta('reset_title'), '<div class="alert alert-err" role="alert">' . e(ta('reset_bad')) . '</div><p class="center"><a href="' . e(A('a=forgot')) . '">' . e(ta('forgot')) . '</a></p>');
    $usr = q1('SELECT * FROM users WHERE id = ? AND active = 1', [$rs['user_id']]);
    if (!$usr) auth_shell(ta('reset_title'), '<div class="alert alert-err">' . e(ta('reset_bad')) . '</div>');
    $err = '';
    if ($isPost) {
        csrf_check();
        $pw = (string)($_POST['pw'] ?? ''); $pw2 = (string)($_POST['pw2'] ?? '');
        $prob = password_problem($pw, $usr['username']);
        if ($prob) $err = ta($prob); elseif ($pw !== $pw2) $err = ta('pw_match');
        else {
            set_password((int)$usr['id'], $pw);
            q('UPDATE password_resets SET used = 1 WHERE id = ?', [$rs['id']]);
            q("INSERT INTO audit_log (user_id, username, action, entity, entity_id, label, ip, at) VALUES (?,?,?,?,?,?,?,?)", [$usr['id'], $usr['username'], 'password_reset', 'users', $usr['id'], '', client_ip(), now_sql()]);
            if (session_status() === PHP_SESSION_ACTIVE) $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
            aflash(ta('reset_ok'));
            redirect(A());
        }
    }
    $h = ($err ? '<div class="alert alert-err" role="alert">' . e($err) . '</div>' : '')
      . '<form method="post" action="' . e(A('a=reset')) . '" class="stack">' . csrf_field() . '<input type="hidden" name="t" value="' . e($tok) . '">'
      . '<input type="text" name="username" value="' . e($usr['username']) . '" autocomplete="username" hidden>'
      . '<div class="fld"><label for="pw">' . e(ta('new_password')) . '</label><input id="pw" name="pw" type="password" autocomplete="new-password" minlength="10" dir="ltr" required><p class="hint">' . e(ta('pw_short')) . ' ' . e(ta('pw_mix')) . '</p></div>'
      . '<div class="fld"><label for="pw2">' . e(ta('confirm_password')) . '</label><input id="pw2" name="pw2" type="password" autocomplete="new-password" dir="ltr" required></div>'
      . '<button class="btn btn-block" type="submit">' . e(ta('save')) . '</button></form>';
    auth_shell(ta('reset_title'), $h);
}
