<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Branded studio login — no stock logo, just a "Welcome / Aram Mizuri" wordmark
 * styled to match the public site's dark + gold identity (see brand-css view).
 */
class Login extends BaseLogin
{
    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return new HtmlString(
            '<span class="aram-eyebrow">Welcome</span>'
            .'<span class="aram-brand">Aram&nbsp;Mizuri</span>'
        );
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('<span class="aram-sub">Sign in to your studio dashboard</span>');
    }
}
