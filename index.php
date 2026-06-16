<?php

/**
 * X-Plore File Manager by Moghadam.pro
 * Two security layers: URL token + session password
 * Version: 2.0 | Dark | Mobile-first | LTR | PWA
 *
 * Layout on the server:
 *   /x/index.php   -> this app            (https://domain.com/x/index.php)
 *   /x/sw.js       -> service worker
 *   /x/asset/      -> icons + manifest + browserconfig
 *   /x/dir/        -> managed root        (https://domain.com/x/dir)  <- uploads land here
 */

// ═══════════════════════════════════════════
// Settings — edit only this block
// ═══════════════════════════════════════════

define('ACCESS_TOKEN',   'YOUR_TOKEN');          // Layer 1: URL token  -> ?t=xxxxxxx
define('ADMIN_PASSWORD', 'YOUR_PASSWORD');       // Layer 2: login password
define('ROOT_PATH',      __DIR__ . '/dir');      // Managed root (the "dir" folder, NOT the app folder)
define('ASSET_URL',      'asset');               // Icon/manifest folder, relative to index.php
define('SESSION_TTL',    3600);                  // Session lifetime in seconds (1 hour)
define('MAX_UPLOAD_MB',  100);                   // Max upload size (MB)
define('BLOCKED_EXT',    ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'phps']); // Blocked upload extensions

// ═══════════════════════════════════════════
session_start();

// Ensure the managed root exists.
if (!is_dir(ROOT_PATH)) {
  @mkdir(ROOT_PATH, 0755, true);
}

// ── Layer 1: URL token check ────────────────
$token = $_GET['t'] ?? $_SESSION['token'] ?? '';
if ($token !== ACCESS_TOKEN) {
  http_response_code(403);
  die('<!DOCTYPE html><html lang="en" dir="ltr"><head><meta charset="utf-8"><title>403</title>
    <style>body{background:#0d0d0f;color:#444;display:flex;align-items:center;justify-content:center;height:100vh;font-family:monospace;margin:0}
    p{color:#333;font-size:13px}</style></head><body><p>403 — Access Denied</p></body></html>');
}
$_SESSION['token'] = $token;

// ── Layer 2: password authentication ────────
if (isset($_POST['logout'])) {
  session_destroy();
  header('Location: ?t=' . ACCESS_TOKEN);
  exit;
}
if (isset($_POST['password'])) {
  if ($_POST['password'] === ADMIN_PASSWORD) {
    $_SESSION['auth'] = true;
    $_SESSION['auth_time'] = time();
  } else {
    $loginError = 'Wrong password.';
  }
}
// Session expiry
if (isset($_SESSION['auth_time']) && (time() - $_SESSION['auth_time']) > SESSION_TTL) {
  session_destroy();
  header('Location: ?t=' . ACCESS_TOKEN);
  exit;
}
if (empty($_SESSION['auth'])) {
  showLogin($loginError ?? null);
  exit;
}
$_SESSION['auth_time'] = time(); // extend session

// ══════════════════════════════════════════
//  Main file-manager logic
// ══════════════════════════════════════════

$baseUrl = '?t=' . ACCESS_TOKEN . '&';
$msg = '';
$msgType = 'ok';

// Current path
$reqPath = $_GET['path'] ?? '';
$currentPath = realpath(ROOT_PATH . '/' . ltrim($reqPath, '/'));
if (!$currentPath || strpos($currentPath, ROOT_PATH) !== 0) {
  $currentPath = ROOT_PATH;
}

// ── Operations ────────────────────────────

// Upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload'])) {
  $file = $_FILES['upload'];
  $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (in_array($ext, BLOCKED_EXT)) {
    $msg = 'This extension is not allowed.';
    $msgType = 'err';
  } elseif ($file['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
    $msg = 'File exceeds the size limit.';
    $msgType = 'err';
  } else {
    $dest = $currentPath . '/' . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $dest)) {
      $msg = 'Uploaded: ' . htmlspecialchars(basename($file['name']));
    } else {
      $msg = 'Upload failed.';
      $msgType = 'err';
    }
  }
}

// Delete
if (isset($_GET['del'])) {
  $target = realpath(ROOT_PATH . '/' . ltrim($_GET['del'], '/'));
  if ($target && strpos($target, ROOT_PATH) === 0 && $target !== ROOT_PATH) {
    if (is_dir($target)) {
      rmdirRecursive($target);
      $msg = 'Folder deleted.';
    } else {
      unlink($target);
      $msg = 'File deleted.';
    }
  }
}

// Create folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mkdir'])) {
  $newDir = $currentPath . '/' . preg_replace('/[^a-zA-Z0-9_\-. ]/', '', $_POST['mkdir']);
  if (@mkdir($newDir)) {
    $msg = 'Folder created.';
  } else {
    $msg = 'Error.';
    $msgType = 'err';
  }
}

