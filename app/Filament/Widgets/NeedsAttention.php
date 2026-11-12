<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ManageSiteContent;
use App\Filament\Pages\PhotoRooms;
use App\Filament\Resources\ClientRooms\ClientRoomResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Filament\Resources\MissingPages\MissingPageResource;
use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\MissingPage;
use App\Models\Project;
use App\Support\SiteText;
use Filament\Widgets\Widget;

/**
 * Under the greeting: everything waiting for the studio, each a click away —
 * the same counts as the badges in the sidebar, and what no badge shows.
 * Only what is waiting is listed; with nothing, one quiet line.
 */
class NeedsAttention extends Widget
{
    protected string $view = 'filament.widgets.needs-attention';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $items = [
            [ContactMessageResource::getNavigationBadge(), 'New enquiries', ContactMessageResource::getUrl()],
            [JobApplicationResource::getNavigationBadge(), 'Job applications to read', JobApplicationResource::getUrl()],
            [ClientRoomResource::getNavigationBadge(), 'Client rooms with something new', ClientRoomResource::getUrl()],
            [TestimonialResource::getNavigationBadge(), 'Client quotes to approve', TestimonialResource::getUrl()],
            [PartnerResource::getNavigationBadge(), 'Partner applications', PartnerResource::getUrl()],
            // content health: each opens the projects list showing just those
            ...collect(Project::NEEDS)->map(fn (string $label, string $key) => [
                Project::query()->needing($key)->count(), 'Live projects: '.lcfirst($label),
                ProjectResource::getUrl('index', ['filters' => ['needs' => ['value' => $key]]]),
            ])->values()->all(),
            [PhotoRooms::untaggedCount(), 'Photos without a room', PhotoRooms::getUrl()],
            [MissingPage::query()->fromThisSite()->count(), 'Broken links on your own pages', MissingPageResource::getUrl()],
            [count(SiteText::untranslated()), 'Site texts missing Kurdish or Arabic', ManageSiteContent::getUrl(['missing' => 1])],
        ];

        return [
            'items' => collect($items)
                ->filter(fn (array $i) => (int) $i[0] > 0)
                ->map(fn (array $i) => ['count' => (int) $i[0], 'label' => $i[1], 'url' => $i[2]])
                ->values(),
        ];
    }
}
