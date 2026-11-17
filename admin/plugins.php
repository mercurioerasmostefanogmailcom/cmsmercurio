<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/zip-reader.php';
require_admin();
$errors=[];
$pluginRoot=dirname(__DIR__).'/plugins';
if(!is_dir($pluginRoot) && !mkdir($pluginRoot,0755,true)) $errors[]='Cartella plugins non disponibile.';
function cms_plugin_remove_tree(string $dir): void {
    if(!is_dir($dir) || is_link($dir)) return;
    foreach(scandir($dir) ?: [] as $item){if($item==='.'||$item==='..')continue;$path=$dir.DIRECTORY_SEPARATOR.$item;if(is_link($path)||is_file($path))@unlink($path);elseif(is_dir($path))cms_plugin_remove_tree($path);}
    @rmdir($dir);
}
function cms_plugin_clean_string($value,int $max): string { if(!is_string($value) && !is_numeric($value)) return ''; $v=trim((string)$value); return strlen($v)<=$max?$v:''; }
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check(); $action=(string)($_POST['action']??''); $id=(string)($_POST['plugin_id']??'');
    if($action==='install'){
        if(!class_exists('ZipArchive') && !function_exists('gzinflate')) $errors[]='Il server non dispone delle funzioni necessarie per leggere archivi ZIP (ZipArchive o zlib).';
        elseif(empty($_FILES['plugin_zip']) || ($_FILES['plugin_zip']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($_FILES['plugin_zip']['tmp_name']??'')) $errors[]='Seleziona un archivio ZIP valido.';
        elseif(($_FILES['plugin_zip']['size']??0)>10485760) $errors[]='Il pacchetto supera il limite di 10 MB.';
        else {
            $zip=class_exists('ZipArchive') ? new ZipArchive() : new CmsZipReader(); $opened=$zip->open($_FILES['plugin_zip']['tmp_name']);
            if($opened!==true) $errors[]='Archivio ZIP non leggibile.';
            else {
                $manifestStat=$zip->statName('plugin.json'); $manifestRaw=($manifestStat && (int)$manifestStat['size']<=32768)?$zip->getFromName('plugin.json'):false; $manifest=is_string($manifestRaw)?json_decode($manifestRaw,true,32):null;
                if(!is_array($manifest)) $errors[]='Nel pacchetto manca un file plugin.json valido nella cartella principale.';
                $pid=is_array($manifest)?(string)($manifest['id']??''):'';
                $name=is_array($manifest)?cms_plugin_clean_string($manifest['name']??'',120):'';
                $description=is_array($manifest)?cms_plugin_clean_string($manifest['description']??'',500):'';
                $version=is_array($manifest)?cms_plugin_clean_string($manifest['version']??'',40):'';
                $minVersion=is_array($manifest)?cms_plugin_clean_string($manifest['min_cms_version']??CMS_VERSION,40):'';
                $entry=is_array($manifest)?(is_string($manifest['entry']??null)?$manifest['entry']:'plugin.php'):'';
                if(!preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/',$pid))$errors[]='Identificativo estensione non valido.';
                if($name===''||$version===''||$minVersion==='')$errors[]='Il manifest deve indicare nome, versione e versione minima di MercurioCMS.';
                if(!preg_match('~^[A-Za-z0-9_/-]+\.php$~',$entry)||preg_match('~(^|/)\.\.(?:/|$)~',$entry))$errors[]='File di avvio non valido nel manifest.';
                if($minVersion!=='' && version_compare(CMS_VERSION,$minVersion,'<'))$errors[]='Questa estensione richiede MercurioCMS '.$minVersion.' o superiore.';
                if($pid!=='' && is_dir($pluginRoot.'/'.$pid))$errors[]='Esiste già una cartella per questa estensione.';
                if($pid!=='' && cms_table_exists('plugins')){$q=$pdo->prepare('SELECT plugin_id FROM '.cms_table('plugins').' WHERE plugin_id=?');$q->execute([$pid]);if($q->fetch())$errors[]='Questa estensione risulta già installata.';}
                $count=$zip->numFiles; $total=0; $seen=[];
                if($count>100)$errors[]='Il pacchetto contiene troppi elementi (massimo 100).';
                for($i=0;!$errors && $i<$count;$i++){
                    $stat=$zip->statIndex($i); if(!$stat) {$errors[]='Voce ZIP non valida.';break;}
                    $path=(string)$stat['name']; $total+=(int)$stat['size'];
                    if($path===''||str_contains($path,"\0")||str_starts_with($path,'/')||str_contains($path,'\\')||preg_match('~(^|/)\.\.(?:/|$)~',$path)||preg_match('/^[A-Za-z]:/',$path)){$errors[]='Il pacchetto contiene un percorso non consentito.';break;}
                    if(isset($seen[$path])){$errors[]='Il pacchetto contiene nomi di file duplicati.';break;}$seen[$path]=true;
                    if($total>20971520 || (int)$stat['size']>5242880){$errors[]='Il contenuto estratto supera i limiti consentiti.';break;}
                    $opsys=0;$attr=0;if($zip->getExternalAttributesIndex($i,$opsys,$attr)){ $mode=($attr>>16)&0170000; if($mode===0120000){$errors[]='I collegamenti simbolici non sono ammessi nei pacchetti.';break;} }
                }
                if(!$errors && !isset($seen[$entry]))$errors[]='Il file PHP indicato come entry non è presente nel pacchetto.';
                if(!$errors){
                    $target=$pluginRoot.'/'.$pid; $ok=mkdir($target,0755,true);
                    for($i=0;$ok && $i<$count;$i++){
                        $stat=$zip->statIndex($i);$path=(string)$stat['name']; if(str_ends_with($path,'/')){if(!is_dir($target.'/'.$path))$ok=mkdir($target.'/'.$path,0755,true);continue;}
                        $dest=$target.'/'.$path;$parent=dirname($dest);if(!is_dir($parent)&&!mkdir($parent,0755,true)){$ok=false;break;}
                        $data=$zip->getFromIndex($i);if(!is_string($data)||file_put_contents($dest,$data,LOCK_EX)===false){$ok=false;break;}@chmod($dest,0644);
                    }
                    if(!$ok){cms_plugin_remove_tree($target);$errors[]='Non è stato possibile estrarre in sicurezza il pacchetto.';}
                    else {
                        try{$pdo->prepare('INSERT INTO '.cms_table('plugins').' (plugin_id,name,description,version,min_cms_version,entry_file,is_active,installed_at) VALUES (?,?,?,?,?,?,0,?)')->execute([$pid,$name,$description,$version,$minVersion,$entry,date('Y-m-d H:i:s')]);$_SESSION['flash']='Estensione installata. È disattivata: puoi attivarla quando sei pronto.';header('Location: plugins.php');exit;}
                        catch(Throwable $ex){cms_plugin_remove_tree($target);error_log('MercurioCMS plugin install: '.$ex->getMessage());$errors[]='Installazione non completata: impossibile registrare l’estensione.';}
                    }
                }
                $zip->close();
            }
        }
    } elseif(in_array($action,['activate','deactivate','uninstall'],true) && preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/',$id)){
        $q=$pdo->prepare('SELECT plugin_id FROM '.cms_table('plugins').' WHERE plugin_id=?');$q->execute([$id]);
        if(!$q->fetch())$errors[]='Estensione non trovata.';
        elseif($action==='activate'){$pdo->prepare('UPDATE '.cms_table('plugins').' SET is_active=1 WHERE plugin_id=?')->execute([$id]);$_SESSION['flash']='Estensione attivata.';header('Location: plugins.php');exit;}
        elseif($action==='deactivate'){$pdo->prepare('UPDATE '.cms_table('plugins').' SET is_active=0 WHERE plugin_id=?')->execute([$id]);$_SESSION['flash']='Estensione disattivata. I suoi file restano installati.';header('Location: plugins.php');exit;}
        else {$pdo->prepare('DELETE FROM '.cms_table('plugins').' WHERE plugin_id=?')->execute([$id]);cms_plugin_remove_tree($pluginRoot.'/'.$id);$_SESSION['flash']='Estensione disinstallata e relativi file rimossi.';header('Location: plugins.php');exit;}
    }
}
$plugins=cms_table_exists('plugins')?$pdo->query('SELECT * FROM '.cms_table('plugins').' ORDER BY name')->fetchAll():[];
$zipAvailable=class_exists('ZipArchive') || function_exists('gzinflate');
$zipMethod=class_exists('ZipArchive') ? 'ZipArchive' : 'lettore ZIP integrato (zlib)';
$flash=$_SESSION['flash']??'';unset($_SESSION['flash']);
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Estensioni - MercurioCMS</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body><?php $adminActive='plugins';require __DIR__.'/_sidebar.php';?><main class="container"><h1>Gestione estensioni</h1><p>Installa funzionalità aggiuntive tramite pacchetti ZIP. Dopo l’installazione ogni estensione resta disattivata finché non scegli di attivarla.</p><?php if($flash):?><p class="notice"><?=e($flash)?></p><?php endif;?><?php if($errors):?><div class="error"><ul><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?><section class="panel"><h2>Installa una nuova estensione</h2><?php if(!$zipAvailable):?><p class="error">Il server non dispone di ZipArchive né di zlib: non è possibile leggere i pacchetti ZIP.</p><?php else:?><p class="editor-help">Lettore ZIP disponibile: <?=e($zipMethod)?>.</p><?php endif;?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="install"><label>Pacchetto estensione (.zip)<input type="file" name="plugin_zip" accept=".zip,application/zip" required <?=!$zipAvailable?'disabled':''?>></label><p class="editor-help">Massimo 10 MB. Il pacchetto deve contenere plugin.json e il file PHP di avvio indicato nel manifest.</p><button type="submit" <?=!$zipAvailable?'disabled':''?>>Carica e installa</button></form></section><section class="panel"><h2>Estensioni installate</h2><?php if(!$plugins):?><p>Nessuna estensione installata.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Estensione</th><th>Versione</th><th>Stato</th><th>Azioni</th></tr></thead><tbody><?php foreach($plugins as $pl):?><tr><td><strong><?=e($pl['name'])?></strong><br><small><?=e($pl['description'])?></small><br><small>ID: <?=e($pl['plugin_id'])?></small></td><td><?=e($pl['version'])?></td><td><?=((int)$pl['is_active']===1)?'Attiva':'Disattivata'?></td><td><div class="actions"><?php if((int)$pl['is_active']===1):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="plugin_id" value="<?=e($pl['plugin_id'])?>"><button type="submit" class="secondary">Disattiva</button></form><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="plugin_id" value="<?=e($pl['plugin_id'])?>"><button type="submit">Attiva</button></form><?php endif;?><form method="post" onsubmit="return confirm('Disinstallare definitivamente questa estensione e cancellarne i file?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="uninstall"><input type="hidden" name="plugin_id" value="<?=e($pl['plugin_id'])?>"><button type="submit" class="danger">Disinstalla</button></form></div></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section><section class="panel"><h2>Nota di sicurezza</h2><p>Le estensioni possono contenere codice PHP eseguibile. Installa soltanto pacchetti provenienti da autori e fonti di cui ti fidi. MercurioCMS controlla manifest, percorsi, dimensioni e collegamenti simbolici, ma questi controlli non possono rendere innocuo codice PHP malevolo.</p></section></main></div></div></body></html>
