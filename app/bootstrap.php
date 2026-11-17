<?php
/** Bootstrap comune di MercurioCMS. */
declare(strict_types=1);

if (!defined('CMS_VERSION')) {
    $versionFile = dirname(__DIR__) . '/VERSION';
    $versionValue = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '0.9.9';
    define('CMS_VERSION', $versionValue !== '' ? $versionValue : '0.9.9');
}

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('MercurioCMS non è configurato. Avviare install.php.');
}
$config = require $configFile;
// Cartelle operative: devono esistere anche dopo installazioni/aggiornamenti che non preservano directory vuote.
foreach ([dirname(__DIR__) . '/storage', dirname(__DIR__) . '/storage/update-backups', dirname(__DIR__) . '/plugins', dirname(__DIR__) . '/uploads', dirname(__DIR__) . '/uploads/images', dirname(__DIR__) . '/uploads/documents'] as $cmsDir) {
    if (!is_dir($cmsDir)) @mkdir($cmsDir, 0755, true);
}
foreach ([dirname(__DIR__) . '/storage', dirname(__DIR__) . '/plugins', dirname(__DIR__) . '/uploads', dirname(__DIR__) . '/uploads/images', dirname(__DIR__) . '/uploads/documents'] as $protectedDir) {
    if (is_dir($protectedDir)) {
        if (!is_file($protectedDir . '/index.html')) @file_put_contents($protectedDir . '/index.html', '');
        if (!is_file($protectedDir . '/.htaccess')) @file_put_contents($protectedDir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php[0-9]?|phtml|phar)$\">\n  Require all denied\n</FilesMatch>\n");
    }
}

if (!is_array($config) || !isset($config['db']['host'], $config['db']['name'], $config['db']['user'], $config['db']['password'], $config['table_prefix'])) {
    http_response_code(500);
    exit('Configurazione MercurioCMS non valida. Controllare config.php.');
}
$prefix = (string)$config['table_prefix'];
if (!preg_match('/^[A-Za-z0-9_]{1,20}$/', $prefix)) {
    http_response_code(500);
    exit('Prefisso delle tabelle non valido.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('mercuriocms_admin');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db']['host'], $config['db']['name']);
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $ex) {
    error_log('MercurioCMS database connection failed: ' . $ex->getMessage());
    http_response_code(503);
    exit('Impossibile collegarsi al database. Verificare la configurazione.');
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function cms_table(string $name): string {
    global $config;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) throw new InvalidArgumentException('Nome tabella non valido.');
    return '`' . $config['table_prefix'] . $name . '`';
}
function cms_table_exists(string $name): bool {
    global $pdo, $config;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$config['table_prefix'] . $name]);
    return (int)$stmt->fetchColumn() > 0;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf'];
}
function csrf_check(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Richiesta non valida o sessione scaduta. Ricaricare la pagina e riprovare.');
    }
}
function require_admin(): void {
    global $pdo;
    if (empty($_SESSION['admin_id'])) {
        header('Location: index.php');
        exit;
    }
    if (cms_table_exists('admins')) {
        $check = $pdo->prepare('SELECT is_active FROM ' . cms_table('admins') . ' WHERE id = ? LIMIT 1');
        $check->execute([(int)$_SESSION['admin_id']]);
        if ((int)$check->fetchColumn() !== 1) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
            header('Location: index.php');
            exit;
        }
    }
}
/**
 * Pulisce l'HTML delle pagine senza eliminare la formattazione CSS scelta dall'amministratore.
 * Sono ammessi stili inline, blocchi <style>, fogli di stile <link>, classi e ID.
 * Restano esclusi script, gestori evento e URL con schemi pericolosi.
 */
