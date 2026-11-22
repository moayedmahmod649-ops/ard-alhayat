<?php
declare(strict_types=1);
/**
 * Hardened image upload (from Tawasot v5): size cap, finfo MIME sniff + getimagesize, pixel cap, GD decode and
 * RE-ENCODE to WebP under a random name — original bytes are never stored.
 * $kind: 'cover' → 1600x900 + -m 800x960 + -t 720x480 · 'photo' (gallery, keeps ratio, max 1600) + -t 600x450 · 'logo' 320x200 contained.
 */
function image_from_upload(array $f, string $kind): GdImage {
    global $CONFIG;
    @ini_set('memory_limit', '256M'); @set_time_limit(60);
    if (!is_string($f['tmp_name'] ?? null) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException(ta('up_fail') . ' (' . (int)($f['error'] ?? 0) . ')');
    if (!is_uploaded_file($f['tmp_name']) && !getenv('AH_ALLOW_LOCAL_UPLOAD')) throw new RuntimeException(ta('up_invalid'));
    if ($f['size'] <= 0 || $f['size'] > $CONFIG['max_upload_mb'] * 1048576) throw new RuntimeException(ta('up_too_big', $CONFIG['max_upload_mb']));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException(ta('up_type'));
    $info = @getimagesize($f['tmp_name']);
    if (!$info || $info[0] < 1 || $info[1] < 1) throw new RuntimeException(ta('up_not_image'));
    if ($info[0] * $info[1] > $CONFIG['max_pixels']) throw new RuntimeException(ta('up_dims'));
    if ($kind === 'cover' && ($info[0] < 800 || $info[1] < 400)) throw new RuntimeException(ta('up_small'));
    $src = match ($allowed[$mime]) {
        'jpeg' => @imagecreatefromjpeg($f['tmp_name']),
        'png'  => @imagecreatefrompng($f['tmp_name']),
        'webp' => @imagecreatefromwebp($f['tmp_name']),
    };
    if (!$src) throw new RuntimeException(ta('up_not_image'));
    if ($allowed[$mime] === 'jpeg' && function_exists('exif_read_data')) $src = exif_orient($src, $f['tmp_name']);
    return $src;
}
function store_image(GdImage $src, string $kind, string $prefix): string {
    global $CONFIG;
    $dir = $CONFIG['uploads_dir'];
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $prefix = preg_replace('/[^a-z]/', '', $prefix) ?: 'img';
    $name = $prefix . '-' . bin2hex(random_bytes(8));
    if ($kind === 'logo') {
        save_webp(fit_contain($src, 320, 200), "$dir/$name.webp", 90);
    } elseif ($kind === 'photo') {
        save_webp(fit_max($src, 1600), "$dir/$name.webp", 80);
        save_webp(fit_cover($src, 600, 450), "$dir/$name-t.webp", 76);
        if (cloud()) save_webp(fit_max($src, 800), "$dir/$name-m.webp", 78); // remote storage: every variant always exists
    } else {
        save_webp(fit_cover($src, 1600, 900), "$dir/$name.webp", 80);
        save_webp(fit_cover($src, 800, 960), "$dir/$name-m.webp", 78);
        save_webp(fit_cover($src, 720, 480), "$dir/$name-t.webp", 76);
    }
    imagedestroy($src);
    $GLOBALS['LAST_IMAGE_BYTES'] = (int)@filesize("$dir/$name.webp");
    if (cloud()) {
        if ($kind === 'logo') { copy("$dir/$name.webp", "$dir/$name-m.webp"); copy("$dir/$name.webp", "$dir/$name-t.webp"); }
        foreach (['', '-m', '-t'] as $v) cloud_push_image("$dir/$name$v.webp");
    }
    return rtrim($CONFIG['uploads_url'], '/') . "/$name.webp";
}
/** Single file field → stored path, or null when nothing was uploaded. */
function handle_image_upload(string $field, string $kind, string $prefix): ?string {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || is_array($_FILES[$field]['name'] ?? null)) return null;
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    return store_image(image_from_upload($_FILES[$field], $kind), $kind, $prefix);
}
/** Multiple file field (name="x[]") → list of stored paths. */
function handle_multi_upload(string $field, string $kind, string $prefix, int $max = 30): array {
    $out = [];
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'] ?? null)) return $out;
    foreach ($f['name'] as $i => $_) {
        if (count($out) >= $max) break;
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $one = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        $out[] = store_image(image_from_upload($one, $kind), $kind, $prefix);
    }
    return $out;
}
function exif_orient(GdImage $im, string $file): GdImage {
    $o = (int)(@exif_read_data($file)['Orientation'] ?? 1);
    return match ($o) { 3 => imagerotate($im, 180, 0), 6 => imagerotate($im, -90, 0), 8 => imagerotate($im, 90, 0), default => $im };
}
function fit_cover(GdImage $src, int $w, int $h): GdImage {
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh);
    $cw = (int)round($w / $scale); $ch = (int)round($h / $scale);
    $dst = imagecreatetruecolor($w, $h);
    imagecopyresampled($dst, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $w, $h, $cw, $ch);
    return $dst;
}
function fit_max(GdImage $src, int $max): GdImage {
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = min(1, $max / max($sw, $sh));
    $w = max(1, (int)round($sw * $scale)); $h = max(1, (int)round($sh * $scale));
    $dst = imagecreatetruecolor($w, $h);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
    return $dst;
}
function fit_contain(GdImage $src, int $w, int $h): GdImage {
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = min($w / $sw, $h / $sh, 1);
    $nw = max(1, (int)round($sw * $scale)); $nh = max(1, (int)round($sh * $scale));
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, (int)(($w - $nw) / 2), (int)(($h - $nh) / 2), 0, 0, $nw, $nh, $sw, $sh);
    return $dst;
}
function save_webp(GdImage $im, string $path, int $q): void {
    imagesavealpha($im, true);
    if (!imagewebp($im, $path, $q)) throw new RuntimeException(ta('up_fail'));
    imagedestroy($im);
    @chmod($path, 0644);
}
/** Delete an uploaded image and its variants — only inside uploads/, never bundled assets. */
function delete_upload(string $rel): void {
    global $CONFIG;
    if (!preg_match('#^/uploads/([a-z]+-[a-f0-9]{16})\.webp$#', $rel, $m)) return;
    if (cloud()) { blob_delete(array_map(fn($v) => "uploads/{$m[1]}$v.webp", ['', '-m', '-t'])); return; }
    foreach (['', '-m', '-t'] as $v) { $p = $CONFIG['uploads_dir'] . '/' . $m[1] . $v . '.webp'; if (is_file($p)) @unlink($p); }
}
/** Responsive variant of an image path (x.webp → x-m.webp / x-t.webp) if it exists. */
function img_variant(string $path, string $v): string {
    if ($path === '') return '';
    $cand = preg_replace('/\.webp$/', "-$v.webp", $path);
    if (cloud() && preg_match('#^/uploads/[a-z]+-[a-f0-9]{16}\.webp$#', $path)) return (string)$cand; // stored remotely with all variants
    return ($cand && is_file(PUBLIC_DIR . $cand)) ? $cand : $path;
}
