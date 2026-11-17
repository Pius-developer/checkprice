<?php
declare(strict_types=1);

// Emails price alerts whose target has been reached. Run once a day from a cron job:
//   0 8 * * *  /usr/local/bin/php /home/USER/domains/checkprice.ejikepius.dev/bin/alerts.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';

$sent = 0;
foreach (prices()->dueAlerts() as $a) {
    $email = require ROOT . '/views/emails/alert.php';
    if (Mailer::fromConfig()->send($a['email'], $email['subject'], $email['text'], $email['html'])) {
        prices()->markAlertSent((int) $a['id']);
        $sent++;
    }
}
printf("[%s] sent %d price alert%s\n", gmdate('c'), $sent, $sent === 1 ? '' : 's');
