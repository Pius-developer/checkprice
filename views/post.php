<?php /** Post a price. Expects $old, $errors. */
$meta = ['title' => 'Post a price · CheckPrice']; ?>
<section class="wrap narrow block">
    <h1>What did you buy?</h1>
    <p class="muted">Post the price you paid. You'll see straight away if it was a good deal, and you'll earn points.</p>
    <form method="post" action="/post" enctype="multipart/form-data" class="stack" novalidate>
        <?= csrf_field() ?>
        <label for="item">Item</label>
        <select id="item" name="item" required>
            <option value="">Choose…</option>
            <?php foreach (Catalog::grouped() as $cat => $items): ?>
                <optgroup label="<?= e(Catalog::CATEGORIES[$cat]) ?>">
                    <?php foreach ($items as $i): ?><option value="<?= e($i['slug']) ?>" <?= $old['item'] === $i['slug'] ? 'selected' : '' ?>><?= e($i['label']) ?></option><?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['item'])): ?><p class="err"><?= e($errors['item']) ?></p><?php endif; ?>

        <label for="price">Price you paid (₦)</label>
        <input id="price" name="price" value="<?= e($old['price']) ?>" inputmode="numeric" placeholder="e.g. 72500" required data-money>
        <?php if (isset($errors['price'])): ?><p class="err"><?= e($errors['price']) ?></p><?php endif; ?>

        <div class="two">
            <div>
                <label for="state">State</label>
                <select id="state" name="state" required>
                    <?php foreach (Catalog::STATES as $slug => $name): ?><option value="<?= e($slug) ?>" <?= $old['state'] === $slug ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                </select>
                <?php if (isset($errors['state'])): ?><p class="err"><?= e($errors['state']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="market">Market or shop</label>
                <input id="market" name="market" value="<?= e($old['market']) ?>" maxlength="60" placeholder="e.g. Mile 12 market" list="market-list" autocomplete="off" required>
                <datalist id="market-list"></datalist>
                <?php if (isset($errors['market'])): ?><p class="err"><?= e($errors['market']) ?></p><?php endif; ?>
            </div>
        </div>

        <label for="photo">Photo <span class="muted">(optional, +5 points)</span></label>
        <label class="photo-drop" for="photo">
            <img id="photo-preview" alt="" hidden>
            <span id="photo-hint">📷 Snap the item or price tag</span>
        </label>
        <input id="photo" name="photo" type="file" accept="image/*" capture="environment" class="sr">
        <p class="small muted">We remove the hidden location data phones add to photos.</p>
        <?php if (isset($errors['photo'])): ?><p class="err"><?= e($errors['photo']) ?></p><?php endif; ?>

        <button class="btn btn-block btn-big">Check my price</button>
    </form>
</section>