// Rename
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_from'])) {
  $from = realpath(ROOT_PATH . '/' . ltrim($_POST['rename_from'], '/'));
  $to   = dirname($from) . '/' . preg_replace('/[^a-zA-Z0-9_\-. ]/', '', $_POST['rename_to']);
  if ($from && strpos($from, ROOT_PATH) === 0) {
    if (rename($from, $to)) {
      $msg = 'Renamed successfully.';
    } else {
      $msg = 'Error.';
      $msgType = 'err';
    }
  }
}

// Download
if (isset($_GET['dl'])) {
  $dlFile = realpath(ROOT_PATH . '/' . ltrim($_GET['dl'], '/'));
  if ($dlFile && is_file($dlFile) && strpos($dlFile, ROOT_PATH) === 0) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($dlFile) . '"');
    header('Content-Length: ' . filesize($dlFile));
    readfile($dlFile);
    exit;
  }
}

// View content
$viewContent = null;
if (isset($_GET['view'])) {
  $vFile = realpath(ROOT_PATH . '/' . ltrim($_GET['view'], '/'));
  if (
    $vFile && is_file($vFile) && strpos($vFile, ROOT_PATH) === 0
    && filesize($vFile) < 512 * 1024
  ) {
    $viewContent = htmlspecialchars(file_get_contents($vFile));
    $viewName    = basename($vFile);
  }
}

// Search
$searchResults = [];
if (isset($_GET['q']) && strlen($_GET['q']) > 1) {
  searchFiles(ROOT_PATH, $_GET['q'], $searchResults);
}

// File listing
$items = listDir($currentPath);

// Breadcrumb path
$breadcrumb = buildBreadcrumb($currentPath);

// ══════════════════════════════════════════
//  Helper functions
// ══════════════════════════════════════════
function listDir($path)
{
  $entries = [];
  foreach (scandir($path) as $item) {
    if ($item === '.' || $item === '..') continue;
    $full = $path . '/' . $item;
    $entries[] = [
      'name'  => $item,
      'path'  => str_replace(ROOT_PATH, '', $full),
      'isDir' => is_dir($full),
      'size'  => is_file($full) ? filesize($full) : null,
      'mtime' => filemtime($full),
    ];
  }
  usort($entries, fn($a, $b) => ($b['isDir'] <=> $a['isDir']) ?: strcmp($a['name'], $b['name']));
  return $entries;
}

function buildBreadcrumb($currentPath)
{
  $rel   = str_replace(ROOT_PATH, '', $currentPath);
  $parts = array_filter(explode('/', $rel));
  $crumbs = [['name' => 'Home', 'path' => '']];
  $acc = '';
  foreach ($parts as $p) {
    $acc .= '/' . $p;
    $crumbs[] = ['name' => $p, 'path' => $acc];
  }
  return $crumbs;
}

function formatSize($bytes)
{
  if ($bytes === null) return '—';
  foreach (['B', 'KB', 'MB', 'GB'] as $u) {
    if ($bytes < 1024) return round($bytes, 1) . ' ' . $u;
    $bytes /= 1024;
  }
  return round($bytes, 1) . ' TB';
}

