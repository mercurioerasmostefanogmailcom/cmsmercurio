<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/zip-reader.php';
if (!defined('CMS_VERSION')) {
    $versionFile = dirname(__DIR__) . '/VERSION';
    $legacyVersion = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '0.9.9';
    define('CMS_VERSION', $legacyVersion !== '' ? $legacyVersion : '0.9.9');
}
require_admin();
if (($_SESSION['admin_role'] ?? '') !== 'superadmin') { http_response_code(403); exit('Solo l’amministratore principale può aggiornare MercurioCMS.'); }

$root = realpath(dirname(__DIR__));
$errors = [];
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'update-backups';
$maxUpload = 52428800; // 50 MB

function cms_update_remove_tree(string $dir): void {
    if (!is_dir($dir) || is_link($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_link($path) || is_file($path)) @unlink($path);
        elseif (is_dir($path)) cms_update_remove_tree($path);
    }
    @rmdir($dir);
}
function cms_update_valid_path(string $path): bool {
    if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) return false;
    foreach (explode('/', $path) as $part) if ($part === '' || $part === '.' || $part === '..') return false;
    $lower = strtolower($path);
    foreach (['config.php','uploads','storage','plugins','.git'] as $blocked) {
        if ($lower === $blocked || str_starts_with($lower, $blocked . '/')) return false;
    }
    return true;
}
function cms_update_target_safe(string $root, string $relative): bool {
    // Consente directory intermedie non ancora presenti, ma verifica ogni
    // componente esistente e l'ultimo antenato reale contro symlink/path traversal.
    if (!cms_update_valid_path($relative)) return false;
    $realRoot = realpath($root);
    if ($realRoot === false) return false;
    $parts = explode('/', $relative);
    $last = array_pop($parts);
    $current = $realRoot;
    foreach ($parts as $part) {
        $current .= DIRECTORY_SEPARATOR . $part;
        if (is_link($current)) return false;
        if (file_exists($current) && !is_dir($current)) return false;
    }
    $target = $current . DIRECTORY_SEPARATOR . $last;
    if (is_link($target) || (file_exists($target) && !is_file($target))) return false;
    // Risale fino al primo antenato esistente: realpath() non risolve
    // directory nuove, ma l'antenato deve comunque restare dentro l'installazione.
    $ancestor = $current;
    while (!file_exists($ancestor)) {
        $parent = dirname($ancestor);
        if ($parent === $ancestor) return false;
        $ancestor = $parent;
    }
    if (is_link($ancestor) || !is_dir($ancestor)) return false;
    $realAncestor = realpath($ancestor);
    return $realAncestor !== false && ($realAncestor === $realRoot || str_starts_with($realAncestor, $realRoot . DIRECTORY_SEPARATOR));
}
function cms_update_db_backup(PDO $pdo, string $file, string $prefix): void {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
    $out = "-- Backup database MercurioCMS\n-- Data: " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
    if (file_put_contents($file, '', LOCK_EX) === false) throw new RuntimeException('Impossibile iniziare il backup del database.');
    foreach ($tables as $row) {
        $table = (string)$row[0];
        if (!str_starts_with($table, $prefix)) continue;
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM);
        if (!$create || !isset($create[1])) throw new RuntimeException('Impossibile leggere la struttura della tabella ' . $table . '.');
        $out .= "\nDROP TABLE IF EXISTS $quoted;\n" . $create[1] . ";\n";
        $stmt = $pdo->query('SELECT * FROM ' . $quoted);
        while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(static fn($c) => '`' . str_replace('`', '``', (string)$c) . '`', array_keys($record));
            $values = [];
            foreach ($record as $value) {
                if ($value === null) $values[] = 'NULL';
                elseif (is_int($value) || is_float($value)) $values[] = (string)$value;
                else $values[] = $pdo->quote((string)$value);
            }
            $out .= 'INSERT INTO ' . $quoted . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
            if (strlen($out) > 8 * 1024 * 1024) { if (file_put_contents($file, $out, FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('Errore durante la scrittura del backup database.'); $out = ''; }
        }
    }
    $out .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
    if (file_put_contents($file, $out, FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('Impossibile salvare il backup del database.');
}
function cms_update_copy_tree(string $source, string $destination, string $exclude): void {
    if (!is_dir($destination) && !mkdir($destination, 0755, true)) throw new RuntimeException('Impossibile creare la cartella di backup.');
    $items = scandir($source);
    if ($items === false) throw new RuntimeException('Impossibile leggere la cartella dell’installazione.');
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $src = $source . DIRECTORY_SEPARATOR . $item;
        $dst = $destination . DIRECTORY_SEPARATOR . $item;
        if ($src === $exclude || is_link($src)) continue;
        if (is_dir($src)) cms_update_copy_tree($src, $dst, $exclude);
        elseif (is_file($src) && !copy($src, $dst)) throw new RuntimeException('Backup non riuscito per il file ' . $item . '.');
    }
}

function cms_backup_manifest_create(string $backupDir): void {
    $files = [];
    $base = $backupDir . DIRECTORY_SEPARATOR . 'files';
    $walk = static function(string $dir, string $relative = '') use (&$walk, &$files, $base): void {
        $items = scandir($dir);
        if ($items === false) throw new RuntimeException('Impossibile verificare i file del backup.');
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $rel = $relative === '' ? $item : $relative . '/' . $item;
            if (is_link($path)) throw new RuntimeException('Il backup contiene un collegamento simbolico non consentito: ' . $rel . '.');
            if (is_dir($path)) $walk($path, $rel);
            elseif (is_file($path)) {
                $hash = hash_file('sha256', $path);
                if (!is_string($hash)) throw new RuntimeException('Impossibile verificare il file ' . $rel . '.');
                $files[$rel] = $hash;
            }
        }
    };
    if (!is_dir($base)) throw new RuntimeException('Cartella file del backup assente.');
    $walk($base);
    $db = $backupDir . DIRECTORY_SEPARATOR . 'database.sql';
    $dbHash = is_file($db) ? hash_file('sha256', $db) : false;
    if (!is_string($dbHash) || !$files) throw new RuntimeException('Backup incompleto: impossibile creare il manifesto.');
    $manifest = ['format'=>1, 'created_at'=>date('c'), 'database_sha256'=>$dbHash, 'files'=>$files];
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || file_put_contents($backupDir . '/BACKUP-MANIFEST.json', $json . "\n", LOCK_EX) === false) throw new RuntimeException('Impossibile salvare il manifesto di integrità del backup.');
}
function cms_backup_manifest_verify(string $backupDir): array {
    $manifestPath = $backupDir . '/BACKUP-MANIFEST.json';
    $raw = @file_get_contents($manifestPath);
    $manifest = is_string($raw) ? json_decode($raw, true) : null;
    // Compatibilità con backup RC precedenti: non dispongono di hash storici,
    // quindi si controllano struttura, assenza di symlink e presenza di tabelle SQL.
    if (!is_file($manifestPath)) {
        $base = $backupDir . DIRECTORY_SEPARATOR . 'files';
        $db = $backupDir . DIRECTORY_SEPARATOR . 'database.sql';
        if (!is_dir($base) || !is_file($db) || !is_readable($db)) throw new RuntimeException('Backup precedente incompleto.');
        $count = 0;
        $legacyScan = static function(string $dir) use (&$legacyScan, &$count): void {
            $items = scandir($dir);
            if ($items === false) throw new RuntimeException('Impossibile leggere il backup precedente.');
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_link($path)) throw new RuntimeException('Il backup precedente contiene un collegamento simbolico non consentito.');
                if (is_dir($path)) $legacyScan($path);
                elseif (is_file($path)) $count++;
            }
        };
        $legacyScan($base);
        $sqlHandle = @fopen($db, 'rb');
        $hasTables = false;
        if ($sqlHandle) {
            while (($line = fgets($sqlHandle)) !== false) if (preg_match('/DROP\s+TABLE\s+IF\s+EXISTS\s+`/i', $line)) { $hasTables = true; break; }
            fclose($sqlHandle);
        }
        if ($count < 1 || !$hasTables) throw new RuntimeException('Backup precedente non valido o privo di tabelle database.');
        return ['legacy'=>true, 'files_count'=>$count];
    }
    if (!is_array($manifest) || ($manifest['format'] ?? null) !== 1 || !is_array($manifest['files'] ?? null) || !preg_match('/^[a-f0-9]{64}$/i', (string)($manifest['database_sha256'] ?? ''))) throw new RuntimeException('Manifest di integrità assente o non valido. Il ripristino è stato annullato.');
    $base = $backupDir . DIRECTORY_SEPARATOR . 'files';
    $expected = [];
    foreach ($manifest['files'] as $relative => $hash) {
        if (!is_string($relative) || $relative === '' || str_contains($relative, '\\') || str_contains($relative, "\0") || str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:/', $relative) || preg_match('~(^|/)\.\.?(/|$)~', $relative) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/i', $hash)) throw new RuntimeException('Manifest del backup non valido.');
        $file = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_link($file) || !is_file($file)) throw new RuntimeException('File mancante nel backup: ' . $relative . '.');
        $actual = hash_file('sha256', $file);
        if (!is_string($actual) || !hash_equals(strtolower($hash), strtolower($actual))) throw new RuntimeException('Integrità non verificata per il file ' . $relative . '.');
        $expected[$relative] = true;
    }
    $actualFiles = [];
    $scan = static function(string $dir, string $relative = '') use (&$scan, &$actualFiles): void {
        $items = scandir($dir);
        if ($items === false) throw new RuntimeException('Impossibile verificare l’inventario del backup.');
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $rel = $relative === '' ? $item : $relative . '/' . $item;
            if (is_link($path)) throw new RuntimeException('Il backup contiene un collegamento simbolico non consentito.');
            if (is_dir($path)) $scan($path, $rel);
            elseif (is_file($path)) $actualFiles[$rel] = true;
        }
    };
    $scan($base);
    if (count($actualFiles) !== count($expected) || array_diff_key($actualFiles, $expected)) throw new RuntimeException('L’inventario dei file del backup non coincide con il manifesto.');
    $db = $backupDir . DIRECTORY_SEPARATOR . 'database.sql';
    $actualDb = is_file($db) ? hash_file('sha256', $db) : false;
    if (!is_string($actualDb) || !hash_equals(strtolower($manifest['database_sha256']), strtolower($actualDb))) throw new RuntimeException('Integrità del database non verificata.');
    if (!$expected) throw new RuntimeException('Il backup non contiene file ripristinabili.');
    return $manifest;
}
function cms_restore_remove_stale_files(string $currentDir, string $backupFilesDir, string $relative = ''): void {
    $items = scandir($currentDir);
    if ($items === false) throw new RuntimeException('Impossibile controllare i file presenti prima del ripristino.');
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $rel = $relative === '' ? $item : $relative . '/' . $item;
        if (($relative === '' && in_array(strtolower($item), ['config.php','uploads','storage','plugins'], true)) || $rel === 'admin/updates.php') continue;
        $current = $currentDir . DIRECTORY_SEPARATOR . $item;
        $saved = $backupFilesDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_link($current)) continue;
        if (is_dir($current)) {
            if (!is_dir($saved)) cms_update_remove_tree($current);
            else cms_restore_remove_stale_files($current, $backupFilesDir, $rel);
        } elseif (is_file($current) && !is_file($saved)) {
            if (!@unlink($current)) throw new RuntimeException('Impossibile rimuovere il file non presente nel punto di ripristino: ' . $rel . '.');
        }
    }
}

