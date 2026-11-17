<?php
/** One item. Expects $item, $state, $sum, $national, $markets, $trend, $states, $feed. */
$meta = ['title' => $item['label'] . ' price in ' . Catalog::state($state) . ' today · CheckPrice',
         'description' => 'What people are paying for ' . $item['label'] . ' in ' . Catalog::state($state) . ($sum['median'] ? ': usually around ' . naira($sum['median']) : '') . '. Updated by shoppers every day.'];
?>
<section class="wrap block item-page">
    <p class="crumb"><a href="/items?state=<?= e($state) ?>">All prices</a> / <?= e(Catalog::CATEGORIES[$item['category']]) ?></p>
    <div class="item-head">
        <div>
            <h1><?= e($item['name']) ?></h1>
            <p class="muted unit"><?= e($item['unit']) ?></p>
        </div>
        <?php require ROOT . '/views/partials/state.php'; ?>
    </div>

    <div class="item-grid">
        <div class="fair-card">
            <?php if ($sum['median']): ?>
                <p class="fc-label">Usual price in <?= e(Catalog::state($state)) ?></p>
                <p class="fc-price"><?= naira($sum['median']) ?></p>
                <p class="fc-range">Fair range: <strong><?= naira($sum['low']) ?> to <?= naira($sum['high']) ?></strong></p>
                <p class="fc-tip">Haggling tip: <strong>don't pay more than <?= naira($sum['high']) ?></strong>.</p>
                <p class="small muted">Based on <?= $sum['count'] ?> price<?= $sum['count'] === 1 ? '' : 's' ?> from the last 30 days<?= $national['median'] ? '. Across Nigeria: ' . naira($national['median']) : '' ?>.</p>
            <?php else: ?>
                <p class="fc-label">No prices in <?= e(Catalog::state($state)) ?> yet</p>
                <p class="fc-price fc-none">?</p>
                <?php if ($national['median']): ?><p class="fc-range">Across Nigeria it's usually <strong><?= naira($national['median']) ?></strong>.</p><?php endif; ?>
                <p class="fc-tip">Be the first to post it here and earn the Market Pioneer badge.</p>
            <?php endif; ?>
            <a class="btn btn-block" href="/post?item=<?= e($item['slug']) ?>">I bought this: post my price</a>
            <a class="link small alert-link" href="/alerts?item=<?= e($item['slug']) ?>&state=<?= e($state) ?>">Email me when it gets cheaper</a>
        </div>

        <div>
            <h2>Last 12 weeks</h2>
            <?php require ROOT . '/views/partials/chart.php'; ?>

            <h2>Cheapest markets in <?= e(Catalog::state($state)) ?></h2>
            <?php if ($markets): ?>
                <ol class="board">
                    <?php foreach ($markets as $i => $m): ?>
                        <li><span class="pos"><?= $i + 1 ?></span><?= e($m['name']) ?><span class="pts-num"><?= naira($m['median']) ?> <span class="muted">(<?= $m['count'] ?>)</span></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p class="muted">No markets yet.</p><?php endif; ?>

            <?php if (count($states) > 1): ?>
                <h2>By state</h2>
                <ol class="board">
                    <?php $n = 0; foreach ($states as $st => $s): if (++$n > 6) break; ?>
                        <li><span class="pos"><?= $n ?></span><a href="/item/<?= e($item['slug']) ?>?state=<?= e($st) ?>"><?= e(Catalog::state($st)) ?></a><span class="pts-num"><?= naira($s['median']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>

    <h2>Latest prices</h2>
    <?php if ($feed): require ROOT . '/views/partials/feed.php'; else: ?><p class="empty">Nothing posted here yet.</p><?php endif; ?>
</section>
