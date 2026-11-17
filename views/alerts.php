<?php /** Price alerts. Expects $alerts, $errors. */
$meta = ['title' => 'Price alerts · CheckPrice'];
$pre = ['item' => (string) ($_GET['item'] ?? ''), 'state' => (string) ($_GET['state'] ?? ($me['home_state'] ?? 'lagos'))]; ?>
<section class="wrap narrow block">
    <h1>Price alerts</h1>
    <p class="muted">Get an email when the usual price of something drops to your target. We check once a day.</p>
    <form method="post" action="/alerts" class="stack" novalidate>
        <?= csrf_field() ?>
        <label for="a-item">Item</label>
        <select id="a-item" name="item">
            <?php foreach (Catalog::grouped() as $cat => $items): ?>
                <optgroup label="<?= e(Catalog::CATEGORIES[$cat]) ?>">
                    <?php foreach ($items as $i): ?><option value="<?= e($i['slug']) ?>" <?= $pre['item'] === $i['slug'] ? 'selected' : '' ?>><?= e($i['label']) ?></option><?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <div class="two">
            <div><label for="a-state">State</label>
                <select id="a-state" name="state"><?php foreach (Catalog::STATES as $slug => $name): ?><option value="<?= e($slug) ?>" <?= $pre['state'] === $slug ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
            <div><label for="a-below">Email me when it's ₦… or less</label>
                <input id="a-below" name="below" inputmode="numeric" placeholder="e.g. 70000" data-money></div>
        </div>
        <?php if (isset($errors['below'])): ?><p class="err"><?= e($errors['below']) ?></p><?php endif; ?>
        <button class="btn">Set alert</button>
    </form>
    <?php if ($alerts): ?>
        <h2>Your alerts</h2>
        <ul class="alert-list">
            <?php foreach ($alerts as $a): $i = Catalog::item($a['item']); $now = prices()->summary($a['item'], $a['state']); ?>
                <li>
                    <span><strong><?= e($i['label']) ?></strong> in <?= e(Catalog::state($a['state'])) ?> at <?= naira((int) $a['below']) ?> or less<br>
                    <span class="small muted">Now: <?= naira($now['median']) ?><?= $a['last_sent_at'] ? ' · last emailed ' . e(ago($a['last_sent_at'])) : '' ?></span></span>
                    <form method="post" action="/alerts/<?= (int) $a['id'] ?>/delete" class="inline"><?= csrf_field() ?><button class="link-btn">Remove</button></form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
