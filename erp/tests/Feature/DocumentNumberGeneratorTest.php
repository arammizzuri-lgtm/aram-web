<?php

namespace Tests\Feature;

use App\Models\NumberSequence;
use App\Services\Documents\DocumentNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private DocumentNumberGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new DocumentNumberGenerator;
        Carbon::setTestNow('2026-08-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function it_allocates_padded_year_scoped_numbers(): void
    {
        $this->assertSame('PO-2026-0001', $this->generator->next('purchase_order', prefix: 'PO'));
        $this->assertSame('PO-2026-0002', $this->generator->next('purchase_order', prefix: 'PO'));
        $this->assertSame('PO-2026-0003', $this->generator->next('purchase_order', prefix: 'PO'));
    }

    #[Test]
    public function each_document_type_keeps_its_own_counter(): void
    {
        $this->generator->next('purchase_order', prefix: 'PO');
        $this->generator->next('purchase_order', prefix: 'PO');

        $this->assertSame('INV-2026-0001', $this->generator->next('invoice', prefix: 'INV'));
        $this->assertSame('SHP-2026-0001', $this->generator->next('shipment', prefix: 'SHP'));
    }

    #[Test]
    public function the_counter_restarts_each_year(): void
    {
        $this->generator->next('invoice', 2025, 'INV');
        $this->generator->next('invoice', 2025, 'INV');

        $this->assertSame('INV-2026-0001', $this->generator->next('invoice', 2026, 'INV'));
        $this->assertSame('INV-2025-0003', $this->generator->next('invoice', 2025, 'INV'));
    }

    #[Test]
    public function numbers_are_gapless_across_many_allocations(): void
    {
        $numbers = collect(range(1, 50))->map(fn () => $this->generator->next('invoice', prefix: 'INV'));

        $this->assertCount(50, $numbers->unique(), 'every allocated number must be distinct');
        $this->assertSame('INV-2026-0001', $numbers->first());
        $this->assertSame('INV-2026-0050', $numbers->last());
    }

    #[Test]
    public function peek_shows_the_next_number_without_consuming_it(): void
    {
        $this->generator->next('invoice', prefix: 'INV');

        $this->assertSame('INV-2026-0002', $this->generator->peek('invoice'));
        $this->assertSame('INV-2026-0002', $this->generator->peek('invoice'));
        $this->assertSame('INV-2026-0002', $this->generator->next('invoice', prefix: 'INV'));
    }

    #[Test]
    public function peek_works_before_a_sequence_exists(): void
    {
        $this->assertSame('PO-2026-0001', $this->generator->peek('purchase_order'));
        $this->assertDatabaseCount('number_sequences', 0);
    }

    #[Test]
    public function the_format_padding_and_prefix_are_configurable(): void
    {
        NumberSequence::create([
            'document_type' => 'shipment',
            'year' => 2026,
            'prefix' => 'CONT',
            'format' => '{prefix}/{year}/{number}',
            'padding' => 6,
            'next_number' => 42,
        ]);

        $this->assertSame('CONT/2026/000042', $this->generator->next('shipment'));
    }

    #[Test]
    public function it_derives_a_prefix_from_the_document_type_when_none_is_given(): void
    {
        $this->assertSame('PO-2026-0001', $this->generator->next('purchase_order'));
        $this->assertSame('GR-2026-0001', $this->generator->next('goods_receipt'));
    }
}
