<?php
declare(strict_types=1);
/** Price alert email. Expects $a (alert row with median, email, username). */
$i = Catalog::item($a['item']);
$st = Catalog::state($a['state']);
$link = base_url() . '/item/' . $a['item'] . '?state=' . $a['state'];
$subject = "📉 {$i['name']} is now " . naira((int) $a['median']) . " in $st";
$text = "Good news!\n\n{$i['label']} in $st is now usually " . naira((int) $a['median']) . ", at or below your target of " . naira((int) $a['below']) . ".\n\nSee the cheapest markets: $link\n\nYou set this alert on CheckPrice. Manage alerts: " . base_url() . "/alerts\n";
ob_start(); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title><?= e($subject) ?></title></head>
<body style="margin:0;background:#fff;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 20px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;font-family:-apple-system,'Segoe UI',Inter,Helvetica,Arial,sans-serif;color:#0e1a12;">
<tr><td style="font-size:18px;font-weight:800;color:#12A150;padding-bottom:24px;">CheckPrice</td></tr>
<tr><td style="font-size:15px;color:#66706a;"><?= e($i['label']) ?> · <?= e($st) ?></td></tr>
<tr><td style="font-size:40px;font-weight:800;padding:6px 0 4px;"><?= naira((int) $a['median']) ?></td></tr>
<tr><td style="font-size:15px;line-height:1.6;color:#2b332e;padding-bottom:24px;">That's at or below your target of <strong><?= naira((int) $a['below']) ?></strong>. Time to buy!</td></tr>
<tr><td><a href="<?= e($link) ?>" style="display:inline-block;background:#12A150;color:#fff;text-decoration:none;font-weight:600;padding:12px 20px;border-radius:12px;">See the cheapest markets</a></td></tr>
<tr><td style="border-top:1px solid #e3e8e5;margin-top:28px;padding-top:16px;font-size:12px;color:#999;"><br>You set this alert on CheckPrice. <a href="<?= e(base_url()) ?>/alerts" style="color:#999;">Manage alerts</a></td></tr>
</table></td></tr></table></body></html>
<?php
$html = ob_get_clean();
return compact('subject', 'text', 'html');
