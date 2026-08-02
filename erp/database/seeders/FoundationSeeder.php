<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

/**
 * Company profile, the three trading currencies, and an opening rate for each.
 *
 * USD is the base currency: freight, insurance, customs valuation and supplier
 * settlement are all natively USD, so landed cost is a USD figure. IQD reporting
 * is a presentation conversion at the edge rather than a second source of truth.
 */
class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(
            ['name' => config('app.name') === 'Laravel' ? 'Import ERP' : config('app.name')],
            [
                'legal_name' => null,
                'country' => 'IQ',
                'base_currency' => 'USD',
                'fiscal_year_start_month' => 1,
            ],
        );

        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'symbol_position' => 'before', 'is_base' => true, 'sort_order' => 1],
            // Practically never quoted with fils, so no fractional part.
            ['code' => 'IQD', 'name' => 'Iraqi Dinar', 'symbol' => 'IQD', 'decimal_places' => 0, 'symbol_position' => 'after', 'is_base' => false, 'sort_order' => 2],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'decimal_places' => 2, 'symbol_position' => 'before', 'is_base' => false, 'sort_order' => 3],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency);
        }

        // Opening rates only — real rates are maintained in Settings › Currencies,
        // and each document freezes the rate in force on its own date.
        $openingRates = [
            ['from_currency' => 'USD', 'to_currency' => 'IQD', 'rate' => '1310.00000000'],
            ['from_currency' => 'USD', 'to_currency' => 'CNY', 'rate' => '7.13000000'],
            ['from_currency' => 'CNY', 'to_currency' => 'USD', 'rate' => '0.14025000'],
        ];

        foreach ($openingRates as $rate) {
            ExchangeRate::firstOrCreate(
                [
                    'from_currency' => $rate['from_currency'],
                    'to_currency' => $rate['to_currency'],
                    'effective_date' => now()->startOfYear()->toDateString(),
                ],
                ['rate' => $rate['rate'], 'source' => 'manual'],
            );
        }
    }
}
