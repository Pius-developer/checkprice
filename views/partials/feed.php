<?php /** A list of posted prices. Expects $feed. */ ?>
<ul class="feed">
<?php foreach ($feed as $r): $i = Catalog::item($r['item']); ?>
    <li class="feed-row">
        <a class="feed-link" href="/r/<?= (int) $r['id'] ?>">
            <?php if ($r['photo']): ?><img class="thumb" src="/photo/<?= e($r['photo']) ?>" alt="" loading="lazy" width="52" height="52"><?php else: ?><span class="thumb thumb-empty" aria-hidden="true">₦</span><?php endif; ?>
            <span class="feed-main">
                <span class="feed-item"><?= e($i['name']) ?> <span class="muted">· <?= e($i['unit']) ?></span></span>
                <span class="feed-where muted"><?= e($r['market']) ?>, <?= e(Catalog::state($r['state'])) ?> · <?= e(ago($r['created_at'])) ?> · @<?= e($r['username']) ?><?= $r['user_verified'] ? ' <span class="tick" title="Verified Scout">✔</span>' : '' ?></span>
            </span>
            <span class="feed-price">
                <strong><?= naira((int) $r['price']) ?></strong>
                <span class="pill pill-<?= e($r['verdict']) ?>"><?= ['sharp' => 'Sharp', 'fair' => 'Fair', 'high' => 'Too high', 'first' => 'First'][$r['verdict']] ?></span>
                <?php if ($r['status'] === 'pending'): ?><span class="pill pill-pending">Needs a check</span><?php endif; ?>
            </span>
        </a>
    </li>
<?php endforeach; ?>
</ul>
