<?php

use App\Support\IndonesianNumber;

it('parses rupiah the way it is printed', function (mixed $input, ?float $expected) {
    expect(IndonesianNumber::parse($input))->toBe($expected);
})->with([
    'plain int' => [1250000, 1250000.0],
    'float' => [11.5, 11.5],
    'Rp with dots' => ['Rp 1.250.000', 1250000.0],
    'Rp. prefix' => ['Rp. 755.040', 755040.0],
    'IDR prefix' => ['IDR 4.650', 4650.0],
    'trailing sen' => ['1.250.000,00', 1250000.0],
    'decimal comma' => ['2,5', 2.5],
    'plain digits' => ['1250000', 1250000.0],
    'dot decimal' => ['11.5', 11.5],
    'negative' => ['-1.000', -1000.0],
    'empty' => ['', null],
    'garbage' => ['n/a', null],
    'null' => [null, null],
    'array' => [[1], null],
]);

it('formats amounts with dots between thousands', function () {
    expect(IndonesianNumber::format(7619040))->toBe('7.619.040')
        ->and(IndonesianNumber::format(2.5))->toBe('2,5')
        ->and(IndonesianNumber::format(12.0))->toBe('12');
});

it('can leave out the grouping for form fields', function () {
    expect(IndonesianNumber::format(1000, grouped: false))->toBe('1000')
        ->and(IndonesianNumber::format(1000.25, grouped: false))->toBe('1000,25');
});
