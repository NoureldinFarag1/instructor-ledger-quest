<?php

namespace App\Support;

class Money
{
    /** Formats integer piastres for display without any float math. */
    public static function egp(int $piastres): string
    {
        $sign = $piastres < 0 ? '-' : '';
        $abs = abs($piastres);

        return sprintf('%s%s.%02d EGP', $sign, number_format(intdiv($abs, 100)), $abs % 100);
    }
}
