<?php /** All items for a state. Expects $state, $rows. */
$meta = ['title' => 'Prices in ' . Catalog::state($state) . ' today · CheckPrice']; ?>
<section class="wrap block">
    <div class="block-head">
        <h1>All prices</h1>
        <?php require ROOT . '/views/partials/state.php'; ?>
    </div>
    <?php foreach ($rows as $cat => $list): ?>
        <h2 class="cat-h"><?= e(Catalog::CATEGORIES[$cat]) ?></h2>
        <div class="table-wrap">
            <table class="price-table">
                <thead><tr><th>Item</th><th>Usual price</th><th class="hide-sm">Fair range</th><th class="hide-sm">Prices</th></tr></thead>
                <tbody>
                <?php foreach ($list as $row): $i = $row['item']; $s = $row['sum']; ?>
                    <tr>
                        <td><a href="/item/<?= e($i['slug']) ?>?state=<?= e($state) ?>"><strong><?= e($i['name']) ?></strong></a> <span class="muted"><?= e($i['unit']) ?></span></td>
                        <td class="num"><?= $s['median'] ? '<strong>' . naira($s['median']) . '</strong>' : '<a class="muted" href="/post?item=' . e($i['slug']) . '">Add first</a>' ?></td>
                        <td class="num hide-sm muted"><?= $s['median'] ? naira($s['low']) . ' – ' . naira($s['high']) : '' ?></td>
                        <td class="num hide-sm muted"><?= $s['count'] ?: '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</section>
