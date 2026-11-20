<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
header('Location: ' . BASE . '/stats.php', true, 302);
exit;
