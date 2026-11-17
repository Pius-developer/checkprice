<?php /** State picker. Expects $state. Changing it reloads the page with ?state=. */ ?>
<form method="get" class="state-pick" data-autosubmit>
    <label for="state-pick">Prices in</label>
    <select id="state-pick" name="state">
        <?php foreach (Catalog::STATES as $slug => $name): ?>
            <option value="<?= e($slug) ?>" <?= $slug === $state ? 'selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-sm">Show</button></noscript>
</form>
