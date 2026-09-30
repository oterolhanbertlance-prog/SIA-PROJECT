<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/app_url.php';
$root = appBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>
  (function () {
    var KEY = 'boba-pos-theme';
    var html = document.documentElement;

    function readTheme() {
      try {
        var saved = localStorage.getItem(KEY);
        if (saved === 'dark' || saved === 'light') return saved;
      } catch (e) {}
      return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }

    function applyTheme(theme, persist) {
      theme = theme === 'dark' ? 'dark' : 'light';
      html.setAttribute('data-pos-theme', theme);
      if (document.body) document.body.setAttribute('data-pos-theme', theme);
      if (persist) {
        try { localStorage.setItem(KEY, theme); } catch (e) {}
      }
      var btn = document.getElementById('posThemeToggle');
      if (btn) {
        btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        btn.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
      }
      return theme;
    }

    window.BobaPosTheme = {
      current: function () { return html.getAttribute('data-pos-theme') || readTheme(); },
      set: function (theme) { return applyTheme(theme, true); },
      toggle: function () {
        return applyTheme((html.getAttribute('data-pos-theme') === 'dark') ? 'light' : 'dark', true);
      },
      refreshButton: function () { return applyTheme(html.getAttribute('data-pos-theme') || readTheme(), false); }
    };

    applyTheme(readTheme(), false);
    document.addEventListener('DOMContentLoaded', function () {
      applyTheme(html.getAttribute('data-pos-theme') || readTheme(), false);
    });
  })();
  </script>
  <title>Boba House POS<?= isset($pageTitle) ? ' — ' . htmlspecialchars($pageTitle) : '' ?></title>
  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= $root ?>assets/css/custom.css">
</head>
<body>
