<?php /** Leaderboards. Expects $state (nullable), $week, $all. */
$meta = ['title' => 'Top price scouts' . ($state ? ' in ' . Catalog::state($state) : '') . ' · CheckPrice']; ?>
<section class="wrap block">
    <div class="block-head">
        <h1>Top scouts<?= $state ? ' in ' . e(Catalog::state($state)) : '' ?></h1>
        <form method="get" class="state-pick" data-autosubmit>
            <label for="sc-state">Show</label>
            <select id="sc-state" name="state">
                <option value="">All of Nigeria</option>
                <?php foreach (Catalog::STATES as $slug => $name): ?><option value="<?= e($slug) ?>" <?= $slug === $state ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="two-col">
        <div>
            <h2>This week</h2>
            <?php if ($week): ?>
                <ol class="board board-big">
                    <?php foreach ($week as $i => $s): ?>
                        <li><span class="pos"><?= $i < 3 ? ['🥇', '🥈', '🥉'][$i] : $i + 1 ?></span>
                            <span><a href="/@<?= e($s['username']) ?>">@<?= e($s['username']) ?></a><?= $s['verified'] ? ' <span class="tick">✔</span>' : '' ?><br><span class="small muted"><?= e(rank_of((int) $s['total'])['name']) ?> · <?= (int) $s['posts'] ?> prices</span></span>
                            <span class="pts-num"><?= (int) $s['earned'] ?> pts</span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p class="empty">No scouts this week yet. <a href="/post">Post a price</a> and take first place.</p><?php endif; ?>
        </div>
        <div>
            <h2>All time</h2>
            <?php if ($all): ?>
                <ol class="board">
                    <?php foreach ($all as $i => $s): ?>
                        <li><span class="pos"><?= $i + 1 ?></span><a href="/@<?= e($s['username']) ?>">@<?= e($s['username']) ?></a><span class="pts-num"><?= number_format((int) $s['total']) ?> pts</span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p class="muted">Nobody yet.</p><?php endif; ?>
            <h2>Ranks</h2>
            <ol class="ranks">
                <?php foreach (Stats::RANKS as $at => $name): ?><li><strong><?= e($name) ?></strong> <span class="muted"><?= number_format($at) ?>+ pts</span></li><?php endforeach; ?>
            </ol>
        </div>
    </div>
</section>
