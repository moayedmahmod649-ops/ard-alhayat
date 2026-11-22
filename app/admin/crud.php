<?php
declare(strict_types=1);
/** Generic list / edit / save / delete for the schema-driven modules in modules.php. */
function fl(array $f): string { return ADMIN_LANG === 'en' ? $f[3] : $f[2]; }
function fopt(array $f, string $k, mixed $d = null): mixed { return $f[4][$k] ?? $d; }
function mod_pub_field(array $m): ?array { foreach ($m['fields'] as $f) if (fopt($f, 'pub')) return $f; return null; }
function row_is_live(array $m, array $r): bool {
    $pf = mod_pub_field($m); if (!$pf) return false;
    return $pf[1] === 'bool' ? (bool)$r[$pf[0]] : ($r[$pf[0]] ?? '') !== 'draft';
}
function select_options(array $f): array {
    if ($sql = fopt($f, 'options_sql')) { $o = []; foreach (qall($sql) as $r) $o[(string)$r['id']] = [$r['name_ar'], $r['name_en'] ?: $r['name_ar']]; return $o; }
    return fopt($f, 'options', []);
}
function parent_options(array $m, ?int $self): array {
    $rows = qall("SELECT id, name_ar, name_en, parent_id FROM {$m['table']} ORDER BY sort, id");
    $kids = []; foreach ($rows as $r) $kids[(int)$r['parent_id']][] = $r;
    $out = []; $walk = function (int $pid, int $d) use (&$walk, &$out, $kids, $self) {
        foreach ($kids[$pid] ?? [] as $r) { if ((int)$r['id'] === $self) continue; $out[(string)$r['id']] = str_repeat('— ', $d) . (ADMIN_LANG === 'en' && $r['name_en'] ? $r['name_en'] : $r['name_ar']); $walk((int)$r['id'], $d + 1); }
    };
    $walk(0, 0);
    return $out;
}
function cell(array $m, array $f, array $r): string {
    $v = $r[$f[0]] ?? '';
    switch ($f[1]) {
        case 'bool': return $f[0] === 'published' ? ($v ? pill(ta('published'), 'ok') : pill(ta('hidden'), 'muted')) : ($v ? pill(ta('yes'), 'info') : '<span class="muted">—</span>');
        case 'select':
            if ($f[0] === 'status' && $m['table'] === 'news') {
                if ($v === 'published' && $r['publish_at'] > now_sql()) return pill(ta('scheduled'), 'warn');
                return pill(ta($v), ['published' => 'ok', 'draft' => 'muted', 'archived' => 'info'][$v] ?? 'muted');
            }
            $o = select_options($f)[(string)$v] ?? null; return $o ? e(al($o)) : '<span class="muted">—</span>';
        case 'parent': if (!$v) return '<span class="muted">—</span>'; $p = q1("SELECT name_ar, name_en FROM {$m['table']} WHERE id = ?", [$v]); return e($p ? (ADMIN_LANG === 'en' && $p['name_en'] ? $p['name_en'] : $p['name_ar']) : '—');
        case 'datetime': return e(substr((string)$v, 0, 16));
        case 'date':
            if ($v === '') return '<span class="muted">—</span>';
            return e($v) . ($v < today() ? ' ' . pill(ta('expired'), 'muted') : '');
        default: return e(mb_strimwidth((string)$v, 0, 70, '…'));
    }
}
function field_by_name(array $m, string $n): ?array { foreach ($m['fields'] as $f) if ($f[0] === $n) return $f; return null; }

function crud_list(string $key, array $m): never {
    need($m['perm']);
    $where = []; $p = [];
    $s = get('s');
    $searchCols = $m['search'] ?? array_values(array_filter([$m['cols'][0]], fn($c) => str_ends_with($c, '_ar'))) ;
    if ($s !== '' && $searchCols) { $w = []; foreach ($searchCols as $c) { $w[] = "$c LIKE ?"; $p[] = '%' . $s . '%'; } $w2 = str_replace('_ar', '_en', $searchCols[0]); if (!in_array($w2, $searchCols, true) && field_by_name($m, $w2)) { $w[] = "$w2 LIKE ?"; $p[] = '%' . $s . '%'; } $where[] = '(' . implode(' OR ', $w) . ')'; }
    $wsql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int)qv("SELECT COUNT(*) FROM {$m['table']}$wsql", $p);
    $per = 25; $pages = max(1, (int)ceil($total / $per)); $pg = clamp_int(get('pg'), 1, $pages, 1);
    $rows = qall("SELECT * FROM {$m['table']}$wsql ORDER BY {$m['order']} LIMIT $per OFFSET " . (($pg - 1) * $per), $p);
    $title = al($m['title']);
    ob_start(); ?>
