<?php

namespace App\Filament\Resources\ReviewRequests;

use App\Filament\Resources\ReviewRequests\Pages\ManageReviewRequests;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\ReviewRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Review links: make a private link for a client, send it on WhatsApp, and
 * see whether their words have arrived. What they send lands in Client
 * quotes as a hidden quote, for the studio to check and publish.
 */
class ReviewRequestResource extends Resource
{
    protected static ?string $model = ReviewRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Clients';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Review links';

    protected static ?string $modelLabel = 'review link';

    protected static ?string $pluralModelLabel = 'review links';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Client\'s name')->required()->maxLength(120)
                ->helperText('Shown on their page: "Dear …". Their first name is used in the WhatsApp message.'),
            Select::make('lang')->label('Their language')->required()->native(false)->default('ku')
                ->options(['ku' => 'Kurdish', 'ar' => 'Arabic', 'en' => 'English']),
            Select::make('project_id')->label('Their project')->relationship('project', 'name')->searchable()->preload()
                ->helperText('Optional. Named on their page, and the quote is linked to it.'),
            Select::make('client_id')->label('Their company')->relationship('client', 'name')->searchable()->preload()
                ->helperText('Optional — for a company client (from Homepage clients): its name and logo ring their portrait.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Client')->searchable()
                    ->description(fn (ReviewRequest $r) => $r->project?->name),
                TextColumn::make('lang')->label('Language')->formatStateUsing(fn ($state) => ['ku' => 'Kurdish', 'ar' => 'Arabic', 'en' => 'English'][$state] ?? $state),
                TextColumn::make('status')->label('Status')->badge()->state(fn (ReviewRequest $r) => $r->status())
                    ->color(fn (string $state) => match ($state) { 'Received' => 'success', 'Expired' => 'gray', default => 'warning' }),
                TextColumn::make('link')->label('Link')->state(fn (ReviewRequest $r) => $r->url())
                    ->copyable()->copyMessage('Link copied')->limit(36)->tooltip('Click to copy')->color('gray'),
                TextColumn::make('created_at')->label('Made')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('whatsapp')->label('Send on WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftRight)->color('success')
                    ->url(fn (ReviewRequest $r) => $r->whatsappUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (ReviewRequest $r) => $r->status() === 'Waiting'),
                Action::make('quote')->label('Open their quote')->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->url(fn (ReviewRequest $r) => $r->testimonial_id ? TestimonialResource::getUrl('edit', ['record' => $r->testimonial_id]) : null)
                    ->visible(fn (ReviewRequest $r) => (bool) $r->testimonial_id),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No review links yet')
            ->emptyStateDescription('After a handover, make a link for the client and send it on WhatsApp. They write a few words — and can record their voice and add a photo for the pencil portrait. It arrives in Client quotes for you to check and publish.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageReviewRequests::route('/')];
    }
}
