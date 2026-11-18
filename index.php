<?php
$scriptDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/.');
header('Location: ' . $scriptDirectory . '/public/index.php', true, 302);
exit;
