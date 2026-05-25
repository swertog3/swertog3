<?php
require_once 'includes/config.php';

echo '<pre>';
echo 'Session status: ' . session_status() . "\n";
echo 'Session id: ' . session_id() . "\n";
echo 'Session data: ';
print_r($_SESSION);
echo '</pre>';

// Проверим, можем ли мы записать в сессию
$_SESSION['test'] = 'WORKING';
echo 'Test write: ' . ($_SESSION['test'] ?? 'FAILED');
?>