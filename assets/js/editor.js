/* MercurioCMS - integrazione Summernote Lite 0.9.1; media picker e bozze locali */
(() => {
  const editor = document.getElementById('visual-editor');
  const source = document.getElementById('content-source');
  const preview = document.getElementById('content-preview');
  if (!editor || !source || !preview || !window.jQuery || !jQuery.fn.summernote) return;

  const $editor = jQuery(editor);
  const form = editor.closest('form');
  const modeButtons = [...document.querySelectorAll('.editor-mode')];
  const titleField = form?.querySelector('[name="title"]');
  const slugField = form?.querySelector('[name="slug"]');
  const statusField = form?.querySelector('[name="status"]');
  const pageId = form?.querySelector('[name="id"]')?.value || 'new';
  const draftKey = `mercuriocms-page-draft-${location.pathname}-${pageId}`;
  let activeMode = 'visual', previewReturnMode = 'visual', selectedMedia = null, submitting = false, editorReady = false;

  const normalizeForSave = html => String(html || '')
    .replace(/(?:\.\.\/)?uploads\/images\//g, 'uploads/images/')
    .replace(/href="\.\.\/download\.php\?/g, 'href="download.php?');
  // CSS e fogli di stile sono conservati separatamente: Summernote può modificare
  // il contenuto visuale, ma non deve mai cancellare le risorse CSS della pagina.
  const extractCssResources = html => {
    const resources = [];
    let body = String(html || '');
    body = body.replace(/<style\b[^>]*>[\s\S]*?<\/style\s*>/gi, block => { resources.push(block); return ''; });
    body = body.replace(/<link\b(?=[^>]*\brel\s*=\s*(["'])stylesheet\1)[^>]*>/gi, block => { resources.push(block); return ''; });
    return { body, resources };
  };
  let preservedCssResources = extractCssResources(source.value).resources;
  const sync = () => {
    const activeMarker = getNoteEditor()?.querySelector('.cms-table-cell-active');
    activeMarker?.classList.remove('cms-table-cell-active');
    const visualHtml = normalizeForSave($editor.summernote('code'));
    source.value = normalizeForSave([...preservedCssResources, visualHtml].filter(Boolean).join('\n'));
    activeMarker?.classList.add('cms-table-cell-active');
  };
  const prettyPrintHtml = html => {
    const extracted = extractCssResources(html);
    const doc = new DOMParser().parseFromString(extracted.body, 'text/html');
    const voids = new Set(['AREA','BASE','BR','COL','EMBED','HR','IMG','INPUT','LINK','META','PARAM','SOURCE','TRACK','WBR']);
    const blocks = new Set(['ADDRESS','ARTICLE','ASIDE','BLOCKQUOTE','DIV','FIGURE','FIGCAPTION','FOOTER','H1','H2','H3','H4','H5','H6','HEADER','HR','LI','MAIN','NAV','OL','P','SECTION','TABLE','TBODY','TFOOT','THEAD','TR','UL']);
    const indent = n => '  '.repeat(n);
    const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const render = (node, depth) => {
      if (node.nodeType === Node.TEXT_NODE) return esc(node.nodeValue || '');
      if (node.nodeType !== Node.ELEMENT_NODE) return '';
      const tag = node.tagName.toLowerCase();
      const attrs = [...node.attributes].map(a => ` ${a.name}="${String(a.value).replace(/&/g,'&amp;').replace(/"/g,'&quot;')}"`).join('');
      const open = `<${tag}${attrs}>`;
      if (voids.has(node.tagName)) return indent(depth) + open;
      const children = [...node.childNodes];
      if (!children.some(ch => ch.nodeType === Node.ELEMENT_NODE && blocks.has(ch.tagName)))
        return indent(depth) + open + children.map(ch => render(ch, 0)).join('') + `</${tag}>`;
      return indent(depth) + open + '\n' + children.map(ch => {
        if (ch.nodeType === Node.TEXT_NODE && !(ch.nodeValue || '').trim()) return '';
        return render(ch, depth + 1);
      }).filter(Boolean).join('\n') + '\n' + indent(depth) + `</${tag}>`;
    };
    const formattedBody = [...doc.body.childNodes].map(n => render(n, 0)).filter(Boolean).join('\n');
    return [...extracted.resources, formattedBody].filter(Boolean).join('\n');
  };
  const safeVisualHtml = html => {
    const allowed = new Set(['P','BR','H1','H2','H3','H4','H5','H6','STRONG','B','EM','I','U','S','DEL','MARK','SMALL','SUB','SUP','UL','OL','LI','BLOCKQUOTE','A','HR','PRE','CODE','IMG','TABLE','THEAD','TBODY','TFOOT','TR','TH','TD','CAPTION','DIV','SPAN','SECTION','ARTICLE','ASIDE','HEADER','FOOTER','MAIN','FIGURE','FIGCAPTION']);
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
    const clean = node => {
      if (node.nodeType === Node.TEXT_NODE) return document.createTextNode(node.nodeValue || '');
      if (node.nodeType !== Node.ELEMENT_NODE) return document.createDocumentFragment();
      const tag = node.tagName;
      if (!allowed.has(tag)) { const f=document.createDocumentFragment(); [...node.childNodes].forEach(ch=>f.append(clean(ch))); return f; }
      const el=document.createElement(tag.toLowerCase());
      for (const attr of ['class','id','title','aria-label','role']) {
        const value=node.getAttribute(attr);
        if (value) el.setAttribute(attr,value);
      }
      const inlineStyle=node.getAttribute('style')||'';
      if (inlineStyle && !/(?:expression\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding)/i.test(inlineStyle)) el.setAttribute('style',inlineStyle);
      if (tag==='IMG') {
        const src=(node.getAttribute('src')||'').replace(/^\.\.\//,'');
        if (/^uploads\/images\/[A-Za-z0-9._-]+$/.test(src)) { el.src='../'+src; el.alt=node.getAttribute('alt')||''; el.loading='lazy'; const w=(node.getAttribute('style')||'').match(/width\s*:\s*(\d{1,3})%/i); if(w)el.style.width=Math.max(10,Math.min(100,+w[1]))+'%'; }
        return el;
      }
      if(tag==='A') { const href=node.getAttribute('href')||''; if(/^(https?:\/\/|mailto:|\/|#|uploads\/documents\/[A-Za-z0-9._-]+$|download\.php\?id=[0-9]+$)/i.test(href)&&!/[\x00-\x20]/.test(href)){el.href=href;el.rel='nofollow noopener';} }
      // Conserva soltanto le proprietà di dimensione e allineamento delle tabelle.
      if (['TABLE','TR','TH','TD'].includes(tag)) {
        const rawStyle = node.getAttribute('style') || '';
        if ((tag === 'TH' || tag === 'TD')) {
          for (const attr of ['rowspan','colspan']) {
            const value = Number.parseInt(node.getAttribute(attr) || '', 10);
            if (Number.isInteger(value) && value >= 1 && value <= 100) el.setAttribute(attr, String(value));
          }
        }
        const dimension = (property, units) => {
          const re = new RegExp('(?:^|;)\\s*' + property + '\\s*:\\s*(\\d+(?:\\.\\d+)?)(' + units + ')\\s*(?:;|$)', 'i');
          const m = rawStyle.match(re);
          return m ? `${m[1]}${m[2]}` : '';
        };
        const width = dimension('width', '%|px');
        const height = dimension('height', 'px');
        if (width) el.style.width = width;
        if (height) el.style.height = height;
        if (tag === 'TH' || tag === 'TD') {
          const horizontal = rawStyle.match(/(?:^|;)\s*text-align\s*:\s*(left|center|right)\s*(?:;|$)/i);
          const vertical = rawStyle.match(/(?:^|;)\s*vertical-align\s*:\s*(top|middle|bottom)\s*(?:;|$)/i);
          const background = rawStyle.match(/(?:^|;)\s*background-color\s*:\s*(#[0-9a-f]{3,8}|rgba?\([0-9.,%\s]+\)|[a-z]{3,20})\s*(?:;|$)/i);
          if (horizontal) el.style.textAlign = horizontal[1].toLowerCase();
          if (vertical) el.style.verticalAlign = vertical[1].toLowerCase();
          if (background) el.style.backgroundColor = background[1];
        }
        if (tag === 'TABLE' && /(?:^|;)\s*border-collapse\s*:\s*(collapse|separate)\s*(?:;|$)/i.test(rawStyle))
          el.style.borderCollapse = rawStyle.match(/(?:^|;)\s*border-collapse\s*:\s*(collapse|separate)/i)[1].toLowerCase();
      }
      [...node.childNodes].forEach(ch=>el.append(clean(ch)));
      return el;
    };
    const out=document.createElement('div'); [...doc.body.childNodes].forEach(n=>out.append(clean(n))); return out.innerHTML;
  };

  const initialContent = extractCssResources(source.value).body;
  source.value = initialContent;
  $editor.summernote({
    lang: 'it-IT', height: 360, minHeight: 260, dialogsInBody: true,
    disableDragAndDrop: true, shortcuts: true,
    toolbar: [
      ['style', ['style']], ['font', ['bold','italic','underline','clear']],
      ['fontname', ['fontname']], ['fontsize', ['fontsize']], ['color', ['color']],
      ['para', ['ul','ol','paragraph']], ['table', ['table']],
      ['insert', ['link']], ['view', ['fullscreen']]
    ],
    styleTags: ['p','h2','h3','h4','blockquote'],
    callbacks: {
      // Summernote emette change anche quando viene impostato il contenuto iniziale.
      // In quella fase il resto dell'integrazione non è ancora stato dichiarato:
      // ignoriamo l'evento iniziale e abilitiamo i salvataggi solo a setup completato.
      onChange: () => { if (!editorReady) return; sync(); saveDraft(); },
      onImageUpload: () => { window.alert('Per inserire immagini utilizza il pulsante “Inserisci immagine” e la libreria media di MercurioCMS.'); },
      onPaste: () => { setTimeout(() => { sync(); saveDraft(); }, 0); }
    }
  });
  $editor.summernote('code', safeVisualHtml(initialContent));
  // Da qui in avanti gli eventi change possono usare sync() e saveDraft().
  editorReady = true;

  const setMode = mode => {
    if (mode==='preview') { if(activeMode==='visual')sync(); previewReturnMode=activeMode; const extracted=extractCssResources(source.value); preservedCssResources=extracted.resources; preview.innerHTML=[...preservedCssResources,safeVisualHtml(extracted.body)].join('\n'); }
    else if(mode==='html') { if(activeMode==='visual')sync(); source.value=prettyPrintHtml(source.value); }
    else if(mode==='visual') { if(activeMode==='html'||(activeMode==='preview'&&previewReturnMode==='html')) { const extracted=extractCssResources(source.value); preservedCssResources=extracted.resources; source.value=extracted.body; $editor.summernote('code',safeVisualHtml(extracted.body)); } }
    getNoteEditor()?.classList.toggle('cms-editor-hidden',mode!=='visual');
    tableTools.hidden = mode !== 'visual' || !activeCell;
    if (mode === 'visual' && activeCell) positionTableTools(activeCell);
    source.hidden=mode!=='html'; preview.hidden=mode!=='preview';
    modeButtons.forEach(b=>{const on=b.dataset.mode===mode;b.classList.toggle('active',on);b.setAttribute('aria-pressed',on?'true':'false');});
    if(mode!=='preview')activeMode=mode;
    if(mode==='visual')$editor.summernote('focus');
  };
  modeButtons.forEach(b=>b.addEventListener('click',()=>setMode(b.dataset.mode)));
  let selectedImage = null;
  $editor.on('click', '.note-editable img', function(){
    selectedImage = this;
    const width = (this.getAttribute('style') || '').match(/width\s*:\s*(\d{1,3})%/i);
    const control = document.getElementById('image-width');
    if (control) control.value = width ? String(Math.max(10, Math.min(100, Number(width[1])))) : '100';
  });
  document.getElementById('image-width')?.addEventListener('change', e=>{
    if (!selectedImage || !getNoteEditor()?.contains(selectedImage)) return;
    selectedImage.style.width = Math.max(10, Math.min(100, Number(e.target.value) || 100)) + '%';
    selectedImage.style.height = 'auto'; sync(); saveDraft();
  });

  // Barra contestuale: struttura tabella, dimensioni di cella/riga/colonna e allineamenti.
  const tableTools = document.createElement('div');
  tableTools.className = 'cms-table-tools';
  tableTools.innerHTML = `
    <div class="cms-table-tools-head"><strong>Strumenti tabella</strong><span data-table-status role="status" aria-live="polite">Seleziona una cella della tabella</span><button type="button" class="cms-table-tools-close" data-table-close aria-label="Chiudi strumenti tabella" title="Chiudi">×</button></div>
    <div class="cms-table-tools-sections">
      <details class="cms-table-tools-section" open>
        <summary>Regolazione rapida</summary>
        <div class="cms-table-tools-subgroup"><span>Dimensioni della cella e della riga selezionata</span><div class="cms-table-tools-buttons"><button type="button" data-table-action="cell-width-minus" title="Riduci larghezza cella">Larghezza −</button><button type="button" data-table-action="cell-width-plus" title="Aumenta larghezza cella">Larghezza +</button><button type="button" data-table-action="row-height-minus" title="Riduci altezza riga">Altezza −</button><button type="button" data-table-action="row-height-plus" title="Aumenta altezza riga">Altezza +</button></div></div>
      </details>
      <details class="cms-table-tools-section">
        <summary>Struttura</summary>
        <div class="cms-table-tools-structure">
          <div class="cms-table-tools-subgroup"><span>Righe</span><div class="cms-table-tools-buttons"><button type="button" data-table-action="row-above">Inserisci sopra</button><button type="button" data-table-action="row-below">Inserisci sotto</button><button type="button" data-table-action="row-delete" class="danger">Elimina riga</button></div></div>
          <div class="cms-table-tools-subgroup"><span>Colonne</span><div class="cms-table-tools-buttons"><button type="button" data-table-action="col-left">Inserisci a sinistra</button><button type="button" data-table-action="col-right">Inserisci a destra</button><button type="button" data-table-action="col-delete" class="danger">Elimina colonna</button></div></div>
        </div>
      </details>
      <details class="cms-table-tools-section">
        <summary>Allineamento e aspetto</summary>
        <div class="cms-table-tools-appearance">
          <div class="cms-table-tools-subgroup"><span>Orizzontale</span><div class="cms-table-tools-buttons"><button type="button" data-table-action="align-left">⇤ Sinistra</button><button type="button" data-table-action="align-center">↔ Centro</button><button type="button" data-table-action="align-right">⇥ Destra</button></div></div>
          <div class="cms-table-tools-subgroup"><span>Verticale</span><div class="cms-table-tools-buttons"><button type="button" data-table-action="valign-top">↑ Alto</button><button type="button" data-table-action="valign-middle">↕ Centro</button><button type="button" data-table-action="valign-bottom">↓ Basso</button></div></div>
          <label class="cms-table-tools-color">Colore di sfondo <input type="color" data-cell-bg value="#ffffff" aria-label="Colore di sfondo cella"></label><button type="button" data-table-action="cell-bg-apply">Applica colore</button><button type="button" data-table-action="cell-delete" class="danger">Elimina cella</button>
        </div>
      </details>
    </div>`;
  const tableToolsStyle = document.createElement('style');
  tableToolsStyle.textContent = `
    /* Barra contestuale ancorata nell'editor: occupa uno spazio proprio e non copre i comandi. */
    .note-table-popover{display:none!important}
    .cms-table-tools{position:relative;z-index:20;display:block;box-sizing:border-box;width:100%;max-height:min(34vh,280px);overflow:auto;margin:8px 0;padding:9px;border:1px solid #b9c5d0;border-radius:8px;background:#f8fafc;box-shadow:0 2px 8px rgba(20,35,50,.10);font:12px/1.35 Arial,sans-serif;color:#263442}
    .cms-table-tools[hidden]{display:none!important}.cms-table-tools-head{display:flex;gap:8px;align-items:center;margin-bottom:8px}.cms-table-tools-head strong{font-size:13px;white-space:nowrap}.cms-table-tools-head span{color:#667482;flex:1}.cms-table-tools-close{font-size:18px!important;line-height:1;min-width:26px;padding:1px!important}
    .cms-table-tools-section{border:1px solid #dce2e8;border-radius:6px;margin:0 0 6px;background:#fff}.cms-table-tools-section>summary{cursor:pointer;padding:7px 9px;font-weight:700;color:#33485b;user-select:none}.cms-table-tools-section>summary:hover{background:#f4f7fa}.cms-table-tools-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:7px 12px;padding:2px 9px 9px}.cms-table-tools-field{display:flex;flex-direction:column;gap:4px;color:#4c5b69}.cms-table-tools-control{display:flex;align-items:center;gap:5px}.cms-table-tools button,.cms-table-tools select,.cms-table-tools input{min-height:30px;box-sizing:border-box;border:1px solid #c6d0d9;border-radius:4px;background:#fff;padding:4px 7px;color:#263442;font:inherit}.cms-table-tools button{cursor:pointer;white-space:nowrap}.cms-table-tools button:hover:not(:disabled){background:#eaf0f5}.cms-table-tools button:disabled,.cms-table-tools input:disabled,.cms-table-tools select:disabled{opacity:.5;cursor:not-allowed}.cms-table-tools input[type=number]{width:86px;min-width:72px;flex:1}.cms-table-tools select{min-width:54px}.cms-table-tools-unit{padding:0 3px;color:#667482}.cms-table-tools-control button{min-width:66px}.cms-table-tools-subgroup{display:flex;flex-direction:column;gap:5px;padding:5px 9px 9px}.cms-table-tools-subgroup>span{font-size:11px;font-weight:700;color:#566575}.cms-table-tools-buttons{display:flex;flex-wrap:wrap;gap:5px}.cms-table-tools-buttons button{min-width:34px}.cms-table-tools-structure,.cms-table-tools-appearance{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}.cms-table-tools-color{display:inline-flex;align-items:center;gap:7px;padding:5px 9px}.cms-table-tools input[type=color]{width:38px;min-width:38px;padding:2px;cursor:pointer}.cms-table-tools .danger{color:#9c2d2d;border-color:#dfb8b8}.cms-table-tools .danger:hover:not(:disabled){background:#fff0f0}
    .note-editable td.cms-table-cell-active,.note-editable th.cms-table-cell-active{outline:2px solid #2878bd;outline-offset:-2px;background-image:linear-gradient(rgba(40,120,189,.045),rgba(40,120,189,.045))}
    @media(max-width:700px){.cms-table-tools{width:100%;max-height:32vh}.cms-table-tools-grid,.cms-table-tools-structure,.cms-table-tools-appearance{grid-template-columns:1fr}}
  `;
  document.head.appendChild(tableToolsStyle);
  // La barra è indipendente dal popover nativo: non deve perdere il focus quando si usano i comandi.
  ['pointerdown','mousedown'].forEach(type => tableTools.addEventListener(type, event => event.stopPropagation()));
  tableTools.hidden = true;
  // Summernote colloca la toolbar principale prima dell'area di scrittura.
  // Inseriamo qui la barra contestuale: resta sempre accessibile e non copre testo o controlli.
  // Summernote 0.9.1 nasconde l'elemento originale e crea .note-editor come fratello successivo.
  // editor.closest('.note-editor') restituisce quindi null: recuperiamo il wrapper reale.
  const getNoteEditor = () => {
    const next = editor.nextElementSibling;
    if (next?.classList.contains('note-editor')) return next;
    return editor.parentElement?.querySelector('.note-editor') || null;
  };
  const noteEditor = getNoteEditor();
  const tableStatus = tableTools.querySelector('[data-table-status]');
  let activeCell = null;
  const editable = noteEditor?.querySelector('.note-editable');
  // .note-editable NON è un figlio diretto di .note-editor: è contenuto
  // in .note-editing-area. Inserire la barra direttamente prima di .note-editable
  // usando noteEditor.insertBefore() genera NotFoundError e interrompe tutto
  // il resto di editor.js (modalità HTML/anteprima, libreria e pulsanti tabella).
  // La barra viene quindi inserita come sorella dell'area di scrittura, subito
  // dopo la toolbar principale di Summernote. Fallback per markup differenti.
  const editingArea = noteEditor?.querySelector('.note-editing-area');
  if (editingArea?.parentElement) {
    editingArea.parentElement.insertBefore(tableTools, editingArea);
  } else if (editable?.parentElement) {
    editable.parentElement.insertBefore(tableTools, editable);
  } else {
    console.warn('MercurioCMS: impossibile individuare il contenitore della barra tabelle.');
  }
  const positionTableTools = cell => {
    if (!cell || activeMode !== 'visual' || !editable?.contains(cell)) { tableTools.hidden = true; return; }
    tableTools.hidden = false;
  };
  const setActiveCell = cell => {
    if (activeCell && activeCell !== cell) activeCell.classList.remove('cms-table-cell-active');
    activeCell = cell && editable?.contains(cell) ? cell : null;
    if (activeCell) activeCell.classList.add('cms-table-cell-active');
    const enabled = !!activeCell;
    tableTools.querySelectorAll('button,input,select').forEach(control => control.disabled = !enabled);
    tableStatus.textContent = activeCell ? `Cella ${activeCell.cellIndex + 1} · riga ${activeCell.parentElement.rowIndex + 1}` : 'Seleziona una cella della tabella';
    tableTools.hidden = !enabled || activeMode !== 'visual';
    if (enabled && activeMode === 'visual') positionTableTools(activeCell);
  };
  tableTools.querySelector('[data-table-close]')?.addEventListener('click', () => setActiveCell(null));
  document.addEventListener('pointerdown', e => {
    if (activeCell && !tableTools.contains(e.target) && !editable?.contains(e.target)) setActiveCell(null);
  });
  const activateCellFromEvent = e => {
    const target = e.target?.nodeType === Node.ELEMENT_NODE ? e.target : e.target?.parentElement;
    const cell = target?.closest?.('td,th');
    if (cell && editable.contains(cell)) setActiveCell(cell);
    else if (editable.contains(target)) setActiveCell(null);
  };
  // Attivazione in capture: intercetta la cella prima dei gestori interni di Summernote.
  editable?.addEventListener('pointerdown', activateCellFromEvent, true);
  editable?.addEventListener('mousedown', activateCellFromEvent, true);
  editable?.addEventListener('click', activateCellFromEvent, true);
  editable?.addEventListener('focusin', activateCellFromEvent, true);
  editable?.addEventListener('keyup', () => {
    const selection = window.getSelection();
    let node = selection?.anchorNode;
    if (node?.nodeType === Node.TEXT_NODE) node = node.parentElement;
    const cell = node?.closest?.('td,th');
    if (cell && editable.contains(cell)) setActiveCell(cell);
  });
  const emptyCell = tag => {
    const cell = document.createElement(tag || 'td');
    cell.innerHTML = '<p><br></p>';
    return cell;
  };
  const getTableContext = () => {
    if (!activeCell || !editable?.contains(activeCell)) return null;
    const row = activeCell.parentElement;
    const table = activeCell.closest('table');
    return table ? {cell:activeCell,row,table,index:activeCell.cellIndex} : null;
  };
  const finishTableEdit = cell => {
    if (cell) setActiveCell(cell);
    sync(); saveDraft();
  };
  tableTools.addEventListener('click', e => {
    const button = e.target.closest('button[data-table-action]');
    if (!button || button.disabled) return;
    e.preventDefault();
    const ctx = getTableContext(); if (!ctx) return;
    const action = button.dataset.tableAction;
    const {cell,row,table,index} = ctx;
    if (action === 'row-above' || action === 'row-below') {
      const newRow = document.createElement('tr');
      [...row.cells].forEach(c => newRow.append(emptyCell(c.tagName.toLowerCase())));
      row.parentElement.insertBefore(newRow, action === 'row-above' ? row : row.nextSibling);
      finishTableEdit(newRow.cells[Math.min(index,newRow.cells.length-1)]);
      return;
    }
    if (action === 'row-delete') {
      if (table.rows.length <= 1) { window.alert('La tabella deve contenere almeno una riga.'); return; }
      const next = row.nextElementSibling || row.previousElementSibling;
      row.remove();
      finishTableEdit(next?.cells[Math.min(index,(next?.cells.length||1)-1)] || null);
      return;
    }
    if (action === 'col-left' || action === 'col-right') {
      let firstInserted = null;
      [...table.rows].forEach(r => {
        const reference = r.cells[index];
        const tag = reference?.tagName.toLowerCase() || (r.parentElement.tagName === 'THEAD' ? 'th' : 'td');
        const added = emptyCell(tag);
        if (reference) r.insertBefore(added, action === 'col-left' ? reference : reference.nextSibling);
        else r.append(added);
        if (r === row) firstInserted = added;
      });
      finishTableEdit(firstInserted);
      return;
    }
    if (action === 'col-delete') {
      if ([...table.rows].some(r => r.cells.length <= 1)) { window.alert('Non è possibile eliminare l’unica colonna rimasta.'); return; }
      [...table.rows].forEach(r => { if (r.cells[index]) r.deleteCell(index); });
      const replacement = row.cells[Math.min(index,row.cells.length-1)] || row.cells[row.cells.length-1];
      finishTableEdit(replacement || null);
      return;
    }
    if (action === 'cell-width-minus' || action === 'cell-width-plus') {
      const current = cell.getBoundingClientRect().width || 100;
      const next = Math.max(20, Math.min(2000, current + (action === 'cell-width-plus' ? 20 : -20)));
      cell.style.width = `${Math.round(next)}px`;
      finishTableEdit(cell); return;
    }
    if (action === 'row-height-minus' || action === 'row-height-plus') {
      const current = row.getBoundingClientRect().height || 48;
      const next = Math.max(10, Math.min(2000, current + (action === 'row-height-plus' ? 10 : -10)));
      row.style.height = `${Math.round(next)}px`;
      [...row.cells].forEach(c => c.style.height = `${Math.round(next)}px`);
      finishTableEdit(cell); return;
    }
    if (action === 'cell-bg-apply') {
      const color = tableTools.querySelector('[data-cell-bg]')?.value || '';
      if (/^#[0-9a-f]{6}$/i.test(color)) { cell.style.backgroundColor = color; finishTableEdit(cell); }
      return;
    }
    if (action === 'cell-delete') {
      if (row.cells.length <= 1) { window.alert('Non è possibile eliminare l’unica cella della riga.'); return; }
      const next = row.cells[index + 1] || row.cells[index - 1];
      row.deleteCell(index); finishTableEdit(next || null); return;
    }
    const horizontal = {'align-left':'left','align-center':'center','align-right':'right'}[action];
    const vertical = {'valign-top':'top','valign-middle':'middle','valign-bottom':'bottom'}[action];
    if (horizontal) cell.style.textAlign = horizontal;
    if (vertical) cell.style.verticalAlign = vertical;
    if (horizontal || vertical) finishTableEdit(cell);
  });
  setActiveCell(null);

  const picker=document.getElementById('media-picker');
  const itemsBox=document.getElementById('media-picker-items');
  const message=document.getElementById('media-picker-message');
  const filter=document.getElementById('media-picker-filter');
  const uploadInput=document.getElementById('media-picker-upload');
  const uploadType=document.getElementById('media-picker-upload-type');
  const insertBox=document.getElementById('media-picker-insert');
  const csrf=document.getElementById('media-picker-csrf')?.value||'';
  let mediaItems=[];
  const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const showPicker=async(type='all')=>{if(activeMode!=='visual')setMode('visual');picker.hidden=false;if(filter)filter.value=type;insertBox.hidden=true;selectedMedia=null;message.textContent='Caricamento libreria…';await loadMedia();};
  const closePicker=()=>{if(!picker)return;picker.hidden=true;if(insertBox)insertBox.hidden=true;selectedMedia=null;};
  // Conserva la selezione/cursore (anche dentro TD/TH) prima che il selettore
  // media prenda il focus. Il click da solo può arrivare dopo la perdita del caret.
  const pickerOpenButtons = ['open-media-picker','insert-image-button','insert-document-button']
    .map(id => document.getElementById(id)).filter(Boolean);
  pickerOpenButtons.forEach(button => {
    const preserveEditorRange = () => {
      if (activeMode === 'visual') $editor.summernote('saveRange');
    };
    button.addEventListener('pointerdown', preserveEditorRange);
    button.addEventListener('mousedown', preserveEditorRange);
    button.addEventListener('click', preserveEditorRange);
  });
  document.getElementById('open-media-picker')?.addEventListener('click',()=>showPicker('all'));
  document.getElementById('insert-image-button')?.addEventListener('click',()=>showPicker('image'));
  document.getElementById('insert-document-button')?.addEventListener('click',()=>showPicker('document'));
  document.getElementById('close-media-picker')?.addEventListener('click',closePicker);
  picker?.addEventListener('click',e=>{if(e.target===picker)closePicker();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&picker&&!picker.hidden)closePicker();});
  async function loadMedia(){try{const r=await fetch('media_api.php',{headers:{'Accept':'application/json'},credentials:'same-origin'});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Errore durante il caricamento.');mediaItems=d.items||[];renderMedia();}catch(e){message.textContent=e.message;itemsBox.innerHTML='';}}
  function renderMedia(){const type=filter.value;const list=mediaItems.filter(x=>type==='all'||x.media_type===type);itemsBox.innerHTML=list.length?list.map(x=>`<button type="button" class="media-picker-item ${selectedMedia?.id===x.id?'selected':''}" data-id="${x.id}">${x.media_type==='image'?`<img src="${esc(x.url)}" alt="">`:`<span class="media-picker-doc">${esc((x.original_name.split('.').pop()||'FILE').toUpperCase())}</span>`}<strong>${esc(x.original_name)}</strong><small>${x.media_type==='image'?'Immagine':'Documento'}</small></button>`).join(''):'<p>Nessun file disponibile per questo filtro.</p>';message.textContent=`${list.length} file disponibili. Seleziona una risorsa per inserirla.`;itemsBox.querySelectorAll('[data-id]').forEach(btn=>btn.addEventListener('click',()=>{selectedMedia=mediaItems.find(x=>String(x.id)===btn.dataset.id)||null;renderMedia();insertBox.hidden=!selectedMedia;document.getElementById('media-picker-alt-label').hidden=selectedMedia?.media_type!=='image';document.getElementById('media-picker-link-label').hidden=selectedMedia?.media_type!=='document';document.getElementById('media-picker-link-text').value=selectedMedia?.original_name||'';}));}
  filter?.addEventListener('change',renderMedia);
  document.getElementById('media-picker-upload-button')?.addEventListener('click',async()=>{const file=uploadInput.files?.[0];if(!file){message.textContent='Scegli prima un file da caricare.';return;}const fd=new FormData();fd.append('csrf',csrf);fd.append('media_type',uploadType.value);fd.append('media_file',file);message.textContent='Caricamento in corso…';try{const r=await fetch('media_api.php',{method:'POST',body:fd,credentials:'same-origin',headers:{'Accept':'application/json'}});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Caricamento non riuscito.');uploadInput.value='';await loadMedia();selectedMedia=d.item;renderMedia();insertBox.hidden=false;document.getElementById('media-picker-alt-label').hidden=selectedMedia.media_type!=='image';document.getElementById('media-picker-link-label').hidden=selectedMedia.media_type!=='document';document.getElementById('media-picker-link-text').value=selectedMedia.original_name;message.textContent='File caricato. Ora puoi inserirlo nella pagina.';}catch(e){message.textContent=e.message;}});
  document.getElementById('media-picker-confirm')?.addEventListener('click',()=>{
    if(!selectedMedia)return;
    let html='';
    if(selectedMedia.media_type==='image'){
      const alt=esc(document.getElementById('media-picker-alt').value.trim());
      // Un'immagine inserita nella cella deve restare nel punto selezionato.
      html=`<img src="../uploads/images/${esc(selectedMedia.stored_name)}" alt="${alt}" style="width:100%;height:auto">`;
    }else{
      const label=esc(document.getElementById('media-picker-link-text').value.trim()||selectedMedia.original_name);
      html=`<a href="download.php?id=${Number(selectedMedia.id)}" rel="nofollow noopener">${label}</a>`;
    }
    $editor.summernote('restoreRange');
    $editor.summernote('focus');
    $editor.summernote('pasteHTML',html);
    sync();saveDraft();closePicker();
  });

  const readContent=()=>activeMode==='html'?source.value:(sync(),source.value);
  const snapshot=()=>({title:titleField?.value||'',slug:slugField?.value||'',excerpt:form?.querySelector('[name=excerpt]')?.value||'',background_image:form?.querySelector('[name=background_image]')?.value||'',status:statusField?.value||'draft',content:readContent(),categories:[...form.querySelectorAll('[name="categories[]"]:checked')].map(x=>x.value).sort()});
  let initialSignature='';
  function saveDraft(){if(!form||submitting)return;try{const current=snapshot();if(JSON.stringify(current)===initialSignature){sessionStorage.removeItem(draftKey);return;}sessionStorage.setItem(draftKey,JSON.stringify({...current,baseSignature:initialSignature,mode:activeMode,stamp:Date.now()}));}catch(_) {}}
  function restoreDraft(){try{const raw=sessionStorage.getItem(draftKey);if(!raw)return;const d=JSON.parse(raw);if(!d||Date.now()-d.stamp>86400000||d.baseSignature!==initialSignature){sessionStorage.removeItem(draftKey);return;}if(!(d.content||'').trim()&&!(d.title||'').trim()){sessionStorage.removeItem(draftKey);return;}if(!confirm('È stata trovata una bozza non salvata di questa pagina. Vuoi recuperarla?')){sessionStorage.removeItem(draftKey);return;}if(titleField)titleField.value=d.title||'';if(slugField)slugField.value=d.slug||'';if(statusField)statusField.value=d.status||'draft';const ex=form.querySelector('[name=excerpt]');if(ex)ex.value=d.excerpt||'';const bg=form.querySelector('[name=background_image]');if(bg)bg.value=d.background_image||'';const restored=extractCssResources(d.content||'');preservedCssResources=restored.resources;source.value=restored.body;$editor.summernote('code',safeVisualHtml(restored.body));(d.categories||[]).forEach(v=>{const cb=form.querySelector(`[name="categories[]"][value="${CSS.escape(v)}"]`);if(cb)cb.checked=true;});if(d.mode==='html')setMode('html');else setMode('visual');}catch(_){try{sessionStorage.removeItem(draftKey);}catch(__){}}}
  source.addEventListener('input',saveDraft);form?.addEventListener('input',saveDraft);form?.addEventListener('change',saveDraft);
  form?.addEventListener('submit',()=>{if(activeMode==='visual')sync();else if(activeMode==='preview'&&previewReturnMode==='visual')sync();submitting=true;try{sessionStorage.removeItem(draftKey);}catch(_) {}});
  window.addEventListener('pagehide',()=>{if(!submitting)saveDraft();});
  sync();initialSignature=JSON.stringify(snapshot());restoreDraft();
})();
