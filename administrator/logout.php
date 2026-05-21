<?php
require_once __DIR__ . '/_bootstrap.php';
admin_log((int)($_SESSION['super_admin_id'] ?? 0), 'logout');
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
