<?php
declare(strict_types=1);

/**
 * Draws the shareable 1200×630 verdict card with PHP's GD library.
 * Cards are cached in data/cards/ because a report never changes.
 */
final class Card
{
    private const W = 1200;
    private const H = 630;

    public static function path(array $r): string
    {
        $dir = ROOT . '/data/cards';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = $dir . '/' . (int) $r['id'] . '-v2.png';
        if (!is_file($file)) {
            imagepng(self::draw($r), $file, 6);
        }
        return $file;
    }

    public static function draw(array $r): GdImage
    {
        $f = ROOT . '/src/fonts/';
        $bold = $f . 'InterDisplay-ExtraBold.otf';
        $semi = $f . 'Inter-SemiBold.otf';
        $med  = $f . 'Inter-Medium.otf';

        $img = imagecreatetruecolor(self::W, self::H);
        imageantialias($img, true);
        $c = fn (string $hex) => imagecolorallocate($img, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
        $white = $c('#FFFFFF'); $ink = $c('#0E1A12'); $muted = $c('#5B6660'); $line = $c('#E3E8E5');
        [$bg, $fg, $soft] = match ($r['verdict']) {
            Stats::SHARP => ['#12A150', '#FFFFFF', '#D7F5E3'],
            Stats::HIGH  => ['#E5484D', '#FFFFFF', '#FDE2E2'],
            Stats::FAIR  => ['#F2B705', '#111111', '#FFF4CC'],
            default      => ['#111111', '#FFFFFF', '#E9ECEA'],
        };
        imagefill($img, 0, 0, $white);

        // Verdict band on the left
        imagefilledrectangle($img, 0, 0, 470, self::H, $c($bg));
        [$title, $sub] = verdict_text($r);
        $title = preg_replace('/\s*[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]+/u', '', $title);   // emoji don't render in GD fonts
        $y = 150;
        foreach (self::wrap($title, $bold, 62, 390) as $ln) {
            imagettftext($img, 62, 0, 50, $y, $c($fg), $bold, $ln);
            $y += 78;
        }
        $y += 10;
        foreach (self::wrap($sub, $med, 24, 390) as $ln) {
            imagettftext($img, 24, 0, 50, $y, $c($fg), $med, $ln);
            $y += 36;
        }

        // Right side: the item and the numbers
        $item = Catalog::item($r['item']);
        $x = 530;
        imagettftext($img, 26, 0, $x, 110, $muted, $semi, Catalog::state($r['state']) . ' · ' . mb_strimwidth($r['market'], 0, 28, '…'));
        $y = 175;
        foreach (self::wrap($item['name'], $bold, 48, 620) as $ln) {
            imagettftext($img, 48, 0, $x, $y, $ink, $bold, $ln);
            $y += 58;
        }
        imagettftext($img, 26, 0, $x, $y, $muted, $med, $item['unit']);

        $y += 90;
        // Shrink the two numbers until both fit side by side with a clear gap
        $paid = self::naira((int) $r['price']);
        $usual = $r['compared_to'] ? self::naira((int) $r['compared_to']) : '—';
        $size = 54;
        do {
            $pw = self::width($paid, $bold, $size);
            $uw = self::width($usual, $bold, $size);
            $size -= 2;
        } while ($pw + $uw + 60 > self::W - 50 - $x && $size > 26);
        $size += 2;
        $ux = $x + max(300, $pw + 60);
        imagettftext($img, 22, 0, $x, $y, $muted, $semi, 'PAID');
        imagettftext($img, 22, 0, $ux, $y, $muted, $semi, 'USUAL PRICE');
        imagettftext($img, $size, 0, $x, $y + 70, $ink, $bold, $paid);
        imagettftext($img, $size, 0, $ux, $y + 70, $muted, $bold, $usual);

        // Footer
        imageline($img, $x, 545, self::W - 50, 545, $line);
        imagettftext($img, 26, 0, $x, 592, $c('#12A150'), $bold, 'CheckPrice');
        $host = preg_replace('#^https?://#', '', base_url());
        $logoEnd = $x + self::width('CheckPrice', $bold, 26) + 30;
        $foot = 'Check yours at ' . $host;
        if (self::width($foot, $med, 20) > self::W - 50 - $logoEnd) $foot = $host;
        $fs = 20;
        while (self::width($foot, $med, $fs) > self::W - 50 - $logoEnd && $fs > 12) $fs--;
        imagettftext($img, $fs, 0, self::W - 50 - self::width($foot, $med, $fs), 590, $muted, $med, $foot);
        return $img;
    }

    private static function naira(int $n): string
    {
        return '₦' . number_format($n);
    }

    private static function width(string $text, string $font, int $size): int
    {
        $b = imagettfbbox($size, 0, $font, $text);
        return $b[2] - $b[0];
    }

    private static function wrap(string $text, string $font, int $size, int $max): array
    {
        $lines = []; $cur = '';
        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            $try = $cur === '' ? $word : "$cur $word";
            $b = imagettfbbox($size, 0, $font, $try);
            if ($b[2] - $b[0] > $max && $cur !== '') {
                $lines[] = $cur;
                $cur = $word;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines;
    }
}