<div class="page-head">
  <div><h1><?= aicon($m['icon']) ?><?= e($title) ?></h1><p class="muted"><?= $total ?> <?= e(ta('items')) ?></p></div>
  <div class="head-actions">
    <?php foreach (sub_tabs($key) as [$k2, $lab]): ?><a class="btn btn-ghost<?= $k2 === $key ? ' is-on' : '' ?>" href="<?= e(A('m=' . $k2)) ?>"><?= e($lab) ?></a><?php endforeach ?>
    <?php if (can($m['perm'], PERM_EDIT)): ?><a class="btn" href="<?= e(A('m=' . $key . '&a=edit')) ?>"><?= aicon('check') ?><?= e(ta('add')) ?></a><?php endif ?>
  </div>
</div>
<?php if (!empty($m['note'])): ?><p class="note"><?= e(al($m['note'])) ?></p><?php endif ?>
<?php if (!can($m['perm'], PERM_FULL) && mod_pub_field($m)): ?><p class="note"><?= e(ta('no_publish')) ?></p><?php endif ?>
<form class="toolbar" method="get" action="/admin/" role="search"><input type="hidden" name="m" value="<?= e($key) ?>">
  <label class="sr" for="s"><?= e(ta('search')) ?></label><input id="s" type="search" name="s" value="<?= e($s) ?>" placeholder="<?= e(ta('search')) ?>…"><button class="btn btn-ghost" type="submit"><?= aicon('search') ?><?= e(ta('search')) ?></button>
</form>
<?php if (!$rows): ?><div class="empty"><?= e(ta('none')) ?></div><?php else: ?>
<div class="table-wrap"><table class="tbl">
<thead><tr><?php if (field_by_name($m, 'image')): ?><th scope="col"><span class="sr"><?= e(ta('current_image')) ?></span></th><?php endif ?>
<?php foreach ($m['cols'] as $c): $f = field_by_name($m, $c); ?><th scope="col"><?= e(preg_replace('/\s*\((عربي|Arabic)\)$/u', '', $f ? fl($f) : $c)) ?></th><?php endforeach ?><th scope="col"><?= e(ta('actions')) ?></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): $ed = A('m=' . $key . '&a=edit&id=' . $r['id']); ?>
<tr>
  <?php if (field_by_name($m, 'image')): ?><td class="thumb"><?php if (!empty($r['image'])): ?><img src="<?= e(img_variant($r['image'], 't')) ?>" alt="" width="72" height="48" loading="lazy"><?php endif ?></td><?php endif ?>
  <?php foreach ($m['cols'] as $i => $c): $f = field_by_name($m, $c) ?? [$c, 'text', $c, $c, []]; ?>
  <td><?= $i === 0 ? '<a class="strong" href="' . e($ed) . '">' . cell($m, $f, $r) . '</a>' : cell($m, $f, $r) ?></td>
  <?php endforeach ?>
  <td class="row-actions">
    <a class="btn btn-sm btn-ghost" href="<?= e($ed) ?>"><?= aicon('file') ?><?= e(can($m['perm'], PERM_EDIT) ? ta('edit') : ta('preview')) ?></a>
    <?php if (!empty($m['path']) && isset($r['slug'])): ?><a class="btn btn-sm btn-ghost" href="/ar/<?= e($m['path'] . $r['slug']) ?>?preview=1" target="_blank" rel="noopener"><?= aicon('eye') ?><span class="sr"><?= e(ta('preview')) ?></span></a><?php endif ?>
    <?php if (can($m['perm'], PERM_FULL) && !in_array($r['slug'] ?? '', $m['locked_slugs'] ?? [], true)): ?>
    <form method="post" action="<?= e(A('m=' . $key . '&a=delete')) ?>" data-confirm="<?= e(ta('confirm_delete')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger" type="submit"><?= aicon('trash') ?><span class="sr"><?= e(ta('delete')) ?></span></button></form>
    <?php endif ?>
  </td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?= apager($pg, $pages, A('m=' . $key . ($s !== '' ? '&s=' . rawurlencode($s) : ''))) ?>
