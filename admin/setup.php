<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$steps = [1=>'Identità del sito',2=>'Logo e favicon',3=>'Aspetto e colori',4=>'Modulo Pagine',5=>'Prima categoria',6=>'Pagina di prova',7=>'Riepilogo'];
$step = max(1, min(7, (int)($_GET['step'] ?? 1)));
$currentStep = max(1, min(7, (int)cms_setting('setup_step', '1')));
$errors = [];
function cms_ui_strlen(string $value): int { return preg_match_all('/./us', $value, $m) !== false ? count($m[0]) : strlen($value); }
function setup_upload(string $field, string $kind, array &$errors): ?string {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) { $errors[] = 'Caricamento del file non riuscito.'; return null; }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) { $errors[] = 'Ogni immagine deve essere inferiore a 2 MB.'; return null; }
    $info = @getimagesize($file['tmp_name']);
    if (!$info) { $errors[] = 'Il file selezionato non è un’immagine valida.'; return null; }
    $mime = $info['mime'] ?? '';
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if (!isset($allowed[$mime])) { $errors[] = 'Formato immagine non supportato. Usa JPG, PNG, WEBP o GIF.'; return null; }
    if ($kind === 'favicon' && !in_array($mime, ['image/png','image/gif'], true)) { $errors[] = 'Per la favicon usa un file PNG o GIF.'; return null; }
    $dir = dirname(__DIR__) . '/uploads/images';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) { $errors[] = 'Cartella immagini non disponibile.'; return null; }
    $name = $kind . '-' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) { $errors[] = 'Impossibile salvare l’immagine caricata.'; return null; }
    return 'uploads/images/' . $name;
}
function setup_install_pages_module(): void {
    global $pdo, $config;
    $required = ['pages','categories','page_categories'];
    $all = true; foreach ($required as $t) if (!cms_table_exists($t)) $all = false;
    if ($all) return;
    $schema = file_get_contents(dirname(__DIR__) . '/app/schema-pages.sql');
    if ($schema === false) throw new RuntimeException('Schema del modulo non disponibile.');
    $schema = str_replace('{{prefix}}', $config['table_prefix'], $schema);
    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema)));
    foreach ($statements as $sql) $pdo->exec($sql);
    foreach ($required as $t) if (!cms_table_exists($t)) throw new RuntimeException('Installazione incompleta del modulo: ' . $t);
}
if ($step > $currentStep) { header('Location: setup.php?step=' . $currentStep); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $postedStep = max(1, min(7, (int)($_POST['step'] ?? 1)));
    if ($postedStep > $currentStep) { header('Location: setup.php?step=' . $currentStep); exit; }
    if ($postedStep === 1) {
        $name = trim((string)($_POST['site_name'] ?? ''));
        $description = trim((string)($_POST['site_description'] ?? ''));
        if ($name === '' || cms_ui_strlen($name) > 100) $errors[] = 'Inserisci un nome del sito (massimo 100 caratteri).';
        if (cms_ui_strlen($description) > 240) $errors[] = 'La descrizione non può superare 240 caratteri.';
        if (!$errors) { cms_set_setting('site_name', $name); cms_set_setting('site_description', $description); cms_set_setting('setup_step', '2'); header('Location: setup.php?step=2'); exit; }
    } elseif ($postedStep === 2) {
        $logo = setup_upload('site_logo', 'logo', $errors); $favicon = setup_upload('site_favicon', 'favicon', $errors);
        if (!$errors) { if ($logo !== null) cms_set_setting('site_logo', $logo); if ($favicon !== null) cms_set_setting('site_favicon', $favicon); cms_set_setting('setup_step', '3'); header('Location: setup.php?step=3'); exit; }
    } elseif ($postedStep === 3) {
        $eyebrowText=trim((string)($_POST['hero_eyebrow_text']??'')); $heroTitleText=trim((string)($_POST['hero_title_text']??''));
        if (cms_ui_strlen($eyebrowText)>100 || cms_ui_strlen($heroTitleText)>160) $errors[]='I testi iniziali superano la lunghezza consentita.';
        if (!$errors) { cms_set_setting('hero_eyebrow_text',$eyebrowText); cms_set_setting('hero_title_text',$heroTitleText); }
        $fields = ['color_primary'=>'Colore principale','color_background'=>'Sfondo generale','color_surface'=>'Sfondo contenuti','color_text'=>'Colore testo','color_footer_bg'=>'Sfondo footer','color_footer_text'=>'Testo footer','color_header_bg'=>'Sfondo header','color_header_text'=>'Testo header e menu','color_hero_bg'=>'Sfondo area introduttiva','color_hero_eyebrow'=>'Testo piccolo “Sito web”','color_hero_title'=>'Titolo “Benvenuto”','color_hero_text'=>'Descrizione introduttiva']; $values=[];
        foreach ($fields as $key=>$label) { $value=strtoupper(trim((string)($_POST[$key]??''))); if (!preg_match('/^#[0-9A-F]{6}$/',$value)) $errors[]=$label.': inserisci un colore HEX valido.'; $values[$key]=$value; }
        if (!$errors) { foreach($values as $key=>$value) cms_set_setting($key,$value); cms_set_setting('setup_step','4'); header('Location: setup.php?step=4'); exit; }
    } elseif ($postedStep === 4) {
        try { setup_install_pages_module(); cms_set_setting('pages_module_installed','1'); cms_set_setting('setup_step','5'); header('Location: setup.php?step=5'); exit; }
        catch (Throwable $ex) { error_log('MercurioCMS setup module: '.$ex->getMessage()); $errors[]='Non è stato possibile installare il modulo Pagine. Controlla i permessi e il log del server.'; }
    } elseif ($postedStep === 5) {
        if (!cms_table_exists('categories')) $errors[]='Installa prima il modulo Pagine.';
        else {
            $count=(int)$pdo->query('SELECT COUNT(*) FROM '.cms_table('categories'))->fetchColumn();
            $name=trim((string)($_POST['category_name']??''));
            if ($count===0 && ($name==='' || cms_ui_strlen($name)>120)) $errors[]='Inserisci il nome della prima categoria (massimo 120 caratteri).';
            if (!$errors) {
                if ($count===0) { $base=slugify($name); $slug=$base; $n=2; $q=$pdo->prepare('SELECT id FROM '.cms_table('categories').' WHERE slug=?'); while(true){$q->execute([$slug]);if(!$q->fetch())break;$slug=$base.'-'.$n++;} $pdo->prepare('INSERT INTO '.cms_table('categories').' (name,slug,sort_order) VALUES (?,?,0)')->execute([$name,$slug]); }
                cms_set_setting('setup_step','6'); header('Location: setup.php?step=6'); exit;
            }
        }
    } elseif ($postedStep === 6) {
        if (!cms_table_exists('pages') || !cms_table_exists('categories')) $errors[]='Il modulo Pagine non è disponibile.';
        else {
            $pageCount=(int)$pdo->query('SELECT COUNT(*) FROM '.cms_table('pages'))->fetchColumn();
            $categoryId=(int)$pdo->query('SELECT id FROM '.cms_table('categories').' ORDER BY sort_order,id LIMIT 1')->fetchColumn();
            if ($categoryId<1) $errors[]='Crea prima almeno una categoria.';
            if ($pageCount===0 && !$errors) {
                $title=trim((string)($_POST['test_title']??'Pagina di prova')); $content=trim((string)($_POST['test_content']??''));
                if ($title==='' || cms_ui_strlen($title)>220) $errors[]='Inserisci un titolo valido per la pagina di prova.';
                if ($content==='') $errors[]='Inserisci un breve contenuto per la pagina di prova.';
                if (!$errors) {
                    $base=slugify($title);$slug=$base;$n=2;$q=$pdo->prepare('SELECT id FROM '.cms_table('pages').' WHERE slug=?');while(true){$q->execute([$slug]);if(!$q->fetch())break;$slug=$base.'-'.$n++;}
                    $now=date('Y-m-d H:i:s');
                    $pdo->prepare('INSERT INTO '.cms_table('pages').' (title,slug,content,status,template,created_at,updated_at,published_at) VALUES (?,?,?,\'draft\',\'standard\',?,?,NULL)')->execute([$title,$slug,sanitize_page_html($content),$now,$now]);
                    $pageId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO '.cms_table('page_categories').' (page_id,category_id) VALUES (?,?)')->execute([$pageId,$categoryId]);
                    cms_set_setting('setup_test_page_id',(string)$pageId);
                }
            }
            if (!$errors) { cms_set_setting('setup_step','7'); header('Location: setup.php?step=7'); exit; }
        }
    } elseif ($postedStep === 7) {
        $moduleOk=cms_table_exists('pages')&&cms_table_exists('categories')&&cms_table_exists('page_categories');
        $catCount=$moduleOk?(int)$pdo->query('SELECT COUNT(*) FROM '.cms_table('categories'))->fetchColumn():0;
        $pageCount=$moduleOk?(int)$pdo->query('SELECT COUNT(*) FROM '.cms_table('pages'))->fetchColumn():0;
        if (!$moduleOk || $catCount<1 || $pageCount<1) $errors[]='Completa prima il modulo Pagine, una categoria e una pagina.';
        else { cms_set_setting('setup_completed','1'); cms_set_setting('setup_completed_at',date(DATE_ATOM)); cms_set_setting('setup_step','7'); $_SESSION['flash']='Configurazione iniziale completata: modulo Pagine, categoria e pagina di prova sono pronti.'; header('Location: dashboard.php'); exit; }
    }
    $step=$postedStep;
}
$defaults=['color_primary'=>'#245B45','color_background'=>'#F3F6F4','color_surface'=>'#FFFFFF','color_text'=>'#24332B','color_footer_bg'=>'#173F31','color_footer_text'=>'#FFFFFF','color_header_bg'=>'#173F31','color_header_text'=>'#FFFFFF','color_hero_bg'=>'#F3F6F4','color_hero_eyebrow'=>'#245B45','color_hero_title'=>'#173F31','color_hero_text'=>'#24332B'];$values=[];foreach(array_keys($defaults) as $key)$values[$key]=cms_setting($key,$defaults[$key]);
$siteName=cms_setting('site_name','');$siteDescription=cms_setting('site_description','');$logo=cms_setting('site_logo','');$favicon=cms_setting('site_favicon','');
$moduleInstalled=cms_table_exists('pages')&&cms_table_exists('categories')&&cms_table_exists('page_categories');
$categoryCount=$moduleInstalled?(int)$pdo->query('SELECT COUNT(*) FROM '.cms_table('categories'))->fetchColumn():0;
$pageCount=$moduleInstalled?(int)$pdo->query('SELECT COUNT(*) FROM '.cms_table('pages'))->fetchColumn():0;
$firstCategory=$moduleInstalled?$pdo->query('SELECT name FROM '.cms_table('categories').' ORDER BY sort_order,id LIMIT 1')->fetchColumn():false;
$firstPage=$moduleInstalled?$pdo->query('SELECT title,status FROM '.cms_table('pages').' ORDER BY id LIMIT 1')->fetch():false;
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Configurazione iniziale - MercurioCMS</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<?php $adminActive='setup'; require __DIR__.'/_sidebar.php'; ?>
<main class="container"><section class="panel setup-panel"><p class="eyebrow">Prima configurazione · Passaggio <?=$step?> di 7</p><h1>Configuriamo il tuo sito</h1><div class="step-track step-track-seven"><?php foreach($steps as $n=>$label):?><span class="<?=$n===$step?'current':($n<$step?'complete':'')?>"><?=$n?>. <?=e($label)?></span><?php endforeach;?></div><p>Questa procedura ti accompagna nella preparazione del sito. Le impostazioni resteranno modificabili dalla barra laterale.</p>
<?php if($errors):?><div class="error"><strong>Controlla questi elementi:</strong><ul><?php foreach($errors as $err):?><li><?=e($err)?></li><?php endforeach;?></ul></div><?php endif;?>
<?php if($step===1):?><h2>1. Identità del sito</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="step" value="1"><label>Nome del sito<input name="site_name" maxlength="100" required value="<?=e($_POST['site_name']??$siteName)?>" placeholder="Il nome del tuo sito"></label><label>Descrizione breve<input name="site_description" maxlength="240" value="<?=e($_POST['site_description']??$siteDescription)?>" placeholder="Una breve presentazione"></label><div class="form-actions"><button type="submit">Avanti</button></div></form>
<?php elseif($step===2):?><h2>2. Logo e favicon</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="step" value="2"><label>Logo (JPG, PNG, WEBP o GIF; massimo 2 MB)<input type="file" name="site_logo" accept="image/jpeg,image/png,image/webp,image/gif"></label><?php if($logo):?><img class="setup-preview" src="../<?=e($logo)?>" alt="Logo attuale"><?php endif;?><label>Favicon (PNG o GIF; massimo 2 MB)<input type="file" name="site_favicon" accept="image/png,image/gif"></label><?php if($favicon):?><img class="favicon-preview" src="../<?=e($favicon)?>" alt="Favicon attuale"><?php endif;?><p>Puoi saltare questo passaggio e caricare le immagini in seguito.</p><div class="form-actions"><a class="button secondary" href="setup.php?step=1">Indietro</a><button type="submit">Salva e continua</button><button class="button secondary" type="submit" name="skip_images" value="1" formnovalidate>Salta</button></div></form>
<?php elseif($step===3):?><h2>3. Aspetto e colori</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="step" value="3"><h3>Colori generali</h3><div class="color-grid"><?php $labels=['color_primary'=>'Colore principale','color_background'=>'Sfondo generale','color_surface'=>'Sfondo contenuti','color_text'=>'Colore testo','color_footer_bg'=>'Sfondo footer','color_footer_text'=>'Testo footer'];foreach($labels as $key=>$label):?><label class="color-field"><?=e($label)?><input type="color" name="<?=e($key)?>" value="<?=e($values[$key])?>"></label><?php endforeach;?></div><h3>Intestazione (header)</h3><div class="color-grid"><?php foreach(['color_header_bg'=>'Sfondo header','color_header_text'=>'Testo header e menu'] as $key=>$label):?><label class="color-field"><?=e($label)?><input type="color" name="<?=e($key)?>" value="<?=e($values[$key])?>"></label><?php endforeach;?></div><div class="header-color-preview" id="setup-header-preview" style="background:<?=e($values['color_header_bg'])?>;color:<?=e($values['color_header_text'])?>"><strong><?=e($siteName ?: 'Nome del sito')?></strong><span>Home　 ·　 Menu di navigazione</span></div><h3>Testi della pagina iniziale</h3><p>Puoi personalizzare le due scritte. Lascia vuoto un campo per non visualizzarlo.</p><div class="form-grid"><label>Scritta piccola<input name="hero_eyebrow_text" maxlength="100" value="<?=e($_POST['hero_eyebrow_text']??cms_setting('hero_eyebrow_text','Sito web'))?>" placeholder="Es. Il mio sito"></label><label>Titolo principale<input name="hero_title_text" maxlength="160" value="<?=e($_POST['hero_title_text']??cms_setting('hero_title_text','Benvenuto'))?>" placeholder="Es. Benvenuti"></label></div><div class="color-grid"><?php foreach(['color_hero_bg'=>'Sfondo area introduttiva','color_hero_eyebrow'=>'Testo piccolo “Sito web”','color_hero_title'=>'Titolo “Benvenuto”','color_hero_text'=>'Descrizione introduttiva'] as $key=>$label):?><label class="color-field"><?=e($label)?><input type="color" name="<?=e($key)?>" value="<?=e($values[$key])?>"></label><?php endforeach;?></div><div class="setup-live-preview" id="setup-live-preview" style="background:<?=e($values['color_background'])?>;color:<?=e($values['color_text'])?>"><div class="setup-live-header" style="background:<?=e($values['color_header_bg'])?>;color:<?=e($values['color_header_text'])?>"><strong>Anteprima del sito</strong><nav>Home　 Pagine　 Contatti</nav></div><div class="setup-live-hero" style="background:<?=e($values['color_hero_bg'])?>"><span style="color:<?=e($values['color_hero_eyebrow'])?>">IL TUO SITO</span><h3 style="color:<?=e($values['color_hero_title'])?>">Un titolo di esempio</h3><p style="color:<?=e($values['color_hero_text'])?>">Una breve descrizione per verificare la leggibilità dei colori.</p><button type="button" style="background:<?=e($values['color_primary'])?>;border-color:<?=e($values['color_primary'])?>">Pulsante di esempio</button></div><article style="color:<?=e($values['color_text'])?>"><h3>Titolo della pagina</h3><p>Questo è un esempio di testo. <a href="#">Questo è un collegamento</a> e questo è il testo normale.</p><div class="setup-live-card" style="background:<?=e($values['color_surface'])?>">Area contenuti e riquadri</div></article><footer style="background:<?=e($values['color_footer_bg'])?>;color:<?=e($values['color_footer_text'])?>">Footer del sito · Informazioni e contatti</footer></div><div class="hero-color-preview" id="setup-hero-preview" style="background:<?=e($values['color_hero_bg'])?>"><?php $eyebrowPreview=cms_setting('hero_eyebrow_text','Sito web'); $titlePreview=cms_setting('hero_title_text','Benvenuto'); ?><span id="setup-eyebrow-preview" style="color:<?=e($values['color_hero_eyebrow'])?>" <?=trim($eyebrowPreview)===''?'hidden':''?>><?=e($eyebrowPreview)?></span><strong id="setup-title-preview" style="color:<?=e($values['color_hero_title'])?>" <?=trim($titlePreview)===''?'hidden':''?>><?=e($titlePreview)?></strong><p style="color:<?=e($values['color_hero_text'])?>">Viaggi in camper in compagnia</p></div><div class="form-actions"><a class="button secondary" href="setup.php?step=2">Indietro</a><button type="submit">Salva e continua</button></div></form><script>document.querySelectorAll('input[name=hero_eyebrow_text],input[name=hero_title_text]').forEach(el=>el.addEventListener('input',()=>{const target=document.getElementById(el.name==='hero_eyebrow_text'?'setup-eyebrow-preview':'setup-title-preview');target.textContent=el.value;target.hidden=el.value.trim()==='';}));document.querySelectorAll('input[type=color]').forEach(el=>el.addEventListener('input',()=>{const h=document.getElementById('setup-header-preview'),v=document.getElementById('setup-hero-preview');if(el.name==='color_header_bg')h.style.background=el.value;if(el.name==='color_header_text')h.style.color=el.value;const map={color_hero_bg:[v,'background'],color_hero_eyebrow:[v.querySelector('span'),'color'],color_hero_title:[v.querySelector('strong'),'color'],color_hero_text:[v.querySelector('p'),'color']};if(map[el.name])map[el.name][0].style[map[el.name][1]]=el.value;const live=document.getElementById('setup-live-preview');const liveMap={color_primary:['.setup-live-hero button','background'],color_background:['','background'],color_surface:['.setup-live-card','background'],color_text:['article','color'],color_footer_bg:['footer','background'],color_footer_text:['footer','color'],color_header_bg:['.setup-live-header','background'],color_header_text:['.setup-live-header','color'],color_hero_bg:['.setup-live-hero','background'],color_hero_eyebrow:['.setup-live-hero span','color'],color_hero_title:['.setup-live-hero h3','color'],color_hero_text:['.setup-live-hero p','color']};if(liveMap[el.name]){const [selector,prop]=liveMap[el.name];const target=selector?live.querySelector(selector):live;if(target)target.style[prop]=el.value;if(el.name==='color_primary')live.querySelector('.setup-live-hero button').style.borderColor=el.value;}}));</script>
<?php elseif($step===4):?><h2>4. Installazione del modulo Pagine</h2><p>Il modulo permette di creare e organizzare le pagine del sito tramite categorie. È necessario per completare la configurazione iniziale.</p><p>Stato: <strong><?=$moduleInstalled?'Modulo già installato':'Modulo non ancora installato'?></strong></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="step" value="4"><div class="form-actions"><a class="button secondary" href="setup.php?step=3">Indietro</a><button type="submit"><?=$moduleInstalled?'Verifica e continua':'Installa modulo Pagine'?></button></div></form>
<?php elseif($step===5):?><h2>5. Crea la prima categoria</h2><?php if($categoryCount>0):?><p class="status-ok">È già presente almeno una categoria. Prima categoria: <strong><?=e((string)$firstCategory)?></strong></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="step" value="5"><label>Nome della categoria<?php if($categoryCount>0):?><input value="<?=e((string)$firstCategory)?>" disabled><small>La categoria esistente sarà utilizzata per la pagina di prova. Potrai aggiungerne altre successivamente.</small><?php else:?><input name="category_name" maxlength="120" required value="<?=e($_POST['category_name']??'Generale')?>" placeholder="Es. Generale"><?php endif;?></label><div class="form-actions"><a class="button secondary" href="setup.php?step=4">Indietro</a><button type="submit">Conferma categoria</button></div></form>
<?php elseif($step===6):?><h2>6. Crea una pagina di prova</h2><?php if($pageCount>0):?><p class="status-ok">È già presente almeno una pagina: <strong><?=e((string)$firstPage['title'])?></strong> (<?=e((string)$firstPage['status'])?>).</p><?php else:?><p>La pagina verrà salvata come <strong>bozza</strong>: non sarà visibile ai visitatori finché non la pubblicherai.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="step" value="6"><label>Titolo<input name="test_title" maxlength="220" required value="<?=e($_POST['test_title']??'Pagina di prova')?>"></label><label>Contenuto<textarea name="test_content" rows="6" required><?=e($_POST['test_content']??'Questa è una pagina di prova creata durante la prima configurazione di MercurioCMS. Puoi modificarla, pubblicarla oppure eliminarla dalla sezione Pagine.')?></textarea></label><div class="form-actions"><a class="button secondary" href="setup.php?step=5">Indietro</a><button type="submit">Crea pagina di prova</button></div></form><?php endif;?><?php if($pageCount>0):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="step" value="6"><div class="form-actions"><a class="button secondary" href="setup.php?step=5">Indietro</a><button type="submit">Continua</button></div></form><?php endif;?>
<?php elseif($step===7):?><h2>7. Riepilogo</h2><p>Controlla gli elementi predisposti prima di terminare.</p><dl class="settings-summary"><dt>Nome sito</dt><dd><?=e($siteName)?></dd><dt>Logo</dt><dd><?=$logo?'Caricato':'Non caricato'?></dd><dt>Favicon</dt><dd><?=$favicon?'Caricata':'Non caricata'?></dd><dt>Modulo Pagine</dt><dd><?=$moduleInstalled?'Installato':'Non installato'?></dd><dt>Categorie</dt><dd><?=$categoryCount?></dd><dt>Pagine</dt><dd><?=$pageCount?></dd><dt>Pagina di prova</dt><dd><?=$firstPage?e((string)$firstPage['title']).' — '.e((string)$firstPage['status']):'Non presente'?></dd></dl><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="step" value="7"><div class="form-actions"><a class="button secondary" href="setup.php?step=6">Indietro</a><button type="submit">Completa configurazione</button></div></form><?php endif;?></section></main>
</div></div></body></html>
