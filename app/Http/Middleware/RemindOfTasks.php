<?php

namespace App\Http\Middleware;

use App\Support\MorningTasks;
use App\Support\TaskReminders;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** On every admin page, and the bell's poll (persistent): ring for tasks due soon or just late (App\Support\TaskReminders). */
class RemindOfTasks
{
    public function handle(Request $request, Closure $next): Response
    {
        // before the page: the bell drawn by this very request already shows it
        TaskReminders::sendIfDue();
        // the morning e-mail, if no visitor has set it off yet today — after the page is sent
        if (MorningTasks::isDue()) {
            \Illuminate\Support\defer(fn () => MorningTasks::sendIfDue());
        }

        return $next($request);
    }
}
