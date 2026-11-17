<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');
$table = cms_table('media');
function media_api_reply(array $data, int $status=200): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query("SELECT id,original_name,stored_name,media_type,mime_type,file_size FROM $table WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) { $folder = $row['media_type']==='image'?'images':'documents'; $row['url'] = $row['media_type']==='image' ? '../uploads/'.$folder.'/'.$row['stored_name'] : '../download.php?id='.(int)$row['id']; }
    media_api_reply(['ok'=>true,'items'=>$rows]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') media_api_reply(['ok'=>false,'error'=>'Metodo non consentito.'],405);
csrf_check();
$type = ($_POST['media_type'] ?? '') === 'document' ? 'document' : 'image';
if (empty($_FILES['media_file']) || ($_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) media_api_reply(['ok'=>false,'error'=>'Seleziona un file valido.'],400);
$f=$_FILES['media_file']; if (($f['size']??0)>10485760) media_api_reply(['ok'=>false,'error'=>'Il file supera il limite di 10 MB.'],400);
$original=basename((string)$f['name']); $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION)); $fi=new finfo(FILEINFO_MIME_TYPE); $mime=(string)$fi->file($f['tmp_name']);
if ($type==='image') {
    $info=@getimagesize($f['tmp_name']); $map=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!$info || !isset($map[$info['mime']]) || !in_array($ext,['jpg','jpeg','png','gif','webp'],true)) media_api_reply(['ok'=>false,'error'=>'Formato immagine non valido. Usa JPG, PNG, GIF o WEBP.'],400);
    $mime=$info['mime']; $ext=$map[$mime];
} else {
    $allowed=['pdf'=>['application/pdf'],'txt'=>['text/plain','application/octet-stream'],'doc'=>['application/msword','application/octet-stream'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream'],'xls'=>['application/vnd.ms-excel','application/octet-stream'],'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream'],'ppt'=>['application/vnd.ms-powerpoint','application/octet-stream'],'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip','application/octet-stream']];
    if (!isset($allowed[$ext]) || !in_array($mime,$allowed[$ext],true)) media_api_reply(['ok'=>false,'error'=>'Formato documento non supportato.'],400);
}
$folder=$type==='image'?'images':'documents'; $dir=dirname(__DIR__).'/uploads/'.$folder;
if (!is_dir($dir) && !mkdir($dir,0755,true)) media_api_reply(['ok'=>false,'error'=>'Cartella di destinazione non disponibile.'],500);
$stored=bin2hex(random_bytes(16)).'.'.$ext;
if (!move_uploaded_file($f['tmp_name'],$dir.'/'.$stored)) media_api_reply(['ok'=>false,'error'=>'Impossibile salvare il file.'],500);
$st=$pdo->prepare("INSERT INTO $table (original_name,stored_name,media_type,mime_type,file_size,created_at,deleted_at) VALUES (?,?,?,?,?,?,NULL)");
$st->execute([$original,$stored,$type,$mime,(int)$f['size'],date('Y-m-d H:i:s')]);
$id=(int)$pdo->lastInsertId();
media_api_reply(['ok'=>true,'item'=>['id'=>$id,'original_name'=>$original,'stored_name'=>$stored,'media_type'=>$type,'mime_type'=>$mime,'file_size'=>(int)$f['size'],'url'=>$type==='image'?'../uploads/images/'.$stored:'../download.php?id='.$id]]);
