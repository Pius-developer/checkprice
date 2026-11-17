<?php $meta = ['title' => 'Log in · CheckPrice']; ?>
<section class="wrap narrow block">
    <h1>Log in</h1>
    <form method="post" action="/login" class="stack" novalidate>
        <?= csrf_field() ?>
        <label for="login">Username or email</label>
        <input id="login" name="login" value="<?= e($login) ?>" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
        <button class="btn btn-block">Log in</button>
    </form>
    <p class="small muted">New here? <a href="/signup">Become a scout</a></p>
</section>