function fileIcon($name, $isDir)
{
  if ($isDir) return '📁';
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return match (true) {
    in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico']) => '🖼',
    in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'webm'])              => '🎬',
    in_array($ext, ['mp3', 'ogg', 'wav', 'flac', 'm4a'])              => '🎵',
    in_array($ext, ['zip', 'rar', 'tar', 'gz', '7z'])                 => '🗜',
    in_array($ext, ['pdf'])                                       => '📄',
    in_array($ext, ['php', 'js', 'ts', 'py', 'sh', 'css', 'html', 'json', 'xml', 'sql', 'env']) => '📝',
    in_array($ext, ['txt', 'md', 'log', 'csv'])                      => '📃',
    default => '📎',
  };
}

function rmdirRecursive($dir)
{
  foreach (scandir($dir) as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = $dir . '/' . $f;
    is_dir($p) ? rmdirRecursive($p) : unlink($p);
  }
  rmdir($dir);
}

function searchFiles($dir, $q, &$results, $depth = 0)
{
  if ($depth > 6) return;
  foreach (@scandir($dir) as $f) {
    if ($f === '.' || $f === '..') continue;
    $full = $dir . '/' . $f;
    if (stripos($f, $q) !== false)
      $results[] = ['name' => $f, 'path' => str_replace(ROOT_PATH, '', $full), 'isDir' => is_dir($full)];
    if (is_dir($full)) searchFiles($full, $q, $results, $depth + 1);
  }
}

