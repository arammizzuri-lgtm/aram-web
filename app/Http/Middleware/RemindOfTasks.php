<?php

namespace App\Http\Middleware;

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

        return $next($request);
    }
}
