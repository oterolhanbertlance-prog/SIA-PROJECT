<?php
// home.php — kept for backward-compat links; delegates to index.php
session_start();
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
header('Location: ' . $base . '/index.php'); exit;
