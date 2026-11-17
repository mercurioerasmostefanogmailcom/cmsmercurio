<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
if (!cms_table_exists('categories') || !cms_table_exists('page_categories') || !cms_table_exists('pages')) { header('Location: dashboard.php'); exit; }
$table = cms_table('categories');
$pageTable = cms_table('pages');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $length = preg_match_all('/./us', $name, $chars) !== false ? count($chars[0]) : strlen($name);
        if ($name === '' || $length > 120) {
            $_SESSION['flash'] = 'Inserisci un nome categoria valido (massimo 120 caratteri).';
        } else {
            $slug = slugify($name); $base = $slug; $n = 2;
            while (true) { $q = $pdo->prepare("SELECT id FROM $table WHERE slug=?"); $q->execute([$slug]); if (!$q->fetch()) break; $slug = $base . '-' . $n++; }
            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM $table")->fetchColumn();
            $pdo->prepare("INSERT INTO $table (name,slug,sort_order,is_visible) VALUES (?,?,?,1)")->execute([$name,$slug,$maxOrder + 10]);
            $_SESSION['flash'] = 'Categoria creata.';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $landing = (int)($_POST['landing_page_id'] ?? 0);
        $length = preg_match_all('/./us', $name, $chars) !== false ? count($chars[0]) : strlen($name);
        $q = $pdo->prepare("SELECT id FROM $table WHERE id=?"); $q->execute([$id]);
        if (!$q->fetch()) $_SESSION['flash'] = 'La categoria selezionata non esiste più.';
        elseif ($name === '' || $length > 120) $_SESSION['flash'] = 'Inserisci un nome categoria valido (massimo 120 caratteri).';
        elseif ($landing > 0) {
            $q = $pdo->prepare("SELECT id FROM $pageTable WHERE id=? AND status='published' AND deleted_at IS NULL"); $q->execute([$landing]);
            if (!$q->fetch()) $_SESSION['flash'] = 'La pagina scelta non esiste o non è pubblicata.';
            else { $pdo->prepare("UPDATE $table SET name=?, landing_page_id=? WHERE id=?")->execute([$name,$landing,$id]); $_SESSION['flash'] = 'Categoria aggiornata.'; }
        } else {
            $pdo->prepare("UPDATE $table SET name=?, landing_page_id=NULL WHERE id=?")->execute([$name,$id]);
            $_SESSION['flash'] = 'Categoria aggiornata.';
        }
    } elseif ($action === 'visibility') {
        $id = (int)($_POST['id'] ?? 0);
        $q = $pdo->prepare("UPDATE $table SET is_visible = IF(is_visible=1,0,1) WHERE id=?");
        $q->execute([$id]);
        $_SESSION['flash'] = $q->rowCount() ? 'Visibilità della categoria aggiornata.' : 'Categoria non trovata.';
    } elseif ($action === 'move') {
        $id = (int)($_POST['id'] ?? 0);
        $direction = (string)($_POST['direction'] ?? '');
        $ordered = $pdo->query("SELECT id FROM $table ORDER BY sort_order,id")->fetchAll(PDO::FETCH_COLUMN);
        $position = array_search($id, array_map('intval', $ordered), true);
        if ($position !== false && (($direction === 'up' && $position > 0) || ($direction === 'down' && $position < count($ordered)-1))) {
            $other = $direction === 'up' ? $position - 1 : $position + 1;
            [$ordered[$position], $ordered[$other]] = [$ordered[$other], $ordered[$position]];
            $saveOrder = $pdo->prepare("UPDATE $table SET sort_order=? WHERE id=?");
            foreach ($ordered as $index => $categoryId) $saveOrder->execute([($index + 1) * 10, (int)$categoryId]);
            $_SESSION['flash'] = 'Ordine delle categorie aggiornato.';
        }
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM $table WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        $_SESSION['flash'] = 'Categoria eliminata. Le pagine non sono state cancellate.';
    }
    header('Location: categories.php'); exit;
}
$rows = $pdo->query("SELECT * FROM $table ORDER BY sort_order,id")->fetchAll();
$pages = $pdo->query("SELECT id,title FROM $pageTable WHERE status='published' AND deleted_at IS NULL ORDER BY title")->fetchAll();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Categorie - MercurioCMS</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<?php $adminActive='categories'; require __DIR__.'/_sidebar.php'; ?>
<main class="container"><h1>Categorie</h1><?php if ($flash): ?><p class="notice"><?=e($flash)?></p><?php endif; ?>
<section class="panel"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add"><label>Nuova categoria</label><div class="toolbar"><input name="name" required maxlength="120" placeholder="Nome categoria"><button type="submit">Aggiungi</button></div></form></section>
<section class="panel"><h2>Categorie esistenti</h2><p class="editor-help">Usa le frecce per cambiare l'ordine delle voci nel menu pubblico. Le categorie nascoste restano nell'amministrazione e possono essere mostrate di nuovo in qualsiasi momento.</p>
<?php if (!$rows): ?><p>Nessuna categoria creata.</p><?php else: ?>
<?php foreach ($rows as $i => $r): ?>
<div class="panel" style="margin:14px 0">
<div class="toolbar" style="justify-content:space-between;align-items:center"><strong><?=e($r['name'])?></strong><span><?=((int)($r['is_visible'] ?? 1)===1)?'Visibile nel menu':'Nascosta dal menu'?></span></div>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?=(int)$r['id']?>">
<label>Nome categoria<input name="name" required maxlength="120" value="<?=e($r['name'])?>"></label>
<label>Pagina da mostrare quando si apre la categoria<select name="landing_page_id"><option value="0">Nessuna: mostra l'elenco delle pagine</option><?php foreach ($pages as $pg): ?><option value="<?=(int)$pg['id']?>" <?=((int)($r['landing_page_id'] ?? 0)===(int)$pg['id'])?'selected':''?>><?=e($pg['title'])?></option><?php endforeach; ?></select></label>
<p class="editor-help">Se scegli una pagina pubblicata, il collegamento alla categoria aprirà direttamente quella pagina. L'indirizzo della categoria resta invariato anche se cambi il nome.</p>
<div class="toolbar"><button type="submit">Salva modifiche</button></div></form>
<div class="toolbar" style="margin-top:8px;flex-wrap:wrap">
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="direction" value="up"><button type="submit" class="secondary" <?= $i===0?'disabled':'' ?> aria-label="Sposta <?=e($r['name'])?> in alto">↑ Sposta su</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="direction" value="down"><button type="submit" class="secondary" <?= $i===count($rows)-1?'disabled':'' ?> aria-label="Sposta <?=e($r['name'])?> in basso">↓ Sposta giù</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="visibility"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button type="submit" class="secondary"><?=((int)($r['is_visible'] ?? 1)===1)?'Nascondi dal menu':'Mostra nel menu'?></button></form>
<form method="post" onsubmit="return confirm('Eliminare la categoria? Le pagine resteranno intatte.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button type="submit" class="link-danger">Elimina</button></form>
</div></div>
<?php endforeach; ?><?php endif; ?></section></main></div></div></body></html>
