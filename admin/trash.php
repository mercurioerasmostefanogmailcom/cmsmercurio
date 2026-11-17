<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
if (!cms_table_exists('pages')) { header('Location: dashboard.php'); exit; }
$table = cms_table('pages');
$mediaTable = cms_table('media');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $kind = ($_POST['kind'] ?? 'page') === 'media' ? 'media' : 'page';
    $action = $_POST['action'] ?? '';
    if ($kind === 'media') {
        if ($action === 'restore') {
            $pdo->prepare("UPDATE $mediaTable SET deleted_at=NULL WHERE id=? AND deleted_at IS NOT NULL")->execute([$id]);
            $_SESSION['flash'] = 'File ripristinato.';
        } elseif ($action === 'purge') {
            $st = $pdo->prepare("SELECT stored_name,media_type FROM $mediaTable WHERE id=? AND deleted_at IS NOT NULL");
            $st->execute([$id]); $file = $st->fetch();
            if ($file) {
                $folder = $file['media_type'] === 'image' ? 'images' : 'documents';
                $path = dirname(__DIR__) . '/uploads/' . $folder . '/' . $file['stored_name'];
                if (is_file($path)) unlink($path);
                $pdo->prepare("DELETE FROM $mediaTable WHERE id=? AND deleted_at IS NOT NULL")->execute([$id]);
            }
            $_SESSION['flash'] = 'File eliminato definitivamente.';
        }
    } else {
        if ($action === 'restore') {
            $pdo->prepare("UPDATE $table SET deleted_at=NULL,updated_at=? WHERE id=? AND deleted_at IS NOT NULL")->execute([date('Y-m-d H:i:s'), $id]);
            $_SESSION['flash'] = 'Pagina ripristinata.';
        } elseif ($action === 'purge') {
            $pdo->prepare('DELETE FROM ' . cms_table('page_revisions') . ' WHERE page_id=?')->execute([$id]);
            $pdo->prepare("DELETE FROM $table WHERE id=? AND deleted_at IS NOT NULL")->execute([$id]);
            $_SESSION['flash'] = 'Pagina eliminata definitivamente.';
        }
    }
    header('Location: trash.php'); exit;
}
$items = $pdo->query("SELECT id,title AS label,slug,deleted_at,'page' AS kind,'' AS media_type FROM $table WHERE deleted_at IS NOT NULL UNION ALL SELECT id,original_name AS label,'' AS slug,deleted_at,'media' AS kind,media_type FROM $mediaTable WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cestino - MercurioCMS</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<?php $adminActive='trash'; require __DIR__.'/_sidebar.php'; ?>
<main class="container"><h1>Cestino</h1><p>Le pagine, le immagini e i documenti eliminati restano qui finché non li elimini definitivamente.</p>
<?php if($flash): ?><p class="notice"><?=e($flash)?></p><?php endif; ?>
<section class="panel"><h2>Elementi nel cestino</h2>
<?php if(!$items): ?><p>Il cestino è vuoto.</p><?php else: ?>
<table><thead><tr><th>Elemento</th><th>Eliminato il</th><th>Azioni</th></tr></thead><tbody>
<?php foreach($items as $item): ?><tr><td><?php if($item['kind']==='page'): ?>Pagina: <?php elseif($item['media_type']==='image'): ?>Immagine: <?php else: ?>Documento: <?php endif; ?><?=e($item['label'])?></td><td><?=e($item['deleted_at'])?></td><td>
<form class="inline" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><input type="hidden" name="kind" value="<?=e($item['kind'])?>"><input type="hidden" name="action" value="restore"><button type="submit">Ripristina</button></form>
<form class="inline" method="post" onsubmit="return confirm('Eliminare definitivamente questo elemento?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><input type="hidden" name="kind" value="<?=e($item['kind'])?>"><input type="hidden" name="action" value="purge"><button class="link-danger" type="submit">Elimina definitivamente</button></form>
</td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section></main></div></div></body></html>
