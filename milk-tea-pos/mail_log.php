<?php

$logFile = __DIR__ . '/mail_log.txt';

if (isset($_GET['clear'])) {
    file_put_contents($logFile, '');
    header('Location: mail_log.php');
    exit;
}

$logs = file_exists($logFile) ? file_get_contents($logFile) : '';

$entries = [];
if (trim($logs)) {
    $parts = preg_split('/={3,}/', $logs);
    foreach ($parts as $part) {
        $part = trim($part);
        if (!$part) continue;
        $entry = ['time'=>'','to'=>'','subject'=>'','body'=>''];
        if (preg_match('/TIME\s*:\s*(.+)/i',    $part, $m)) $entry['time']    = trim($m[1]);
        if (preg_match('/TO\s*:\s*(.+)/i',       $part, $m)) $entry['to']      = trim($m[1]);
        if (preg_match('/SUBJECT\s*:\s*(.+)/i',  $part, $m)) $entry['subject'] = trim($m[1]);
        if (preg_match('/BODY\s*:\s*(.+)/si',    $part, $m)) $entry['body']    = trim($m[1]);
        if ($entry['time'] || $entry['to']) $entries[] = $entry;
    }
    $entries = array_reverse($entries);
}
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dev Mail Log — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
 <style>
    * { box-sizing: border-box; }
    
    body {
      font-family: 'Nunito', sans-serif;
      background: #fdf6ee;
      min-height: 100vh;
      margin: 0;
      padding: 2rem 1rem;
      display: flex;
      flex-direction: column;
      justify-content: center; 
      align-items: center;     
    }
    
    .page-wrap {
      width: 100%;             
      max-width: 760px;
      margin: 0 auto;
    }
    
    .logo {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    
    .logo span { font-size: 2.2rem; }
    
    .logo h1 {
      font-size: 1.5rem;
      font-weight: 900;
      color: #2d1a0e;
      margin: .2rem 0 0;
    }
    
    .logo p {
      color: #a0855b;
      font-size: .9rem;
      margin: 0;
    }
    
    .mail-card {
      background: #fff;
      border-radius: 1rem;
      border: 1px solid #f0e0cc;
      margin-bottom: 1rem;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,.06);
    }
    
    .mail-header {
      background: linear-gradient(135deg, #e8a87c, #d4956a);
      color: #fff;
      padding: .75rem 1.25rem;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: .5rem;
    }
    
    .mail-header .subject {
      font-weight: 800;
      font-size: 1rem;
    }
    
    .mail-header .meta {
      font-size: .78rem;
      opacity: .9;
    }
    
    .mail-header .time-badge {
      font-size: .75rem;
      background: rgba(255,255,255,.25);
      border-radius: .4rem;
      padding: .2rem .5rem;
      white-space: nowrap;
    }
    
    .mail-body {
      padding: 1.25rem;
    }
    
    .mail-body pre {
      white-space: pre-wrap;
      word-break: break-word;
      font-family: 'Nunito', sans-serif;
      font-size: .88rem;
      color: #3d2b1a;
      margin: 0;
      line-height: 1.7;
    }
    
    .link-highlight {
      background: #fff8e8;
      border: 1px dashed #e8a87c;
      border-radius: .5rem;
      padding: .6rem 1rem;
      margin-top: .75rem;
      word-break: break-all;
      font-size: .85rem;
    }
    
    .link-highlight a {
      color: #c07a3a;
      font-weight: 700;
    }
    
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: #a0855b;
    }
    
    .empty-state i { font-size: 3rem; opacity: .5; }
    
    .topbar-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
      flex-wrap: wrap;
      gap: .5rem;
    }
    
    .btn-amber {
      background: linear-gradient(135deg,#e8a87c,#d4956a);
      color: #fff;
      border: none;
      border-radius: .6rem;
      padding: .4rem .9rem;
      font-weight: 700;
      font-size: .85rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    }
    
    .btn-amber:hover { opacity: .9; color: #fff; }
    
    .btn-outline-red {
      background: transparent;
      color: #dc3545;
      border: 1px solid #dc3545;
      border-radius: .6rem;
      padding: .4rem .9rem;
      font-weight: 700;
      font-size: .85rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    }
    
    .btn-outline-red:hover { background: #dc3545; color: #fff; }
    
    .dev-notice {
      background: #fff8e8;
      border: 1px solid #f0cc88;
      border-radius: .65rem;
      padding: .65rem 1rem;
      font-size: .83rem;
      color: #7a5a2a;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
  </style>
</head><body>
<div class="page-wrap">

  <div class="logo">
    <span>&#9749;</span>
    <h1>Boba House POS</h1>
    <p>Development Mail Log</p>
  </div>

  <div class="topbar-actions">
    <a href="auth/forgot_password.php" class="btn-amber">
      <i class="bi bi-arrow-left"></i> Back to Forgot Password
    </a>
    <div class="d-flex gap-2">
      <a href="mail_log.php" class="btn-amber">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </a>
      <?php if (!empty($entries)): ?>
        <a href="?clear=1" class="btn-outline-red"
           onclick="return confirm('Clear all mail logs?')">
          <i class="bi bi-trash"></i> Clear
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="dev-notice">
    <i class="bi bi-info-circle-fill" style="font-size:1rem;color:#e8a040"></i>
    Emails are not actually sent on this server. They are captured here instead.
    Copy the reset or verification link below and open it in your browser.
  </div>

  <?php if (empty($entries)): ?>
    <div class="mail-card">
      <div class="empty-state">
        <i class="bi bi-inbox d-block mb-2"></i>
        No emails logged yet. Try using Forgot Password or Register first.
      </div>
    </div>
  <?php else: ?>
    <p style="color:#a0855b;font-size:.83rem;margin-bottom:.75rem">
      <?php echo count($entries); ?> email(s) — newest first
    </p>
    <?php foreach ($entries as $e): ?>
      <div class="mail-card">
        <div class="mail-header">
          <div>
            <div class="subject"><?php echo htmlspecialchars($e['subject']); ?></div>
            <div class="meta">To: <?php echo htmlspecialchars($e['to']); ?></div>
          </div>
          <div class="time-badge">
            <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($e['time']); ?>
          </div>
        </div>
        <div class="mail-body">
          <pre><?php echo htmlspecialchars($e['body']); ?></pre>
          <?php
          // 1. Check for standard clickable URL strings first
          if (preg_match('/(?:https?:\/\/|localhost\/)\S+/i', $e['body'], $url)) {
              $link = htmlspecialchars(rtrim($url[0], '.),] '));
          ?>
          <div class="link-highlight">
            <strong><i class="bi bi-link-45deg"></i> Link Detected — click to open:</strong><br>
            <a href="<?php echo $link; ?>" target="_blank"><?php echo $link; ?></a>
          </div>
          <?php 
          // 2. Fallback: If no link is found, handle raw hex token dynamically
          } elseif (preg_match('/([a-f0-9]{64})/i', $e['body'], $token)) { 
              // This dynamically finds your current subfolder URL path automatically!
              $currentDir = dirname($_SERVER['SCRIPT_NAME']);
              $projectRoot = ($currentDir === '\\' || $currentDir === '/') ? '' : rtrim($currentDir, '/\\');
              
             $link = $projectRoot . "/auth/verify_email.php?token=" . $token[1];
          ?>
          <div class="link-highlight">
            <strong><i class="bi bi-link-45deg"></i> Generated Verification Link — click to open:</strong><br>
            <a href="<?php echo $link; ?>" target="_blank"><?php echo $link; ?></a>
          </div>
          <?php } ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
</body></html>