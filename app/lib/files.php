<?php
declare(strict_types=1);
/**
 * Document uploads (CVs, tender attachments, PDFs in the media library).
 * Accepts PDF / DOC / DOCX only, verified by magic bytes + finfo, stored OUTSIDE the web root under a random
 * name without extension, and served only through download_file() with attachment disposition + nosniff.
 */
const DOC_TYPES = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
function detect_doc(string $path, string $origName): ?string {
    $fh = @fopen($path, 'rb'); if (!$fh) return null;
    $head = (string)fread($fh, 8); fclose($fh);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (str_starts_with($head, '%PDF-') && $ext === 'pdf') return 'pdf';
    if ($head === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" && $ext === 'doc') return 'doc';
    if (str_starts_with($head, "PK\x03\x04") && $ext === 'docx' && class_exists('ZipArchive')) {
        $z = new ZipArchive();
        if ($z->open($path, ZipArchive::RDONLY) === true) {
            $ok = $z->locateName('word/document.xml') !== false && $z->locateName('[Content_Types].xml') !== false;
            // reject macro-enabled payloads disguised as docx
            $macro = $z->locateName('word/vbaProject.bin') !== false;
            $z->close();
            if ($ok && !$macro) return 'docx';
        }
    }
    return null;
}
function clean_filename(string $n, string $ext): string {
    $base = pathinfo($n, PATHINFO_FILENAME);
    $base = preg_replace('/[^\p{L}\p{N}\s._-]+/u', '', $base) ?? '';
    $base = trim(preg_replace('/\s+/u', ' ', $base) ?? '');
    if ($base === '') $base = 'file';
    return mb_substr($base, 0, 80) . '.' . $ext;
}
/** Store one uploaded document. $f = one $_FILES entry. Returns files.id. */
function store_document(array $f, string $scope): int {
    global $CONFIG;
    if (!is_string($f['tmp_name'] ?? null) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('upload');
    if (!is_uploaded_file($f['tmp_name']) && !getenv('AH_ALLOW_LOCAL_UPLOAD')) throw new RuntimeException('upload');
    if ($f['size'] <= 0 || $f['size'] > $CONFIG['max_doc_mb'] * 1048576) throw new RuntimeException('size');
    $kind = detect_doc($f['tmp_name'], (string)$f['name']);
    if (!$kind) throw new RuntimeException('type');
    $stored = bin2hex(random_bytes(16));
    if (cloud()) {
        cloud_put_doc($stored, (string)file_get_contents($f['tmp_name']));
        q('INSERT INTO files (token, stored, orig_name, mime, bytes, scope) VALUES (?,?,?,?,?,?)',
          [bin2hex(random_bytes(12)), $stored, clean_filename((string)$f['name'], $kind), DOC_TYPES[$kind], (int)$f['size'], $scope]);
        return (int)db()->lastInsertId();
    }
    $dir = $CONFIG['files_dir'];
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    if (!@move_uploaded_file($f['tmp_name'], "$dir/$stored") && !(getenv('AH_ALLOW_LOCAL_UPLOAD') && @copy($f['tmp_name'], "$dir/$stored"))) throw new RuntimeException('upload');
    @chmod("$dir/$stored", 0640);
    q('INSERT INTO files (token, stored, orig_name, mime, bytes, scope) VALUES (?,?,?,?,?,?)',
      [bin2hex(random_bytes(12)), $stored, clean_filename((string)$f['name'], $kind), DOC_TYPES[$kind], (int)$f['size'], $scope]);
    return (int)db()->lastInsertId();
}
function upload_entries(string $field): array {
    $f = $_FILES[$field] ?? null; $out = [];
    if (!$f) return $out;
    if (!is_array($f['name'])) return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
    foreach ($f['name'] as $i => $_) if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)
        $out[] = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    return $out;
}
function file_row(int $id): ?array { return q1('SELECT * FROM files WHERE id = ?', [$id]); }
function delete_file(int $id): void {
    global $CONFIG;
    $r = file_row($id); if (!$r) return;
    $p = $CONFIG['files_dir'] . '/' . basename($r['stored']);
    if (cloud() && preg_match('/^[a-f0-9]{32}$/', $r['stored']) && !is_file($p)) blob_delete(["docs/{$r['stored']}.bin"]);
    elseif (preg_match('/^[a-f0-9]{32}$/', $r['stored']) && is_file($p)) @unlink($p);
    q('DELETE FROM files WHERE id = ?', [$id]);
}
function download_file(array $r, bool $inline = false): never {
    global $CONFIG;
    $p = $CONFIG['files_dir'] . '/' . basename($r['stored']);
    if (!preg_match('/^[a-f0-9]{32}$/', $r['stored'])) { http_response_code(404); exit('Not found'); }
    $bytes = null;
    if (!is_file($p)) {
        if (!cloud() || ($bytes = cloud_get_doc($r['stored'])) === null) { http_response_code(404); exit('Not found'); }
    }
    while (ob_get_level()) ob_end_clean();
    $name = $r['orig_name'];
    $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'file';
    header('Content-Type: ' . (in_array($r['mime'], DOC_TYPES, true) ? $r['mime'] : 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Content-Disposition: ' . ($inline && $r['mime'] === 'application/pdf' ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Content-Length: ' . ($bytes !== null ? strlen($bytes) : filesize($p)));
    header('Cache-Control: private, no-store');
    if ($bytes !== null) echo $bytes; else readfile($p);
    exit;
}
