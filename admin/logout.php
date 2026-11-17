<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }
require_admin();
csrf_check();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']);
}
session_destroy();
header('Location: index.php'); exit;
