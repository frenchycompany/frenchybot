<?php
/**
 * FrenchyBot - CRON de relance des conversations abandonnees
 * Usage: php /var/www/frenchybot/cron/followups.php
 * Crontab: */15 * * * * php /var/www/frenchybot/cron/followups.php
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chatbot-functions.php';

$count = chatbotProcessFollowups();
echo date('Y-m-d H:i:s') . " - {$count} followup(s) traite(s)\n";
