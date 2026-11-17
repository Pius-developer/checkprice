<?php $meta = ['title' => 'Become a price scout · CheckPrice']; ?>
<section class="wrap narrow block">
    <h1>Become a price scout</h1>
    <p class="muted">Free. Post prices, earn points, climb to Market Legend. Browsing never needs an account.</p>
    <form method="post" action="/signup" class="stack" novalidate>
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input id="username" name="username" value="<?= e($old['username']) ?>" maxlength="20" autocomplete="username" required>
        <?php if (isset($errors['username'])): ?><p class="err"><?= e($errors['username']) ?></p><?php endif; ?>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" autocomplete="email" required>
        <?php if (isset($errors['email'])): ?><p class="err"><?= e($errors['email']) ?></p><?php endif; ?>
        <label for="state">Your state</label>
        <select id="state" name="state"><?php foreach (Catalog::STATES as $slug => $name): ?><option value="<?= e($slug) ?>" <?= $old['state'] === $slug ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select>
        <?php if (isset($errors['state'])): ?><p class="err"><?= e($errors['state']) ?></p><?php endif; ?>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
        <?php if (isset($errors['password'])): ?><p class="err"><?= e($errors['password']) ?></p><?php endif; ?>
        <button class="btn btn-block">Create my account</button>
    </form>
    <p class="small muted">Already a scout? <a href="/login">Log in</a></p>
</section>
