<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (!empty($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role, display_name, is_active FROM ' . cms_table('admins') . ' WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && (int)$admin['is_active'] === 1 && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_display_name'] = $admin['display_name'] ?? ''; 
        header('Location: dashboard.php'); exit;
    }
    $error = 'Credenziali non valide.';
}
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Accesso MercurioCMS</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body><main class="panel narrow"><h1>MercurioCMS</h1><h2>Accesso amministratore</h2><?php if($error): ?><p class="error"><?=e($error)?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Nome utente<input name="username" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button type="submit">Accedi</button></form></main></body></html>
