<?php
/** Front-end pubblico di MercurioCMS. */
declare(strict_types=1);
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    ?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MercurioCMS - configurazione</title><link rel="stylesheet" href="assets/css/public.css"></head><body><main class="site-shell"><h1>MercurioCMS</h1><p>Il sito non è ancora configurato.</p><p><a class="site-button" href="install.php">Avvia installazione</a></p></main></body></html><?php
    exit;
}
require_once __DIR__ . '/app/bootstrap.php';
$moduleInstalled = cms_table_exists('pages') && cms_table_exists('categories') && cms_table_exists('page_categories');
$siteTitle = cms_setting('site_name', 'MercurioCMS');
$siteDescription = cms_setting('site_description', '');
$siteLogo = cms_setting('site_logo', '');
$siteFavicon = cms_setting('site_favicon', '');
$heroEyebrow = trim(cms_setting('hero_eyebrow_text', 'Sito web'));
$heroTitle = trim(cms_setting('hero_title_text', 'Benvenuto'));
$theme = [
    '--primary' => cms_setting('color_primary', '#245B45'),
    '--site-bg' => cms_setting('color_background', '#F3F6F4'),
    '--surface' => cms_setting('color_surface', '#FFFFFF'),
    '--site-text' => cms_setting('color_text', '#24332B'),
    '--footer-bg' => cms_setting('color_footer_bg', '#173F31'),
    '--footer-text' => cms_setting('color_footer_text', '#FFFFFF'),
    '--header-bg' => cms_setting('color_header_bg', '#173F31'),
    '--header-text' => cms_setting('color_header_text', '#FFFFFF'),
    '--hero-bg' => cms_setting('color_hero_bg', '#F3F6F4'),
    '--hero-eyebrow' => cms_setting('color_hero_eyebrow', '#245B45'),
    '--hero-title' => cms_setting('color_hero_title', '#173F31'),
    '--hero-text' => cms_setting('color_hero_text', '#24332B'),
];
$themeStyle = '';
foreach ($theme as $key => $value) if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) $themeStyle .= $key . ':' . $value . ';';
$page = null;
$categoryFilter = trim((string)($_GET['category'] ?? ''));
if ($moduleInstalled && isset($_GET['page'])) {
    $slug = trim((string)$_GET['page']);
    if ($slug !== '') {
        $stmt = $pdo->prepare('SELECT id,title,slug,content,excerpt,background_image,hide_title,hide_category,status,published_at,updated_at FROM ' . cms_table('pages') . " WHERE slug = ? AND status IN ('published','hidden') AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$slug]);
        $page = $stmt->fetch() ?: null;
    }
}
// Se non è stata richiesta una pagina esplicita, verifica la pagina di destinazione
// configurata per la categoria oppure per la homepage.
if ($moduleInstalled && !$page && !isset($_GET['page'])) {
    $landingId = 0;
    if ($categoryFilter !== '') {
        $q = $pdo->prepare('SELECT landing_page_id FROM ' . cms_table('categories') . ' WHERE slug=? LIMIT 1');
        $q->execute([$categoryFilter]);
        $landingId = (int)($q->fetchColumn() ?: 0);
    } elseif (!isset($_GET['page'])) {
        $landingId = (int)cms_setting('home_page_id', '0');
    }
    if ($landingId > 0) {
        $q = $pdo->prepare('SELECT id,title,slug,content,excerpt,background_image,hide_title,hide_category,status,published_at,updated_at FROM ' . cms_table('pages') . " WHERE id=? AND status='published' AND deleted_at IS NULL LIMIT 1");
        $q->execute([$landingId]);
        $page = $q->fetch() ?: null;
    }
}
$categories = [];
$publishedPages = [];
if ($moduleInstalled) {
    $categories = $pdo->query('SELECT id,name,slug FROM ' . cms_table('categories') . ' WHERE is_visible=1 ORDER BY sort_order,id')->fetchAll();
    if ($categoryFilter !== '') {
        $stmt = $pdo->prepare('SELECT p.id,p.title,p.slug,p.excerpt,p.background_image,p.updated_at FROM ' . cms_table('pages') . ' p INNER JOIN ' . cms_table('page_categories') . ' pc ON pc.page_id=p.id INNER JOIN ' . cms_table('categories') . ' c ON c.id=pc.category_id WHERE p.status = \'published\' AND p.deleted_at IS NULL AND c.slug = ? ORDER BY p.published_at DESC,p.updated_at DESC');
        $stmt->execute([$categoryFilter]);
        $publishedPages = $stmt->fetchAll();
    } else {
        $publishedPages = $pdo->query('SELECT id,title,slug,excerpt,background_image,updated_at FROM ' . cms_table('pages') . " WHERE status = 'published' AND deleted_at IS NULL ORDER BY published_at DESC,updated_at DESC")->fetchAll();
    }
}
$requestHost = (string)($_SERVER['HTTP_HOST'] ?? '');
$requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$publicBase = '';
if ($requestHost !== '' && preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $requestHost)) {
    $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    $scriptDir = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
    $publicBase = $requestScheme . '://' . $requestHost . $scriptDir . '/';
}
$absoluteAsset = static function (string $path) use ($publicBase): string {
    $path = ltrim($path, '/');
    return $publicBase !== '' ? $publicBase . $path : $path;
};
$pageTitle = (string)($page['title'] ?? $siteTitle);
$pageDescription = (string)($page['excerpt'] ?? $siteDescription);
$shareImage = $siteLogo !== '' ? $siteLogo : $siteFavicon;
$shareUrl = $publicBase !== '' ? $publicBase . 'index.php' : 'index.php';
if (!empty($_SERVER['QUERY_STRING'])) $shareUrl .= '?' . (string)$_SERVER['QUERY_STRING'];
$iconMime = strtolower(pathinfo($siteFavicon, PATHINFO_EXTENSION)) === 'gif' ? 'image/gif' : 'image/png';
$shareImageMime = strtolower(pathinfo($shareImage, PATHINFO_EXTENSION));
$shareImageMime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'][$shareImageMime] ?? 'image/png';
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($pageTitle)?></title><meta name="description" content="<?=e($pageDescription)?>">
<?php if ($siteFavicon): ?><link rel="icon" type="<?=e($iconMime)?>" href="<?=e($absoluteAsset($siteFavicon))?>"><link rel="shortcut icon" href="<?=e($absoluteAsset($siteFavicon))?>"><?php if ($iconMime === 'image/png'): ?><link rel="apple-touch-icon" href="<?=e($absoluteAsset($siteFavicon))?>"><?php endif; ?><?php endif; ?>
<meta property="og:type" content="website"><meta property="og:title" content="<?=e($pageTitle)?>"><meta property="og:description" content="<?=e($pageDescription)?>"><meta property="og:url" content="<?=e($shareUrl)?>"><?php if ($shareImage): ?><meta property="og:image" content="<?=e($absoluteAsset($shareImage))?>"><meta property="og:image:type" content="<?=e($shareImageMime)?>"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="<?=e($absoluteAsset($shareImage))?>"><?php else: ?><meta name="twitter:card" content="summary"><?php endif; ?><link rel="stylesheet" href="assets/css/public.css"></head><body style="<?=e($themeStyle)?>">
<header class="site-header"><div class="site-shell header-inner"><a class="brand" href="index.php"><?php if ($siteLogo): ?><img src="<?=e($siteLogo)?>" alt=""><?php endif; ?><span><?=e($siteTitle)?></span></a><nav aria-label="Navigazione principale"><a href="index.php">Home</a><?php foreach ($categories as $cat): ?><a href="index.php?category=<?=rawurlencode($cat['slug'])?>"><?=e($cat['name'])?></a><?php endforeach; ?></nav></div></header>
<main class="site-shell">
<?php if (!$moduleInstalled): ?>
<section class="site-panel"><h1>Il sito è in preparazione</h1><p>Il modulo dei contenuti non è ancora installato. Accedi all'area amministrativa per completare la configurazione.</p></section>
<?php elseif ($page): ?>
<article class="site-panel page-panel" style="<?=!empty($page['background_image'])?'--page-background:url(\''.e($page['background_image']).'\')':''?>"><?php if (empty($page['hide_category'])): ?><p class="breadcrumbs"><?php if ($categoryFilter !== ''): ?><a href="index.php">Home</a> / <?=e((string)($pdo->query('SELECT name FROM '.cms_table('categories').' WHERE slug='.$pdo->quote($categoryFilter).' LIMIT 1')->fetchColumn() ?: 'Categoria'))?><?php else: ?><a href="index.php">Home</a><?php endif; ?></p><?php endif; ?><?php if (empty($page['hide_title'])): ?><h1><?=e($page['title'])?></h1><?php endif; ?><div class="page-content"><?=sanitize_page_html((string)$page['content'])?></div></article>
<?php elseif (isset($_GET['page'])): ?>
<section class="site-panel"><h1>Pagina non trovata</h1><p>La pagina richiesta non esiste oppure non è pubblicata.</p><p><a href="index.php">Torna alla Home</a></p></section>
<?php else: ?>
<section class="hero"><?php if ($categoryFilter !== ''): ?><h1>Contenuti</h1><p>Pagine pubblicate in questa categoria.</p><?php else: ?><?php if ($heroEyebrow !== ''): ?><p class="eyebrow"><?=e($heroEyebrow)?></p><?php endif; ?><?php if ($heroTitle !== ''): ?><h1><?=e($heroTitle)?></h1><?php endif; ?><p><?=e($siteDescription !== '' ? $siteDescription : 'Questo sito è gestito con MercurioCMS.')?></p><?php endif; ?></section>
<section class="page-list"><h2><?= $categoryFilter !== '' ? 'Pagine della categoria' : 'Pagine pubblicate' ?></h2>
<?php if (!$publishedPages): ?><p class="empty-state">Non ci sono ancora pagine pubblicate.</p><?php else: ?><div class="page-grid"><?php foreach ($publishedPages as $item): ?><?php $cardBackground=(string)($item['background_image']??''); $cardStyle=''; if(preg_match('~^uploads/images/[A-Za-z0-9._-]+$~',$cardBackground)) $cardStyle="background-image:linear-gradient(rgba(255,255,255,.88),rgba(255,255,255,.88)),url('".e($cardBackground)."');"; ?><article class="page-card"<?= $cardStyle!==''?' style="'.e($cardStyle).'"':'' ?>><h3><a href="index.php?page=<?=rawurlencode($item['slug'])?>"><?=e($item['title'])?></a></h3><?php if ($cardStyle===''): ?><?php if (!empty($item['excerpt'])): ?><p><?=e($item['excerpt'])?></p><?php endif; ?><a class="read-more" href="index.php?page=<?=rawurlencode($item['slug'])?>">Leggi la pagina →</a><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?></section>
<?php endif; ?>
</main><footer class="site-footer"><div class="site-shell"><?=e($siteTitle)?> · Realizzato con MercurioCMS</div></footer></body></html>
