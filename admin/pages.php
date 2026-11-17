<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

if (!cms_table_exists('pages') || !cms_table_exists('categories') || !cms_table_exists('page_categories')) { header('Location: dashboard.php'); exit; }
if (isset($_GET['new']) && (int)$pdo->query('SELECT COUNT(*) FROM ' . cms_table('categories'))->fetchColumn() === 0) { $_SESSION['flash'] = 'Prima di creare una pagina, crea almeno una categoria.'; header('Location: categories.php'); exit; }

$pageTable = cms_table('pages');
$categoryTable = cms_table('categories');
$linkTable = cms_table('page_categories');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'restore_revision') {
        $id=(int)($_POST['id']??0); $revisionId=(int)($_POST['revision_id']??0);
        $current=$pdo->prepare("SELECT title,slug,content,excerpt,background_image,status FROM $pageTable WHERE id=? AND deleted_at IS NULL"); $current->execute([$id]); $old=$current->fetch();
        $rev=$pdo->prepare('SELECT title,slug,content,excerpt,background_image,status FROM ' . cms_table('page_revisions') . ' WHERE id=? AND page_id=?'); $rev->execute([$revisionId,$id]); $previous=$rev->fetch();
        if($old && $previous){$slugCheck=$pdo->prepare("SELECT id FROM $pageTable WHERE slug=? AND id<>? AND deleted_at IS NULL");$slugCheck->execute([$previous['slug'],$id]);if($slugCheck->fetch())$previous['slug']=substr(slugify($previous['slug']),0,210).'-ripristinata-'.$id;$pdo->prepare('INSERT INTO '.cms_table('page_revisions').' (page_id,title,slug,content,excerpt,background_image,status,saved_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$id,$old['title'],$old['slug'],$old['content'],$old['excerpt']??'',$old['background_image']??null,$old['status'],date('Y-m-d H:i:s')]);$pdo->prepare("UPDATE $pageTable SET title=?,slug=?,content=?,excerpt=?,background_image=?,status=?,updated_at=? WHERE id=?")->execute([$previous['title'],$previous['slug'],$previous['content'],$previous['excerpt']??'',$previous['background_image']??null,$previous['status'],date('Y-m-d H:i:s'),$id]);$_SESSION['flash']='Versione precedente ripristinata. La versione sostituita è stata conservata nella cronologia.';} else {$_SESSION['flash']='Versione non disponibile.';}
        header('Location: pages.php?edit='.$id); exit;
    } elseif ($action === 'duplicate') {
        $sourceId=(int)($_POST['id']??0); $newTitle=trim((string)($_POST['duplicate_title']??''));
        $q=$pdo->prepare("SELECT title,slug,content,excerpt,background_image FROM $pageTable WHERE id=? AND deleted_at IS NULL");$q->execute([$sourceId]);$sourcePage=$q->fetch();
        if(!$sourcePage || $newTitle==='') { $_SESSION['flash']='Duplicazione non riuscita: indica un nuovo titolo.'; }
        else {
            $base=slugify($newTitle);$slug=$base;$n=2;$q=$pdo->prepare("SELECT id FROM $pageTable WHERE slug=? AND deleted_at IS NULL");
            while(true){$q->execute([$slug]);if(!$q->fetch())break;$slug=$base.'-'.$n++;}
            $now=date('Y-m-d H:i:s');$q=$pdo->prepare("INSERT INTO $pageTable (title,slug,content,excerpt,background_image,status,created_at,updated_at,published_at) VALUES (?,?,?,?,?,'draft',?,?,NULL)");$q->execute([$newTitle,$slug,$sourcePage['content'],$sourcePage['excerpt']??'',$sourcePage['background_image']??null,$now,$now]);$newId=(int)$pdo->lastInsertId();
            $q=$pdo->prepare("INSERT IGNORE INTO $linkTable (page_id,category_id) SELECT ?,category_id FROM $linkTable WHERE page_id=?");$q->execute([$newId,$sourceId]);
            $_SESSION['flash']='Pagina duplicata come bozza. L’originale non è stato modificato.';header('Location: pages.php?edit='.$newId);exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE $pageTable SET deleted_at=? WHERE id=? AND deleted_at IS NULL");
        $stmt->execute([date('Y-m-d H:i:s'),$id]);
        $_SESSION['flash'] = 'Pagina spostata nel cestino. Puoi ripristinarla dalla sezione Cestino.';
    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $content = sanitize_page_html(trim((string)($_POST['content'] ?? '')));
        $excerpt = trim((string)($_POST['excerpt'] ?? ''));
        if ($excerpt === '') {
            $plain = preg_replace('/<\s*img\b[^>]*\balt\s*=\s*([\"\'])(.*?)\1[^>]*>/is', ' $2 ', $content) ?? $content;
            $plain = preg_replace_callback('/<img\b[^>]*\bsrc\s*=\s*([\"\'])(.*?)\1[^>]*>/is', static function($m){ return ' Immagine '; }, $plain) ?? $plain;
            $plain = html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
            $excerpt = function_exists('mb_substr') ? mb_substr($plain, 0, 500, 'UTF-8') : (preg_match_all('/./us', $plain, $chars) !== false ? implode('', array_slice($chars[0], 0, 500)) : substr($plain, 0, 500));
        }
        $backgroundImage = trim((string)($_POST['background_image'] ?? ''));
        $hideTitle = isset($_POST['hide_title']) ? 1 : 0;
        $hideCategory = isset($_POST['hide_category']) ? 1 : 0;
        if ($backgroundImage !== '' && !preg_match('~^uploads/images/[A-Za-z0-9._-]+$~', $backgroundImage)) $backgroundImage = '';
        if (preg_match_all('/./us', $excerpt, $excerptChars) !== false && count($excerptChars[0]) > 500) $excerpt = implode('', array_slice($excerptChars[0], 0, 500));
        $status = (string)($_POST['status'] ?? 'draft');
        if (!in_array($status, ['draft','published','hidden'], true)) $status = 'draft';
        if ($title === '') {
            $_SESSION['flash'] = 'Il titolo è obbligatorio.';
            header('Location: pages.php' . ($id ? '?edit=' . $id : '?new=1')); exit;
        }
        $slug = slugify((string)($_POST['slug'] ?? $title));
        $base = $slug; $n = 2;
        while (true) {
            $check = $pdo->prepare("SELECT id FROM $pageTable WHERE slug=? AND id<>?");
            $check->execute([$slug, $id]);
            if (!$check->fetch()) break;
            $slug = $base . '-' . $n++;
        }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $oldStmt = $pdo->prepare("SELECT title,slug,content,excerpt,background_image,status FROM $pageTable WHERE id=? AND deleted_at IS NULL");
            $oldStmt->execute([$id]); $old = $oldStmt->fetch();
            if ($old && ($old['title'] !== $title || $old['slug'] !== $slug || $old['content'] !== $content || ($old['excerpt']??'') !== $excerpt || ($old['background_image']??'') !== $backgroundImage || $old['status'] !== $status)) {
                $revStmt = $pdo->prepare('INSERT INTO ' . cms_table('page_revisions') . ' (page_id,title,slug,content,excerpt,background_image,status,saved_at) VALUES (?,?,?,?,?,?,?,?)');
                $revStmt->execute([$id,$old['title'],$old['slug'],$old['content'],$old['excerpt']??'',$old['background_image']??null,$old['status'],$now]);
            }
            $stmt = $pdo->prepare("UPDATE $pageTable SET title=?,slug=?,content=?,excerpt=?,background_image=?,hide_title=?,hide_category=?,status=?,updated_at=?,published_at=IF(?='published',COALESCE(published_at,?),published_at) WHERE id=? AND deleted_at IS NULL");
            $stmt->execute([$title,$slug,$content,$excerpt,$backgroundImage,$hideTitle,$hideCategory,$status,$now,$status,$now,$id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO $pageTable (title,slug,content,excerpt,background_image,hide_title,hide_category,status,created_at,updated_at,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$title,$slug,$content,$excerpt,$backgroundImage,$hideTitle,$hideCategory,$status,$now,$now,$status === 'published' ? $now : null]);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->prepare("DELETE FROM $linkTable WHERE page_id=?")->execute([$id]);
        foreach (($_POST['categories'] ?? []) as $catId) {
            $catId = (int)$catId;
            $pdo->prepare("INSERT IGNORE INTO $linkTable (page_id,category_id) VALUES (?,?)")->execute([$id,$catId]);
        }
        $_SESSION['flash'] = 'Pagina salvata.';
        header('Location: pages.php?edit=' . $id); exit;
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
$selectedCats = [];
if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM $pageTable WHERE id=? AND deleted_at IS NULL");
    $stmt->execute([$editId]); $editing = $stmt->fetch();
    if ($editing) {
        $stmt = $pdo->prepare("SELECT category_id FROM $linkTable WHERE page_id=?");
        $stmt->execute([$editId]); $selectedCats = array_map('intval', array_column($stmt->fetchAll(), 'category_id'));
    }
}
$revisions=[]; if($editing){$rv=$pdo->prepare('SELECT id,title,saved_at FROM '.cms_table('page_revisions').' WHERE page_id=? ORDER BY saved_at DESC,id DESC LIMIT 30');$rv->execute([$editId]);$revisions=$rv->fetchAll();}
$categories = $pdo->query("SELECT * FROM $categoryTable ORDER BY sort_order,name")->fetchAll();
$filter = $_GET['status'] ?? 'all';
$where = ' WHERE deleted_at IS NULL' . (in_array($filter,['draft','published','hidden'],true) ? ' AND status=' . $pdo->quote($filter) : '');
$pages = $pdo->query("SELECT id,title,slug,status,updated_at FROM $pageTable$where ORDER BY updated_at DESC")->fetchAll();
$images = $pdo->query("SELECT original_name,stored_name FROM " . cms_table('media') . " WHERE media_type='image' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 100")->fetchAll();
$currentBackground = (string)($editing['background_image'] ?? '');
$documents = $pdo->query("SELECT id,original_name,stored_name FROM " . cms_table('media') . " WHERE media_type='document' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 100")->fetchAll();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pagine - MercurioCMS</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="../assets/vendor/summernote/summernote-lite.min.css"></head><body>
<?php $adminActive='pages'; require __DIR__.'/_sidebar.php'; ?>
<main class="container">
<h1>Gestione pagine</h1>
<?php if ($flash): ?><p class="notice"><?=e($flash)?></p><?php endif; ?>
<div class="toolbar"><a class="button" href="pages.php?new=1">+ Nuova pagina</a>
<a href="pages.php">Tutte</a> · <a href="pages.php?status=published">Pubblicate</a> · <a href="pages.php?status=hidden">Nascoste</a> · <a href="pages.php?status=draft">Bozze</a></div>
<?php if (isset($_GET['new']) || $editing): ?>
<section class="panel"><h2><?= $editing ? 'Modifica pagina' : 'Nuova pagina' ?></h2>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
<label>Titolo</label><input name="title" required maxlength="220" value="<?=e($editing['title'] ?? '')?>">
<label>Indirizzo breve (slug)</label><input name="slug" maxlength="240" value="<?=e($editing['slug'] ?? '')?>" placeholder="generato-dal-titolo">
<label>Riassunto della pagina <small>(facoltativo, massimo 500 caratteri; utilizzato nelle anteprime e nella descrizione SEO)</small></label><textarea name="excerpt" rows="3" maxlength="500" placeholder="Breve descrizione del contenuto della pagina"><?=e($editing['excerpt'] ?? '')?></textarea>
<label>Immagine di sfondo della pagina <small>(facoltativa, seleziona un'immagine già presente nella libreria)</small></label><select name="background_image"><option value="">Nessuna immagine di sfondo</option><?php foreach($images as $im): $bgPath='uploads/images/'.$im['stored_name']; ?><option value="<?=e($bgPath)?>" <?=$currentBackground===$bgPath?'selected':''?>><?=e($im['original_name'])?></option><?php endforeach;?></select><p class="editor-help">L'immagine viene applicata allo sfondo del riquadro della pagina, con una leggera velatura per mantenere leggibile il testo.</p>
<label>Contenuto della pagina</label>
<div class="editor-shell">
<div class="media-insert-row"><strong>Inserisci contenuti multimediali</strong><button type="button" id="open-media-picker" class="secondary">Apri libreria</button><button type="button" id="insert-image-button" class="secondary">Inserisci immagine</button><button type="button" id="insert-document-button" class="secondary">Inserisci documento</button><label class="image-width-control">Larghezza immagine <select id="image-width"><option value="100">100%</option><option value="75">75%</option><option value="50">50%</option><option value="25">25%</option><option value="10">10%</option></select></label><span class="editor-help">Puoi caricare un file o selezionare una risorsa già presente senza lasciare questa pagina.</span></div>
<div id="media-picker" class="media-picker" hidden role="dialog" aria-modal="true" aria-labelledby="media-picker-title"><div class="media-picker-head"><h3 id="media-picker-title">Libreria media</h3><button type="button" id="close-media-picker" aria-label="Chiudi">✕</button></div><div class="media-picker-tools"><label>Mostra<select id="media-picker-filter"><option value="all">Tutti i file</option><option value="image">Solo immagini</option><option value="document">Solo documenti</option></select></label><label>Carica un file<input type="file" id="media-picker-upload" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx"></label><label>Tipo<select id="media-picker-upload-type"><option value="image">Immagine</option><option value="document">Documento</option></select></label><button type="button" id="media-picker-upload-button">Carica</button></div><p id="media-picker-message" class="editor-help" aria-live="polite"></p><div id="media-picker-items" class="media-picker-items"></div><div id="media-picker-insert" class="media-picker-insert" hidden><label id="media-picker-alt-label">Testo alternativo<input id="media-picker-alt" maxlength="250" placeholder="Descrizione dell’immagine"></label><label id="media-picker-link-label" hidden>Testo del collegamento<input id="media-picker-link-text" maxlength="250"></label><button type="button" id="media-picker-confirm">Inserisci nella pagina</button></div></div><input type="hidden" id="media-picker-csrf" value="<?=e(csrf_token())?>">
<div class="editor-modebar" role="group" aria-label="Modalità editor">
<button type="button" class="editor-mode active" data-mode="visual" aria-pressed="true">Editor visuale</button>
<button type="button" class="editor-mode" data-mode="html" aria-pressed="false">Codice HTML</button>
<button type="button" class="editor-mode" data-mode="preview" aria-pressed="false">Anteprima</button>
</div>
<div id="visual-editor" class="visual-editor" aria-label="Testo della pagina"><?= str_replace('src="uploads/images/', 'src="../uploads/images/', sanitize_page_html((string)($editing['content'] ?? ''))) ?></div>
</div>
<textarea id="content-source" name="content" rows="14" spellcheck="false" aria-label="Codice HTML della pagina" hidden><?=e($editing['content'] ?? '')?></textarea>
<div id="content-preview" class="content-preview" hidden aria-label="Anteprima del contenuto"></div>
<p class="editor-help">Puoi lavorare nell'editor visuale oppure modificare direttamente il codice HTML. L'anteprima mostra il contenuto prima del salvataggio. I tag e gli attributi non consentiti vengono rimossi automaticamente quando salvi.</p>
<fieldset><legend>Visualizzazione pubblica</legend><label class="check"><input type="checkbox" name="hide_title" value="1" <?=!empty($editing['hide_title'])?'checked':''?>> Nascondi il titolo della pagina</label><label class="check"><input type="checkbox" name="hide_category" value="1" <?=!empty($editing['hide_category'])?'checked':''?>> Nascondi la categoria / il percorso di navigazione</label><p class="editor-help">Le due opzioni sono indipendenti e riguardano la pagina pubblica.</p></fieldset>
<label>Stato</label><select name="status"><?php foreach(['draft'=>'Bozza','published'=>'Pubblicata','hidden'=>'Pubblicata ma nascosta'] as $v=>$label): ?><option value="<?=$v?>" <?=($editing['status'] ?? 'draft')===$v?'selected':''?>><?=$label?></option><?php endforeach; ?></select>
<?php if ($categories): ?><fieldset><legend>Categorie</legend><?php foreach($categories as $cat): ?><label class="check"><input type="checkbox" name="categories[]" value="<?=(int)$cat['id']?>" <?=in_array((int)$cat['id'],$selectedCats,true)?'checked':''?>> <?=e($cat['name'])?></label><?php endforeach; ?></fieldset><?php endif; ?>
<button type="submit">Salva pagina</button> <a href="pages.php">Annulla</a></form></section>
<?php if($editing): ?><section class="panel"><h2>Cronologia delle modifiche</h2><p>Le versioni precedenti vengono conservate quando salvi modifiche alla pagina.</p><?php if(!$revisions):?><p>Non ci sono ancora versioni precedenti.</p><?php else:?><table><thead><tr><th>Versione salvata il</th><th>Titolo</th><th>Azioni</th></tr></thead><tbody><?php foreach($revisions as $rev):?><tr><td><?=e($rev['saved_at'])?></td><td><?=e($rev['title'])?></td><td><form method="post" class="inline" onsubmit="return confirm('Ripristinare questa versione? La versione attuale sarà conservata nella cronologia.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore_revision"><input type="hidden" name="id" value="<?=$editId?>"><input type="hidden" name="revision_id" value="<?=(int)$rev['id']?>"><button type="submit">Ripristina</button></form></td></tr><?php endforeach;?></tbody></table><?php endif;?></section><?php endif; ?>
<?php endif; ?>
<section class="panel"><h2>Elenco pagine</h2><?php if (!$pages): ?><p>Nessuna pagina presente.</p><?php else: ?><table><thead><tr><th>Titolo</th><th>Stato</th><th>Ultima modifica</th><th>Azioni</th></tr></thead><tbody><?php foreach($pages as $p): ?><tr><td><?=e($p['title'])?><br><small>/<?=e($p['slug'])?></small></td><td><?=e($p['status'])?></td><td><?=e($p['updated_at'])?></td><td><a href="pages.php?edit=<?=(int)$p['id']?>">Modifica</a> <form class="inline duplicate-form" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><input name="duplicate_title" aria-label="Nuovo titolo della copia" placeholder="Titolo della copia" required maxlength="220" style="width:150px"><button type="submit" class="secondary">Duplica</button></form> <form class="inline" method="post" onsubmit="return confirm('Spostare questa pagina nel cestino?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><button class="link-danger">Elimina</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<script src="../assets/js/jquery-3.6.0.min.js" defer></script><script src="../assets/vendor/summernote/summernote-lite.min.js" defer></script><script src="../assets/vendor/summernote/lang/summernote-it-IT.min.js" defer></script><script src="../assets/js/editor.js?v=<?=rawurlencode(CMS_VERSION)?>" defer></script></main></div></div></body></html>
