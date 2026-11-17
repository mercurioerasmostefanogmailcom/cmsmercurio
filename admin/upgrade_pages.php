<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }
csrf_check();
$required = ['pages','categories','page_categories'];
$allInstalled = true;
foreach ($required as $tableName) if (!cms_table_exists($tableName)) $allInstalled = false;
if ($allInstalled) {
    $_SESSION['flash'] = 'Il modulo pagine e categorie è già installato. Non occorre ripetere l’operazione.';
    header('Location: dashboard.php'); exit;
}
$schema = file_get_contents(__DIR__ . '/../app/schema-pages.sql');
if ($schema === false) { $_SESSION['flash'] = 'File schema non disponibile.'; header('Location: dashboard.php'); exit; }
$schema = str_replace('{{prefix}}', $config['table_prefix'], $schema);
$statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema)));
try {
    foreach ($statements as $sql) $pdo->exec($sql);
    foreach ($required as $tableName) {
        if (!cms_table_exists($tableName)) throw new RuntimeException('Tabella mancante dopo l’installazione: ' . $tableName);
    }
    $_SESSION['flash'] = 'Modulo pagine e categorie installato correttamente. Ora crea almeno una categoria e poi la prima pagina.';
} catch (Throwable $ex) {
    error_log('MercurioCMS pages module install: ' . $ex->getMessage());
    $_SESSION['flash'] = 'Installazione del modulo non completata. Controllare il log del server.';
}
header('Location: dashboard.php'); exit;
