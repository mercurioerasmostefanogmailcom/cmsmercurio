<?php
/** Installazione iniziale di MercurioCMS. */
declare(strict_types=1);
$root = __DIR__;
// La cartella storage è necessaria per lock, backup e ripristini.
// Crearla automaticamente nelle installazioni nuove, senza richiedere interventi manuali.
$storagePath = $root . '/storage';
$storageCreationError = false;
if (!is_dir($storagePath)) {
    $storageCreationError = !@mkdir($storagePath, 0755, true) && !is_dir($storagePath);
}
if (is_dir($storagePath)) {
    @chmod($storagePath, 0755);
    $storageHtaccess = $storagePath . '/.htaccess';
    if (!is_file($storageHtaccess)) {
        @file_put_contents($storageHtaccess, "Require all denied\nDeny from all\n", LOCK_EX);
    }
    $storageIndex = $storagePath . '/index.html';
    if (!is_file($storageIndex)) @file_put_contents($storageIndex, "<!doctype html><title>Accesso negato</title>\n", LOCK_EX);
}
$lock = $root . '/storage/install.lock';
$configPath = $root . '/config.php';
if (is_file($lock) || is_file($configPath)) {
    http_response_code(403);
    exit('Installazione già eseguita o configurazione presente. Installatore bloccato.');
}
$checks = [
    'PHP 8.2 o superiore' => version_compare(PHP_VERSION, '8.2.0', '>='),
    'PDO disponibile' => extension_loaded('pdo'),
    'PDO MySQL disponibile' => extension_loaded('pdo_mysql'),
    'JSON disponibile' => extension_loaded('json'),
    'Fileinfo disponibile (controllo upload)' => extension_loaded('fileinfo'),
    'Iconv disponibile (generazione slug)' => extension_loaded('iconv'),
    'Cartella storage creata e scrivibile' => !$storageCreationError && is_dir($root . '/storage') && is_writable($root . '/storage'),
    'Cartella principale scrivibile (per config.php)' => is_writable($root),
];
$errors = [];
$done = false;
session_name('mercuriocms_install');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Strict']);
session_start();
if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
function install_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function install_length(string $s): int {
    if (preg_match_all('/./us', $s, $m) !== false) return count($m[0]);
    return strlen($s);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentToken = $_POST['install_csrf'] ?? '';
    if (!is_string($sentToken) || !hash_equals((string)$_SESSION['install_csrf'], $sentToken)) {
        http_response_code(403); exit('Richiesta non valida. Ricaricare la pagina.');
    }
    foreach ($checks as $label => $ok) if (!$ok) $errors[] = $label . ': requisito non soddisfatto.';
    $host = trim((string)($_POST['db_host'] ?? ''));
    $name = trim((string)($_POST['db_name'] ?? ''));
    $user = trim((string)($_POST['db_user'] ?? ''));
    $pass = (string)($_POST['db_pass'] ?? '');
    $prefix = trim((string)($_POST['db_prefix'] ?? 'mcms_'));
    $admin = trim((string)($_POST['admin_user'] ?? ''));
    $email = trim((string)($_POST['admin_email'] ?? ''));
    $pw = (string)($_POST['admin_pass'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]{1,20}$/', $prefix)) $errors[] = 'Prefisso tabelle non valido: usa da 1 a 20 lettere, numeri o underscore.';
    if ($host === '' || $name === '' || $user === '') $errors[] = 'Compila host, database e utente MySQL.';
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $admin)) $errors[] = 'Nome utente amministratore non valido.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Indirizzo email non valido.';
    if (install_length($pw) < 9) $errors[] = 'La password amministratore deve contenere almeno 9 caratteri.';

    if (!$errors) {
        $pdo = null;
        $createdTables = [];
        $configWritten = false;
        $lockWritten = false;
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Un database può contenere altre applicazioni: è sufficiente usare un prefisso libero.
            // Il controllo è sul prefisso completo, non soltanto sulle due tabelle iniziali.
            $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
            $collisions = [];
            foreach ($existing as $row) {
                $tableName = (string)($row[0] ?? '');
                if ($tableName !== '' && str_starts_with($tableName, $prefix)) $collisions[] = $tableName;
            }
            if ($collisions) {
                throw new RuntimeException('Il prefisso "' . $prefix . '" è già utilizzato. Scegli un prefisso diverso. Tabelle trovate: ' . implode(', ', array_slice($collisions, 0, 8)));
            }

            $admins = $prefix . 'admins';
            $settings = $prefix . 'settings';
            $pdo->exec("CREATE TABLE `{$admins}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(60) NOT NULL UNIQUE,
                `email` VARCHAR(190) NOT NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `role` VARCHAR(20) NOT NULL DEFAULT 'superadmin',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $createdTables[] = $admins;
            $pdo->exec("CREATE TABLE `{$settings}` (
                `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                `setting_value` MEDIUMTEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $createdTables[] = $settings;

            $stmt = $pdo->prepare("INSERT INTO `{$admins}` (username,email,password_hash,role) VALUES (?,?,?,'superadmin')");
            $stmt->execute([$admin, $email, password_hash($pw, PASSWORD_DEFAULT)]);

            $cfg = "<?php\n// MercurioCMS — credenziali riservate; non condividere questo file.\nreturn " .
                var_export(['db'=>['host'=>$host,'name'=>$name,'user'=>$user,'password'=>$pass], 'table_prefix'=>$prefix], true) . ";\n";
            if (file_put_contents($configPath, $cfg, LOCK_EX) === false) throw new RuntimeException('Impossibile scrivere config.php. Verificare i permessi della cartella principale.');
            $configWritten = true; @chmod($configPath, 0600);
            if (file_put_contents($lock, date(DATE_ATOM), LOCK_EX) === false) throw new RuntimeException('Impossibile creare storage/install.lock. Verificare i permessi della cartella storage.');
            $lockWritten = true; @chmod($lock, 0600);
            $done = true;
        } catch (Throwable $ex) {
            // MySQL esegue implicit commit sui CREATE TABLE: ripuliamo solo le tabelle create
            // in questo specifico tentativo, dopo aver verificato che il prefisso fosse libero.
            if ($pdo instanceof PDO) {
                foreach (array_reverse($createdTables) as $tableName) {
                    try { $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $tableName) . '`'); } catch (Throwable $ignored) {}
                }
            }
            if ($lockWritten && is_file($lock)) @unlink($lock);
            if ($configWritten && is_file($configPath)) @unlink($configPath);
            $errors[] = $ex instanceof RuntimeException && str_starts_with($ex->getMessage(), 'Il prefisso')
                ? $ex->getMessage()
                : 'Installazione non completata. Verificare database e permessi; dettagli registrati nel log del server.';
            error_log('MercurioCMS installer: ' . $ex->getMessage());
        }
    }
}
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Installazione MercurioCMS</title><link rel="stylesheet" href="assets/css/admin.css"></head>
<body><main class="panel installer"><h1>Installazione MercurioCMS</h1><p>Puoi utilizzare anche un database già esistente, purché il prefisso scelto sia libero e non sia usato da altre tabelle.</p>
<?php if ($done): ?><div class="success"><h2>Installazione completata</h2><p>Il primo amministratore è stato creato. Al primo accesso partirà la configurazione guidata del sito.</p><p><a class="button" href="admin/">Apri amministrazione</a></p><p><a href="index.php">Visualizza il sito pubblico</a></p></div>
<?php else: ?><?php if ($errors): ?><div class="error"><strong>Controlla questi elementi:</strong><ul><?php foreach ($errors as $err): ?><li><?=install_h($err)?></li><?php endforeach; ?></ul></div><?php endif; ?>
<h2>Controlli preliminari</h2><ul><?php foreach ($checks as $label=>$ok): ?><li><?= $ok ? 'OK' : 'DA VERIFICARE' ?> — <?=install_h($label)?></li><?php endforeach; ?></ul>
<form method="post"><input type="hidden" name="install_csrf" value="<?=install_h((string)$_SESSION['install_csrf'])?>"><h2>Database MySQL</h2><label>Host database<input name="db_host" required value="<?=install_h($_POST['db_host']??'localhost')?>"></label><label>Nome database<input name="db_name" required value="<?=install_h($_POST['db_name']??'')?>"></label><label>Utente database<input name="db_user" required value="<?=install_h($_POST['db_user']??'')?>"></label><label>Password database<input name="db_pass" type="password" autocomplete="new-password"></label><label>Prefisso tabelle<input name="db_prefix" value="<?=install_h($_POST['db_prefix']??'mcms_')?>" required><small>Deve essere diverso dai prefissi già presenti nel database, ad esempio mcms_site2_.</small></label>
<h2>Primo amministratore</h2><label>Nome utente<input name="admin_user" required value="<?=install_h($_POST['admin_user']??'')?>"></label><label>Email amministratore<input name="admin_email" type="email" required value="<?=install_h($_POST['admin_email']??'')?>"></label><label>Password (almeno 9 caratteri)<input name="admin_pass" type="password" minlength="9" required autocomplete="new-password"></label><button type="submit">Verifica e installa</button></form><?php endif; ?></main></body></html>
