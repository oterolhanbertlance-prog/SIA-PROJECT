<?php
require_once __DIR__ . '/includes/app_url.php';
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . appUrl('admin/users.php') . ($query !== '' ? '?' . $query : ''));
exit;
