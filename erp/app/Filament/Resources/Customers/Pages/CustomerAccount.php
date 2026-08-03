<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\Deal;
use App\Services\Customers\CustomerAccount as Account;
use App\Services\Deals\PaymentWriter;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

/**
 * One customer, and everything that has passed between you.
 *
 * There was no such screen. Customers was a flat list with a balance column,
 * invoices were on their own screen and payments on a third, and answering
 * "where are we with this customer?" meant three screens and holding the
 * arithmetic in your head. That is the whole reason none of it felt clear.
 *
 * The page reads as an account, not a receivable: money in counts up, invoices
 * count down, and a balance below zero means they owe you. The sign is turned
 * over from the way the reports state it — see CustomerAccount — because this
 * is the screen you look at with the customer in front of you, and "your
 * balance is minus two thousand" is a sentence they already understand.
 */
class CustomerAccount extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CustomerResource::class;

    protected string $view = 'filament.customers.account';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return (string) $this->customer()->name;
    }

    public function customer(): Customer
    {
        return $this->getRecord();
    }

    private function account(): Account
    {
        return app(Account::class);
    }

    // ---------------------------------------------------------------- money

    /**
     * The figures across the top, worked out once for the view.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $customer = $this->customer();
        $account = $this->account();

        $statement = $account->statement($customer);

        $deposits = $statement
            ->where('kind', 'deposit')
            ->sum(fn (array $m) => $m['change']->toFloat());

        $spending = $statement
            ->where('kind', 'spending')
            ->sum(fn (array $m) => abs($m['change']->toFloat()));

        $withdrawals = $statement
            ->where('kind', 'withdrawal')
            ->sum(fn (array $m) => abs($m['change']->toFloat()));

        $balance = $account->balance($customer);

        return [
            'balance' => $balance,
            // The sentence under the big number. A balance is meaningless
            // without knowing which way it points.
            'balance_meaning' => match (true) {
                $balance->isNegative() => 'they owe you',
                $balance->isPositive() => 'credit you are holding',
                default => 'settled',
            },
            'deposits' => Money::of($deposits, 'USD'),
            'spending' => Money::of($spending, 'USD'),
            'withdrawals' => Money::of($withdrawals, 'USD'),
            'credit' => $account->credit($customer),
            'owed' => $account->owed($customer),
            'ageing' => $account->ageing($customer),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function statement(): Collection
    {
        // Newest first on screen: the last thing that happened is the thing
        // being asked about. The running balance is still built oldest-first,
        // so each row shows where the account stood after it.
        return $this->account()->statement($this->customer())->reverse()->values();
    }

    /**
     * The balance line, as points on a 100 × 40 grid.
     *
     * Drawn as SVG rather than handed to a charting library: twelve points and
     * a zero line need no JavaScript, and this way the chart takes its colours
     * from the same tokens as everything around it and cannot fail to load.
     *
     * @return array<string, mixed>
     */
    public function chart(): array
    {
        $series = $this->account()->balanceByMonth($this->customer(), 12);
        $values = $series->pluck('balance')->all();

        $high = max(max($values), 0.0);
        $low = min(min($values), 0.0);
        $span = ($high - $low) ?: 1.0;

        // A little headroom, so the line never runs along the very edge.
        $high += $span * 0.12;
        $low -= $span * 0.12;
        $span = $high - $low;

        $y = fn (float $value): float => round((1 - (($value - $low) / $span)) * 40, 2);
        $x = fn (int $index): float => round($index * (100 / max(1, count($values) - 1)), 2);

        $points = [];

        foreach ($values as $index => $value) {
            $points[] = $x($index).','.$y($value);
        }

        return [
            'points' => implode(' ', $points),
            // Closed back along the baseline so the area can be filled.
            'area' => implode(' ', $points).' '.$x(count($values) - 1).','.$y(0.0).' '.$x(0).','.$y(0.0),
            'zero' => $y(0.0),
            'last' => end($values) ?: 0.0,
            'months' => $series->map(fn (array $point) => $point['month']->format('M'))->all(),
            'series' => $series->all(),
            'everNegative' => min($values) < 0,
        ];
    }

    /** @return Collection<int, CustomerInvoice> */
    public function invoices(): Collection
    {
        return $this->customer()->invoices()
            ->with(['allocations', 'deal'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
    }

    /** @return Collection<int, Deal> */
    public function deals(): Collection
    {
        return $this->customer()->deals()
            ->with('lines')
            ->orderByDesc('deal_date')
            ->limit(15)
            ->get();
    }

    // -------------------------------------------------------------- actions

    protected function getHeaderActions(): array
    {
        return [
            $this->receiveAction(),
            $this->applyCreditAction(),
            $this->refundAction(),

            Action::make('back')
                ->label('All customers')
                ->color('gray')
                ->url(fn () => CustomerResource::getUrl()),
        ];
    }

    /** Money arriving. The common case, so it leads. */
    private function receiveAction(): Action
    {
        return Action::make('receive')
            ->label('Record a payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading(fn () => 'Money in from '.$this->customer()->name)
            ->schema([
                TextInput::make('amount')->label('Amount')->numeric()->required(),

                Select::make('currency')
                    ->options(['USD' => 'USD', 'IQD' => 'IQD', 'CNY' => 'RMB'])
                    ->default(fn () => $this->customer()->default_currency ?? 'USD')
                    ->required()
                    ->live(),

                TextInput::make('exchange_rate')
                    ->label('Rate to USD')
                    ->numeric()
                    ->helperText('Only needed when it is not dollars. Leave blank to use today\'s.')
                    ->visible(fn ($get) => $get('currency') !== 'USD'),

                Select::make('method')
                    ->options([
                        'cash' => 'Cash',
                        'bank' => 'Bank transfer',
                        'exchange' => 'Exchange office',
                        'other' => 'Other',
                    ])
                    ->default('cash')
                    ->native(false),

                DatePicker::make('paid_at')->label('Date')->default(now())->required(),
                TextInput::make('reference')->label('Reference')->maxLength(255),
                Textarea::make('notes')->rows(2),
            ])
            ->action(function (array $data): void {
                $payments = app(PaymentWriter::class);

                $payment = $payments->receive(
                    customer: $this->customer(),
                    amount: abs((float) $data['amount']),
                    currency: $data['currency'],
                    exchangeRate: $payments->rate($data['exchange_rate'] ?? null),
                    paidAt: $data['paid_at'] ?? null,
                    method: $data['method'] ?? 'cash',
                    reference: $data['reference'] ?? null,
                    notes: $data['notes'] ?? null,
                );

                // Matched straight away against whatever is outstanding, oldest
                // first. Anything left over stays as credit and goes on to the
                // next invoice by itself.
                $payments->autoAllocate($payment);

                $left = $payment->fresh()->load('allocations')->unallocatedBase();

                Notification::make()
                    ->title("Payment {$payment->number} recorded")
                    ->body($left->isPositive()
                        ? $left->display().' of it is credit, and will go against their next invoice.'
                        : 'Matched against what was outstanding.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Spend credit now rather than waiting for the next invoice.
     *
     * Credit finds its own way onto new invoices, but an invoice raised before
     * the money arrived will not have seen it — so the button exists for the
     * case the automatic path cannot cover.
     */
    private function applyCreditAction(): Action
    {
        return Action::make('applyCredit')
            ->label('Use the credit')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->visible(fn () => $this->account()->credit($this->customer())->isPositive()
                && $this->account()->owed($this->customer())->isPositive())
            ->requiresConfirmation()
            ->modalHeading('Put the credit against what is owed')
            ->modalDescription(fn () => sprintf(
                '%s of credit against %s outstanding, oldest invoices first.',
                $this->account()->credit($this->customer())->display(),
                $this->account()->owed($this->customer())->display(),
            ))
            ->action(function (): void {
                $payments = app(PaymentWriter::class);
                $applied = Money::zero('USD');

                foreach ($this->account()->paymentsWithCredit($this->customer()) as $payment) {
                    $before = $payment->unallocatedBase();
                    $payments->autoAllocate($payment);
                    $after = $payment->fresh()->load('allocations')->unallocatedBase();

                    $applied = $applied->plus($before->minus($after));
                }

                Notification::make()
                    ->title($applied->isPositive() ? $applied->display().' applied' : 'Nothing to apply')
                    ->success()
                    ->send();
            });
    }

    /** Money going back out — an overpayment returned, or a deal that failed. */
    private function refundAction(): Action
    {
        return Action::make('refund')
            ->label('Refund')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->schema([
                TextInput::make('amount')
                    ->label('Amount to return')
                    ->numeric()
                    ->required()
                    ->helperText(fn () => 'Credit held: '.$this->account()->credit($this->customer())->display()),

                Select::make('currency')
                    ->options(['USD' => 'USD', 'IQD' => 'IQD', 'CNY' => 'RMB'])
                    ->default(fn () => $this->customer()->default_currency ?? 'USD')
                    ->required(),

                DatePicker::make('paid_at')->label('Date')->default(now())->required(),
                Textarea::make('notes')->rows(2),
            ])
            ->action(function (array $data): void {
                $payments = app(PaymentWriter::class);

                $refund = $payments->refund(
                    customer: $this->customer(),
                    amount: abs((float) $data['amount']),
                    currency: $data['currency'],
                    paidAt: $data['paid_at'] ?? null,
                    notes: $data['notes'] ?? null,
                );

                Notification::make()->title("Refund {$refund->number} recorded")->success()->send();
            });
    }

    /** @return Collection<int, CustomerPayment> */
    public function paymentsWithCredit(): Collection
    {
        return $this->account()->paymentsWithCredit($this->customer());
    }
}
