<?php

namespace App\Support;

final class Base62
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function encode(int $num): string
    {
        if ($num < 0) {
            throw new \InvalidArgumentException('Number must be non-negative');
        }
        if ($num === 0) return '0';

        $base = 62;
        $out = '';
        while ($num > 0) {
            $out = self::ALPHABET[$num % $base] . $out;
            $num = intdiv($num, $base);
        }
        return $out;
    }
}
