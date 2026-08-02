<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[Test]
    public function it_keeps_the_precision_floats_would_lose(): void
    {
        // 0.1 + 0.2 !== 0.3 in binary floating point. It must here.
        $sum = Money::of('0.1', 'USD')->plus(Money::of('0.2', 'USD'));

        $this->assertSame('0.3000', $sum->amount);
        $this->assertTrue($sum->equals(Money::of('0.3', 'USD')));
    }

    #[Test]
    public function it_adds_and_subtracts(): void
    {
        $a = Money::of('18300.00', 'USD');
        $b = Money::of('8148.5707', 'USD');

        $this->assertSame('26448.5707', $a->plus($b)->amount);
        $this->assertSame('10151.4293', $a->minus($b)->amount);
    }

    #[Test]
    public function it_multiplies_a_quantity_by_a_unit_price(): void
    {
        $this->assertSame('8500.0000', Money::of('85.00', 'USD')->times(100)->amount);
        $this->assertSame('4400.0000', Money::of('220.00', 'USD')->times('20')->amount);
    }

    #[Test]
    public function it_divides_to_a_unit_cost(): void
    {
        $this->assertSame('107.6865', Money::of('10768.6507', 'USD')->dividedBy(100)->amount);
        $this->assertSame('406.1574', Money::of('8123.1487', 'USD')->dividedBy(20)->amount);
    }

    #[Test]
    public function it_rounds_half_up_away_from_zero(): void
    {
        $this->assertSame('2.35', Money::of('2.345', 'USD')->roundTo(2)->amount);
        $this->assertSame('-2.35', Money::of('-2.345', 'USD')->roundTo(2)->amount);
        $this->assertSame('2.34', Money::of('2.3449', 'USD')->roundTo(2)->amount);
    }

    #[Test]
    public function it_refuses_to_mix_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('100', 'USD')->plus(Money::of('100', 'IQD'));
    }

    #[Test]
    public function it_rejects_a_malformed_currency_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('100', 'DOLLAR');
    }

    #[Test]
    public function it_refuses_division_by_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('100', 'USD')->dividedBy(0);
    }

    // ------------------------------------------------------------ allocation

    /**
     * The freight split from docs/04-LANDED-COST.md §4.3.
     *
     * This is the case that proves the residual rule: the three rounded shares
     * come to 3199.9999, and the missing hundredth of a cent has to land on the
     * largest line rather than evaporate.
     */
    #[Test]
    public function it_allocates_sea_freight_by_volume_and_keeps_the_residual(): void
    {
        $freight = Money::of('3200.00', 'USD');

        $shares = $freight->allocate([
            'crystal' => '8.00',
            'sofa' => '32.00',
            'fabric' => '18.00',
        ]);

        $this->assertSame('441.3793', $shares['crystal']->amount);
        $this->assertSame('1765.5173', $shares['sofa']->amount, 'the residual belongs to the largest line');
        $this->assertSame('993.1034', $shares['fabric']->amount);

        $this->assertTrue(Money::sum(...array_values($shares))->equals($freight));
    }

    #[Test]
    public function it_allocates_insurance_by_value(): void
    {
        $shares = Money::of('183.00', 'USD')->allocate([8500, 4400, 5400]);

        $this->assertSame('85.0000', $shares[0]->amount);
        $this->assertSame('44.0000', $shares[1]->amount);
        $this->assertSame('54.0000', $shares[2]->amount);
    }

    #[Test]
    public function it_allocates_every_remaining_cost_from_the_worked_example(): void
    {
        $value = [8500, 4400, 5400];
        $volume = ['8.00', '32.00', '18.00'];

        $cases = [
            ['450.00', $value, ['209.0164', '108.1967', '132.7869']],   // clearance agent
            ['95.00', $value, ['44.1257', '22.8415', '28.0328']],       // bank charges
            ['380.00', $volume, ['52.4138', '209.6552', '117.9310']],   // port charges
            ['600.00', $volume, ['82.7586', '331.0345', '186.2069']],   // inland transport
        ];

        foreach ($cases as [$amount, $ratios, $expected]) {
            $cost = Money::of($amount, 'USD');
            $shares = $cost->allocate($ratios);

            $this->assertSame($expected, array_map(fn (Money $m) => $m->amount, $shares), "allocating {$amount}");
            $this->assertTrue(Money::sum(...$shares)->equals($cost), "allocating {$amount} must reconcile");
        }
    }

    #[Test]
    public function allocation_always_reconciles_however_awkward_the_ratios(): void
    {
        // 1/3 splits are the classic place for a cent to go missing.
        $cost = Money::of('1000.00', 'USD');
        $shares = $cost->allocate([1, 1, 1]);

        $this->assertTrue(Money::sum(...$shares)->equals($cost));

        $seven = Money::of('0.01', 'USD')->allocate([3, 5, 11, 2, 7, 13, 1]);
        $this->assertTrue(Money::sum(...$seven)->equals(Money::of('0.01', 'USD')));
    }

    #[Test]
    public function it_falls_back_to_an_even_split_when_every_ratio_is_zero(): void
    {
        // A shipment whose products have no recorded CBM must still cost out.
        $shares = Money::of('300.00', 'USD')->allocate([0, 0, 0]);

        $this->assertSame('100.0000', $shares[0]->amount);
        $this->assertTrue(Money::sum(...$shares)->equals(Money::of('300.00', 'USD')));
    }

    #[Test]
    public function it_preserves_keys_so_shares_map_back_onto_lines(): void
    {
        $shares = Money::of('100.00', 'USD')->allocate([17 => 1, 42 => 3]);

        $this->assertSame([17, 42], array_keys($shares));
        $this->assertSame('25.0000', $shares[17]->amount);
        $this->assertSame('75.0000', $shares[42]->amount);
    }

    #[Test]
    public function it_rejects_negative_allocation_ratios(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('100', 'USD')->allocate([5, -2]);
    }

    // ------------------------------------------------------------ conversion

    #[Test]
    public function it_converts_at_a_given_rate(): void
    {
        $usd = Money::of('1000.00', 'USD');
        $iqd = $usd->convertTo('IQD', '1310.00000000');

        $this->assertSame('IQD', $iqd->currency);
        $this->assertSame('1310000.0000', $iqd->amount);
    }

    // --------------------------------------------------------------- display

    #[Test]
    public function it_formats_to_a_currencys_own_precision(): void
    {
        $this->assertSame('$18,300.00', Money::of('18300', 'USD')->format(2, '$'));
        $this->assertSame('24,150,000 IQD', Money::of('24150000', 'IQD')->format(0, 'IQD', 'after'));
    }

    #[Test]
    public function it_formats_negatives_with_a_true_minus_sign(): void
    {
        // U+2212, not a hyphen — it aligns in tabular figures.
        $this->assertSame("\u{2212}$1,240.00", Money::of('-1240', 'USD')->format(2, '$'));
    }

    #[Test]
    public function it_reports_sign_and_zero(): void
    {
        $this->assertTrue(Money::zero('USD')->isZero());
        $this->assertTrue(Money::of('-0.0001', 'USD')->isNegative());
        $this->assertTrue(Money::of('0.0001', 'USD')->isPositive());
        $this->assertSame('5.0000', Money::of('-5', 'USD')->absolute()->amount);
    }

    /**
     * Each currency written the way it is actually written.
     *
     * `format()` defaults to no symbol at all, which produced a dashboard where
     * one tile read "$8,655.75" and the one beside it read "9,523.81" — the
     * reader could not tell whether the second was dollars, dinars, or a count.
     */
    #[Test]
    public function display_carries_the_currency_it_is_in(): void
    {
        $this->assertSame('$1,234.56', Money::of('1234.56', 'USD')->display());
        $this->assertSame('¥6,250.00', Money::of('6250', 'CNY')->display());

        // The dinar has no subunit in practice and is written after the number.
        $this->assertSame('14,000,000 IQD', Money::of('14000000', 'IQD')->display());
    }

    /** An unknown currency still says which one it is, rather than nothing. */
    #[Test]
    public function display_falls_back_to_the_currency_code(): void
    {
        $this->assertSame('50.00 EUR', Money::of('50', 'EUR')->display());
    }

    #[Test]
    public function a_negative_display_keeps_the_minus_outside_the_symbol(): void
    {
        $this->assertSame("\u{2212}$3,431.65", Money::of('-3431.65', 'USD')->display());
    }
}
