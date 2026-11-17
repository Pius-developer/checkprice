<?php
/** One posted price, with its verdict. Expects $r, $news (just posted?), $voted. */
$i = Catalog::item($r['item']);
[$title, $sub] = verdict_text($r);
$url = base_url() . '/r/' . (int) $r['id'];
$meta = ['title' => $title . ' ' . $i['name'] . ' for ' . naira((int) $r['price']) . ' in ' . Catalog::state($r['state']) . ' · CheckPrice',
         'description' => $sub, 'image' => base_url() . '/r/' . (int) $r['id'] . '/card.png'];
$shareText = match ($r['verdict']) {
    'high'  => "Dem cheat me 😭 I paid " . naira((int) $r['price']) . " for {$i['label']} in " . Catalog::state($r['state']) . ". Usual price is " . naira((int) $r['compared_to']) . ". Check your own prices:",
    'sharp' => "I just bought {$i['label']} for " . naira((int) $r['price']) . ", below the usual price in " . Catalog::state($r['state']) . " 🔥 Check yours:",
    default => "{$i['label']} costs " . naira((int) $r['price']) . " at {$r['market']}, " . Catalog::state($r['state']) . ". Check prices near you:",
};
$me = current_user();
?>
<section class="wrap block verdict-page">
    <?php if ($news): $rk = rank_of((int) $me['points']); ?>
        <div class="earned" role="status">
            <p class="earned-pts">+<?= (int) $news['points'] ?> points</p>
            <p>Rank: <strong><?= e($rk['name']) ?></strong><?= $rk['next'] ? ', ' . $rk['to_next'] . ' points to ' . e($rk['next']) : '' ?>.<?= $news['streak'] > 1 ? ' 🔥 ' . (int) $news['streak'] . '-day streak!' : '' ?></p>
            <?php foreach ($news['badges'] as $b): ?><p class="badge-new">🏅 New badge: <strong><?= e(Users::BADGES[$b][0]) ?></strong></p><?php endforeach; ?>
            <?php if ($news['status'] === 'pending'): ?><p class="small">This price is far from what others paid, so it'll show once another scout confirms it.</p><?php endif; ?>
        </div>
    <?php endif; ?>

    <article class="tag tag-<?= e($r['verdict']) ?> tag-big">
        <p class="tag-head"><?= e($title) ?></p>
        <p class="tag-sub"><?= e($sub) ?></p>
        <p class="tag-item"><?= e($i['name']) ?> · <?= e($i['unit']) ?> · <?= e($r['market']) ?>, <?= e(Catalog::state($r['state'])) ?></p>
        <div class="tag-nums">
            <div><span>Paid</span><strong><?= naira((int) $r['price']) ?></strong></div>
            <div><span>Usual price</span><strong><?= $r['compared_to'] ? naira((int) $r['compared_to']) : '—' ?></strong></div>
        </div>
        <p class="tag-by">Posted by <a href="/@<?= e($r['username']) ?>">@<?= e($r['username']) ?></a><?= $r['user_verified'] ? ' <span class="tick">✔</span>' : '' ?> · <?= e(ago($r['created_at'])) ?><?= $r['confirms'] ? ' · ✅ confirmed by ' . (int) $r['confirms'] : '' ?></p>
    </article>

    <?php if ($r['photo']): ?><img class="report-photo" src="/photo/<?= e($r['photo']) ?>" alt="Photo of <?= e($i['name']) ?> from <?= e($r['market']) ?>"><?php endif; ?>

    <div class="share">
        <h2>Share it</h2>
        <div class="actions">
            <a class="btn btn-wa" href="https://wa.me/?text=<?= rawurlencode($shareText . ' ' . $url) ?>">WhatsApp</a>
            <a class="btn btn-x" href="https://x.com/intent/post?text=<?= rawurlencode($shareText) ?>&url=<?= rawurlencode($url) ?>">Post on X</a>
            <button class="btn btn-line" type="button" data-copy="<?= e($url) ?>">Copy link</button>
            <a class="btn btn-line" href="/r/<?= (int) $r['id'] ?>/card.png" download="checkprice-<?= (int) $r['id'] ?>.png">Download card</a>
        </div>
    </div>

    <?php if (!$me || (int) $me['id'] !== (int) $r['user_id']): ?>
        <div class="vote">
            <h2>Did you see this price too?</h2>
            <?php if ($voted): ?>
                <p class="muted">You <?= $voted === 'confirm' ? 'confirmed' : 'flagged' ?> this price. Thanks!</p>
            <?php elseif ($me): ?>
                <div class="actions">
                    <form method="post" action="/r/<?= (int) $r['id'] ?>/confirm" class="inline"><?= csrf_field() ?><button class="btn btn-line">✅ Yes, same price (+1 pt)</button></form>
                    <form method="post" action="/r/<?= (int) $r['id'] ?>/flag" class="inline"><?= csrf_field() ?><button class="btn btn-line">🚩 This looks wrong</button></form>
                </div>
            <?php else: ?>
                <p class="muted"><a href="/login">Log in</a> to confirm or flag prices and earn points.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p class="more"><a class="btn" href="/item/<?= e($r['item']) ?>?state=<?= e($r['state']) ?>">See all <?= e($i['name']) ?> prices in <?= e(Catalog::state($r['state'])) ?></a> <a class="link" href="/post">Post another price</a></p>
</section>
