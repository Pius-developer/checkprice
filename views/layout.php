<?php
$meta = ($meta ?? []) + [
    'title'       => 'CheckPrice: what things really cost in Nigeria, today',
    'description' => 'Real prices of rice, garri, eggs, petrol, gas and more, posted by people in markets across Nigeria. Check before you buy, and see if they cheated you.',
    'url'         => base_url() . ($_SERVER['REQUEST_URI'] ?? '/'),
    'image'       => base_url() . '/assets/og.png',
];
$rank = $me ? rank_of((int) $me['points']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($meta['title']) ?></title>
    <meta name="description" content="<?= e($meta['description']) ?>">
    <meta name="theme-color" content="#12A150">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="CheckPrice">
    <meta property="og:title" content="<?= e($meta['title']) ?>">
    <meta property="og:description" content="<?= e($meta['description']) ?>">
    <meta property="og:url" content="<?= e($meta['url']) ?>">
    <meta property="og:image" content="<?= e($meta['image']) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?= e($meta['image']) ?>">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="preload" href="/assets/fonts/InterDisplay-ExtraBold.woff" as="font" type="font/woff" crossorigin>
    <link rel="stylesheet" href="/assets/app.css?v=1">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="top">
    <div class="wrap top-row">
        <a class="logo" href="/" aria-label="CheckPrice home"><span class="logo-tag" aria-hidden="true">₦</span>CheckPrice</a>
        <nav aria-label="Main">
            <a href="/items" class="hide-sm">Prices</a>
            <a href="/basket" class="hide-sm">My basket</a>
            <a href="/scouts" class="hide-sm">Top scouts</a>
            <?php if ($me): ?>
                <a href="/@<?= e($me['username']) ?>" class="me" title="<?= e($rank['name']) ?>"><span class="pts"><?= number_format((int) $me['points']) ?></span> @<?= e($me['username']) ?></a>
            <?php else: ?>
                <a href="/login">Log in</a>
            <?php endif; ?>
            <a class="btn btn-sm" href="/post">Post a price</a>
        </nav>
    </div>
</header>
<nav class="tabbar" aria-label="Sections">
    <a href="/items">Prices</a><a href="/basket">Basket</a><a href="/scouts">Scouts</a><a href="/report">Report</a>
</nav>

<main id="main">
    <?php if ($flash): ?><div class="wrap"><p class="flash" role="status"><?= e($flash) ?></p></div><?php endif; ?>
    <?= $content ?>
</main>

<footer class="foot">
    <div class="wrap foot-row">
        <p><strong>CheckPrice</strong> is built by <a href="https://ejikepius.dev"><?= e(config('creator')) ?></a> as part of The Daily Ship. Prices are posted by the public, so always use your own judgement.</p>
        <p class="foot-links"><a href="/report">Weekly report</a><a href="/alerts">Price alerts</a><a href="<?= e(config('github_url')) ?>">GitHub</a><?php if ($me): ?><form method="post" action="/logout" class="inline"><?= csrf_field() ?><button class="link-btn">Log out</button></form><?php endif; ?></p>
    </div>
</footer>
<script src="/assets/app.js?v=1" defer></script>
</body>
</html>