// ══════════════════════════════════════════
//  Render main page
// ══════════════════════════════════════════
$relPath = str_replace(ROOT_PATH, '', $currentPath) ?: '/';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>X-Plore</title>
  <meta name="robots" content="noindex,nofollow">

  <!-- PWA / icons -->
  <link rel="apple-touch-icon" sizes="57x57" href="<?= ASSET_URL ?>/apple-icon-57x57.png">
  <link rel="apple-touch-icon" sizes="60x60" href="<?= ASSET_URL ?>/apple-icon-60x60.png">
  <link rel="apple-touch-icon" sizes="72x72" href="<?= ASSET_URL ?>/apple-icon-72x72.png">
  <link rel="apple-touch-icon" sizes="76x76" href="<?= ASSET_URL ?>/apple-icon-76x76.png">
  <link rel="apple-touch-icon" sizes="114x114" href="<?= ASSET_URL ?>/apple-icon-114x114.png">
  <link rel="apple-touch-icon" sizes="120x120" href="<?= ASSET_URL ?>/apple-icon-120x120.png">
  <link rel="apple-touch-icon" sizes="144x144" href="<?= ASSET_URL ?>/apple-icon-144x144.png">
  <link rel="apple-touch-icon" sizes="152x152" href="<?= ASSET_URL ?>/apple-icon-152x152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= ASSET_URL ?>/apple-icon-180x180.png">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= ASSET_URL ?>/android-icon-192x192.png">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= ASSET_URL ?>/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96" href="<?= ASSET_URL ?>/favicon-96x96.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= ASSET_URL ?>/favicon-16x16.png">
  <link rel="manifest" href="<?= ASSET_URL ?>/manifest.json">
  <meta name="msapplication-TileColor" content="#0c0c0e">
  <meta name="msapplication-TileImage" content="<?= ASSET_URL ?>/ms-icon-144x144.png">
  <meta name="msapplication-config" content="<?= ASSET_URL ?>/browserconfig.xml">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="X-Plore">

  <style>
    :root {
      --bg0: #000000;
      --bg1: #141414;
      --bg2: #1a1a1a;
      --bg3: #222222;
      --line: #2b2b2b;
      --acc: #bd7800;
      --acc2: #a16600;
      --ok: #3ecfb2;
      --err: #f76582;
      --warn: #f1c752;
      --txt: #f0f0f0;
      --txt2: #9c9c9c;
      --txt3: #626262;
      --radius: 8px;
      --font: 'SF Pro Display', 'Segoe UI', system-ui, sans-serif;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      -webkit-tap-highlight-color: transparent;
    }

    body {
      background: var(--bg0);
      color: var(--txt);
      font-family: var(--font);
      font-size: 14px;
      min-height: 100dvh;
    }

    /* ── Header ── */
    .header {
      background: var(--bg1);
      border-bottom: 1px solid var(--line);
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      position: sticky;
      top: 0;
      z-index: 50;
    }

    .header-logo {
      font-size: 18px;
      font-weight: 700;
      color: var(--acc);
      letter-spacing: -0.5px;
      flex-shrink: 0;
    }

    .header-logo span {
      color: var(--txt2);
      font-weight: 400;
      font-size: 13px;
      margin-left: 4px;
    }

    .search-wrap {
      flex: 1;
      position: relative;
    }

    .search-input {
      width: 100%;
      background: var(--bg2);
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 12px 8px 36px;
      color: var(--txt);
      font-size: 13px;
      outline: none;
    }

    .search-input:focus {
      border-color: var(--acc);
    }

    .search-ico {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--txt3);
      font-size: 15px;
      pointer-events: none;
    }

    .btn-logout {
      background: none;
      border: 1px solid var(--line);
      color: var(--txt2);
      border-radius: 8px;
      padding: 7px 10px;
      font-size: 12px;
      cursor: pointer;
      white-space: nowrap;
    }

    /* ── Breadcrumb ── */
    .breadcrumb {
      padding: 10px 16px;
      display: flex;
      align-items: center;
      gap: 4px;
      flex-wrap: wrap;
      border-bottom: 1px solid var(--line);
      background: var(--bg1);
    }

    .bc-item {
      color: var(--txt2);
      font-size: 12px;
      text-decoration: none;
    }

    .bc-item:hover {
      color: var(--acc);
    }

    .bc-sep {
      color: var(--txt3);
      font-size: 11px;
    }

    .bc-current {
      color: var(--txt);
      font-size: 12px;
      font-weight: 600;
    }

    /* ── Message ── */
    .msg {
      margin: 12px 16px 0;
      padding: 10px 14px;
      border-radius: var(--radius);
      font-size: 13px;
      border-left: 3px solid;
    }

    .msg.ok {
      background: rgba(62, 207, 142, .1);
      border-color: var(--ok);
      color: var(--ok);
    }

    .msg.err {
      background: rgba(247, 101, 101, .1);
      border-color: var(--err);
      color: var(--err);
    }

    /* ── Toolbar ── */
    .toolbar {
      padding: 12px 16px;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      border-bottom: 1px solid var(--line);
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      border: none;
      text-decoration: none;
      transition: opacity .15s;
    }

    .btn:active {
      opacity: .7;
    }

    .btn-primary {
      background: var(--acc);
      color: #fff;
    }

    .btn-ghost {
      background: var(--bg2);
      color: var(--txt2);
      border: 1px solid var(--line);
    }

    .btn-danger {
      background: rgba(247, 101, 101, .15);
      color: var(--err);
      border: 1px solid rgba(247, 101, 101, .2);
    }

    /* ── Upload area ── */
    .upload-zone {
      margin: 0 16px 0;
      background: var(--bg2);
      border: 1.5px dashed var(--line);
      border-radius: var(--radius);
      padding: 14px;
      text-align: center;
      display: none;
    }

    .upload-zone.open {
      display: block;
    }

    .upload-zone input {
      display: none;
    }

    .upload-zone label {
      color: var(--acc);
      display: block;
      cursor: pointer;
      font-size: 13px;
      padding: 24px;
      border: 1px dashed;
      border-radius: 8px;
      margin-bottom: 10px;
    }

    .upload-zone .hint {
      color: var(--txt3);
      font-size: 11px;
      margin-top: 4px;
    }

    .upload-filename {
      color: var(--txt2);
      font-size: 12px;
      margin-top: 6px;
    }

    /* ── Modals ── */
    .modal-bg {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .7);
      z-index: 100;
      align-items: flex-end;
      justify-content: center;
    }

    .modal-bg.open {
      display: flex;
    }

    .modal {
      background: var(--bg2);
      border-radius: 16px 16px 0 0;
      width: 100%;
      max-width: 480px;
      padding: 20px 16px 32px;
    }

    .modal h3 {
      font-size: 15px;
      margin-bottom: 12px;
      color: var(--txt);
    }

    .modal input[type=text] {
      width: 100%;
      background: var(--bg3);
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px 12px;
      color: var(--txt);
      font-size: 14px;
      outline: none;
      margin-bottom: 10px;
    }

    .modal input:focus {
      border-color: var(--acc);
    }

    .modal-actions {
      display: flex;
      gap: 8px;
    }

    .modal-actions .btn {
      flex: 1;
      justify-content: center;
    }

    /* ── File list ── */
    .section-title {
      padding: 14px 16px 6px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .8px;
      color: var(--txt3);
    }

    .file-list {
      padding: 0 8px 80px;
    }

    .file-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 10px;
      border-radius: var(--radius);
      margin-bottom: 2px;
      cursor: pointer;
      user-select: none;
      position: relative;
    }

    .file-item:hover,
    .file-item.selected {
      background: var(--bg2);
    }

    .file-icon {
      font-size: 22px;
      flex-shrink: 0;
      width: 36px;
      text-align: center;
    }

    .file-meta {
      flex: 1;
      min-width: 0;
    }

    .file-name {
      font-size: 14px;
      color: var(--txt);
      font-weight: 500;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .file-info {
      font-size: 11px;
      color: var(--txt3);
      margin-top: 2px;
    }

    .file-actions {
      display: flex;
      gap: 6px;
      opacity: 0;
      transition: opacity .15s;
    }

    .file-item:hover .file-actions {
      opacity: 1;
    }

    .file-actions a,
    .file-actions button {
      background: var(--bg3);
      border: none;
      color: var(--txt2);
      border-radius: 6px;
      padding: 5px 8px;
      font-size: 12px;
      cursor: pointer;
      text-decoration: none;
    }

    .file-actions .del-btn {
      color: var(--err);
    }

    .empty {
      padding: 40px 16px;
      text-align: center;
      color: var(--txt3);
      font-size: 13px;
    }

    /* ── Viewer ── */
    .viewer {
      position: fixed;
      inset: 0;
      background: var(--bg0);
      z-index: 200;
      display: none;
      flex-direction: column;
    }

    .viewer.open {
      display: flex;
    }

    .viewer-header {
      background: var(--bg1);
      border-bottom: 1px solid var(--line);
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .viewer-title {
      flex: 1;
      font-size: 14px;
      font-weight: 600;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .viewer-close {
      background: none;
      border: 1px solid var(--line);
      color: var(--txt2);
      border-radius: 8px;
      padding: 6px 12px;
      font-size: 13px;
      cursor: pointer;
    }

    .viewer-body {
      flex: 1;
      overflow: auto;
      padding: 16px;
    }

    .viewer-body pre {
      font-family: 'SF Mono', 'Fira Code', monospace;
      font-size: 12px;
      line-height: 1.7;
      color: var(--txt);
      white-space: pre-wrap;
      word-break: break-all;
    }

    /* ── Search results ── */
    .search-res {
      padding: 0 16px;
    }

    .search-res-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 0;
      border-bottom: 1px solid var(--line);
    }

    .search-res-item a {
      color: var(--acc);
      font-size: 13px;
      text-decoration: none;
    }

    .search-res-path {
      color: var(--txt3);
      font-size: 11px;
      margin-top: 2px;
    }

    /* ── FAB ── */
    .fab {
      position: fixed;
      bottom: 24px;
      right: 16px;
      background: var(--acc);
      color: #fff;
      border: none;
      border-radius: 50%;
      width: 52px;
      height: 52px;
      font-size: 24px;
      cursor: pointer;
      box-shadow: 0 4px 20px rgba(124, 106, 247, .4);
      z-index: 40;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* @media (prefers-color-scheme: light) {
      :root {}
    } */

    /* force dark */
  </style>
</head>

<body>

  <!-- Header -->
  <div class="header">
    <div class="header-logo">X</div>
    <form class="search-wrap" method="get" action="">
      <input type="hidden" name="t" value="<?= ACCESS_TOKEN ?>">
      <span class="search-ico">🔍</span>
      <input class="search-input" name="q" type="search" placeholder="Search files..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" autocomplete="off">
    </form>
    <form method="post"><button class="btn-logout" name="logout" value="1">Logout</button></form>
  </div>

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <?php foreach ($breadcrumb as $i => $bc): ?>
      <?= $i > 0 ? '<span class="bc-sep">/</span>' : '' ?>
      <?php if ($i === count($breadcrumb) - 1): ?>
        <span class="bc-current"><?= htmlspecialchars($bc['name']) ?></span>
      <?php else: ?>
        <a class="bc-item" href="<?= $baseUrl ?>path=<?= urlencode($bc['path']) ?>"><?= htmlspecialchars($bc['name']) ?></a>
      <?php endif ?>
    <?php endforeach ?>
  </div>

  <!-- Message -->
  <?php if ($msg): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif ?>

  <!-- Toolbar -->
  <div class="toolbar">
    <button class="btn btn-primary" onclick="toggleUpload()">⬆ Upload</button>
    <button class="btn btn-ghost" onclick="openMkdir()">📁 New Folder</button>
  </div>

  <!-- Upload Zone -->
  <div class="upload-zone" id="uploadZone">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="path" value="<?= htmlspecialchars($relPath) ?>">
      <input type="file" name="upload" id="fileInput" onchange="showFilename(this)">
      <label for="fileInput">Click here to choose file</label>
      <div class="hint">Max <?= MAX_UPLOAD_MB ?>MB — no PHP files</div>
      <div class="upload-filename" id="uploadFilename"></div>
      <div style="margin-top:10px; display:flex; gap:8px;">
        <button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">Upload</button>
        <button class="btn btn-ghost" type="button" onclick="toggleUpload()" style="flex:1;justify-content:center">Cancel</button>
      </div>
    </form>
  </div>

  <?php if (!empty($_GET['q'])): ?>
    <!-- Search Results -->
    <div class="section-title">Results for &laquo;<?= htmlspecialchars($_GET['q']) ?>&raquo;</div>
    <?php if (empty($searchResults)): ?>
      <div class="empty">No results found.</div>
    <?php else: ?>
      <div class="search-res">
        <?php foreach ($searchResults as $r): ?>
          <div class="search-res-item">
            <span><?= fileIcon($r['name'], $r['isDir']) ?></span>
            <div>
              <a href="<?= $baseUrl ?><?= $r['isDir'] ? 'path=' . urlencode($r['path']) : 'view=' . urlencode($r['path']) ?>">
                <?= htmlspecialchars($r['name']) ?></a>
              <div class="search-res-path"><?= htmlspecialchars($r['path']) ?></div>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  <?php else: ?>
    <!-- File List -->
    <div class="section-title"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></div>
    <div class="file-list">
      <?php if (empty($items)): ?>
        <div class="empty">This folder is empty.</div>
      <?php endif ?>

      <?php
      // Go-up button
      if ($currentPath !== ROOT_PATH):
        $parentRel = str_replace(ROOT_PATH, '', dirname($currentPath));
      ?>
        <div class="file-item" onclick="location.href='<?= $baseUrl ?>path=<?= urlencode($parentRel) ?>'">
          <div class="file-icon">↩️</div>
          <div class="file-meta">
            <div class="file-name">.. Back</div>
          </div>
        </div>
      <?php endif ?>

      <?php foreach ($items as $item):
        $isImg = preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $item['name']);
        $canView = !$item['isDir'] && $item['size'] < 512 * 1024;
        $href = $item['isDir']
          ? $baseUrl . 'path=' . urlencode($item['path'])
          : ($canView ? '#' : $baseUrl . 'dl=' . urlencode($item['path']));
        $onclick = (!$item['isDir'] && $canView)
          ? "viewFile('" . addslashes($item['path']) . "','" . addslashes($item['name']) . "')"
          : '';
      ?>
        <div class="file-item" onclick="<?= $item['isDir'] ? "location.href='" . $baseUrl . 'path=' . urlencode($item['path']) . "'" : $onclick ?>">
          <div class="file-icon"><?= fileIcon($item['name'], $item['isDir']) ?></div>
          <div class="file-meta">
            <div class="file-name"><?= htmlspecialchars($item['name']) ?></div>
            <div class="file-info"><?= formatSize($item['size']) ?> · <?= date('Y-m-d', $item['mtime']) ?></div>
          </div>
          <div class="file-actions" onclick="event.stopPropagation()">
            <?php if (!$item['isDir']): ?>
              <a href="<?= $baseUrl ?>dl=<?= urlencode($item['path']) ?>">⬇</a>
            <?php endif ?>
            <button onclick="openRename('<?= addslashes($item['path']) ?>','<?= addslashes($item['name']) ?>')">✏️</button>
            <button class="del-btn" onclick="confirmDel('<?= $baseUrl ?>del=<?= urlencode($item['path']) ?>&path=<?= urlencode($relPath) ?>','<?= addslashes($item['name']) ?>')">🗑</button>
          </div>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <!-- FAB (upload shortcut) -->
  <button class="fab" onclick="toggleUpload()" title="Upload">+</button>

  <!-- Viewer -->
  <div class="viewer" id="viewer">
    <div class="viewer-header">
      <div class="viewer-title" id="viewerTitle"></div>
      <a id="viewerDl" href="#" class="btn btn-ghost" style="font-size:12px">⬇ Download</a>
      <button class="viewer-close" onclick="closeViewer()">✕ Close</button>
    </div>
    <div class="viewer-body">
      <pre id="viewerContent"></pre>
    </div>
  </div>

  <!-- Modal: New Folder -->
  <div class="modal-bg" id="mkdirModal" onclick="closeMkdir()">
    <div class="modal" onclick="event.stopPropagation()">
      <h3>📁 Create New Folder</h3>
      <form method="post">
        <input type="hidden" name="path" value="<?= htmlspecialchars($relPath) ?>">
        <input type="text" name="mkdir" placeholder="Folder name" autofocus>
        <div class="modal-actions">
          <button class="btn btn-primary" type="submit">Create</button>
          <button class="btn btn-ghost" type="button" onclick="closeMkdir()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Rename -->
  <div class="modal-bg" id="renameModal" onclick="closeRename()">
    <div class="modal" onclick="event.stopPropagation()">
      <h3>✏️ Rename</h3>
      <form method="post">
        <input type="hidden" name="rename_from" id="renameFrom">
        <input type="text" name="rename_to" id="renameTo" placeholder="New name">
        <div class="modal-actions">
          <button class="btn btn-primary" type="submit">Rename</button>
          <button class="btn btn-ghost" type="button" onclick="closeRename()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // ── Service worker (PWA) ──
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {}));
    }

    // ── Upload zone ──
    function toggleUpload() {
      document.getElementById('uploadZone').classList.toggle('open');
    }

    function showFilename(input) {
      document.getElementById('uploadFilename').textContent = input.files[0]?.name || '';
    }

    // ── Viewer ──
    async function viewFile(path, name) {
      document.getElementById('viewerTitle').textContent = name;
      document.getElementById('viewerDl').href = '?t=<?= ACCESS_TOKEN ?>&dl=' + encodeURIComponent(path);
      document.getElementById('viewerContent').textContent = 'Loading...';
      document.getElementById('viewer').classList.add('open');
      try {
        const res = await fetch('?t=<?= ACCESS_TOKEN ?>&view=' + encodeURIComponent(path));
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const pre = doc.getElementById('viewerData');
        document.getElementById('viewerContent').textContent = pre ? pre.textContent : '(Failed to load)';
      } catch (e) {
        document.getElementById('viewerContent').textContent = 'Error: ' + e.message;
      }
    }

    function closeViewer() {
      document.getElementById('viewer').classList.remove('open');
    }

    // ── Modals ──
    function openMkdir() {
      document.getElementById('mkdirModal').classList.add('open');
    }

    function closeMkdir() {
      document.getElementById('mkdirModal').classList.remove('open');
    }

    function openRename(path, name) {
      document.getElementById('renameFrom').value = path;
      document.getElementById('renameTo').value = name;
      document.getElementById('renameModal').classList.add('open');
    }

    function closeRename() {
      document.getElementById('renameModal').classList.remove('open');
    }

    // ── Confirm delete ──
    function confirmDel(url, name) {
      if (confirm('Delete "' + name + '"?\nThis action cannot be undone.')) location.href = url;
    }
  </script>

  <?php if ($viewContent !== null): ?>
    <!-- hidden data for viewer AJAX -->
    <pre id="viewerData" style="display:none"><?= $viewContent ?></pre>
    <script>
      // Direct view link
      document.addEventListener('DOMContentLoaded', () => {
        const pre = document.getElementById('viewerData');
        if (pre) {
          document.getElementById('viewerTitle').textContent = '<?= addslashes($viewName ?? '') ?>';
          document.getElementById('viewerDl').href = '?t=<?= ACCESS_TOKEN ?>&dl=<?= urlencode($_GET['view'] ?? '') ?>';
          document.getElementById('viewerContent').textContent = pre.textContent;
          document.getElementById('viewer').classList.add('open');
        }
      });
    </script>
  <?php endif ?>

