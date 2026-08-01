<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Notifications\BusinessAlert;
use App\Services\Notifications\BusinessAlertService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class SendBusinessAlerts extends Command
{
    protected $signature = 'erp:alerts {--dry-run : List what would be sent without sending it}';

    protected $description = 'Check the business for conditions worth acting on and notify the right people';

    public function handle(BusinessAlertService $alerts): int
    {
        $found = $alerts->all();

        if ($found->isEmpty()) {
            $this->info('Nothing to report.');

            return self::SUCCESS;
        }

        $recipients = $this->recipients();

        foreach ($found as $alert) {
            $this->line("[{$alert->severity}] {$alert->title}");

            if ($this->option('dry-run')) {
                continue;
            }

            $this->send($alert, $recipients);
        }

        $this->newLine();
        $this->info($this->option('dry-run')
            ? $found->count().' alerts would be sent.'
            : $found->count().' alerts sent to '.$recipients->count().' users.');

        return self::SUCCESS;
    }

    /**
     * Only people who can act on it.
     *
     * Alerting Sales about provisional landed costs trains everyone to ignore the
     * bell, so recipients are scoped by the permission that gates the underlying
     * screen.
     */
    private function recipients(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->can('view_cost'));
    }

    private function send(BusinessAlert $alert, Collection $recipients): void
    {
        foreach ($recipients as $user) {
            /*
             * Re-running must not stack duplicates of a standing condition.
             *
             * Matched on the title rather than a separate fingerprint field:
             * Filament decides what ends up in the stored `data` payload, and the
             * title is the part that is reliably there. It also carries the counts,
             * so "13 products low" genuinely is a different alert from "20 low".
             */
            $exists = $user->unreadNotifications()
                ->where('data', 'like', '%'.str_replace('%', '\%', $alert->title).'%')
                ->exists();

            if ($exists) {
                continue;
            }

            Notification::make()
                ->title($alert->title)
                ->body($alert->body)
                ->icon($alert->icon())
                ->color($alert->colour())
                ->actions([
                    Action::make('view')->label($alert->actionLabel)->url($alert->url)->markAsRead(),
                ])
                ->sendToDatabase($user);
        }
    }
}
