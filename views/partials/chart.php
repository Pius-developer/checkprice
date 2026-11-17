<?php
/** 12-week price line as SVG. Expects $trend (from Prices::trend). */
$pts = array_values(array_filter($trend, fn ($t) => $t['median'] !== null));
if (count($pts) < 2): ?>
    <p class="muted small chart-empty">Not enough prices yet to draw a trend. Post one to help!</p>
<?php else:
    $W = 640; $H = 180; $pad = 28;
    $vals = array_column($pts, 'median');
    $min = min($vals); $max = max($vals); $span = max(1, $max - $min);
    $n = count($trend);
    $xy = [];
    foreach ($trend as $i => $t) {
        if ($t['median'] === null) continue;
        $x = $pad + $i * ($W - 2 * $pad) / max(1, $n - 1);
        $y = $H - $pad - ($t['median'] - $min) / $span * ($H - 2 * $pad);
        $xy[] = [round($x, 1), round($y, 1), $t];
    }
    $line = implode(' ', array_map(fn ($p) => $p[0] . ',' . $p[1], $xy));
    $area = $line . ' ' . end($xy)[0] . ',' . ($H - $pad) . ' ' . $xy[0][0] . ',' . ($H - $pad);
?>
    <svg class="chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Weekly typical price, last 12 weeks: from <?= naira($xy[0][2]['median']) ?> to <?= naira(end($xy)[2]['median']) ?>">
        <line x1="<?= $pad ?>" x2="<?= $W - $pad ?>" y1="<?= $H - $pad ?>" y2="<?= $H - $pad ?>" class="chart-axis"/>
        <polygon points="<?= $area ?>" class="chart-area"/>
        <polyline points="<?= $line ?>" class="chart-line"/>
        <?php foreach ($xy as [$x, $y, $t]): ?><circle cx="<?= $x ?>" cy="<?= $y ?>" r="4" class="chart-dot"><title><?= e(gmdate('j M', strtotime($t['week']))) ?>: <?= naira($t['median']) ?> (<?= $t['count'] ?> prices)</title></circle><?php endforeach; ?>
        <text x="<?= $pad ?>" y="16" class="chart-label"><?= naira($max) ?></text>
        <text x="<?= $pad ?>" y="<?= $H - 8 ?>" class="chart-label"><?= naira($min) ?></text>
        <text x="<?= $W - $pad ?>" y="<?= $H - 8 ?>" class="chart-label" text-anchor="end">This week</text>
    </svg>
<?php endif; ?>