function sanitize_page_html(string $html): string {
    $resources = [];
    $token = static function (string $kind, string $value) use (&$resources): string {
        $key = 'MERCURIO_CSS_RESOURCE_' . count($resources) . '_END';
        $resources[$key] = $value;
        return $key;
    };

    // Rimuove integralmente gli elementi eseguibili o incorporati non ammessi.
    $html = preg_replace('~<(script|iframe|object|embed|applet|form|input|button|textarea|select|video|audio)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? $html;
    $html = preg_replace('~<(script|iframe|object|embed|applet|form|input|button|textarea|select|video|audio)\b[^>]*/?>~is', '', $html) ?? $html;

    // Conserva i blocchi CSS come risorse protette durante la pulizia dell'HTML.
    $html = preg_replace_callback('~<style\b[^>]*>(.*?)</style\s*>~is', static function ($m) use ($token) {
        $css = (string)$m[1];
        if (preg_match('~(?:expression\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding)~i', $css)) return '';
        return $token('style', '<style>' . $css . '</style>');
    }, $html) ?? $html;

    // Conserva solo link a fogli di stile con URL http(s), locali o relativi.
    $html = preg_replace_callback('~<link\b([^>]*)>~i', static function ($m) use ($token) {
        $attrs = $m[1];
        if (!preg_match('/\brel\s*=\s*(["\'])(.*?)\1/i', $attrs, $rel) || strtolower(trim($rel[2])) !== 'stylesheet') return '';
        if (!preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $href)) return '';
        $url = trim(html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!preg_match('~^(https?://|/|(?:\./|\.\./)?[A-Za-z0-9_./-]+\.css(?:\?[A-Za-z0-9_=&.-]*)?)$~i', $url)
            || preg_match('~[\x00-\x20]|^(?:javascript|data|vbscript):~i', $url)) return '';
        $safe = '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        return $token('link', $safe);
    }, $html) ?? $html;

    $allowed = '<p><br><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><s><del><mark><small><sub><sup><ul><ol><li><blockquote><a><hr><pre><code><img><table><thead><tbody><tfoot><tr><th><td><caption><div><span><section><article><aside><header><footer><main><figure><figcaption>'; 
    $html = strip_tags($html, $allowed);

    // Ricostruisce gli elementi con una whitelist di attributi, mantenendo class, id e CSS inline.
    $html = preg_replace_callback('~<(\/?)\s*([a-z0-9]+)\b([^>]*)>~i', static function ($m) {
        $closing = $m[1] === '/';
        $tag = strtolower($m[2]);
        $void = in_array($tag, ['br','hr','img'], true);
        if ($closing) return $void ? '' : '</' . $tag . '>';
        $raw = $m[3];
        $attrs = [];
        $found = [];
        preg_match_all('/([a-zA-Z_:][a-zA-Z0-9_:.-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/s', $raw, $found, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        foreach ($found as $a) {
            $name = strtolower((string)($a[1] ?? ''));
            $rawValue = $a[2] ?? $a[3] ?? $a[4] ?? '';
            $value = html_entity_decode((string)$rawValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($name, 'on') || in_array($name, ['srcdoc','formaction','xmlns'], true)) continue;
            if (in_array($name, ['class','id','title','aria-label','role'], true)) {
                if ($value !== '') $attrs[$name] = $value;
            } elseif ($name === 'style') {
                if (!preg_match('~(?:expression\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding)~i', $value)) $attrs['style'] = $value;
            } elseif ($tag === 'a' && $name === 'href') {
                if ($value !== '' && preg_match('~^(https?://|mailto:|/|#|uploads/documents/[A-Za-z0-9._-]+$|download\.php\?id=[0-9]+$)~i', $value)
                    && !preg_match('~[\x00-\x20]|^(?:javascript|data|vbscript):~i', $value)) $attrs['href'] = $value;
            } elseif ($tag === 'img' && $name === 'src') {
                if (preg_match('~^uploads/images/[A-Za-z0-9._-]+$~', $value)) $attrs['src'] = $value;
            } elseif ($tag === 'img' && $name === 'alt') $attrs['alt'] = $value;
            elseif (in_array($tag, ['td','th'], true) && in_array($name, ['rowspan','colspan'], true) && ctype_digit($value) && (int)$value >= 1 && (int)$value <= 100) $attrs[$name] = (string)(int)$value;
        }
        if ($tag === 'img' && empty($attrs['src'])) return '';
        if ($tag === 'a' && isset($attrs['href'])) $attrs['rel'] = 'nofollow noopener';
        $out = '<' . $tag;
        foreach ($attrs as $name => $value) $out .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        return $out . ($void ? '>' : '>');
    }, $html) ?? $html;

    return strtr($html, $resources);
}

function slugify(string $text): string {
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = strtolower(trim($converted !== false ? $converted : $text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'pagina';
}


/** Legge un'impostazione del sito, restituendo il valore predefinito se assente. */
function cms_setting(string $key, string $default = ''): string {
    global $pdo;
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!cms_table_exists('settings')) return $cache[$key] = $default;
    $stmt = $pdo->prepare('SELECT setting_value FROM ' . cms_table('settings') . ' WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $cache[$key] = ($value === false || $value === null ? $default : (string)$value);
}
function cms_set_setting(string $key, string $value): void {
    global $pdo;
    if (!preg_match('/^[a-z0-9_]{1,100}$/', $key)) throw new InvalidArgumentException('Chiave impostazione non valida.');
    $stmt = $pdo->prepare('INSERT INTO ' . cms_table('settings') . ' (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}
function cms_setting_cache_clear(): void {
    // Le pagine amministrative salvano e poi effettuano un redirect: la cache vive solo nella richiesta.
}


/** Aggiornamento automatico e non distruttivo dello schema 0.8.0. */
function cms_upgrade_080(): void {
    global $pdo, $config;
    static $done = false;
    if ($done) return;
    $done = true;
    $media = cms_table('media');
    if (!cms_table_exists('media')) $pdo->exec("CREATE TABLE $media (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        media_type ENUM('image','document') NOT NULL,
        mime_type VARCHAR(120) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        deleted_at DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (id), KEY idx_media_type_created (media_type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (cms_table_exists('media')) {
        $mediaColumn = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $mediaColumn->execute([$config['table_prefix'] . 'media', 'deleted_at']);
        if ((int)$mediaColumn->fetchColumn() === 0) $pdo->exec('ALTER TABLE ' . cms_table('media') . ' ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL');
    }
    if (cms_table_exists('pages')) {
        $columnCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $columnCheck->execute([$config['table_prefix'] . 'pages', 'deleted_at']);
        if ((int)$columnCheck->fetchColumn() === 0) $pdo->exec('ALTER TABLE ' . cms_table('pages') . ' ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL');
        $columnCheck->execute([$config['table_prefix'] . 'pages', 'excerpt']);
        if ((int)$columnCheck->fetchColumn() === 0) $pdo->exec('ALTER TABLE ' . cms_table('pages') . ' ADD COLUMN excerpt TEXT NULL');
        $columnCheck->execute([$config['table_prefix'] . 'pages', 'background_image']);
        if ((int)$columnCheck->fetchColumn() === 0) $pdo->exec('ALTER TABLE ' . cms_table('pages') . ' ADD COLUMN background_image VARCHAR(255) NULL');
        foreach (['hide_title' => 'TINYINT(1) NOT NULL DEFAULT 0', 'hide_category' => 'TINYINT(1) NOT NULL DEFAULT 0'] as $column => $definition) {
            $columnCheck->execute([$config['table_prefix'] . 'pages', $column]);
            if ((int)$columnCheck->fetchColumn() === 0) $pdo->exec('ALTER TABLE ' . cms_table('pages') . " ADD COLUMN `$column` $definition");
        }
        $rev = cms_table('page_revisions');
        if (!cms_table_exists('page_revisions')) $pdo->exec("CREATE TABLE $rev (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(220) NOT NULL,
            slug VARCHAR(240) NOT NULL,
            content MEDIUMTEXT NOT NULL,
            excerpt TEXT NULL,
            status VARCHAR(20) NOT NULL,
            saved_at DATETIME NOT NULL,
            PRIMARY KEY (id), KEY idx_revision_page_date (page_id, saved_at), KEY idx_revision_date (saved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (cms_table_exists('page_revisions')) {
            $columnCheck->execute([$config['table_prefix'] . 'page_revisions', 'excerpt']);
            if ((int)$columnCheck->fetchColumn() === 0) $pdo->exec('ALTER TABLE ' . cms_table('page_revisions') . ' ADD COLUMN excerpt TEXT NULL');
            $columnCheck->execute([$config['table_prefix'] . 'page_revisions', 'background_image']);
            if ((int)$columnCheck->fetchColumn() === 0) $pdo->exec('ALTER TABLE ' . cms_table('page_revisions') . ' ADD COLUMN background_image VARCHAR(255) NULL');
        }
    }
}
/** Aggiornamento non distruttivo gestione profili amministratori. */
function cms_upgrade_admin_profiles(): void {
    global $pdo, $config;
    static $done = false; if ($done) return; $done = true;
    if (!cms_table_exists('admins')) return;
    $tableName = $config['table_prefix'] . 'admins';
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    foreach (['display_name' => "VARCHAR(120) NOT NULL DEFAULT ''", 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1'] as $column => $definition) {
        $check->execute([$tableName, $column]);
        if ((int)$check->fetchColumn() === 0) $pdo->exec('ALTER TABLE ' . cms_table('admins') . " ADD COLUMN `$column` $definition");
    }
}

/** Aggiornamento non distruttivo 0.9.6: pagina di destinazione per categorie. */
function cms_upgrade_096(): void {
    global $pdo, $config;
    static $done = false;
    if ($done) return;
    $done = true;
    if (!cms_table_exists('categories')) return;
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $check->execute([$config['table_prefix'] . 'categories', 'landing_page_id']);
    if ((int)$check->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE ' . cms_table('categories') . ' ADD COLUMN landing_page_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER sort_order');
    }
}

/** Aggiornamento non distruttivo 0.9.7: visibilità delle categorie nel menu. */
function cms_upgrade_097(): void {
    global $pdo, $config;
    static $done = false;
    if ($done) return;
    $done = true;
    if (!cms_table_exists('categories')) return;
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $check->execute([$config['table_prefix'] . 'categories', 'is_visible']);
    if ((int)$check->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE ' . cms_table('categories') . ' ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER landing_page_id');
    }
}


/** Registro voci menu dei plugin attivi. I plugin sono codice PHP fidato installato dall'amministratore. */
$GLOBALS['cms_plugin_admin_menu'] = $GLOBALS['cms_plugin_admin_menu'] ?? [];
function cms_register_admin_menu(string $pluginId, string $label, string $relativeUrl): void {
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $pluginId) || trim($label)==='' || strlen($label)>80) return;
    if (!preg_match('~^\.\./plugins/' . preg_quote($pluginId, '~') . '/[A-Za-z0-9_./-]+\.php(?:\?[A-Za-z0-9_=&-]*)?$~', $relativeUrl) || preg_match('~(^|/)\.\.(?:/|$)~', substr($relativeUrl, 3))) return;
    $GLOBALS['cms_plugin_admin_menu'][$pluginId] = ['label'=>$label, 'url'=>$relativeUrl];
}
function cms_load_active_plugins(): void {
    global $pdo;
    if (!cms_table_exists('plugins')) return;
    $rows=$pdo->query('SELECT plugin_id, entry_file FROM '.cms_table('plugins')." WHERE is_active=1 ORDER BY plugin_id")->fetchAll();
    foreach($rows as $row) {
        $id=(string)$row['plugin_id']; $entry=(string)$row['entry_file'];
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/',$id) || !preg_match('~^[A-Za-z0-9_/-]+\.php$~',$entry) || str_contains($entry,'..')) continue;
        $base=realpath(dirname(__DIR__).'/plugins/'.$id); $file=realpath(dirname(__DIR__).'/plugins/'.$id.'/'.$entry);
        if($base && $file && str_starts_with($file,$base.DIRECTORY_SEPARATOR) && is_file($file)) {
            try { require_once $file; } catch(Throwable $ex) { error_log('MercurioCMS plugin '.$id.' error: '.$ex->getMessage()); }
        }
    }
}
/** Aggiornamento non distruttivo 0.9.9: registro delle estensioni. */
function cms_upgrade_098(): void {
    global $pdo;
    if (!cms_table_exists('plugins')) $pdo->exec("CREATE TABLE ".cms_table('plugins')." (
        plugin_id VARCHAR(50) NOT NULL,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(500) NOT NULL DEFAULT '',
        version VARCHAR(40) NOT NULL,
        min_cms_version VARCHAR(40) NOT NULL DEFAULT '1.0.0-rc1',
        entry_file VARCHAR(190) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        installed_at DATETIME NOT NULL,
        PRIMARY KEY (plugin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

cms_upgrade_080();
cms_upgrade_admin_profiles();
cms_upgrade_096();
cms_upgrade_097();
cms_upgrade_098();
cms_load_active_plugins();
