<?php

namespace App\Support;

use App\Filament\Pages\Tasks;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The admin's bell rings for a task an hour before it is due, and again when
 * it turns late. The host runs no scheduled jobs: this is checked on admin
 * pages and on the bell's own poll (App\Http\Middleware\RemindOfTasks), at
 * most once a minute. A task moved to another time rings again.
 */
class TaskReminders
{
    public static function sendIfDue(): void
    {
        try {
            if (Cache::add('task-reminders', true, 60)) {
                self::send();
            }
        } catch (Throwable) {
            // a reminder must never break a page
        }
    }

    /** @return int how many rang */
    public static function send(): int
    {
        $users = User::all();
        $rang = 0;
        // late: only those that turned late in the last day (older ones are on the board, red already)
        $late = Task::query()->open()->where(fn ($q) => $q->whereNull('reminder_sent')->orWhere('reminder_sent', 'soon'))
            ->whereBetween('due_at', [now()->subDay(), now()])->get();
        $soon = Task::query()->open()->whereNull('reminder_sent')->whereBetween('due_at', [now(), now()->addHour()])->get();

        foreach ([...$late->all(), ...$soon->all()] as $task) {
            $isLate = $task->due_at->isPast();
            $at = $task->due_at->copy()->timezone(Task::TZ)->format('H:i');
            $note = Notification::make()
                ->title(($isLate ? 'Late: ' : 'Due at '.$at.': ').$task->title)
                ->body(collect([$isLate ? 'It was due at '.$at.'.' : 'Within the hour.', $task->place()])->filter()->join(' · '))
                ->icon($isLate ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-bell-alert')
                ->iconColor($isLate ? 'danger' : 'warning')
                ->actions([Action::make('open')->label('Open Today')->button()->markAsRead()
                    ->url(Tasks::getUrl(['show' => 'today'], panel: 'studio-aks-panel'))]);
            // written now: Filament's sendToDatabase() queues it, and the host runs no queue worker
            $users->each(fn (User $user) => $user->notifyNow($note->toDatabase()));
            $task->update(['reminder_sent' => $isLate ? 'late' : 'soon']);
            $rang++;
        }

        return $rang;
    }
}