<?php endif;
    admin_page($title, (string)ob_get_clean(), nav_key($key));
}
function nav_key(string $key): string { return ['company_values' => 'about', 'org_units' => 'about', 'team' => 'about', 'news_categories' => 'news'][$key] ?? $key; }
function sub_tabs(string $key): array {
    $M = modules();
    if (in_array($key, ['team', 'company_values', 'org_units'], true)) return [['team', al($M['team']['title'])], ['company_values', al($M['company_values']['title'])], ['org_units', al($M['org_units']['title'])]];
    if (in_array($key, ['news', 'news_categories'], true)) return [['news', al($M['news']['title'])], ['news_categories', al($M['news_categories']['title'])]];
    return [];
}

/* ---------------- edit form ---------------- */
function render_field(array $m, array $f, array $r, array $err, bool $ro): string {
    [$n, $type] = $f; $v = $r[$n] ?? ''; $id = 'f_' . $n; $lab = fl($f);
    $req = fopt($f, 'req') === true || (fopt($f, 'req') === 'ar' && fopt($f, 'lang') === 'ar');
    $dis = $ro ? ' disabled' : '';
    $lang = fopt($f, 'lang'); $la = $lang ? ' lang="' . $lang . '" dir="' . ($lang === 'ar' ? 'rtl' : 'ltr') . '"' : '';
    $max = fopt($f, 'max'); $ml = $max ? ' maxlength="' . (int)$max . '"' : '';
    $e = isset($err[$n]) ? '<p class="ferr" id="' . $id . '_err">' . e($err[$n]) . '</p>' : '';
    $aria = isset($err[$n]) ? ' aria-invalid="true" aria-describedby="' . $id . '_err"' : '';
    $L = '<label for="' . $id . '">' . e($lab) . ($req ? ' <b class="req" aria-hidden="true">*</b>' : '') . '</label>';
    $rq = $req ? ' required' : '';
    switch ($type) {
        case 'text': case 'tags':
            return "<div class=\"fld\">$L<input id=\"$id\" name=\"$n\" type=\"text\" value=\"" . e((string)$v) . "\"$ml$rq$la$aria$dis>$e</div>";
        case 'url':
            return "<div class=\"fld\">$L<input id=\"$id\" name=\"$n\" type=\"text\" inputmode=\"url\" dir=\"ltr\" value=\"" . e((string)$v) . "\"$rq$aria$dis>$e</div>";
        case 'slug':
            $locked = in_array($v, $m['locked_slugs'] ?? [], true);
            return "<div class=\"fld\">$L<input id=\"$id\" name=\"$n\" type=\"text\" dir=\"ltr\" pattern=\"[a-z0-9]+(-[a-z0-9]+)*\" maxlength=\"80\" value=\"" . e((string)$v) . "\"" . ($locked ? ' readonly' : '') . "$aria$dis><p class=\"hint\">" . e(ADMIN_LANG === 'en' ? 'Leave empty to generate from the English title.' : 'اتركه فارغًا ليُولَّد من العنوان الإنجليزي.') . "</p>$e</div>";
        case 'textarea': case 'videos':
            return "<div class=\"fld fld-wide\">$L<textarea id=\"$id\" name=\"$n\" rows=\"" . ($type === 'videos' ? 4 : 3) . "\"$ml$rq" . ($type === 'videos' ? ' dir="auto"' : $la) . "$aria$dis>" . e((string)$v) . "</textarea>$e</div>";
        case 'rich':
            return "<div class=\"fld fld-wide\"><span class=\"lbl\" id=\"{$id}_l\">" . e($lab) . "</span><div class=\"rte\" data-rte$la><textarea id=\"$id\" name=\"$n\" rows=\"10\" aria-labelledby=\"{$id}_l\"$dis>" . e((string)$v) . "</textarea></div>$e</div>";
        case 'number':
            return "<div class=\"fld fld-sm\">$L<input id=\"$id\" name=\"$n\" type=\"number\" step=\"1\" value=\"" . e((string)$v) . "\"$aria$dis>$e</div>";
        case 'date':
            return "<div class=\"fld fld-sm\">$L<input id=\"$id\" name=\"$n\" type=\"date\" value=\"" . e((string)$v) . "\"$aria$dis>$e</div>";
        case 'datetime':
            $dv = $v ? str_replace(' ', 'T', substr((string)$v, 0, 16)) : date('Y-m-d\TH:i');
            return "<div class=\"fld fld-sm\">$L<input id=\"$id\" name=\"$n\" type=\"datetime-local\" value=\"" . e($dv) . "\"$aria$dis>$e</div>";
        case 'bool':
            $pubLock = fopt($f, 'pub') && !can($m['perm'], PERM_FULL);
            return "<div class=\"fld fld-check\"><input type=\"hidden\" name=\"$n\" value=\"0\"><input id=\"$id\" name=\"$n\" type=\"checkbox\" value=\"1\"" . ($v ? ' checked' : '') . ($pubLock ? ' disabled' : '') . "$dis><label for=\"$id\">" . e($lab) . "</label>" . ($pubLock ? '<p class="hint">' . e(ta('no_publish')) . '</p>' : '') . "$e</div>";
        case 'select': case 'parent':
            $opts = $type === 'parent' ? parent_options($m, isset($r['id']) ? (int)$r['id'] : null) : select_options($f);
            $pubLock = fopt($f, 'pub') && !can($m['perm'], PERM_FULL);
            $h = "<div class=\"fld fld-sm\">$L<select id=\"$id\" name=\"$n\"$aria" . ($pubLock ? ' disabled' : '') . "$dis>";
            if ($type === 'parent') $h .= '<option value="">' . e(ta('parent_none')) . '</option>';
            elseif (fopt($f, 'null')) $h .= '<option value="">' . e(ta('none_opt')) . '</option>';
            foreach ($opts as $k => $o) $h .= '<option value="' . e((string)$k) . '"' . ((string)$k === (string)$v ? ' selected' : '') . '>' . e(is_array($o) ? al($o) : $o) . '</option>';
            return $h . '</select>' . ($pubLock ? '<p class="hint">' . e(ta('no_publish')) . '</p>' : '') . "$e</div>";
        case 'image':
            $h = "<div class=\"fld fld-wide fld-image\"><span class=\"lbl\">" . e($lab) . ($req ? ' <b class="req" aria-hidden="true">*</b>' : '') . "</span>";
            if ($v) $h .= '<figure class="cur-img"><img src="' . e(img_variant((string)$v, 't')) . '" alt="' . e(ta('current_image')) . '" loading="lazy">' . '</figure>'
                . (!$req && !$ro ? "<div class=\"fld-check\"><input type=\"checkbox\" id=\"{$id}_rm\" name=\"{$n}__remove\" value=\"1\"><label for=\"{$id}_rm\">" . e(ta('remove_image')) . '</label></div>' : '');
            return $h . "<label class=\"file-btn\" for=\"$id\">" . aicon('image') . e(ta('upload_new')) . "</label><input class=\"file-in\" id=\"$id\" name=\"{$n}__file\" type=\"file\" accept=\"image/jpeg,image/png,image/webp\"$aria$dis><p class=\"hint\">JPG · PNG · WebP — " . e(ADMIN_LANG === 'en' ? 'max ' : 'حتى ') . (int)$GLOBALS['CONFIG']['max_upload_mb'] . " MB</p>$e</div>";
        case 'gallery':
            $items = gallery_items((string)$v);
            $h = "<fieldset class=\"fld fld-wide gallery-ed\"><legend>" . e($lab) . "</legend><ol class=\"g-list\" data-sortable>";
            foreach ($items as $i => $g) {
                $h .= '<li class="g-item"><img src="' . e(img_variant($g['src'], 't')) . '" alt="" loading="lazy"><input type="hidden" name="g_src[]" value="' . e($g['src']) . '">'
                    . '<label class="sr" for="g_ar' . $i . '">' . e(ta('caption_ar')) . '</label><input id="g_ar' . $i . '" name="g_ar[]" lang="ar" dir="rtl" maxlength="160" placeholder="' . e(ta('caption_ar')) . '" value="' . e((string)($g['ar'] ?? '')) . "\"$dis>"
                    . '<label class="sr" for="g_en' . $i . '">' . e(ta('caption_en')) . '</label><input id="g_en' . $i . '" name="g_en[]" lang="en" dir="ltr" maxlength="160" placeholder="' . e(ta('caption_en')) . '" value="' . e((string)($g['en'] ?? '')) . "\"$dis>"
                    . "<div class=\"fld-check\"><input type=\"checkbox\" id=\"g_rm$i\" name=\"g_rm[]\" value=\"" . e($g['src']) . "\"$dis><label for=\"g_rm$i\">" . e(ta('remove')) . '</label></div></li>';
            }
            return $h . "</ol><label class=\"file-btn\" for=\"$id\">" . aicon('image') . e(ta('add_photos')) . "</label><input class=\"file-in\" id=\"$id\" name=\"g_new[]\" type=\"file\" multiple accept=\"image/jpeg,image/png,image/webp\"$dis>$e</fieldset>";
        case 'files':
            $ids = array_map('intval', json_list((string)$v));
            $h = "<fieldset class=\"fld fld-wide\"><legend>" . e($lab) . '</legend><ul class="f-list">';
            foreach ($ids as $fid) { $fr = file_row($fid); if (!$fr) continue;
                $h .= '<li>' . aicon('file') . '<a href="' . e(A('a=file&id=' . $fid)) . '">' . e($fr['orig_name']) . '</a> <span class="muted">' . human_bytes((int)$fr['bytes']) . '</span><input type="hidden" name="f_keep[]" value="' . $fid . '">'
                    . "<span class=\"fld-check\"><input type=\"checkbox\" id=\"f_rm$fid\" name=\"f_rm[]\" value=\"$fid\"$dis><label for=\"f_rm$fid\">" . e(ta('remove')) . '</label></span></li>'; }
            return $h . "</ul><label class=\"file-btn\" for=\"$id\">" . aicon('file') . e(ta('add_files')) . "</label><input class=\"file-in\" id=\"$id\" name=\"f_new[]\" type=\"file\" multiple accept=\".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document\"$dis>$e</fieldset>";
    }
    return '';
}
function crud_edit(string $key, array $m, array $r = [], array $err = []): never {
    need($m['perm']);
    $id = (int)($r['id'] ?? get('id'));
    if (!$r && $id) { $r = q1("SELECT * FROM {$m['table']} WHERE id = ?", [$id]) ?? deny(); }
    if (!$id && !can($m['perm'], PERM_EDIT)) deny();
    if (!$r) foreach ($m['fields'] as $f) if ($f[1] === 'bool' && $f[0] === 'published') $r['published'] = can($m['perm'], PERM_FULL) ? 1 : 0;
    $ro = !can($m['perm'], PERM_EDIT) || ($id && !can($m['perm'], PERM_FULL) && row_is_live($m, $r));
    $title = $id ? ta('edit_of', al($m['title'])) : ta('new_of', al($m['title']));
    $groups = ['main' => [], 'ar' => [], 'en' => [], 'seo' => []];
    foreach ($m['fields'] as $f) {
        if (fopt($f, 'group') === 'seo') $groups['seo'][] = $f;
        elseif (($l = fopt($f, 'lang'))) $groups[$l][] = $f;
        else $groups['main'][] = $f;
    }
    ob_start(); ?>
<div class="page-head">
  <div><p class="crumb"><a href="<?= e(A('m=' . $key)) ?>"><?= e(al($m['title'])) ?></a></p><h1><?= e($title) ?></h1></div>
  <div class="head-actions">
  <?php if ($id && !empty($m['path']) && isset($r['slug'])): ?><a class="btn btn-ghost" href="/ar/<?= e($m['path'] . $r['slug']) ?>?preview=1" target="_blank" rel="noopener"><?= aicon('eye') ?><?= e(ta('preview')) ?> AR</a><a class="btn btn-ghost" href="/en/<?= e($m['path'] . $r['slug']) ?>?preview=1" target="_blank" rel="noopener"><?= aicon('eye') ?><?= e(ta('preview')) ?> EN</a><?php endif ?>
  </div>
</div>
<?php if ($err): ?><div class="alert alert-err" role="alert"><?= e(ta('fix_errors')) ?><?php if (isset($err['_'])): ?> — <?= e($err['_']) ?><?php endif ?></div><?php endif ?>
<?php if ($ro && can($m['perm'], PERM_EDIT)): ?><p class="note"><?= e(ADMIN_LANG === 'en' ? 'This item is live. Only a content manager can change or unpublish it.' : 'هذا العنصر منشور. لا يعدّله أو يلغي نشره إلا مدير المحتوى.') ?></p><?php endif ?>
<form class="edit-form" method="post" action="<?= e(A('m=' . $key . '&a=save')) ?>" enctype="multipart/form-data" data-autosave="<?= e($key . ':' . $id) ?>">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<?php if ($groups['ar'] || $groups['en']): ?>
<div class="tabs" role="tablist" data-tabs>
  <button type="button" role="tab" id="t-ar" aria-controls="p-ar" aria-selected="true"><?= e(ta('arabic')) ?></button>
  <button type="button" role="tab" id="t-en" aria-controls="p-en" aria-selected="false" tabindex="-1"><?= e(ta('english')) ?></button>
</div>
<section class="card tabpanel" role="tabpanel" id="p-ar" aria-labelledby="t-ar"><div class="grid-f"><?php foreach ($groups['ar'] as $f) echo render_field($m, $f, $r, $err, $ro) ?></div></section>
<section class="card tabpanel" role="tabpanel" id="p-en" aria-labelledby="t-en" hidden><div class="grid-f"><?php foreach ($groups['en'] as $f) echo render_field($m, $f, $r, $err, $ro) ?></div></section>
<?php endif ?>
<section class="card"><h2 class="h3"><?= e(ta('general')) ?></h2><div class="grid-f"><?php foreach ($groups['main'] as $f) echo render_field($m, $f, $r, $err, $ro) ?></div></section>
<?php if ($groups['seo']): ?><details class="card"><summary class="h3"><?= e(ta('seo')) ?></summary><div class="grid-f"><?php foreach ($groups['seo'] as $f) echo render_field($m, $f, $r, $err, $ro) ?></div></details><?php endif ?>
<div class="form-actions">
  <?php if (!$ro): ?><button class="btn" type="submit"><?= aicon('check') ?><?= e(ta('save')) ?></button><?php endif ?>
  <a class="btn btn-ghost" href="<?= e(A('m=' . $key)) ?>"><?= e(ta('back')) ?></a>
</div>
</form>
<?php
    admin_page($title, (string)ob_get_clean(), nav_key($key));
}

