<?php /** A scout's profile. Expects $user, $badges, $feed. */
$rank = rank_of((int) $user['points']);
$meta = ['title' => '@' . $user['username'] . ' · ' . $rank['name'] . ' on CheckPrice'];
$owned = array_column($badges, 'badge'); ?>
<section class="wrap block">
    <div class="profile-head">
        <div>
            <h1>@<?= e($user['username']) ?><?= $user['verified'] ? ' <span class="tick" title="Verified Scout">✔</span>' : '' ?></h1>
            <p class="rank-name"><?= e($rank['name']) ?></p>
            <div class="progress" role="img" aria-label="<?= round($rank['progress'] * 100) ?>% of the way to <?= e($rank['next'] ?? 'the top') ?>"><span style="width:<?= round($rank['progress'] * 100) ?>%"></span></div>
            <p class="small muted"><?= number_format((int) $user['points']) ?> points<?= $rank['next'] ? ' · ' . $rank['to_next'] . ' to ' . e($rank['next']) : ' · top rank' ?><?= (int) $user['streak'] > 1 ? ' · 🔥 ' . (int) $user['streak'] . '-day streak' : '' ?></p>
        </div>
    </div>
    <h2>Badges</h2>
    <ul class="badges">
        <?php foreach (Users::BADGES as $key => [$name, $desc]): $has = in_array($key, $owned, true); ?>
            <li class="badge <?= $has ? 'on' : 'off' ?>" title="<?= e($desc) ?>"><span class="badge-ico" aria-hidden="true"><?= $has ? '🏅' : '🔒' ?></span><strong><?= e($name) ?></strong><span class="small muted"><?= e($desc) ?></span></li>
        <?php endforeach; ?>
    </ul>
    <h2>Prices posted</h2>
    <?php if ($feed): require ROOT . '/views/partials/feed.php'; else: ?><p class="empty">No prices yet.</p><?php endif; ?>
</section>
