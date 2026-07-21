<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

/**
 * Rebuild the monochrome mask for every client that has an uploaded logo.
 * Run after the conversion algorithm changes so existing uploads pick it up:
 *     php artisan clients:remono
 */
class RegenerateClientLogos extends Command
{
    protected $signature = 'clients:remono';

    protected $description = 'Regenerate the monochrome version of every uploaded client logo';

    public function handle(): int
    {
        $clients = Client::whereNotNull('logo')->where('logo', '!=', '')->get();

        if ($clients->isEmpty()) {
            $this->info('No uploaded client logos to convert.');

            return self::SUCCESS;
        }

        foreach ($clients as $client) {
            $client->regenerateMono();
            $this->line(($client->logo_mono ? '<info>✓</info>' : '<error>✗</error>')." {$client->name}");
        }

        $this->info("Done — {$clients->count()} logo(s) reprocessed.");

        return self::SUCCESS;
    }
}
