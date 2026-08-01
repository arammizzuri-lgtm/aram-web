<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KpiDaily extends Model
{
    protected $table = 'kpi_daily';

    protected $fillable = [
        'date', 'revenue_base', 'cogs_base', 'gross_profit_base', 'expenses_base',
        'net_profit_base', 'inventory_value_base', 'goods_in_transit_base',
        'receivables_base', 'payables_base', 'cash_balance_base',
        'orders_count', 'invoices_count', 'new_customers', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'computed_at' => 'datetime',
            'revenue_base' => 'decimal:4',
            'cogs_base' => 'decimal:4',
            'gross_profit_base' => 'decimal:4',
            'expenses_base' => 'decimal:4',
            'net_profit_base' => 'decimal:4',
            'inventory_value_base' => 'decimal:4',
            'goods_in_transit_base' => 'decimal:4',
            'receivables_base' => 'decimal:4',
            'payables_base' => 'decimal:4',
        ];
    }

    public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween('date', [$from, $to])->orderBy('date');
    }
}