/* ---------------- save ---------------- */
function crud_save(string $key, array $m): never {
    need($m['perm'], PERM_EDIT);
    csrf_check();
    $id = (int)post('id');
    $old = $id ? (q1("SELECT * FROM {$m['table']} WHERE id = ?", [$id]) ?? deny()) : [];
    $full = can($m['perm'], PERM_FULL);
    if ($old && !$full && row_is_live($m, $old)) deny();
    $data = []; $err = []; $newUploads = []; $toDelete = []; $newFiles = []; $filesToDelete = [];
    $prefix = preg_replace('/[^a-z]/', '', $m['table']);
    try {
    foreach ($m['fields'] as $f) {
        [$n, $type] = $f; $max = (int)fopt($f, 'max', 0);
        $req = fopt($f, 'req') === true || (fopt($f, 'req') === 'ar' && fopt($f, 'lang') === 'ar');
        switch ($type) {
            case 'text': case 'textarea':
                $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', post($n)) ?? '';
                if ($max && mb_strlen($v) > $max) $err[$n] = ta('too_long');
                if ($req && $v === '') $err[$n] = ta('required');
                if ($n === 'value' && $m['table'] === 'stats' && $v !== '' && !preg_match('/^[0-9][0-9.,]*$/', $v)) $err[$n] = ta('bad_number');
                $data[$n] = $v; break;
            case 'tags':
                $tags = array_values(array_unique(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 40), preg_split('/[,،]+/u', post($n)) ?: []))));
                $data[$n] = implode(', ', array_slice($tags, 0, 15)); break;
            case 'videos':
                $out = [];
                foreach (lines(post($n)) as $ln) { $parts = array_map('trim', explode('|', $ln)); if (!video_info($parts[0])) { $err[$n] = ta('bad_url') . ' ' . mb_substr($parts[0], 0, 60); continue; } $out[] = implode(' | ', array_map(fn($x) => mb_substr($x, 0, 160), array_slice($parts, 0, 3))); }
                $data[$n] = implode("\n", array_slice($out, 0, 30)); break;
            case 'rich': $data[$n] = sanitize_html(is_string($_POST[$n] ?? null) ? $_POST[$n] : ''); break;
            case 'url':
                $raw = post($n); $v = $raw === '' ? '' : safe_url($raw, !fopt($f, 'abs'));
                if ($raw !== '' && $v === '') $err[$n] = ta('bad_url');
                if ($req && $raw === '') $err[$n] = ta('required');
                $data[$n] = $v; break;
            case 'slug':
                if ($old && in_array($old[$n], $m['locked_slugs'] ?? [], true)) { $data[$n] = $old[$n]; break; }
                $v = strtolower(post($n));
                if ($v === '') { $src = fopt($f, 'from'); $v = slugify(post((string)$src)); if ($v === '') $v = slugify($prefix) . '-' . substr(bin2hex(random_bytes(3)), 0, 5); }
                if (!valid_slug($v)) $err[$n] = ta('bad_slug');
                elseif (q1("SELECT id FROM {$m['table']} WHERE slug = ? AND id <> ?", [$v, $id])) $err[$n] = ta('dup_slug');
                $data[$n] = $v; break;
            case 'number': $raw = post($n, '0'); if ($raw !== '' && !preg_match('/^-?\d{1,6}$/', $raw)) $err[$n] = ta('bad_number'); $data[$n] = (int)$raw; break;
            case 'date': $v = post($n); if ($v !== '' && !valid_date($v)) $err[$n] = ta('bad_date'); $data[$n] = $v; break;
            case 'datetime':
                $v = post($n);
                if ($v === '') $v = now_sql();
                elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $v) && strtotime($v)) $v = date('Y-m-d H:i:s', strtotime($v));
                else { $err[$n] = ta('bad_date'); }
                $data[$n] = $v; break;
            case 'bool':
                if (fopt($f, 'pub') && !$full) { $data[$n] = $old ? (int)$old[$n] : 0; break; }
                $data[$n] = post_bool($n); break;
            case 'select':
                if (fopt($f, 'pub') && !$full) { $data[$n] = $old[$n] ?? 'draft'; break; }
                $v = post($n); $opts = select_options($f);
                if ($v === '' && fopt($f, 'null')) { $data[$n] = null; break; }
                if (!array_key_exists($v, $opts)) { $v = (string)array_key_first($opts); }
                $data[$n] = fopt($f, 'options_sql') ? (int)$v : $v; break;
            case 'parent':
                $v = (int)post($n); $data[$n] = ($v && array_key_exists((string)$v, parent_options($m, $id ?: null))) ? $v : null; break;
            case 'image':
                $cur = (string)($old[$n] ?? '');
                $up = handle_image_upload($n . '__file', (string)fopt($f, 'kind', 'cover'), $prefix);
                if ($up) { $newUploads[] = $up; if ($cur) $toDelete[] = $cur; $data[$n] = $up; }
                elseif (post_bool($n . '__remove') && !$req) { if ($cur) $toDelete[] = $cur; $data[$n] = ''; }
                else $data[$n] = $cur;
                if ($req && $data[$n] === '') $err[$n] = ta('required');
                break;
            case 'gallery':
                $oldItems = gallery_items((string)($old[$n] ?? '')); $oldSrc = array_column($oldItems, 'src');
                $src = (array)($_POST['g_src'] ?? []); $ar = (array)($_POST['g_ar'] ?? []); $en = (array)($_POST['g_en'] ?? []); $rm = array_map('strval', (array)($_POST['g_rm'] ?? []));
                $items = [];
                foreach ($src as $i => $s) {
                    if (!is_string($s) || !in_array($s, $oldSrc, true)) continue; // only items that really belong to this record
                    if (in_array($s, $rm, true)) { $toDelete[] = $s; continue; }
                    $items[] = ['src' => $s, 'ar' => mb_substr(trim((string)($ar[$i] ?? '')), 0, 160), 'en' => mb_substr(trim((string)($en[$i] ?? '')), 0, 160)];
                }
                foreach (array_diff($oldSrc, array_column($items, 'src'), $toDelete) as $lost) $toDelete[] = $lost;
                foreach (handle_multi_upload('g_new', 'photo', $prefix, 40) as $p) { $newUploads[] = $p; $items[] = ['src' => $p, 'ar' => '', 'en' => '']; }
                $data[$n] = $items ? json_encode(array_slice($items, 0, 80), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''; break;
            case 'files':
                $oldIds = array_map('intval', json_list((string)($old[$n] ?? '')));
                $rm = array_map('intval', (array)($_POST['f_rm'] ?? []));
                $keep = array_values(array_filter(array_map('intval', (array)($_POST['f_keep'] ?? [])), fn($x) => in_array($x, $oldIds, true) && !in_array($x, $rm, true)));
                $filesToDelete = array_values(array_diff($oldIds, $keep));
                foreach (upload_entries('f_new') as $one) {
                    try { $newFiles[] = $keep[] = store_document($one, 'public'); }
                    catch (RuntimeException $ex) { $err[$n] = ta('doc_bad'); }
                }
                $data[$n] = $keep ? json_encode($keep) : ''; break;
        }
    }
    } catch (RuntimeException $ex) { $err['_'] = $ex->getMessage(); }
    if ($err) {
        foreach ($newUploads as $p) delete_upload($p);
        foreach ($newFiles as $fid) delete_file($fid);
        $keepImgs = []; foreach ($m['fields'] as $f) if ($f[1] === 'image') $data[$f[0]] = $old[$f[0]] ?? '';
        if (isset($data['gallery'])) $data['gallery'] = $old['gallery'] ?? '';
        if (isset($data['attachments'])) $data['attachments'] = $old['attachments'] ?? '';
        crud_edit($key, $m, ['id' => $id] + $data + $old, $err);
    }
    $data['updated_at'] = now_sql();
    if ($id) {
        $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
        q("UPDATE {$m['table']} SET $sets WHERE id = ?", [...array_values($data), $id]);
    } else {
        $cols = implode(', ', array_keys($data)); $qs = implode(', ', array_fill(0, count($data), '?'));
        q("INSERT INTO {$m['table']} ($cols) VALUES ($qs)", array_values($data));
        $id = (int)db()->lastInsertId();
    }
    foreach (array_unique($toDelete) as $p) delete_upload($p);
    foreach ($filesToDelete as $fid) delete_file($fid);
    $label = (string)($data['title_ar'] ?? $data['name_ar'] ?? $data['label_ar'] ?? '#' . $id);
    audit($old ? 'update' : 'create', $m['table'], $id, $label);
    page_cache_clear();
    aflash(ta('saved'));
    redirect(A('m=' . $key . '&a=edit&id=' . $id));
}
function crud_delete(string $key, array $m): never {
    need($m['perm'], PERM_FULL);
    csrf_check();
    $id = (int)post('id');
    $r = q1("SELECT * FROM {$m['table']} WHERE id = ?", [$id]);
    if ($r) {
        if (in_array($r['slug'] ?? '', $m['locked_slugs'] ?? [], true)) deny();
        q("DELETE FROM {$m['table']} WHERE id = ?", [$id]);
        foreach ($m['fields'] as $f) {
            if ($f[1] === 'image' && !empty($r[$f[0]])) delete_upload($r[$f[0]]);
            if ($f[1] === 'gallery') foreach (gallery_items((string)$r[$f[0]]) as $g) delete_upload($g['src']);
            if ($f[1] === 'files') foreach (json_list((string)$r[$f[0]]) as $fid) delete_file((int)$fid);
        }
        audit('delete', $m['table'], $id, (string)($r['title_ar'] ?? $r['name_ar'] ?? $r['label_ar'] ?? ''));
        page_cache_clear();
        aflash(ta('deleted'));
    }
    redirect(A('m=' . $key));
}