</body>

</html>
<?php

// ══════════════════════════════════════════
//  Login page
// ══════════════════════════════════════════
function showLogin($error = null)
{ ?>
  <!DOCTYPE html>
  <html lang="en" dir="ltr">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>X-Plore — Login</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ASSET_URL ?>/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ASSET_URL ?>/apple-icon-180x180.png">
    <link rel="manifest" href="<?= ASSET_URL ?>/manifest.json">
    <meta name="theme-color" content="#0c0c0e">
    <style>
      * {
        box-sizing: border-box;
        margin: 0;
        padding: 0
      }

      body {
        background: #0c0c0e;
        color: #d8d8e8;
        font-family: 'SF Pro Display', 'Segoe UI', system-ui, sans-serif;
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px
      }

      .card {
        background: #131318;
        border: 1px solid #2d2d2d;
        border-radius: 16px;
        padding: 32px 24px;
        width: 100%;
        max-width: 360px
      }

      .logo {
        font-size: 30px;
        font-weight: 800;
        color: #bd7800;
        text-align: center;
        letter-spacing: -1px
      }

      .sub {
        text-align: center;
        color: #575757;
        font-size: 12px;
        margin: 4px 0 28px
      }

      label {
        display: block;
        font-size: 12px;
        color: #888888;
        margin-bottom: 6px
      }

      input[type=password] {
        width: 100%;
        background: #1c1c1c;
        border: 1px solid #303030;
        border-radius: 10px;
        padding: 12px 14px;
        color: #e2e2e2;
        font-size: 15px;
        outline: none;
        margin-bottom: 16px
      }

      input[type=password]:focus {
        border-color: #bd7800
      }

      button {
        width: 100%;
        background: #bd7800;
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 13px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer
      }

      .err {
        background: rgba(247, 101, 101, .1);
        color: #f76565;
        border-radius: 8px;
        padding: 10px;
        font-size: 13px;
        text-align: center;
        margin-bottom: 14px;
        border: 1px solid rgba(247, 101, 101, .2)
      }

      .lock {
        margin-bottom: 12px
      }
    </style>
  </head>

  <body>
    <div class="card">
      <div class="lock"><img src="/asset/favicon-32x32.png" /></div>
      <div class="logo">X-Plore</div>
      <div class="sub">Secure File Manager</div>
      <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif ?>
      <form method="post">
        <label>Access Password</label>
        <input type="password" name="password" autofocus autocomplete="current-password" placeholder="••••••••">
        <button type="submit">Sign in</button>
      </form>
    </div>
  </body>

  </html>
<?php }
