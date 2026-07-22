<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The site runs behind Cloudflare, so the origin only ever sees Cloudflare IPs.
 * These tests lock in that we resolve the real visitor IP / scheme from the
 * forwarded headers when (and only when) the connection comes from Cloudflare.
 */
class TrustedProxiesTest extends TestCase
{
    private function probe(): void
    {
        Route::get('/_ip_probe', fn (Request $r) => [
            'ip' => $r->ip(),
            'secure' => $r->isSecure(),
        ]);
    }

    public function test_real_visitor_ip_and_https_are_read_from_cloudflare(): void
    {
        $this->probe();

        $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.10']) // a Cloudflare IP
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.55',
                'X-Forwarded-Proto' => 'https',
            ])
            ->getJson('/_ip_probe')
            ->assertOk()
            ->assertJson(['ip' => '203.0.113.55', 'secure' => true]);
    }

    public function test_forwarded_headers_from_a_non_cloudflare_ip_are_ignored(): void
    {
        $this->probe();

        // A direct hit to the origin must not be able to spoof its IP.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->withHeaders(['X-Forwarded-For' => '10.9.9.9'])
            ->getJson('/_ip_probe')
            ->assertOk()
            ->assertJson(['ip' => '203.0.113.99']);
    }
}
