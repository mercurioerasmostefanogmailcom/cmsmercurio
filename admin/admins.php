<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
$table = cms_table('admins');
$myId = (int)$_SESSION['admin_id'];
$isSuper = (($_SESSION['admin_role'] ?? '') === 'superadmin');
$errors = []; $flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$secondaryCount = $isSuper ? (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE id<>1")->fetchColumn() : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $action = (string)($_POST['action'] ?? 'profile');
    if ($action === 'profile') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $display = trim((string)($_POST['display_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) $errors[] = 'Nome utente non valido (3–60 caratteri: lettere, numeri, punto, trattino o underscore).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Indirizzo email non valido.';
        if (strlen($display) > 480) $errors[] = 'Il nome visualizzato è troppo lungo.';
        $q=$pdo->prepare("SELECT id FROM $table WHERE username=? AND id<>?"); $q->execute([$username,$myId]); if($q->fetch()) $errors[]='Il nome utente è già utilizzato.';
        $q=$pdo->prepare("SELECT id FROM $table WHERE email=? AND id<>?"); $q->execute([$email,$myId]); if($q->fetch()) $errors[]='L’indirizzo email è già utilizzato.';
        if ($password !== '' && strlen($password) < 9) $errors[]='La nuova password deve contenere almeno 9 caratteri.';
        if (!$errors) {
            if ($password !== '') $q=$pdo->prepare("UPDATE $table SET username=?,email=?,display_name=?,password_hash=? WHERE id=?");
            else $q=$pdo->prepare("UPDATE $table SET username=?,email=?,display_name=? WHERE id=?");
            $args = $password !== '' ? [$username,$email,$display,password_hash($password,PASSWORD_DEFAULT),$myId] : [$username,$email,$display,$myId];
            $q->execute($args); $_SESSION['admin_username']=$username; $_SESSION['admin_display_name']=$display;
            $_SESSION['flash']='Profilo aggiornato.'; header('Location: admins.php'); exit;
        }
    } elseif ($action === 'create' && $isSuper) {
        $username=trim((string)($_POST['username']??'')); $email=trim((string)($_POST['email']??'')); $display=trim((string)($_POST['display_name']??'')); $password=(string)($_POST['password']??'');
        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/',$username)) $errors[]='Nome utente non valido.';
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Email non valida.';
        if (strlen($password)<9) $errors[]='La password deve contenere almeno 9 caratteri.';
        $q=$pdo->prepare("SELECT COUNT(*) FROM $table WHERE username=? OR email=?");$q->execute([$username,$email]);if((int)$q->fetchColumn())$errors[]='Nome utente o email già utilizzati.';
        $countSecondary=(int)$pdo->query("SELECT COUNT(*) FROM $table WHERE id<>1")->fetchColumn(); if($countSecondary>=1)$errors[]='È già presente il secondo amministratore. Eliminalo prima di crearne un altro.';
        if (!$errors) { $q=$pdo->prepare("INSERT INTO $table (username,email,password_hash,role,display_name,is_active) VALUES (?,?,?,'admin',?,1)");$q->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),$display]);$_SESSION['flash']='Secondo amministratore creato.';header('Location: admins.php');exit; }
    } elseif ($action === 'toggle' && $isSuper) {
        $target=(int)($_POST['id']??0);
        if ($target > 1 && $target !== $myId) {
            $q=$pdo->prepare("UPDATE $table SET is_active=IF(is_active=1,0,1) WHERE id=? AND role<>'superadmin'");
            $q->execute([$target]);
            $_SESSION['flash']=$q->rowCount() ? 'Stato del secondo amministratore aggiornato.' : 'Non è stato possibile modificare lo stato dell’account.';
        } else { $_SESSION['flash']='Non puoi modificare lo stato dell’amministratore principale o dell’account attualmente utilizzato.'; }
        header('Location: admins.php');exit;
    } elseif ($action === 'delete' && $isSuper) {
        $target=(int)($_POST['id']??0);
        if($target>1 && $target!==$myId){
            $q=$pdo->prepare("DELETE FROM $table WHERE id=? AND role<>'superadmin'");
            $q->execute([$target]);
            $_SESSION['flash']=$q->rowCount() ? 'Secondo amministratore eliminato. Ora puoi crearne uno nuovo.' : 'Non è stato possibile eliminare questo account.';
        } else { $_SESSION['flash']='L’amministratore principale o l’account attualmente utilizzato non può essere eliminato.'; }
        header('Location: admins.php');exit;
    }
}
$q=$pdo->prepare("SELECT id,username,email,display_name,role,is_active FROM $table WHERE id=?");$q->execute([$myId]);$me=$q->fetch();
$others=$isSuper?$pdo->query("SELECT id,username,email,display_name,role,is_active FROM $table ORDER BY id")->fetchAll():[];
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profilo e amministratori - MercurioCMS</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body><?php $adminActive='admins'; require __DIR__.'/_sidebar.php'; ?><main class="container"><h1>Profilo e amministratori</h1><?php if($flash):?><p class="notice"><?=e($flash)?></p><?php endif;?><?php if($errors):?><div class="error"><ul><?php foreach($errors as $x):?><li><?=e($x)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="panel"><h2>Il mio profilo</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="profile"><label>Nome visualizzato<input name="display_name" maxlength="120" value="<?=e($_POST['display_name']??$me['display_name'])?>"></label><label>Nome utente<input name="username" required maxlength="60" value="<?=e($_POST['username']??$me['username'])?>"></label><label>Email<input type="email" name="email" required value="<?=e($_POST['email']??$me['email'])?>"></label><label>Nuova password <small>(lascia vuoto per non modificarla)</small><input type="password" name="password" minlength="9" autocomplete="new-password"></label><button type="submit">Salva profilo</button></form></section>
<?php if($isSuper):?><section class="panel"><h2>Amministratori</h2><p>L'account principale è protetto. Il secondo amministratore può essere attivato, disattivato o eliminato.</p><table><thead><tr><th>Nome utente</th><th>Email</th><th>Ruolo</th><th>Stato</th><th>Azioni</th></tr></thead><tbody><?php foreach($others as $a):?><tr><td><?=e($a['display_name']?:$a['username'])?> (<?=e($a['username'])?>)</td><td><?=e($a['email'])?></td><td><?=e($a['role'])?></td><td><?=$a['is_active']?'Attivo':'Disattivato'?></td><td><?php if((int)$a['id']>1 && (int)$a['id']!==$myId && $a['role']!=='superadmin'):?><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button type="submit" class="secondary"><?=$a['is_active']?'Disattiva':'Attiva'?></button></form> <form method="post" class="inline" onsubmit="return confirm('Eliminare definitivamente il secondo amministratore? L’operazione non può essere annullata.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button type="submit" class="link-danger">Elimina</button></form><?php else:?>Protetto<?php endif;?></td></tr><?php endforeach;?></tbody></table></section>
<?php if($secondaryCount===0):?><section class="panel"><h2>Crea secondo amministratore</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create"><label>Nome visualizzato<input name="display_name" maxlength="120"></label><label>Nome utente<input name="username" required maxlength="60"></label><label>Email<input type="email" name="email" required></label><label>Password (almeno 9 caratteri)<input type="password" name="password" required minlength="9" autocomplete="new-password"></label><button type="submit">Crea amministratore</button></form></section><?php else:?><section class="panel"><h2>Secondo amministratore già presente</h2><p>Per crearne uno diverso, elimina prima quello attuale dalla tabella qui sopra.</p></section><?php endif;?><?php endif;?></main></div></div></body></html>
