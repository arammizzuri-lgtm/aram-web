<?php

namespace App\Http\Controllers;

use App\Models\Partner;

/**
 * Partners & suppliers — /partners (and /ku, /ar): the firms and brands the
 * studio builds with, grouped by trade. Managed under Manage → Partners.
 */
class PartnersController extends Controller
{
    public function index()
    {
        $partners = Partner::publishedOrdered()->get();

        // by trade, in the order the trades are listed (Partner::CATEGORIES)
        $groups = collect(array_keys(Partner::CATEGORIES))
            ->mapWithKeys(fn ($key) => [$key => $partners->where('category', $key)->values()])
            ->filter(fn ($group) => $group->isNotEmpty());

        return view('partners', ['groups' => $groups, 'count' => $partners->count()]);
    }
}
