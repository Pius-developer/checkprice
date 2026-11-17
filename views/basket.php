<?php /** My basket (works in the browser, saved on this device). Expects $state. */
$meta = ['title' => 'My basket: what your shopping costs now · CheckPrice']; ?>
<section class="wrap block basket" data-basket data-state="<?= e($state) ?>">
    <h1>My basket</h1>
    <p class="lede">Add what you buy every month. See what it costs today, and how much more than three months ago. Saved on this device only.</p>

    <div class="basket-grid">
        <div class="basket-pick">
            <label for="b-state">State</label>
            <select id="b-state">
                <?php foreach (Catalog::STATES as $slug => $name): ?><option value="<?= e($slug) ?>" <?= $slug === $state ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
            <label for="b-item">Add an item</label>
            <div class="row">
                <select id="b-item">
                    <?php foreach (Catalog::grouped() as $cat => $items): ?>
                        <optgroup label="<?= e(Catalog::CATEGORIES[$cat]) ?>">
                            <?php foreach ($items as $i): ?><option value="<?= e($i['slug']) ?>"><?= e($i['label']) ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="button" id="b-add">Add</button>
            </div>
        </div>

        <div class="basket-result" aria-live="polite">
            <div class="basket-total">
                <span class="muted">Your basket today</span>
                <strong id="b-total">₦0</strong>
                <span id="b-change" class="chg"></span>
            </div>
            <table class="price-table" id="b-table">
                <thead><tr><th>Item</th><th>Qty</th><th>Each</th><th>vs 3 months ago</th><th><span class="sr">Remove</span></th></tr></thead>
                <tbody></tbody>
            </table>
            <p class="small muted" id="b-note"></p>
            <p class="empty" id="b-empty">Your basket is empty. Add rice, garri, eggs… whatever you buy each month.</p>
        </div>
    </div>
</section>
