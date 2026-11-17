<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
$errors = [];
$mediaTable = cms_table('media');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check(); $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE $mediaTable SET deleted_at=? WHERE id=? AND deleted_at IS NULL")->execute([date('Y-m-d H:i:s'), $id]);
    $_SESSION['flash'] = 'File spostato nel cestino. Puoi ripristinarlo dalla sezione Cestino.';
    header('Location: media.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $type = ($_POST['media_type'] ?? 'image') === 'document' ? 'document' : 'image';
    if (empty($_FILES['media_file']) || ($_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Seleziona un file valido da caricare.';
    } else {
        $f = $_FILES['media_file'];
        if (($f['size'] ?? 0) > 10485760) $errors[] = 'Il file supera il limite di 10 MB.';
        $original = basename((string)$f['name']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $fi = new finfo(FILEINFO_MIME_TYPE); $mime = (string)$fi->file($f['tmp_name']);
        $imageExt = ['jpg','jpeg','png','gif','webp'];
        $docExt = ['pdf','txt','doc','docx','xls','xlsx','ppt','pptx'];
        if (!$errors && $type === 'image') {
            $info = @getimagesize($f['tmp_name']);
            if (!$info || !in_array($ext, $imageExt, true)) $errors[] = 'Formato immagine non supportato. Usa JPG, PNG, GIF o WEBP.';
            else { $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$info['mime']] ?? ''; $mime = (string)$info['mime']; if ($ext === '') $errors[] = 'Il contenuto del file non corrisponde a un formato immagine ammesso.'; }
        } elseif (!$errors) {
            $genericMimes = ['application/octet-stream','application/zip','application/x-zip','application/x-ole-storage','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/vnd.openxmlformats-officedocument.presentationml.presentation'];
            $knownMimes = ['application/pdf','text/plain','application/msword','application/vnd.ms-excel','application/vnd.ms-powerpoint'];
            if (!in_array($ext, $docExt, true) || (!in_array($mime, $knownMimes, true) && !in_array($mime, $genericMimes, true))) $errors[] = 'Documento non supportato. Formati ammessi: PDF, TXT, DOC, DOCX, XLS, XLSX, PPT e PPTX.';
            if ($ext === 'txt' && $mime !== 'text/plain' && !in_array($mime, $genericMimes, true)) $errors[] = 'Il file TXT non sembra essere un testo valido.';
        }
        if (!$errors) {
            $folder = $type === 'image' ? 'images' : 'documents';
            $dir = dirname(__DIR__) . '/uploads/' . $folder;
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) $errors[] = 'Cartella di destinazione non disponibile.';
            if (!$errors) {
                $stored = bin2hex(random_bytes(16)) . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) $errors[] = 'Impossibile salvare il file.';
                else {
                    $stmt = $pdo->prepare("INSERT INTO $mediaTable (original_name,stored_name,media_type,mime_type,file_size,created_at,deleted_at) VALUES (?,?,?,?,?,?,NULL)");
                    $stmt->execute([$original,$stored,$type,$mime,(int)$f['size'],date('Y-m-d H:i:s')]);
                    $_SESSION['flash'] = 'File caricato nella libreria media.'; header('Location: media.php'); exit;
                }
            }
        }
    }
}
$filter = $_GET['type'] ?? 'all';
$where = in_array($filter, ['image','document'], true) ? ' WHERE media_type=' . $pdo->quote($filter) . ' AND deleted_at IS NULL' : ' WHERE deleted_at IS NULL';
$items = $pdo->query("SELECT * FROM $mediaTable$where ORDER BY created_at DESC")->fetchAll();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Immagini e documenti - MercurioCMS</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<?php $adminActive='media'; require __DIR__.'/_sidebar.php'; ?>
<main class="container"><h1>Libreria media</h1><p>Carica immagini da inserire nelle pagine e documenti da collegare ai contenuti. Dimensione massima: 10 MB per file.</p>
<div class="notice"><strong>Formati ammessi</strong><br>Immagini: JPG, JPEG, PNG, GIF, WEBP.<br>Documenti: PDF, TXT, DOC, DOCX, XLS, XLSX, PPT, PPTX.<br><strong>Attenzione:</strong> PDF e TXT sono normalmente visualizzabili nel browser. I file DOC/DOCX, XLS/XLSX e PPT/PPTX non sono visualizzati direttamente dal browser: devono essere scaricati e aperti con Word, Excel, PowerPoint o programmi compatibili.</div>
<?php if($flash): ?><p class="notice"><?=e($flash)?></p><?php endif; ?><?php if($errors): ?><div class="error"><ul><?php foreach($errors as $err): ?><li><?=e($err)?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="panel"><h2>Carica un file</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Tipo di file<select name="media_type"><option value="image">Immagine</option><option value="document">Documento</option></select></label><label>File<input type="file" name="media_file" required accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx"></label><button type="submit">Carica nella libreria</button></form></section>
<div class="toolbar"><a href="media.php">Tutti</a> · <a href="media.php?type=image">Immagini</a> · <a href="media.php?type=document">Documenti</a></div>
<section class="panel"><h2>File disponibili</h2><?php if(!$items): ?><p>Nessun file caricato.</p><?php else: ?><div class="media-grid"><?php foreach($items as $item): $folder=$item['media_type']==='image'?'images':'documents'; $url='../uploads/'.$folder.'/'.$item['stored_name']; $ext=strtolower(pathinfo($item['original_name'],PATHINFO_EXTENSION)); ?><article class="media-card">
<?php if($item['media_type']==='image'): ?><img src="<?=e($url)?>" alt=""><?php else: ?><div class="media-document"><?=e(strtoupper($ext))?></div><?php endif; ?>
<strong><?=e($item['original_name'])?></strong><small><?=e($item['mime_type'])?> · <?=number_format((int)$item['file_size']/1024,1,',','.')?> KB</small>
<?php if($item['media_type']==='image' || in_array($ext,['pdf','txt'],true)): ?><a href="<?=e($url)?>" target="_blank" rel="noopener">Visualizza</a><?php else: ?><a href="../download.php?id=<?=(int)$item['id']?>">Scarica file</a><?php endif; ?>
<form method="post" onsubmit="return confirm('Spostare questo file nel cestino? Se è utilizzato in una pagina, il collegamento non sarà disponibile finché non lo ripristini.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><button class="link-danger" type="submit">Sposta nel cestino</button></form></article><?php endforeach; ?></div><?php endif; ?></section>
</main></div></div></body></html>
