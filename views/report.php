<?php /** Weekly market report. Expects $w. */
$meta = ['title' => 'This week in Nigerian market prices · CheckPrice']; ?>
<section class="wrap block report">
    <p class="crumb">Weekly market report · <?= gmdate('j M Y') ?></p>
    <h1>This week in prices</h1>
    <div class="stats3">
        <div><strong><?= number_format($w['posts']) ?></strong><span>prices posted</span></div>
        <div><strong><?= number_format($w['people']) ?></strong><span>scouts</span></div>
        <div><strong><?= number_format($w['markets']) ?></strong><span>markets</span></div>
    </div>

    <h2>Biggest moves vs last week</h2>
    <?php if ($w['movers']): ?>
        <ul class="movers">
            <?php foreach ($w['movers'] as $m): $i = Catalog::item($m['item']); ?>
                <li><a href="/item/<?= e($m['item']) ?>"><?= e($i['name']) ?> <span class="muted"><?= e($i['unit']) ?></span></a>
                    <span class="num"><?= naira($m['before']) ?> → <strong><?= naira($m['now']) ?></strong></span>
                    <span class="chg <?= $m['change'] > 0 ? 'up' : 'down' ?>"><?= $m['change'] > 0 ? '▲' : '▼' ?> <?= abs($m['change']) ?>%</span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?><p class="muted">Not enough prices yet to compare weeks. Check back next week.</p><?php endif; ?>

    <div class="two-col">
        <div>
            <h2>Cheapest state for each item</h2>
            <?php if ($w['cheapest']): ?>
                <ol class="board">
                    <?php foreach ($w['cheapest'] as $c): ?><li><a href="/item/<?= e($c['item']) ?>?state=<?= e($c['state']) ?>"><?= e(Catalog::item($c['item'])['name']) ?></a><span class="pts-num"><?= e(Catalog::state($c['state'])) ?> · <?= naira($c['median']) ?></span></li><?php endforeach; ?>
                </ol>
            <?php else: ?><p class="muted">Needs at least 3 prices per state.</p><?php endif; ?>
        </div>
        <div>
            <h2>Scouts of the week</h2>
            <?php if ($w['scouts']): ?>
                <ol class="board"><?php foreach ($w['scouts'] as $i => $s): ?><li><span class="pos"><?= $i + 1 ?></span><a href="/@<?= e($s['username']) ?>">@<?= e($s['username']) ?></a><span class="pts-num"><?= (int) $s['earned'] ?> pts</span></li><?php endforeach; ?></ol>
            <?php else: ?><p class="muted">No scouts yet this week.</p><?php endif; ?>
        </div>
    </div>
</section>
