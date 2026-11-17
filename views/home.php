<?php /** Home. Expects $state, $popular, $feed, $scouts, $totals. */ ?>
<section class="hero">
    <div class="wrap hero-grid">
        <div>
            <h1>What does it <span class="hl">really</span> cost?</h1>
            <p class="lede">Real prices from markets across Nigeria, posted by people like you. Check before you buy. Snap what you paid and find out if <strong>dem cheat you</strong>.</p>
            <form class="search" action="/item" method="get" data-item-search>
                <label class="sr" for="q-item">Item</label>
                <select id="q-item" name="item" required>
                    <option value="">What are you buying?</option>
                    <?php foreach (Catalog::grouped() as $cat => $items): ?>
                        <optgroup label="<?= e(Catalog::CATEGORIES[$cat]) ?>">
                            <?php foreach ($items as $i): ?><option value="<?= e($i['slug']) ?>"><?= e($i['label']) ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <label class="sr" for="q-state">State</label>
                <select id="q-state" name="state">
                    <?php foreach (Catalog::STATES as $slug => $name): ?><option value="<?= e($slug) ?>" <?= $slug === $state ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                </select>
                <button class="btn">Check price</button>
            </form>
            <p class="small muted hero-note">
                <?= number_format($totals['prices']) ?> prices · <?= number_format($totals['markets']) ?> markets · <?= number_format($totals['scouts']) ?> scouts so far
            </p>
        </div>

        <aside class="tag-demo" aria-label="Example verdict">
            <div class="tag tag-high">
                <p class="tag-head">Dem don cheat you 😭</p>
                <p class="tag-item">Rice · 50kg bag · Mile 12, Lagos</p>
                <div class="tag-nums"><div><span>You paid</span><strong>₦86,000</strong></div><div><span>Usual price</span><strong>₦74,500</strong></div></div>
            </div>
            <p class="small muted">Post what you paid and you get a verdict like this, plus points.</p>
        </aside>
    </div>
</section>

<section class="wrap block">
    <div class="block-head">
        <h2>Today's prices</h2>
        <?php require ROOT . '/views/partials/state.php'; ?>
    </div>
    <div class="grid-prices">
        <?php foreach ($popular as $p): $i = $p['item']; $s = $p['sum']; ?>
            <a class="price-card" href="/item/<?= e($i['slug']) ?>?state=<?= e($state) ?>">
                <span class="pc-name"><?= e($i['name']) ?></span>
                <span class="pc-unit muted"><?= e($i['unit']) ?></span>
                <?php if ($s['median']): ?>
                    <strong class="pc-price"><?= naira($s['median']) ?></strong>
                    <span class="pc-meta">
                        <?php if ($p['change'] !== null): ?><span class="chg <?= $p['change'] > 0 ? 'up' : ($p['change'] < 0 ? 'down' : '') ?>"><?= $p['change'] > 0 ? '▲' : ($p['change'] < 0 ? '▼' : '') ?> <?= abs($p['change']) ?>%</span><?php endif; ?>
                        <span class="muted"><?= $s['count'] ?> price<?= $s['count'] === 1 ? '' : 's' ?></span>
                    </span>
                <?php else: ?>
                    <span class="pc-empty">No prices yet. Be the first 🚩</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="more"><a href="/items?state=<?= e($state) ?>">See all <?= count(Catalog::ITEMS) ?> items in <?= e(Catalog::state($state)) ?></a></p>
</section>

<section class="wrap block two-col">
    <div>
        <h2>Just posted</h2>
        <?php if ($feed): require ROOT . '/views/partials/feed.php'; else: ?><p class="empty">No prices yet. <a href="/post">Post the very first one</a> and become a Market Pioneer.</p><?php endif; ?>
    </div>
    <div>
        <h2>Top scouts this week</h2>
        <?php if ($scouts): ?>
            <ol class="board">
                <?php foreach ($scouts as $i => $s): ?>
                    <li><span class="pos"><?= $i + 1 ?></span><a href="/@<?= e($s['username']) ?>">@<?= e($s['username']) ?></a><?= $s['verified'] ? ' <span class="tick" title="Verified Scout">✔</span>' : '' ?><span class="pts-num"><?= (int) $s['earned'] ?> pts</span></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?><p class="empty">No scouts yet this week. The first spot is yours.</p><?php endif; ?>
        <div class="how">
            <h3>How points work</h3>
            <ul>
                <li><strong>+10</strong> for every price you post</li>
                <li><strong>+5</strong> when you add a photo</li>
                <li><strong>+20</strong> for the first price of an item at a market</li>
                <li><strong>+3</strong> each time someone confirms your price</li>
            </ul>
            <p class="small muted">Climb from Market Newbie to <strong>Market Legend</strong>. Scouts whose prices keep getting confirmed become Verified, and their prices count double.</p>
        </div>
    </div>
</section>
