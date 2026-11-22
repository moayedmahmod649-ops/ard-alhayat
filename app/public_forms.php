<?php
declare(strict_types=1);
/** Public form handlers (contact, job applications, newsletter). PRG on success; re-render with errors otherwise. */
$FORM = ['errors' => [], 'old' => []];
function form_fail(string $view, array $vars, array $meta, array $errors): never {
    global $FORM, $cacheable;
    $cacheable = false;
    $FORM['errors'] = $errors;
    $FORM['old'] = array_map(fn($v) => is_string($v) ? mb_substr($v, 0, 5000) : '', $_POST);
    http_response_code(422);
    echo render_page($view, $vars, $meta); exit;
}
function old(string $k): string { global $FORM; return (string)($FORM['old'][$k] ?? ''); }
function form_errors(): string {
    global $FORM;
    if (!$FORM['errors']) return '';
    return '<div class="alert alert-err" role="alert" tabindex="-1" data-focus><ul>' . implode('', array_map(fn($e) => '<li>' . e($e) . '</li>', array_unique($FORM['errors']))) . '</ul></div>';
}
function bot_check(string $form): ?string {
    if (post('website') !== '') return 'bot';
    if (!form_token_ok($form)) return t('err_form');
    if (form_throttled($form)) return t('err_throttle');
    return null;
}
function handle_contact(): never {
    $meta = ['title' => t('contact'), 'section' => 'contact'];
    if (($b = bot_check('contact')) === 'bot') redirect(url('contact?sent=1'));
    $err = $b ? [$b] : [];
    $topic = in_array(post('topic'), ['general', 'partner', 'services', 'invest'], true) ? post('topic') : 'general';
    $name = mb_substr(post('name'), 0, 120); $email = mb_substr(post('email'), 0, 190); $phone = mb_substr(post('phone'), 0, 40);
    $org = mb_substr(post('organisation'), 0, 160); $subject = mb_substr(post('subject'), 0, 200); $msg = mb_substr(post('message'), 0, 5000);
    if ($name === '' || $email === '' || $msg === '') $err[] = t('err_required');
    if ($email !== '' && !valid_email($email)) $err[] = t('err_email');
    if ($err) form_fail('contact', [], $meta, $err);
    form_hit('contact');
    q('INSERT INTO messages (topic, name, email, phone, organisation, subject, message, lang) VALUES (?,?,?,?,?,?,?,?)', [$topic, $name, $email, $phone, $org, $subject, $msg, LANG]);
    $to = setting('notify_email');
    if ($to !== '') send_mail($to, '[أرض الحياة] رسالة جديدة: ' . ($subject ?: $name), "النوع: $topic\nالاسم: $name\nالبريد: $email\nالهاتف: $phone\nالجهة: $org\n\n$msg\n\n— " . base_url() . "/admin/?m=messages");
    redirect(url('contact?sent=1#form'));
}
function handle_application(?array $job): never {
    global $CONFIG;
    $view = $job ? 'job' : 'careers'; $vars = $job ? ['j' => $job] : [];
    $meta = ['title' => $job ? L($job, 'title') : t('careers'), 'section' => 'careers'];
    $back = $job ? url('careers/' . $job['slug']) : url('careers');
    if (($b = bot_check('apply')) === 'bot') redirect($back . '?sent=1');
    $err = $b ? [$b] : [];
    $name = mb_substr(post('name'), 0, 120); $email = mb_substr(post('email'), 0, 190); $phone = mb_substr(post('phone'), 0, 40);
    $city = mb_substr(post('city'), 0, 80); $msg = mb_substr(post('message'), 0, 3000);
    if ($name === '' || $email === '' || $phone === '') $err[] = t('err_required');
    if ($email !== '' && !valid_email($email)) $err[] = t('err_email');
    $cv = upload_entries('cv');
    if (count($cv) !== 1) $err[] = t('err_cv');
    if ($err) form_fail($view, $vars, $meta, $err);
    try { $fid = store_document($cv[0], 'private'); }
    catch (Throwable $e) { form_fail($view, $vars, $meta, [t('err_cv')]); }
    form_hit('apply');
    q('INSERT INTO applications (job_id, job_title, name, email, phone, city, message, cv_file, lang) VALUES (?,?,?,?,?,?,?,?,?)',
      [$job['id'] ?? null, $job ? $job['title_ar'] : t('general_app'), $name, $email, $phone, $city, $msg, $fid, LANG]);
    $to = setting('hr_email') ?: setting('notify_email');
    if ($to !== '') send_mail($to, '[أرض الحياة] طلب توظيف جديد: ' . ($job['title_ar'] ?? 'طلب عام'), "الاسم: $name\nالبريد: $email\nالهاتف: $phone\nالمدينة: $city\n\n— " . base_url() . "/admin/?m=applications");
    redirect($back . '?sent=1#apply');
}
function handle_newsletter(): never {
    $back = url('?nl=1');
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        $path = (string)parse_url($ref, PHP_URL_PATH);
        if (preg_match('#^/(ar|en)/[a-z0-9/-]*$#', $path)) $back = $path;
    }
    $sep = '?';
    $b = bot_check('newsletter');
    if ($b === 'bot') redirect($back . $sep . 'nl=ok#nl-h');
    $email = mb_strtolower(post('email'));
    if ($b || !valid_email($email)) redirect($back . $sep . 'nl=err#nl-h');
    form_hit('newsletter');
    $double = setting('double_optin') === '1' && mail_configured();
    $ex = q1('SELECT * FROM subscribers WHERE email = ?', [$email]);
    $tok = $ex['token'] ?? bin2hex(random_bytes(16));
    if (!$ex) q('INSERT INTO subscribers (email, token, confirmed, active, lang) VALUES (?,?,?,1,?)', [$email, $tok, $double ? 0 : 1, LANG]);
    elseif (!$ex['active']) q('UPDATE subscribers SET active = 1, confirmed = ?, updated_at = ? WHERE id = ?', [$double ? 0 : 1, now_sql(), $ex['id']]);
    if ($double && (!$ex || !$ex['confirmed'] || !$ex['active'])) {
        send_mail($email, t('newsletter') . ' — ' . t('company_short'), t('confirm_sent') . "\n\n" . base_url() . url('newsletter/confirm?t=' . $tok) . "\n\n" . base_url() . url('newsletter/unsubscribe?t=' . $tok));
        redirect($back . $sep . 'nl=confirm#nl-h');
    }
    redirect($back . $sep . 'nl=ok#nl-h');
}
function handle_newsletter_link(string $what): never {
    $tok = get('t');
    $row = preg_match('/^[a-f0-9]{32}$/', $tok) ? q1('SELECT * FROM subscribers WHERE token = ?', [$tok]) : null;
    $msg = t('not_found');
    if ($row && $what === 'unsubscribe') { q('UPDATE subscribers SET active = 0, updated_at = ? WHERE id = ?', [now_sql(), $row['id']]); $msg = t('unsubscribed'); }
    if ($row && $what === 'confirm') { q('UPDATE subscribers SET confirmed = 1, active = 1, updated_at = ? WHERE id = ?', [now_sql(), $row['id']]); $msg = t('confirmed'); }
    echo render_page('message', ['msg' => $msg], ['title' => t('newsletter'), 'noindex' => true]); exit;
}
