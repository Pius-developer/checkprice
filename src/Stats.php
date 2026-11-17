<?php
declare(strict_types=1);

/**
 * The maths, as pure functions (no database, easy to test).
 */
final class Stats
{
    public const SHARP = 'sharp';      // paid clearly less than usual
    public const FAIR  = 'fair';
    public const HIGH  = 'high';       // "dem don cheat you"
    public const FIRST = 'first';      // nobody has posted this item here yet

    /**
     * Weighted quantile. Prices from Verified Scouts count double.
     * @param list<array{price:int, weight:int|float}> $rows
     */
    public static function quantile(array $rows, float $q): ?float
    {
        if (!$rows) return null;
        usort($rows, fn ($a, $b) => $a['price'] <=> $b['price']);
        $total = array_sum(array_column($rows, 'weight'));
        $target = $q * $total;
        $acc = 0.0;
        foreach ($rows as $i => $r) {
            $acc += $r['weight'];
            if ($acc >= $target) {
                // Exactly on a boundary between two prices: take the midpoint (like a normal median)
                if (abs($acc - $target) < 1e-9 && isset($rows[$i + 1]) && $q > 0 && $q < 1) {
                    return ($r['price'] + $rows[$i + 1]['price']) / 2;
                }
                return (float) $r['price'];
            }
        }
        return (float) end($rows)['price'];
    }

    /**
     * Summary used everywhere: typical price and the "fair range" (middle half of prices).
     * @return array{count:int, median:?int, low:?int, high:?int, min:?int, max:?int}
     */
    public static function summary(array $rows): array
    {
        if (!$rows) {
            return ['count' => 0, 'median' => null, 'low' => null, 'high' => null, 'min' => null, 'max' => null];
        }
        $prices = array_column($rows, 'price');
        return [
            'count'  => count($rows),
            'median' => (int) round(self::quantile($rows, 0.5)),
            'low'    => (int) round(self::quantile($rows, 0.25)),
            'high'   => (int) round(self::quantile($rows, 0.75)),
            'min'    => (int) min($prices),
            'max'    => (int) max($prices),
        ];
    }

    /**
     * Compare what someone paid with the typical price.
     * @return array{kind:string, diff:int, percent:float}
     */
    public static function verdict(int $paid, ?int $median): array
    {
        if (!$median) {
            return ['kind' => self::FIRST, 'diff' => 0, 'percent' => 0.0];
        }
        $diff = $paid - $median;
        $pct = $diff / $median * 100;
        $kind = $pct <= -10 ? self::SHARP : ($pct >= 10 ? self::HIGH : self::FAIR);
        return ['kind' => $kind, 'diff' => $diff, 'percent' => round($pct, 1)];
    }

    /**
     * Is a new price so far from normal that it should wait for someone to confirm it?
     * Only applies once there's enough data to know what "normal" is.
     */
    public static function isOutlier(int $price, array $summary): bool
    {
        if ($summary['count'] < 5 || !$summary['median']) return false;
        return $price > $summary['median'] * 2.5 || $price < $summary['median'] / 2.5;
    }

    /** Percent change between two prices, or null if one is missing. */
    public static function change(?int $old, ?int $new): ?float
    {
        if (!$old || !$new) return null;
        return round(($new - $old) / $old * 100, 1);
    }

    // ---- Points, ranks, streaks ------------------------------------------

    public const POINTS = [
        'post'         => 10,
        'photo'        => 5,
        'pioneer'      => 20,   // first price from a market for this item
        'confirmed'    => 3,    // someone confirmed your price
        'confirm'      => 1,    // you confirmed someone's price
        'flagged_hide' => -15,  // your price was hidden after flags
    ];

    public const RANKS = [
        0    => 'Market Newbie',
        50   => 'Price Hunter',
        200  => 'Bargain Boss',
        600  => 'Oga Market',
        1500 => 'Market Legend',
    ];

    /** @return array{name:string, next:?string, to_next:?int, progress:float} */
    public static function rank(int $points): array
    {
        $name = 'Market Newbie'; $floor = 0; $next = null; $nextAt = null;
        foreach (self::RANKS as $at => $r) {
            if ($points >= $at) { $name = $r; $floor = $at; }
            elseif ($nextAt === null) { $next = $r; $nextAt = $at; }
        }
        return [
            'name' => $name, 'next' => $next,
            'to_next' => $nextAt !== null ? $nextAt - $points : null,
            'progress' => $nextAt !== null ? max(0, min(1, ($points - $floor) / ($nextAt - $floor))) : 1.0,
        ];
    }

    /**
     * New streak after posting on $today (Y-m-d, Lagos time).
     * Posting again the same day keeps it; the next day adds one; a gap resets to 1.
     */
    public static function streak(?string $lastDay, int $current, string $today): int
    {
        if ($lastDay === $today) return max(1, $current);
        if ($lastDay && date('Y-m-d', strtotime($today . ' -1 day')) === $lastDay) return $current + 1;
        return 1;
    }

    /** "₦72,500" */
    public static function naira(?int $n): string
    {
        return $n === null ? '—' : '₦' . number_format($n);
    }
}
