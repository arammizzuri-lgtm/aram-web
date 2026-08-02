<?php

namespace Tests\Feature;

use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\QuotationWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The approval record is the point of this feature.
 *
 * Anyone can print a price list. What protects you is being able to say, six
 * weeks later, exactly which model at exactly which price the customer agreed
 * to — and having that not move when the deal is edited afterwards.
 */
class QuotationTest extends TestCase
{
    use RefreshDatabase;

    private Deal $deal;

    private QuotationWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $owner->assignRole('owner');
        $this->actingAs($owner);

        $customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'IQD', 'document_language' => 'ckb', 'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Yiwu Crystals', 'default_currency' => 'CNY',
        ]);

        $this->deal = Deal::create([
            'number' => 'D-2026-0001',
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
        ]);

        DealLine::create([
            'deal_id' => $this->deal->id,
            'supplier_id' => $supplier->id,
            'description' => 'Crystal P07 20mm · Gold',
            'specification' => 'AB finish, 20mm faceted',
            'photo_path' => 'quotations/p07-gold.jpg',
            'quantity' => 500,
            'unit_cost' => 12.50,
            'cost_currency' => 'CNY',
            'unit_price' => 28000,
        ]);

        $this->deal = app(DealWriter::class)->sync($this->deal->fresh());
        $this->writer = app(QuotationWriter::class);
    }

    // ---------------------------------------------------------------- building

    #[Test]
    public function a_quotation_copies_the_deals_lines_and_their_photos(): void
    {
        $quotation = $this->writer->build($this->deal);

        $this->assertSame(1, $quotation->version);
        $this->assertSame('draft', $quotation->status);
        $this->assertSame('IQD', $quotation->currency);

        $line = $quotation->lines()->first();

        $this->assertSame('Crystal P07 20mm · Gold', $line->description);
        $this->assertSame('quotations/p07-gold.jpg', $line->photo_path);
        $this->assertSame('28000.0000', $line->unit_price);
        $this->assertSame('14000000.0000', $line->line_total);
    }

    /** The total is stated in the customer's currency and again in dollars. */
    #[Test]
    public function the_total_is_recorded_in_both_currencies(): void
    {
        $quotation = $this->writer->build($this->deal);

        $this->assertSame('14000000.0000', $quotation->total);
        // 14,000,000 IQD / 1,470 = 9,523.8095
        $this->assertSame(9523.81, round((float) $quotation->total_base, 2));
    }

    /** Printing must never ask a question the customer record already answers. */
    #[Test]
    public function the_language_comes_from_the_customer(): void
    {
        $quotation = $this->writer->build($this->deal);

        $this->assertSame('ckb', $quotation->language);
        $this->assertTrue($quotation->isRightToLeft());
    }

    #[Test]
    public function a_quotation_cannot_be_built_from_a_deal_with_no_items(): void
    {
        $empty = Deal::create([
            'number' => 'D-2026-0002',
            'customer_id' => $this->deal->customer_id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
        ]);

        $this->expectException(RuntimeException::class);
        $this->writer->build($empty);
    }

    // ------------------------------------------------------------- versioning

    #[Test]
    public function rebuilding_supersedes_the_previous_version_rather_than_editing_it(): void
    {
        $first = $this->writer->build($this->deal);
        $this->writer->markSent($first);

        $second = $this->writer->build($this->deal->fresh());

        $this->assertSame(2, $second->version);
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame(2, $this->deal->quotations()->count());
    }

    // -------------------------------------------------------------- approval

    #[Test]
    public function approval_records_who_said_yes_and_how(): void
    {
        $quotation = $this->writer->markSent($this->writer->build($this->deal));

        $approved = $this->writer->approve($quotation, 'Ali Hassan', 'whatsapp', 'Confirmed the gold finish');

        $this->assertSame('approved', $approved->status);
        $this->assertSame('Ali Hassan', $approved->approved_by_name);
        $this->assertSame('whatsapp', $approved->approval_channel);
        $this->assertNotNull($approved->approved_at);
        // The person who typed it in is a user; the person who agreed is not.
        $this->assertSame(auth()->id(), $approved->recorded_by);
    }

    #[Test]
    public function approving_a_quotation_approves_the_deal(): void
    {
        $quotation = $this->writer->build($this->deal);

        $this->assertFalse($this->deal->isApproved());

        $this->writer->approve($quotation, 'Ali Hassan');

        $deal = $this->deal->fresh();
        $this->assertTrue($deal->isApproved());
        $this->assertSame('approved', $deal->status);
    }

    /**
     * The whole reason the snapshot exists.
     *
     * Change the price and the photo on the deal afterwards; the approved
     * document must still show what was agreed.
     */
    #[Test]
    public function editing_the_deal_afterwards_cannot_rewrite_what_was_approved(): void
    {
        $quotation = $this->writer->approve(
            $this->writer->build($this->deal),
            'Ali Hassan',
        );

        $this->deal->lines()->first()->update([
            'unit_price' => 35000,
            'description' => 'Crystal P07 20mm · Silver',
            'photo_path' => 'quotations/p07-silver.jpg',
        ]);

        $line = $quotation->fresh()->lines()->first();

        $this->assertSame('28000.0000', $line->unit_price, 'the agreed price stands');
        $this->assertSame('Crystal P07 20mm · Gold', $line->description);
        $this->assertSame('quotations/p07-gold.jpg', $line->photo_path, 'the agreed model stands');
        $this->assertSame('14000000.0000', $quotation->fresh()->total);
    }

    /** A superseded quotation is history and must not gain a new approval. */
    #[Test]
    public function a_superseded_quotation_cannot_be_approved(): void
    {
        $first = $this->writer->markSent($this->writer->build($this->deal));
        $this->writer->build($this->deal->fresh());

        $this->expectException(RuntimeException::class);
        $this->writer->approve($first->fresh(), 'Ali Hassan');
    }

    #[Test]
    public function an_approval_survives_a_later_version_being_raised(): void
    {
        $approved = $this->writer->approve($this->writer->build($this->deal), 'Ali Hassan');

        $this->writer->build($this->deal->fresh());

        // Only draft and sent versions are superseded; the agreement stays put.
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('Ali Hassan', $approved->fresh()->approved_by_name);
    }

    #[Test]
    public function a_rejection_records_the_reason(): void
    {
        $quotation = $this->writer->markSent($this->writer->build($this->deal));

        $rejected = $this->writer->reject($quotation, 'Too expensive, wants 24,000');

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Too expensive, wants 24,000', $rejected->rejection_reason);
        $this->assertNotNull($rejected->rejected_at);
        $this->assertFalse($this->deal->fresh()->isApproved());
    }

    // ----------------------------------------------------------------- screen

    /**
     * The buttons reach the service.
     *
     * The tests above prove QuotationWriter does the right thing; these prove
     * the deal screen actually calls it — action visibility, the modal schema
     * and its validation are wiring a service test cannot see.
     */
    #[Test]
    public function the_deal_screen_can_create_a_quotation(): void
    {
        Livewire::test(EditDeal::class, ['record' => $this->deal->getRouteKey()])
            ->callAction('quote')
            ->assertHasNoActionErrors();

        $this->assertSame(1, $this->deal->quotations()->count());
        $this->assertSame('Q-2026-0001', $this->deal->quotations()->first()->number);
    }

    #[Test]
    public function the_approval_button_only_appears_once_there_is_something_to_approve(): void
    {
        Livewire::test(EditDeal::class, ['record' => $this->deal->getRouteKey()])
            ->assertActionHidden('approve');

        $this->writer->build($this->deal);

        Livewire::test(EditDeal::class, ['record' => $this->deal->getRouteKey()])
            ->assertActionVisible('approve');
    }

    #[Test]
    public function the_deal_screen_records_an_approval_with_its_evidence(): void
    {
        $this->writer->build($this->deal);

        Livewire::test(EditDeal::class, ['record' => $this->deal->getRouteKey()])
            ->callAction('approve', [
                'approved_by_name' => 'Ali Hassan',
                'approval_channel' => 'whatsapp',
                'approval_note' => 'Confirmed the gold finish',
            ])
            ->assertHasNoActionErrors();

        $quotation = $this->deal->quotations()->first();

        $this->assertSame('approved', $quotation->status);
        $this->assertSame('Ali Hassan', $quotation->approved_by_name);
        $this->assertSame('whatsapp', $quotation->approval_channel);
        $this->assertTrue($this->deal->fresh()->isApproved());
    }

    /** Who agreed is the whole record, so it cannot be left blank. */
    #[Test]
    public function an_approval_without_a_name_is_rejected(): void
    {
        $this->writer->build($this->deal);

        Livewire::test(EditDeal::class, ['record' => $this->deal->getRouteKey()])
            ->callAction('approve', ['approved_by_name' => ''])
            ->assertHasActionErrors(['approved_by_name']);

        $this->assertFalse($this->deal->fresh()->isApproved());
    }

    // ------------------------------------------------------------ at own risk

    /**
     * Approval is a warning, not a wall — but the risk is recorded.
     *
     * A purchase made before the customer committed is money you are carrying
     * on your own judgement, and the dashboard totals it.
     */
    #[Test]
    public function purchases_made_after_approval_are_no_longer_flagged_at_risk(): void
    {
        $this->assertTrue((bool) $this->deal->purchases()->first()->bought_at_risk);

        $this->writer->approve($this->writer->build($this->deal), 'Ali Hassan');

        // A supplier added after approval creates a purchase that is not at risk.
        $other = Supplier::create(['code' => 'SUP-B', 'name' => 'Shaoxing', 'default_currency' => 'CNY']);

        DealLine::create([
            'deal_id' => $this->deal->id,
            'supplier_id' => $other->id,
            'description' => 'Lining fabric',
            'quantity' => 50,
            'unit_cost' => 20,
            'cost_currency' => 'CNY',
            'unit_price' => 60000,
        ]);

        $deal = app(DealWriter::class)->sync($this->deal->fresh());

        $this->assertFalse(
            (bool) $deal->purchases()->where('supplier_id', $other->id)->first()->bought_at_risk
        );
    }
}
