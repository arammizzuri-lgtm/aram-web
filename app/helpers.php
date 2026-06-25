<?php

use App\Models\SiteSetting;

if (! function_exists('setting')) {
    /**
     * Read an editable site-content value by key, with a fallback default.
     * Backed by SiteSetting's per-request cache, so it is cheap to call often.
     */
    function setting(string $key, string $default = ''): string
    {
        $value = SiteSetting::get($key, $default);

        return $value === null ? $default : (string) $value;
    }
}
