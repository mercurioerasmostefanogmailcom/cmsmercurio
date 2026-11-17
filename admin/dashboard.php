<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
if (cms_setting('setup_completed', '0') !== '1') { header('Location: setup.php'); exit; }
$requiredTables = ['pages','categories','page_categories'];
$moduleInstalled = true;
foreach ($requiredTables as $tableName) if (!cms_table_exists($tableName)) $moduleInstalled = false;
$categoryCount = $moduleInstalled ? (int)$pdo->query('SELECT COUNT(*) FROM ' . cms_table('categories'))->fetchColumn() : 0;
$pageCount = $moduleInstalled ? (int)$pdo->query('SELECT COUNT(*) FROM ' . cms_table('pages'))->fetchColumn() : 0;
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard - MercurioCMS <?=e(CMS_VERSION)?></title><link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<?php $adminActive='dashboard'; require __DIR__.'/_sidebar.php'; ?>
<main class="container"><h1>Dashboard</h1><p>Benvenuto nel pannello di gestione.</p>
<?php if ($flash): ?><p class="notice"><?=e($flash)?></p><?php endif; ?>
<div class="cards dashboard-cards">
<section class="panel"><h2>Stato moduli</h2>
<p>Modulo pagine e categorie: <strong><?=$moduleInstalled?'Installato':'Da installare'?></strong></p>
<?php if (!$moduleInstalled): ?><p>Primo passaggio: installa il modulo per attivare la gestione dei contenuti.</p><form method="post" action="upgrade_pages.php"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button type="submit">1. Installa modulo pagine e categorie</button></form>
<?php else: ?><p class="status-ok">Il modulo è pronto.</p><p>Tabelle necessarie: tutte presenti.</p><?php endif; ?>
</section>
<section class="panel"><h2>Contenuti</h2>
<?php if (!$moduleInstalled): ?><p>Per iniziare, installa prima il modulo nella sezione a sinistra.</p><span class="button disabled" aria-disabled="true">Gestisci categorie</span> <span class="button disabled" aria-disabled="true">Gestisci pagine</span>
<?php else: ?>
<div class="workflow-step"><strong>2. Categorie</strong><p><?=$categoryCount>0?'Categorie create: '.$categoryCount:'Crea almeno una categoria prima di inserire le pagine.'?></p><a class="button" href="categories.php">Gestisci categorie</a></div>
<div class="workflow-step"><strong>3. Pagine</strong><p><?=$pageCount>0?'Pagine create: '.$pageCount:'Dopo aver creato una categoria, potrai inserire le pagine.'?></p><?php if ($categoryCount>0): ?><a class="button" href="pages.php">Gestisci pagine</a><?php else: ?><span class="button disabled" aria-disabled="true">Gestisci pagine</span><?php endif; ?></div>
<?php endif; ?>
<p class="public-link"><a href="../index.php" target="_blank" rel="noopener">Apri il sito pubblico</a></p>
</section>
<?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?><section class="panel"><h2>Manutenzione del CMS</h2><p>Versione installata: <strong><?=e(CMS_VERSION)?></strong></p><p>Puoi applicare un pacchetto di aggiornamento dal pannello. Prima dell’operazione viene creato un backup di file e database.</p><a class="button" href="updates.php">Gestisci aggiornamenti</a></section><?php endif; ?>
</div></main></div></div></body></html>
