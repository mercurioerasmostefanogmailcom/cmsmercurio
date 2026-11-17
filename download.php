<?php
require_once __DIR__ . '/app/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT original_name,stored_name,mime_type,media_type FROM " . cms_table('media') . " WHERE id=? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$id]);
$file = $stmt->fetch();
if (!$file || $file['media_type'] !== 'document') { http_response_code(404); exit('Documento non trovato.'); }
$stored = basename($file['stored_name']);
$path = __DIR__ . '/uploads/documents/' . $stored;
if (!is_file($path)) { http_response_code(404); exit('File non disponibile.'); }
$name = str_replace(["\r", "\n", '"'], '', basename($file['original_name']));
header('Content-Type: application/octet-stream');
header('Content-Length: ' . (string)filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