function cms_restore_database(PDO $pdo, string $file, string $prefix): void {
    if (!is_file($file) || !is_readable($file)) throw new RuntimeException('Backup database assente o non leggibile.');
    $scanHandle = @fopen($file, 'rb');
    if (!$scanHandle) throw new RuntimeException('Backup database vuoto o non leggibile.');
    $expectedTables = [];
    try {
        while (($line = fgets($scanHandle)) !== false) {
            if (preg_match('/DROP\s+TABLE\s+IF\s+EXISTS\s+`((?:``|[^`])+)`\s*;/i', $line, $match)) $expectedTables[str_replace('``', '`', $match[1])] = true;
        }
    } finally { fclose($scanHandle); }
    if (!$expectedTables) throw new RuntimeException('Il backup database non contiene tabelle riconoscibili.');
    if ($prefix !== '') {
        $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($existing as $row) {
                $tableName = (string)$row[0];
                if (str_starts_with($tableName, $prefix) && !isset($expectedTables[$tableName])) {
                    $quoted = '`' . str_replace('`', '``', $tableName) . '`';
                    $pdo->exec('DROP TABLE ' . $quoted);
                }
            }
        } finally { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); }
    }
    $handle = fopen($file, 'rb');
    if (!$handle) throw new RuntimeException('Impossibile aprire il backup database.');
    $statement = ''; $quote = null; $escaped = false; $lineStart = true; $commentLine = false;
    try {
        while (($ch = fgetc($handle)) !== false) {
            if ($commentLine) {
                if ($ch === "\n") { $commentLine = false; $lineStart = true; }
                continue;
            }
            if ($quote !== null) {
                $statement .= $ch;
                if ($escaped) { $escaped = false; continue; }
                if ($ch === "\\") { $escaped = true; continue; }
                if ($ch === $quote) {
                    $next = fgetc($handle);
                    if ($next === $quote) { $statement .= $next; continue; }
                    $quote = null;
                    if ($next !== false) { if ($next === ';') { $sql = trim($statement); if ($sql !== '') $pdo->exec($sql); $statement = ''; } else $statement .= $next; }
                }
                continue;
            }
            if ($lineStart && ($ch === '-' || $ch === '#')) {
                if ($ch === '-') {
                    $next = fgetc($handle);
                    if ($next === '-') { $commentLine = true; continue; }
                    $statement .= $ch . ($next === false ? '' : $next);
                    $lineStart = false;
                    continue;
                }
                $commentLine = true; continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; $statement .= $ch; $lineStart = false; continue; }
            if ($ch === ';') {
                $sql = trim($statement);
                if ($sql !== '') $pdo->exec($sql);
                $statement = ''; $lineStart = false; continue;
            }
            $statement .= $ch;
            if ($ch === "\n") $lineStart = true; elseif (!ctype_space($ch)) $lineStart = false;
        }
        $sql = trim($statement);
        if ($sql !== '') $pdo->exec($sql);
    } finally { fclose($handle); }
}
function cms_restore_copy_tree(string $source, string $root, string $relative = ''): void {
    $items = scandir($source);
    if ($items === false) throw new RuntimeException('Impossibile leggere i file del backup.');
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $rel = $relative === '' ? $item : $relative . '/' . $item;
        if (($relative === '' && in_array(strtolower($item), ['config.php','uploads','storage','plugins'], true)) || $rel === 'admin/updates.php') continue;
        $src = $source . DIRECTORY_SEPARATOR . $item;
        if (is_link($src)) continue;
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!cms_update_valid_path($rel)) throw new RuntimeException('Percorso non valido nel backup: ' . $rel . '.');
        if (is_dir($src)) {
            if (is_file($target)) @unlink($target);
            if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) throw new RuntimeException('Impossibile ripristinare la cartella ' . $rel . '.');
            if (is_link($target)) throw new RuntimeException('Percorso di ripristino non sicuro: ' . $rel . '.');
            cms_restore_copy_tree($src, $root, $rel);
        } elseif (is_file($src)) {
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0755, true)) throw new RuntimeException('Impossibile creare la cartella per ' . $rel . '.');
            if (is_link($target)) throw new RuntimeException('Percorso di ripristino non sicuro: ' . $rel . '.');
            if (is_dir($target)) cms_update_remove_tree($target);
            $tmp = $target . '.restore-' . bin2hex(random_bytes(3));
            if (!copy($src, $tmp) || !rename($tmp, $target)) { @unlink($tmp); throw new RuntimeException('Ripristino non riuscito per il file ' . $rel . '.'); }
        }
    }
}
function cms_restore_backup_version(string $backupDir): string {
    $info = @file_get_contents($backupDir . '/BACKUP-INFO.txt');
    if (!is_string($info)) return 'versione non identificata';
    if (preg_match('/Versione (?:precedente|salvata):\s*([^\r\n]+)/i', $info, $m)) return trim($m[1]);
    return 'versione non identificata';
}
function cms_backup_size(string $dir): int {
    if (!is_dir($dir) || is_link($dir)) return 0;
    $total = 0;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_link($path)) continue;
        if (is_dir($path)) $total += cms_backup_size($path);
        elseif (is_file($path)) $total += (int)@filesize($path);
    }
    return $total;
}
function cms_format_bytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float)$bytes; $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) { $value /= 1024; $unit++; }
    return ($unit === 0 ? (string)$bytes : number_format($value, 2, ',', '.')) . ' ' . $units[$unit];
}
function cms_backup_is_protected(string $dir): bool {
    return is_file($dir . DIRECTORY_SEPARATOR . '.PROTECTED');
}
function cms_backup_kind(string $id): string {
    return str_ends_with($id, '-restore-safety') ? 'safety' : 'automatic';
}
function cms_backup_valid_id(string $id): bool {
    return (bool)preg_match('/^\d{8}-\d{6}(?:-restore-safety)?$/', $id);
}
function cms_backup_create_directory(string $backupRoot, string $kind = 'automatic'): array {
    if (!is_dir($backupRoot) && !mkdir($backupRoot, 0755, true) && !is_dir($backupRoot)) {
        throw new RuntimeException('Impossibile creare la cartella dei backup.');
    }
    if (!is_writable($backupRoot)) throw new RuntimeException('La cartella dei backup non è scrivibile.');
    // mkdir è atomico: due richieste simultanee non possono ottenere lo stesso identificativo.
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $id = date('Ymd-His') . ($kind === 'safety' ? '-restore-safety' : '');
        $dir = $backupRoot . DIRECTORY_SEPARATOR . $id;
        if (@mkdir($dir, 0755)) return [$id, $dir];
        usleep(150000);
    }
    throw new RuntimeException('Impossibile generare un identificativo univoco per il backup. Riprova tra qualche secondo.');
}
function cms_backup_protected_count(string $backupRoot): int {
    $count = 0;
    if (!is_dir($backupRoot)) return 0;
    foreach (array_diff(scandir($backupRoot) ?: [], ['.','..']) as $id) {
        $dir = $backupRoot . DIRECTORY_SEPARATOR . $id;
        if (is_dir($dir) && !is_link($dir) && cms_backup_valid_id($id) && cms_backup_is_protected($dir)) $count++;
    }
    return $count;
}
function cms_backup_delete_from(string $backupRoot, string $cutoffId): array {
    if (!cms_backup_valid_id($cutoffId)) throw new RuntimeException('Punto di partenza non valido.');
    if (!is_dir($backupRoot) || !is_writable($backupRoot)) throw new RuntimeException('La cartella dei backup non è disponibile o non è scrivibile.');
    $cutoffStamp = substr($cutoffId, 0, 15);
    $removed = []; $protected = []; $failed = []; $freed = 0;
    $realRoot = realpath($backupRoot);
    if ($realRoot === false) throw new RuntimeException('Percorso della cartella backup non valido.');
    foreach (array_diff(scandir($backupRoot) ?: [], ['.','..']) as $id) {
        if (!cms_backup_valid_id($id) || substr($id, 0, 15) > $cutoffStamp) continue;
        $target = $backupRoot . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($target) || is_link($target)) continue;
        $realTarget = realpath($target);
        if ($realTarget === false || !str_starts_with($realTarget, $realRoot . DIRECTORY_SEPARATOR)) { $failed[] = $id; continue; }
        if (cms_backup_is_protected($realTarget)) { $protected[] = $id; continue; }
        $size = cms_backup_size($realTarget);
        cms_update_remove_tree($realTarget);
        if (!file_exists($realTarget)) { $removed[] = $id; $freed += $size; }
        else $failed[] = $id;
    }
    return ['removed'=>$removed, 'protected'=>$protected, 'failed'=>$failed, 'freed'=>$freed];
}
function cms_backup_ensure_protected_anchor(string $backupRoot): ?string {
    if (cms_backup_protected_count($backupRoot) > 0 || !is_dir($backupRoot)) return null;
    $ids = array_values(array_filter(array_diff(scandir($backupRoot) ?: [], ['.','..']), static function($id) use ($backupRoot) {
        $dir = $backupRoot . DIRECTORY_SEPARATOR . $id;
        return cms_backup_valid_id((string)$id) && is_dir($dir) && !is_link($dir)
            && is_dir($dir . '/files') && is_file($dir . '/database.sql') && is_file($dir . '/BACKUP-INFO.txt');
    }));
    rsort($ids);
    foreach ($ids as $id) {
        $dir = $backupRoot . DIRECTORY_SEPARATOR . $id;
        try { cms_backup_manifest_verify($dir); }
        catch (Throwable $ignored) { continue; }
        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . '.PROTECTED', 'Punto più recente protetto automaticamente per conservare almeno un ripristino il ' . date('c') . "\n", LOCK_EX) !== false) return $id;
    }
    throw new RuntimeException('Non esiste un backup completo e verificabile da conservare come ultimo punto protetto.');
}
function cms_backup_cleanup(string $backupRoot, int $keepAutomatic = 5, int $safetyDays = 7): array {
    $automatic = []; $removed = []; $freed = 0; $now = time();
    foreach (array_diff(scandir($backupRoot) ?: [], ['.','..']) as $id) {
        $dir = $backupRoot . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($dir) || is_link($dir) || !cms_backup_valid_id($id) || cms_backup_is_protected($dir)) continue;
        if (cms_backup_kind($id) === 'safety') {
            if (preg_match('/^(\d{8})-(\d{6})-restore-safety$/', $id, $m)) {
                $stamp = DateTime::createFromFormat('!Ymd-His', $m[1] . '-' . $m[2]);
                if ($stamp && ($now - $stamp->getTimestamp()) > $safetyDays * 86400) {
                    $size = cms_backup_size($dir); cms_update_remove_tree($dir);
                    if (!is_dir($dir)) { $removed[] = $id; $freed += $size; }
                }
            }
        } else {
            $automatic[] = $id;
        }
    }
    rsort($automatic);
    foreach (array_slice($automatic, $keepAutomatic) as $id) {
        $dir = $backupRoot . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($dir) || cms_backup_is_protected($dir)) continue;
        $size = cms_backup_size($dir); cms_update_remove_tree($dir);
        if (!is_dir($dir)) { $removed[] = $id; $freed += $size; }
    }
    return ['removed' => $removed, 'freed' => $freed];
}
function cms_backup_cleanup_preview(string $backupRoot, int $keepAutomatic = 5, int $safetyDays = 7): array {
    $automatic = []; $removable = []; $bytes = 0; $now = time();
    if (!is_dir($backupRoot)) return ['count'=>0, 'bytes'=>0];
    foreach (array_diff(scandir($backupRoot) ?: [], ['.','..']) as $id) {
        $dir = $backupRoot . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($dir) || is_link($dir) || !cms_backup_valid_id($id) || cms_backup_is_protected($dir)) continue;
        if (cms_backup_kind($id) === 'safety') {
            if (preg_match('/^(\d{8})-(\d{6})-restore-safety$/', $id, $m)) {
                $stamp = DateTime::createFromFormat('!Ymd-His', $m[1] . '-' . $m[2]);
                if ($stamp && ($now - $stamp->getTimestamp()) > $safetyDays * 86400) $removable[$id] = true;
            }
        } else $automatic[] = $id;
    }
    rsort($automatic);
    foreach (array_slice($automatic, $keepAutomatic) as $id) $removable[$id] = true;
    foreach (array_keys($removable) as $id) $bytes += cms_backup_size($backupRoot . DIRECTORY_SEPARATOR . $id);
    return ['count'=>count($removable), 'bytes'=>$bytes];
}
function cms_backup_summary(string $backupRoot): array {
    $summary = ['automatic'=>['count'=>0,'bytes'=>0], 'protected'=>['count'=>0,'bytes'=>0], 'safety'=>['count'=>0,'bytes'=>0], 'total'=>['count'=>0,'bytes'=>0]];
    if (!is_dir($backupRoot)) return $summary;
    foreach (array_diff(scandir($backupRoot) ?: [], ['.','..']) as $id) {
        $dir = $backupRoot . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($dir) || is_link($dir) || !cms_backup_valid_id($id)) continue;
        $size = cms_backup_size($dir); $key = cms_backup_is_protected($dir) ? 'protected' : (cms_backup_kind($id) === 'safety' ? 'safety' : 'automatic');
        $summary[$key]['count']++; $summary[$key]['bytes'] += $size;
        $summary['total']['count']++; $summary['total']['bytes'] += $size;
    }
    return $summary;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'create_restore_point') {
        $typed = (string)($_POST['confirm_text'] ?? '');
        if ($typed !== 'CREA PUNTO') $errors[] = 'Conferma non valida. Digita esattamente CREA PUNTO.';
        elseif (!is_writable($root) || !is_writable($root . '/storage')) $errors[] = 'Permessi insufficienti per creare un punto di ripristino.';
        else {
            $pointDir = ''; $pointComplete = false;
            try {
                [$pointId, $pointDir] = cms_backup_create_directory($backupRoot, 'automatic');
                cms_update_copy_tree($root, $pointDir . '/files', $backupRoot);
                cms_update_db_backup($pdo, $pointDir . '/database.sql', $config['table_prefix']);
                if (file_put_contents($pointDir . '/BACKUP-INFO.txt', "Punto di ripristino creato manualmente\nVersione salvata: " . CMS_VERSION . "\nData: " . date('c') . "\nCopia completa di file e database.\n", LOCK_EX) === false) throw new RuntimeException('Impossibile salvare le informazioni del punto di ripristino.');
                cms_backup_manifest_create($pointDir);
                cms_backup_manifest_verify($pointDir);
                if (file_put_contents($pointDir . '/.PROTECTED', 'Punto di ripristino protetto manualmente il ' . date('c') . "\n", LOCK_EX) === false) throw new RuntimeException('Impossibile proteggere il punto di ripristino creato.');
                $pointComplete = true;
                $_SESSION['flash'] = 'Punto di ripristino ' . $pointId . ' creato, verificato e protetto. Non verrà eliminato dalla pulizia automatica.';
                header('Location: updates.php?point_created=1'); exit;
            } catch (Throwable $pointError) {
                if (!$pointComplete && $pointDir !== '' && is_dir($pointDir)) cms_update_remove_tree($pointDir);
                $errors[] = 'Creazione del punto di ripristino non completata: ' . $pointError->getMessage();
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete_backups_from') {
        $cutoffId = (string)($_POST['cutoff_id'] ?? '');
        $typed = (string)($_POST['confirm_text'] ?? '');
        if (!cms_backup_valid_id($cutoffId) || $typed !== 'ELIMINA PRECEDENTI') $errors[] = 'Conferma non valida. Seleziona un punto e digita esattamente ELIMINA PRECEDENTI.';
        else {
            try {
                $newAnchor = cms_backup_ensure_protected_anchor($backupRoot);
                $result = cms_backup_delete_from($backupRoot, $cutoffId);
                $_SESSION['flash'] = 'Pulizia dal punto ' . $cutoffId . ': eliminati ' . count($result['removed']) . ' backup (' . cms_format_bytes($result['freed']) . '). Conservati ' . count($result['protected']) . ' punti protetti.' . ($newAnchor ? ' Il backup più recente (' . $newAnchor . ') è stato protetto automaticamente per mantenere almeno un ripristino.' : '') . (count($result['failed']) ? ' Non è stato possibile eliminare ' . count($result['failed']) . ' elementi.' : '');
                header('Location: updates.php?bulk_deleted=1'); exit;
            } catch (Throwable $bulkError) { $errors[] = 'Eliminazione multipla non completata: ' . $bulkError->getMessage(); }
        }
    } elseif (($_POST['action'] ?? '') === 'delete_backup') {
        $backupId = (string)($_POST['backup_id'] ?? '');
        $typed = (string)($_POST['confirm_text'] ?? '');
        if (!cms_backup_valid_id($backupId) || $typed !== 'ELIMINA') {
            $errors[] = 'Conferma non valida. Digita esattamente ELIMINA per cancellare il backup.';
        } else {
            try { $newAnchor = cms_backup_ensure_protected_anchor($backupRoot); }
            catch (Throwable $anchorError) { $newAnchor = null; $errors[] = $anchorError->getMessage(); }
            $target = $backupRoot . DIRECTORY_SEPARATOR . $backupId;
            $realRoot = realpath($backupRoot); $realTarget = realpath($target);
            if ($errors) {
                // L'ancora di ripristino non è stata verificata: nessuna cancellazione viene eseguita.
            } elseif (!$realRoot || !$realTarget || !str_starts_with($realTarget, $realRoot . DIRECTORY_SEPARATOR) || !is_dir($realTarget) || is_link($target)) {
                $errors[] = 'Backup non trovato o percorso non sicuro. Nessun file è stato eliminato.';
            } elseif (cms_backup_is_protected($realTarget)) {
                $errors[] = 'Questo punto di ripristino è protetto. Rimuovi prima la protezione per poterlo eliminare.';
            } elseif (!is_writable($backupRoot)) {
                $errors[] = 'La cartella dei backup non è scrivibile.';
            } else {
                cms_update_remove_tree($realTarget);
                if (!file_exists($realTarget)) { $_SESSION['flash'] = 'Backup ' . $backupId . ' eliminato.' . ($newAnchor ? ' Il backup più recente (' . $newAnchor . ') è stato protetto automaticamente per conservare almeno un ripristino.' : ''); header('Location: updates.php?deleted=1'); exit; }
                $errors[] = 'Non è stato possibile eliminare completamente il backup selezionato.';
            }
        }
    } elseif (in_array(($_POST['action'] ?? ''), ['protect_backup','unprotect_backup'], true)) {
        $backupId = (string)($_POST['backup_id'] ?? '');
        if (!cms_backup_valid_id($backupId)) $errors[] = 'Punto di ripristino non valido.';
        else {
            $target = $backupRoot . DIRECTORY_SEPARATOR . $backupId;
            $realRoot = realpath($backupRoot); $realTarget = realpath($target);
            if (!$realRoot || !$realTarget || !str_starts_with($realTarget, $realRoot . DIRECTORY_SEPARATOR) || !is_dir($realTarget) || is_link($target)) $errors[] = 'Backup non trovato o percorso non sicuro.';
            elseif (($_POST['action'] ?? '') === 'protect_backup') {
                try {
                    if (!is_dir($realTarget . '/files') || !is_file($realTarget . '/database.sql') || !is_file($realTarget . '/BACKUP-INFO.txt')) throw new RuntimeException('Il backup non è completo e non può diventare un punto protetto.');
                    cms_backup_manifest_verify($realTarget);
                    if (@file_put_contents($realTarget . DIRECTORY_SEPARATOR . '.PROTECTED', 'Protetto manualmente il ' . date('c') . "\n", LOCK_EX) === false) throw new RuntimeException('Impossibile proteggere il backup.');
                    $_SESSION['flash'] = 'Punto di ripristino protetto e verificato: non verrà eliminato automaticamente.'; header('Location: updates.php?protected=1'); exit;
                } catch (Throwable $protectError) { $errors[] = 'Protezione non applicata: ' . $protectError->getMessage(); }
            } else {
                if (cms_backup_protected_count($backupRoot) <= 1) $errors[] = 'Non puoi rimuovere la protezione dall’ultimo punto di ripristino protetto. Proteggi prima un’altra versione funzionante.';
                elseif (!is_file($realTarget . DIRECTORY_SEPARATOR . '.PROTECTED') || !@unlink($realTarget . DIRECTORY_SEPARATOR . '.PROTECTED')) $errors[] = 'Impossibile rimuovere la protezione.';
                else { $_SESSION['flash'] = 'Protezione rimossa. Il backup resterà disponibile fino alla prossima pulizia o eliminazione manuale.'; header('Location: updates.php?unprotected=1'); exit; }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'cleanup_backups') {
        if (!is_dir($backupRoot)) { $_SESSION['flash'] = 'Non ci sono backup da pulire.'; header('Location: updates.php?cleaned=1'); exit; }
        $result = cms_backup_cleanup($backupRoot, 5, 7);
        $_SESSION['flash'] = 'Pulizia completata: eliminati ' . count($result['removed']) . ' backup, recuperati ' . cms_format_bytes($result['freed']) . '. I backup protetti sono stati conservati.';
        header('Location: updates.php?cleaned=1'); exit;
    } elseif (($_POST['action'] ?? '') === 'restore_backup') {
        $backupId = (string)($_POST['backup_id'] ?? '');
        $typed = (string)($_POST['confirm_text'] ?? '');
        if (!cms_backup_valid_id($backupId) || $typed !== 'RIPRISTINA') {
            $errors[] = 'Conferma non valida. Seleziona un backup e digita esattamente RIPRISTINA.';
        } else {
            $backupDir = $backupRoot . '/' . $backupId;
            $realBackupRoot = realpath($backupRoot);
            $realBackupDir = realpath($backupDir);
            if (!$realBackupRoot || !$realBackupDir || !str_starts_with($realBackupDir, $realBackupRoot . DIRECTORY_SEPARATOR) || !is_dir($backupDir . '/files') || !is_file($backupDir . '/database.sql') || !is_file($backupDir . '/BACKUP-INFO.txt')) {
                $errors[] = 'Il backup selezionato è incompleto o non valido. Nessun file è stato modificato.';
            } elseif (!is_writable($root) || !is_writable($root . '/storage')) {
                $errors[] = 'Permessi insufficienti per eseguire un ripristino sicuro.';
            } else {
                try { $restoreManifest = cms_backup_manifest_verify($realBackupDir); }
                catch (Throwable $verifyError) { $errors[] = 'Backup non verificato: ' . $verifyError->getMessage(); }
                if (!$errors) {
                $safetyId = ''; $safetyDir = ''; $safetyMade = false;
                try {
                    [$safetyId, $safetyDir] = cms_backup_create_directory($backupRoot, 'safety');
                    cms_update_copy_tree($root, $safetyDir . '/files', $backupRoot);
                    cms_update_db_backup($pdo, $safetyDir . '/database.sql', $config['table_prefix']);
                    if (file_put_contents($safetyDir . '/BACKUP-INFO.txt', "Backup di sicurezza prima del ripristino di $backupId\nVersione precedente: " . CMS_VERSION . "\nData: " . date('c') . "\n", LOCK_EX) === false) throw new RuntimeException('Impossibile salvare le informazioni del backup di sicurezza.');
                    cms_backup_manifest_create($safetyDir);
                    cms_backup_manifest_verify($safetyDir);
                    $safetyMade = true;
                    cms_restore_remove_stale_files($root, $backupDir . '/files');
                    cms_restore_copy_tree($backupDir . '/files', $root);
                    cms_restore_database($pdo, $backupDir . '/database.sql', (string)$config['table_prefix']);
                    // La protezione è obbligatoria: se non è scrivibile, il ripristino viene annullato.
                    if (!is_file($backupDir . '/.PROTECTED') && @file_put_contents($backupDir . '/.PROTECTED', 'Protetto automaticamente dopo il ripristino ' . date('c') . "\n", LOCK_EX) === false) throw new RuntimeException('Ripristino eseguito ma impossibile proteggere il punto selezionato; avvio del recupero di sicurezza.');
                    cms_backup_cleanup($backupRoot, 5, 7);
                    $_SESSION['flash'] = 'Ripristino completato. È stata ripristinata la versione ' . cms_restore_backup_version($backupDir) . '. Il backup di sicurezza della situazione precedente è conservato in storage/update-backups/' . $safetyId . '.';
                    header('Location: updates.php?restored=1'); exit;
                } catch (Throwable $ex) {
                    error_log('MercurioCMS restore failed: ' . $ex->getMessage());
                    $rollbackMessage = '';
                    if ($safetyMade) {
                        try {
                            cms_restore_remove_stale_files($root, $safetyDir . '/files');
                            cms_restore_copy_tree($safetyDir . '/files', $root);
                            cms_restore_database($pdo, $safetyDir . '/database.sql', (string)$config['table_prefix']);
                            $rollbackMessage = ' La situazione precedente è stata recuperata dal backup di sicurezza.';
                        } catch (Throwable $rollbackEx) {
                            error_log('MercurioCMS restore rollback failed: ' . $rollbackEx->getMessage());
                            @file_put_contents($safetyDir . DIRECTORY_SEPARATOR . '.PROTECTED', 'Protetto automaticamente: rollback non riuscito il ' . date('c') . "\n", LOCK_EX);
                            $rollbackMessage = ' ATTENZIONE: anche il recupero automatico non è riuscito; il backup di sicurezza è stato protetto per impedirne la cancellazione automatica. Conserva la cartella ' . $safetyDir . ' e richiedi assistenza.';
                        }
                    } elseif ($safetyDir !== '' && is_dir($safetyDir)) {
                        cms_update_remove_tree($safetyDir);
                    }
                    $errors[] = 'Ripristino non completato: ' . $ex->getMessage() . '.' . $rollbackMessage;
                }
                }
            }
        }
    } else {
    if (empty($_FILES['update_zip']) || ($_FILES['update_zip']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['update_zip']['tmp_name'] ?? '')) {
        $errors[] = 'Seleziona un pacchetto ZIP di aggiornamento valido.';
    } elseif ((int)($_FILES['update_zip']['size'] ?? 0) > $maxUpload) {
        $errors[] = 'Il pacchetto supera il limite di 50 MB.';
    } elseif (!is_dir($root . '/storage') || !is_writable($root . '/storage')) {
        $errors[] = 'La cartella storage non è scrivibile. Non è possibile creare un backup sicuro.';
    } else {
        $zip = class_exists('ZipArchive') ? new ZipArchive() : new CmsZipReader();
        $opened = $zip->open($_FILES['update_zip']['tmp_name']);
        if ($opened !== true) $errors[] = 'Archivio ZIP non leggibile. Usa un normale ZIP non cifrato.';
        else {
            $manifestStat = $zip->statName('update.json');
            $manifestRaw = ($manifestStat && (int)$manifestStat['size'] <= 65536) ? $zip->getFromName('update.json') : false;
            $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true, 32) : null;
            if (!is_array($manifest) || ($manifest['product'] ?? '') !== 'MercurioCMS' || !is_string($manifest['version'] ?? null) || !is_array($manifest['files'] ?? null)) $errors[] = 'Manifest update.json assente o non valido.';
            $newVersion = is_array($manifest) ? trim((string)$manifest['version']) : '';
            if ($newVersion !== '' && !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.-]+)?$/', $newVersion)) $errors[] = 'Numero di versione non valido nel pacchetto.';
            $minVersion = is_array($manifest) ? trim((string)($manifest['min_version'] ?? '0.0.0')) : '0.0.0';
            if ($minVersion !== '' && version_compare(CMS_VERSION, $minVersion, '<')) $errors[] = 'Questo aggiornamento richiede MercurioCMS ' . $minVersion . ' o superiore.';
            if ($newVersion !== '' && version_compare($newVersion, CMS_VERSION, '<=')) $errors[] = 'Il pacchetto non è più recente della versione installata (' . CMS_VERSION . ').';
            $fileMap = [];
            if (!$errors) {
                foreach ($manifest['files'] as $relative => $hash) {
                    if (!is_string($relative) || !cms_update_valid_path($relative) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/i', $hash)) { $errors[] = 'Elenco file del pacchetto non valido.'; break; }
                    $entry = 'files/' . $relative;
                    $stat = $zip->statName($entry);
                    if (!$stat || (int)$stat['size'] > 20 * 1024 * 1024) { $errors[] = 'File mancante o troppo grande nel pacchetto: ' . $relative; break; }
                    $fileMap[$relative] = strtolower($hash);
                }
                if (!$errors && !isset($fileMap['VERSION'])) $errors[] = 'Il pacchetto deve includere il file VERSION.';
            }
            $totalSize = 0;
            if (!$errors) {
                foreach ($fileMap as $relative => $hash) {
                    $data = $zip->getFromName('files/' . $relative);
                    if (!is_string($data) || !hash_equals($hash, hash('sha256', $data))) { $errors[] = 'Verifica SHA-256 non riuscita per ' . $relative . '.'; break; }
                    $totalSize += strlen($data);
                    if ($totalSize > 100 * 1024 * 1024) { $errors[] = 'Il contenuto estratto supera 100 MB.'; break; }
                    if ($relative === 'VERSION' && trim($data) !== $newVersion) { $errors[] = 'La versione dichiarata non coincide con il file VERSION.'; break; }
                }
            }
            if (!$errors) {
                $stamp = ''; $backupDir = '';
                $stageDir = sys_get_temp_dir() . '/mercuriocms-update-stage-' . bin2hex(random_bytes(5));
                $backupMade = false; $changed = []; $created = [];
                try {
                    [$stamp, $backupDir] = cms_backup_create_directory($backupRoot, 'automatic');
                    if (!mkdir($stageDir, 0755, true)) throw new RuntimeException('Impossibile creare la cartella temporanea dell’aggiornamento.');
                    foreach ($fileMap as $relative => $hash) {
                        $data = $zip->getFromName('files/' . $relative);
                        $dest = $stageDir . '/' . $relative;
                        $parent = dirname($dest);
                        if (!is_dir($parent) && !mkdir($parent, 0755, true)) throw new RuntimeException('Impossibile preparare il file ' . $relative . '.');
                        if (file_put_contents($dest, $data, LOCK_EX) === false) throw new RuntimeException('Impossibile preparare il file ' . $relative . '.');
                    }
                    // Copia integrale dell'installazione; esclude soltanto la cartella dei backup per evitare ricorsione.
                    cms_update_copy_tree($root, $backupDir . '/files', $backupRoot);
                    cms_update_db_backup($pdo, $backupDir . '/database.sql', $config['table_prefix']);
                    if (file_put_contents($backupDir . '/BACKUP-INFO.txt', "Backup automatico prima dell'aggiornamento a $newVersion\nVersione precedente: " . CMS_VERSION . "\nData: " . date('c') . "\nRipristinare insieme file e database.\n", LOCK_EX) === false) throw new RuntimeException('Impossibile salvare le informazioni del backup.');
                    cms_backup_manifest_create($backupDir);
                    cms_backup_manifest_verify($backupDir);
                    $backupMade = true;
                    // Se non esiste ancora alcun punto protetto, conserva automaticamente
                    // la versione funzionante da cui si sta aggiornando.
                    if (cms_backup_protected_count($backupRoot) === 0 && @file_put_contents($backupDir . DIRECTORY_SEPARATOR . '.PROTECTED', 'Primo punto di ripristino protetto automaticamente il ' . date('c') . "\n", LOCK_EX) === false) {
                        throw new RuntimeException('Impossibile proteggere il primo punto di ripristino. Aggiornamento annullato.');
                    }
                    foreach ($fileMap as $relative => $hash) {
                        if (!cms_update_target_safe($root, $relative)) throw new RuntimeException('Percorso di destinazione non sicuro: ' . $relative . '.');
                        $target = $root . '/' . $relative;
                        $parent = dirname($target);
                        if (!is_dir($parent) && !mkdir($parent, 0755, true)) throw new RuntimeException('Impossibile creare la cartella per ' . $relative . '.');
                        if (is_file($target)) $changed[$relative] = true; else $created[$relative] = true;
                        if (!copy($stageDir . '/' . $relative, $target)) throw new RuntimeException('Aggiornamento non riuscito durante la sostituzione di ' . $relative . '.');
                    }
                    cms_update_remove_tree($stageDir);
                    cms_backup_cleanup($backupRoot, 5, 7);
                    $_SESSION['flash'] = 'Aggiornamento completato: MercurioCMS ' . $newVersion . '. Backup completo conservato in storage/update-backups/' . $stamp . '. Verifica ora il sito e il pannello.';
                    $zip->close(); header('Location: updates.php?done=1'); exit;
                } catch (Throwable $ex) {
                    error_log('MercurioCMS update failed: ' . $ex->getMessage());
                    // Ripristino immediato dei file già sostituiti, usando la copia appena creata.
                    if ($backupMade) {
                        foreach (array_keys($changed) as $relative) {
                            $saved = $backupDir . '/files/' . $relative;
                            if (is_file($saved)) @copy($saved, $root . '/' . $relative);
                        }
                        foreach (array_keys($created) as $relative) @unlink($root . '/' . $relative);
                    }
                    cms_update_remove_tree($stageDir);
                    if (!$backupMade && $backupDir !== '' && is_dir($backupDir)) cms_update_remove_tree($backupDir);
                    $errors[] = 'Aggiornamento interrotto. ' . ($backupMade ? 'I file modificati sono stati riportati alla versione precedente; il backup completo è conservato.' : 'Non è stato modificato alcun file del programma.') . ' Dettaglio: ' . $ex->getMessage();
                }
            }
            $zip->close();
        }
    }
    }
}
$backups = [];
if (is_dir($backupRoot)) foreach (array_diff(scandir($backupRoot) ?: [], ['.','..']) as $item) if (is_dir($backupRoot . '/' . $item) && cms_backup_valid_id($item)) $backups[] = $item;
rsort($backups);
$backupSummary = cms_backup_summary($backupRoot);
$cleanupPreview = cms_backup_cleanup_preview($backupRoot, 5, 7);
$protectedBackups = array_values(array_filter($backups, static fn($b) => cms_backup_is_protected($backupRoot.'/'.$b)));
$automaticBackups = array_values(array_filter($backups, static fn($b) => !cms_backup_is_protected($backupRoot.'/'.$b) && cms_backup_kind($b) === 'automatic'));
$safetyBackups = array_values(array_filter($backups, static fn($b) => !cms_backup_is_protected($backupRoot.'/'.$b) && cms_backup_kind($b) === 'safety'));
$zipAvailable = class_exists('ZipArchive') || function_exists('gzinflate');
$zipMethod = class_exists('ZipArchive') ? 'ZipArchive' : 'lettore ZIP integrato (zlib)';
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aggiornamenti - MercurioCMS</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<?php $adminActive='updates'; require __DIR__.'/_sidebar.php'; ?>
<main class="container"><h1>Aggiornamenti di MercurioCMS</h1><p>Versione installata: <strong><?=e(CMS_VERSION)?></strong></p>
<?php if($flash):?><p class="notice"><?=e($flash)?></p><?php endif;?>
<?php if($errors):?><div class="error"><ul><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="panel"><h2>Applica un pacchetto di aggiornamento</h2><p>Carica il file ZIP ufficiale ricevuto per aggiornare MercurioCMS. Prima di sostituire i file, il sistema crea automaticamente una copia completa dei file dell'installazione e delle tabelle del database.</p>
<?php if(!$zipAvailable):?><p class="error">Il server non dispone delle funzioni necessarie per leggere archivi ZIP.</p><?php else:?><p class="editor-help">Lettore ZIP disponibile: <?=e($zipMethod)?>.</p><?php endif;?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Pacchetto aggiornamento (.zip)<input type="file" name="update_zip" accept=".zip,application/zip" required <?=!$zipAvailable?'disabled':''?>></label><p class="editor-help">Massimo 50 MB. Il pacchetto deve provenire da MercurioCMS e includere un manifest con verifica SHA-256 dei file.</p><button type="submit" <?=!$zipAvailable?'disabled':''?> onclick="return confirm('Procedere con l’aggiornamento? Il CMS creerà prima un backup completo. Durante l’operazione non chiudere la pagina.')">Verifica e applica aggiornamento</button></form></section>
<section class="panel"><h2>Gestione dei punti di ripristino</h2><p>Ogni backup è una copia completa e indipendente di file e database: non dipende dagli altri. Puoi eliminare un backup senza compromettere gli altri punti di ripristino. Proteggi le versioni sicuramente funzionanti per conservarle senza scadenza.</p>
<div class="backup-summary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:12px;margin:16px 0"><div class="notice"><strong>Totale</strong><br><?=e((string)$backupSummary['total']['count'])?> backup<br><?=e(cms_format_bytes($backupSummary['total']['bytes']))?></div><div class="notice"><strong>Protetti</strong><br><?=e((string)$backupSummary['protected']['count'])?> backup<br><?=e(cms_format_bytes($backupSummary['protected']['bytes']))?></div><div class="notice"><strong>Automatici</strong><br><?=e((string)$backupSummary['automatic']['count'])?> backup<br><?=e(cms_format_bytes($backupSummary['automatic']['bytes']))?></div><div class="notice"><strong>Di sicurezza</strong><br><?=e((string)$backupSummary['safety']['count'])?> backup<br><?=e(cms_format_bytes($backupSummary['safety']['bytes']))?></div></div><p><strong>Spazio recuperabile con la pulizia:</strong> <?=e((string)$cleanupPreview['count'])?> backup · <?=e(cms_format_bytes($cleanupPreview['bytes']))?></p>
<p><strong>Regole di conservazione:</strong> restano gli ultimi 5 backup automatici non protetti; i backup di sicurezza scadono dopo 7 giorni; i punti protetti non hanno scadenza. Il sistema non consente di togliere la protezione all’ultimo punto protetto: prima occorre proteggerne un altro.</p><label for="backupFilter">Cerca nei backup (data o versione)</label><input id="backupFilter" type="search" placeholder="Es. RC20 oppure 20261105" style="max-width:360px;width:100%;margin:6px 0 12px" oninput="document.querySelectorAll('[data-backup-row]').forEach(function(r){r.hidden=!r.textContent.toLowerCase().includes(this.value.toLowerCase())}.bind(this))">
<form method="post" style="margin:12px 0" onsubmit="return confirm('Eseguire la pulizia? Saranno rimossi i backup automatici oltre gli ultimi 5 e i backup di sicurezza più vecchi di 7 giorni. I punti protetti resteranno intatti.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="cleanup_backups"><button type="submit">Pulisci backup non necessari (mantieni ultimi 5 + protetti)</button></form>
<form method="post" style="margin:12px 0;padding:12px;border:1px solid #cbd5e1;border-radius:8px" onsubmit="return confirm('Creare una copia completa e protetta della versione attualmente installata e del database?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_restore_point"><label>Per confermare, digita CREA PUNTO <input type="text" name="confirm_text" required pattern="CREA PUNTO" autocomplete="off" style="max-width:220px"></label> <button type="submit">Crea punto di ripristino protetto adesso</button></form>
<?php if($backups):?><form method="post" style="margin:12px 0;padding:12px;border:1px solid #cbd5e1;border-radius:8px" onsubmit="return confirm('Eliminare il punto selezionato e tutti i backup più vecchi? I punti protetti non verranno mai eliminati.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_backups_from"><label>Elimina da questo punto in giù (incluso): <select name="cutoff_id" required><?php foreach($backups as $choice):?><option value="<?=e($choice)?>"><?=e($choice)?> — <?=e(cms_restore_backup_version($backupRoot.'/'.$choice))?><?=cms_backup_is_protected($backupRoot.'/'.$choice)?' · protetto (sarà conservato)':''?></option><?php endforeach;?></select></label> <label>Digita ELIMINA PRECEDENTI <input type="text" name="confirm_text" required pattern="ELIMINA PRECEDENTI" autocomplete="off" style="max-width:220px"></label> <button type="submit" class="danger">Elimina questo e i più vecchi</button><p class="editor-help">I punti protetti vengono sempre saltati. Ogni backup è indipendente: la cancellazione degli altri non compromette quelli conservati.</p></form><?php endif;?>
<?php if(!$backups):?><p>Nessun backup presente. Crea subito un punto di ripristino protetto prima di continuare a lavorare.</p><?php endif;?>
<details open><summary><strong>Punti di ripristino protetti</strong> · <?=count($protectedBackups)?> · conservazione senza scadenza</summary><?php if(!$protectedBackups):?><p>Nessun punto protetto. Proteggi una versione funzionante per conservarla senza scadenza.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Data</th><th>Versione</th><th>Dimensione</th><th>Azioni</th></tr></thead><tbody><?php foreach($protectedBackups as $b):?><tr data-backup-row><td><?=e($b)?></td><td><?=e(cms_restore_backup_version($backupRoot.'/'.$b))?></td><td><?=e(cms_format_bytes(cms_backup_size($backupRoot.'/'.$b)))?></td><td><form method="post" class="inline-form" onsubmit="return confirm('Rimuovere la protezione? Il backup non verrà eliminato, ma potrà essere rimosso dalla pulizia automatica.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="unprotect_backup"><input type="hidden" name="backup_id" value="<?=e($b)?>"><button type="submit">Rimuovi protezione</button></form> <form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore_backup"><input type="hidden" name="backup_id" value="<?=e($b)?>"><label>Digita RIPRISTINA <input type="text" name="confirm_text" required pattern="RIPRISTINA" autocomplete="off"></label><button type="submit" class="danger">Ripristina</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</details><details open><summary><strong>Backup automatici</strong> · <?=count($automaticBackups)?> · conservati in rotazione (ultimi 5)</summary><?php if(!$automaticBackups):?><p>Nessun backup automatico non protetto.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Data</th><th>Versione</th><th>Dimensione</th><th>Azioni</th></tr></thead><tbody><?php foreach($automaticBackups as $b):?><tr data-backup-row><td><?=e($b)?></td><td><?=e(cms_restore_backup_version($backupRoot.'/'.$b))?></td><td><?=e(cms_format_bytes(cms_backup_size($backupRoot.'/'.$b)))?></td><td><form method="post" class="inline-form" onsubmit="return confirm('Proteggere questo punto? Non verrà eliminato automaticamente.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="protect_backup"><input type="hidden" name="backup_id" value="<?=e($b)?>"><button type="submit">Proteggi</button></form> <form method="post" class="inline-form" onsubmit="return confirm('Ripristinare file e database a questa data? Le modifiche successive andranno perse.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore_backup"><input type="hidden" name="backup_id" value="<?=e($b)?>"><label>Digita RIPRISTINA <input type="text" name="confirm_text" required pattern="RIPRISTINA" autocomplete="off"></label><button type="submit" class="danger">Ripristina</button></form> <form method="post" class="inline-form" onsubmit="return confirm('Eliminare definitivamente questo backup?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_backup"><input type="hidden" name="backup_id" value="<?=e($b)?>"><label>Digita ELIMINA <input type="text" name="confirm_text" required pattern="ELIMINA" autocomplete="off"></label><button type="submit" class="danger">Elimina</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</details><details><summary><strong>Backup di sicurezza</strong> · <?=count($safetyBackups)?> · scadenza dopo 7 giorni</summary><?php if(!$safetyBackups):?><p>Nessun backup di sicurezza in attesa di scadenza.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Data</th><th>Dimensione</th><th>Azioni</th></tr></thead><tbody><?php foreach($safetyBackups as $b):?><tr data-backup-row><td><?=e($b)?></td><td><?=e(cms_format_bytes(cms_backup_size($backupRoot.'/'.$b)))?></td><td><form method="post" class="inline-form" onsubmit="return confirm('Proteggere questo backup di sicurezza senza scadenza?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="protect_backup"><input type="hidden" name="backup_id" value="<?=e($b)?>"><button type="submit">Proteggi</button></form> <form method="post" class="inline-form" onsubmit="return confirm('Ripristinare questa copia di sicurezza? Le modifiche successive andranno perse.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore_backup"><input type="hidden" name="backup_id" value="<?=e($b)?>"><label>Digita RIPRISTINA <input type="text" name="confirm_text" required pattern="RIPRISTINA" autocomplete="off"></label><button type="submit" class="danger">Ripristina</button></form> <form method="post" class="inline-form" onsubmit="return confirm('Eliminare definitivamente questo backup di sicurezza?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_backup"><input type="hidden" name="backup_id" value="<?=e($b)?>"><label>Digita ELIMINA <input type="text" name="confirm_text" required pattern="ELIMINA" autocomplete="off"></label><button type="submit" class="danger">Elimina</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></details>
</section>

<section class="panel"><h2>Prima di procedere</h2><ul><li>Il pacchetto deve essere più recente della versione installata.</li><li>I file di configurazione, i contenuti caricati, le estensioni e i dati di servizio non vengono sovrascritti dal pacchetto.</li><li>Il database non viene cancellato: le migrazioni previste dalla nuova versione vengono eseguite dal CMS in modo non distruttivo al successivo avvio.</li><li>Conserva comunque una copia esterna del backup prima di aggiornare un sito in produzione.</li></ul></section>
</main></div></div></body></html>
