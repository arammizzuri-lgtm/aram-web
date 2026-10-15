<?php

namespace App\Filament\Resources\Testimonials\Pages;

use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Testimonial;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;

class EditTestimonial extends EditRecord
{
    protected static string $resource = TestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * The portrait drawn in this browser from the client's photo
     * (portrait-drawer.js, in the form's "The drawing"), kept: its strokes
     * arrive as numbers and become the website's drawing.
     *
     * @param  array<string, mixed>  $drawing
     */
    public function saveDrawing(array $drawing, string $detail = 'balanced'): void
    {
        /** @var Testimonial $record */
        $record = $this->getRecord();
        try {
            $record->storeDrawing($drawing, $detail);
        } catch (InvalidArgumentException) {
            Notification::make()->danger()->title('That drawing could not be kept — draw it again')->send();

            return;
        }

        Notification::make()->success()->title('The portrait is drawn and saved')
            ->body($record->media_consent ? null : 'It shows on the website once “They agreed to publish…” is on.')
            ->send();
    }
}
