<?php

if (! function_exists('rupiah')) {
    function rupiah(int|float|null $amount): string
    {
        if ($amount === null) {
            return '-';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
