<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Currency\CurrencyConverter;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    private CurrencyConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_base' => true]);
        Currency::create(['code' => 'IQD', 'name' => 'Iraqi Dinar', 'symbol' => 'IQD', 'decimal_places' => 0]);
        Currency::create(['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'decimal_places' => 2]);

        $this->converter = new CurrencyConverter;
    }

    private function rate(string $from, string $to, string $rate, string $date): void
    {
        ExchangeRate::create([
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'effective_date' => $date,
        ]);
    }

    #[Test]
    public function it_converts_at_the_rate_in_force_on_the_date(): void
    {
        $this->rate('USD', 'IQD', '1310.00000000', '2026-01-01');

        $converted = $this->converter->convert(Money::of('100', 'USD'), 'IQD', '2026-03-15');

        $this->assertSame('131000.0000', $converted->amount);
        $this->assertSame('IQD', $converted->currency);
    }

    /**
     * The reason every method takes a date: a document raised in March must keep
     * its March rate even after the dinar moves in June.
     */
    #[Test]
    public function a_later_rate_does_not_rewrite_an_earlier_document(): void
    {
        $this->rate('USD', 'IQD', '1310.00000000', '2026-01-01');
        $this->rate('USD', 'IQD', '1450.00000000', '2026-06-01');

        $march = $this->converter->convert(Money::of('100', 'USD'), 'IQD', '2026-03-15');
        $july = $this->converter->convert(Money::of('100', 'USD'), 'IQD', '2026-07-15');

        $this->assertSame('131000.0000', $march->amount);
        $this->assertSame('145000.0000', $july->amount);
    }

    #[Test]
    public function it_uses_the_newest_rate_on_or_before_the_date_not_a_future_one(): void
    {
        $this->rate('USD', 'IQD', '1310.00000000', '2026-01-01');
        $this->rate('USD', 'IQD', '1450.00000000', '2026-06-01');

        // 31 May: the June rate exists but is not yet in force.
        $rate = $this->converter->rate('USD', 'IQD', '2026-05-31');

        $this->assertSame('1310.00000000', $rate);
    }

    #[Test]
    public function it_falls_back_to_the_inverse_when_only_one_direction_is_maintained(): void
    {
        $this->rate('USD', 'CNY', '7.13000000', '2026-01-01');

        // No CNY→USD row exists, so 1 / 7.13 is used.
        $converted = $this->converter->convert(Money::of('713', 'CNY'), 'USD', '2026-03-01');

        $this->assertSame('100.0000', $converted->amount);
    }

    #[Test]
    public function converting_to_the_same_currency_is_a_no_op(): void
    {
        $money = Money::of('1240.50', 'USD');

        $this->assertTrue($this->converter->convert($money, 'USD', '2026-03-01')->equals($money));
    }

    #[Test]
    public function it_converts_a_supplier_invoice_into_the_base_currency(): void
    {
        $this->rate('CNY', 'USD', '0.14020000', '2026-01-01');

        $base = $this->converter->toBase(Money::of('1240.00', 'CNY'), '2026-02-01');

        $this->assertSame('USD', $base->currency);
        $this->assertSame('173.8480', $base->amount);
    }

    #[Test]
    public function it_fails_loudly_when_no_rate_exists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No exchange rate for USD→IQD');

        // Guessing a rate would corrupt every downstream cost figure silently.
        $this->converter->convert(Money::of('100', 'USD'), 'IQD', '2026-03-01');
    }

    #[Test]
    public function it_reports_whether_a_rate_is_available(): void
    {
        $this->rate('USD', 'IQD', '1310.00000000', '2026-01-01');

        $this->assertTrue($this->converter->hasRate('USD', 'IQD', '2026-02-01'));
        $this->assertFalse($this->converter->hasRate('USD', 'IQD', '2025-12-01'));
    }

    #[Test]
    public function base_currency_comes_from_the_currencies_table(): void
    {
        $this->assertSame('USD', Currency::base());
    }
}
