<?php

namespace Tests\Feature;

use App\Filament\Resources\Deals\DealResource;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\RelationManagers\ConsignmentsRelationManager;
use App\Filament\Resources\Deals\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Deals\RelationManagers\PurchasesRelationManager;
use App\Filament\Resources\Deals\RelationManagers\QuotationsRelationManager;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\InvoiceWriter;
use App\Services\Deals\QuotationWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The deal screen as the working screen it was always described as.
 *
 * It was the lines and nothing else. The purchases, the quotations, the
 * tracking numbers and the invoices all existed and all lived on screens
 * listing every deal's at once, so the ordinary questions — has this been paid
 * for, where are the goods, what did I quote — meant leaving the deal to go and
 * find out, and carrying the answer back in your head.
 */
class DealScreenTest extends TestCase
{
    use RefreshDatabase;

    private Deal $deal;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $this->owner->assignRole('owner');
        $this->actingAs($this->owner);

        $customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'USD', 'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Yiwu', 'default_currency' => 'CNY',
        ]);

        $this->deal = Deal::create([
            'number' => 'D-2026-0001',
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 7.2,
        ]);

        DealLine::create([
            'deal_id' => $this->deal->id,
            'supplier_id' => $supplier->id,
            'description' => 'Crystal P07',
            'quantity' => 10,
            'unit_cost' => 12.5,
            'cost_currency' => 'CNY',
            'unit_price' => 3,
        ]);

        // A purchase appears from the line; a quotation and an invoice are made
        // so each list has something in it to render.
        app(DealWriter::class)->sync($this->deal);
        app(QuotationWriter::class)->build($this->deal->fresh());
        app(InvoiceWriter::class)->issueGoods($this->deal->fresh());
    }

    /** @return array<string, array<int, class-string>> */
    public static function relationManagers(): array
    {
        return [
            'quotations' => [QuotationsRelationManager::class],
            'purchases' => [PurchasesRelationManager::class],
            'consignments' => [ConsignmentsRelationManager::class],
            'invoices' => [InvoicesRelationManager::class],
        ];
    }

    #[Test]
    public function the_deal_carries_every_part_of_itself(): void
    {
        $this->assertSame(
            [
                QuotationsRelationManager::class,
                PurchasesRelationManager::class,
                ConsignmentsRelationManager::class,
                InvoicesRelationManager::class,
            ],
            DealResource::getRelations(),
        );
    }

    /**
     * Each one is a Livewire component of its own, so a mistake in any of them
     * takes down the deal screen rather than showing an empty table.
     */
    #[Test]
    public function every_part_of_the_deal_renders(): void
    {
        foreach (array_keys(self::relationManagers()) as $name) {
            $class = self::relationManagers()[$name][0];

            Livewire::test($class, [
                'ownerRecord' => $this->deal->fresh(),
                'pageClass' => EditDeal::class,
            ])->assertOk();
        }
    }

    #[Test]
    public function the_purchases_show_what_is_owed_on_this_deal(): void
    {
        $purchase = $this->deal->purchases()->firstOrFail();

        Livewire::test(PurchasesRelationManager::class, [
            'ownerRecord' => $this->deal->fresh(),
            'pageClass' => EditDeal::class,
        ])->assertCanSeeTableRecords([$purchase]);
    }

    #[Test]
    public function the_invoices_issued_from_the_deal_appear_on_it(): void
    {
        $invoice = $this->deal->invoices()->firstOrFail();

        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $this->deal->fresh(),
            'pageClass' => EditDeal::class,
        ])->assertCanSeeTableRecords([$invoice]);
    }

    /**
     * The commercial boundary holds here too.
     *
     * The hiding has to be total — a cost that leaks through one table defeats
     * the entire arrangement, and a purchase list is nothing but cost.
     */
    #[Test]
    public function an_assistant_is_not_shown_the_buying_side(): void
    {
        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        $this->assertFalse(
            PurchasesRelationManager::canViewForRecord($this->deal, EditDeal::class),
            'the purchases are cost from end to end',
        );

        $this->assertTrue(
            InvoicesRelationManager::canViewForRecord($this->deal, EditDeal::class),
            'an assistant does bill customers',
        );
    }
}
