<?php
// Run every 15 minutes:  php C:\xampp\htdocs\healthcenter\cron_reminders.php
require_once __DIR__ . '/db.php';
if (PHP_SAPI !== 'cli' && (CRON_KEY === 'change-me' || ($_GET['key'] ?? '') !== CRON_KEY)) { http_response_code(403); exit('Forbidden'); }
echo 'Reminders processed: ' . run_reminders($pdo) . "\n";
