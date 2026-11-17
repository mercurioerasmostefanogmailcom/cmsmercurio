<?php
$adminActive = $adminActive ?? '';
$adminPagesInstalled = cms_table_exists('pages') && cms_table_exists('categories') && cms_table_exists('page_categories');
?>
<div class="admin-shell">
<aside class="admin-sidebar">
  <a class="admin-brand" href="dashboard.php"><span class="brand-mark">M</span><span>MercurioCMS<small>Area amministrativa</small></span></a>
  <nav class="side-nav" aria-label="Menu amministrazione">
    <span class="side-label">PRINCIPALE</span>
    <a class="<?= $adminActive==='dashboard'?'active':'' ?>" href="dashboard.php"><span>▦</span> Dashboard</a>
    <span class="side-label">CONTENUTI</span>
    <?php if ($adminPagesInstalled): ?>
      <a class="<?= $adminActive==='pages'?'active':'' ?>" href="pages.php"><span>▤</span> Pagine</a>
      <a class="<?= $adminActive==='categories'?'active':'' ?>" href="categories.php"><span>▧</span> Categorie</a>
      <a class="<?= $adminActive==='trash'?'active':'' ?>" href="trash.php"><span>♲</span> Cestino</a>
      <a class="<?= $adminActive==='media'?'active':'' ?>" href="media.php"><span>▧</span> Immagini e documenti</a>
    <?php else: ?>
      <span class="side-disabled"><span>▤</span> Pagine <small>Modulo non installato</small></span>
      <span class="side-disabled"><span>▧</span> Categorie</span>
    <?php endif; ?>
    <span class="side-label">CONFIGURAZIONE</span>
    <a class="<?= $adminActive==='setup'?'active':'' ?>" href="setup.php"><span>⚙</span> Configurazione guidata</a>
    <a class="<?= $adminActive==='settings'?'active':'' ?>" href="settings.php"><span>☷</span> Impostazioni</a>
    <a class="<?= $adminActive==='admins'?'active':'' ?>" href="admins.php"><span>♙</span> Profilo e amministratori</a>
    <?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?><a class="<?= $adminActive==='updates'?'active':'' ?>" href="updates.php"><span>↻</span> Aggiornamenti CMS</a><?php endif; ?>
    <span class="side-label">ESTENSIONI</span>
    <a class="<?= $adminActive==='plugins'?'active':'' ?>" href="plugins.php"><span>▣</span> Gestione estensioni</a>
    <?php foreach (($GLOBALS['cms_plugin_admin_menu'] ?? []) as $pluginMenu): ?>
      <a href="<?=e($pluginMenu['url'])?>"><span>↳</span> <?=e($pluginMenu['label'])?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side-bottom">
    <a href="../index.php" target="_blank" rel="noopener"><span>↗</span> Visualizza sito</a>
    <form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button type="submit"><span>⇥</span> Esci</button></form>
  </div>
</aside>
<div class="admin-main">
<div class="mobile-admin-bar"><strong>MercurioCMS</strong><a href="dashboard.php">Dashboard</a></div>
