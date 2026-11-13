<?php

namespace App\Http\Controllers;

use App\Mail\RoomActivity;
use App\Models\ClientRoom;
use App\Models\RoomChange;
use App\Models\RoomChoice;
use App\Models\RoomChoiceOption;
use App\Models\RoomDrawing;
use App\Support\StudioMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * A client's private room — /room/{token}, and /ku/room/{token}.
 *
 * Two locks: the link, whose 40-character token cannot be guessed, and a
 * 4-digit PIN the studio gives separately, so a forwarded link alone shows
 * nothing. A phone that has entered the PIN is remembered for 90 days by an
 * encrypted cookie tied to the link and the PIN (a new one of either locks
 * every phone out). Wrong PINs are limited per room, not per address.
 *
 * A signed-in studio account sees the room as its client does without the
 * PIN — to check it before sending the link — but read-only: approving and
 * commenting are the client's.
 *
 * Every response is private: never cached, never indexed, and sent with no
 * referrer, so the link cannot leak into another site's logs (PrivatePage).
 */
class RoomController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $room = $this->room($token);
        $unlocked = $room->is_open && $this->unlocked($request, $room);
        $preview = ! $unlocked && $this->preview();

        if (! $unlocked && ! $preview) {
            return response()->view('room-pin', [
                'room' => $room,
                'error' => session('pin_error'),
            ]);
        }

        if ($unlocked) {
            ClientRoom::query()->whereKey($room->id)->toBase()->update(['last_seen_at' => now()]);
        }

        $room->load(['payments', 'drawings.comments', 'comments', 'changes', 'choices.options.partner']);

        return response()->view('room', [
            'room' => $room,
            'project' => $room->project,
            'updates' => $room->updates(),
            'comments' => $room->comments->groupBy('project_update_id'),
            'preview' => $preview,
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $room = $this->room($token);
        $back = redirect()->to($this->address($room));
        if (! $room->is_open) {
            return $back;
        }

        $limit = 'room-pin:'.$room->id;
        if (RateLimiter::tooManyAttempts($limit, ClientRoom::PIN_TRIES)) {
            return $back->with('pin_error', 'room_pin_locked');
        }
        if (! $room->pinMatches((string) $request->input('pin'))) {
            RateLimiter::hit($limit, 15 * 60);

            return $back->with('pin_error', 'room_pin_wrong');
        }

        RateLimiter::clear($limit);

        return $back->withCookie(cookie($room->accessCookie(), $room->accessKey(), ClientRoom::REMEMBER_DAYS * 24 * 60));
    }

    /** The client approves a drawing, or sends it back with what to change. */
    public function decide(Request $request, string $token, int $drawing): RedirectResponse
    {
        $room = $this->clientRoom($request, $token);
        $d = $room->drawings()->whereKey($drawing)->firstOrFail();

        $data = $request->validate([
            'decision' => ['required', Rule::in([RoomDrawing::APPROVED, RoomDrawing::CHANGES])],
            'note' => ['nullable', 'required_if:decision,'.RoomDrawing::CHANGES, 'string', 'max:2000'],
        ]);

        // a decision stands as given: a revision goes out as a new drawing
        if ($d->isPending()) {
            $d->update([
                'status' => $data['decision'],
                'decision_note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
                'decided_at' => now(),
                'decided_ip' => $request->ip(),
                'seen_at' => null,
            ]);
            $this->tellStudio(RoomActivity::decided($room, $d));
        }

        return redirect()->to($this->address($room).'#drawing-'.$d->id)->with('room_flash', 'room_decided');
    }

    /** A comment or question under one of the project's progress updates. */
    public function comment(Request $request, string $token, int $update): RedirectResponse
    {
        $room = $this->clientRoom($request, $token);
        $u = $room->project->updates()->whereKey($update)->firstOrFail();
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $comment = $room->comments()->create(['project_update_id' => $u->id, 'body' => trim($data['body'])]);
        $this->tellStudio(RoomActivity::commented($room, $comment->setRelation('projectUpdate', $u)));

        return redirect()->to($this->address($room).'#update-'.$u->id)->with('room_flash', 'room_comment_sent');
    }

    /**
     * A comment pinned to a spot on a drawing — or added to a spot's thread —
     * from the room's drawing viewer, which gets back every pin on it.
     */
    public function pin(Request $request, string $token, int $drawing): JsonResponse
    {
        $room = $this->clientRoom($request, $token);
        $d = $room->drawings()->whereKey($drawing)->firstOrFail();
        $data = $request->validate([
            'page' => ['required', 'integer', 'between:1,9999'],
            'x' => ['required', 'numeric', 'between:0,1'],
            'y' => ['required', 'numeric', 'between:0,1'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $room->comments()->create([
            'room_drawing_id' => $d->id, 'page' => (int) $data['page'],
            'x' => round((float) $data['x'], 5), 'y' => round((float) $data['y'], 5), 'body' => trim($data['body']),
        ]);
        $this->tellStudio(RoomActivity::commented($room, $comment->setRelation('drawing', $d)));

        $pins = $d->load('comments')->pins();

        return response()->json([
            'pins' => $d->pinData(site_lang()),
            'n' => $pins->search(fn ($thread) => $thread->contains('id', $comment->id)),
        ]);
    }

    /** The client asks for a change; the studio answers with its price and the days it adds. */
    public function askChange(Request $request, string $token): RedirectResponse
    {
        $room = $this->clientRoom($request, $token);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $change = $room->changes()->create([
            'title' => trim($data['title']),
            'details' => filled($data['details'] ?? null) ? trim($data['details']) : null,
            'asked_by_client' => true,
            'status' => RoomChange::ASKED,
        ]);
        $this->tellStudio(RoomActivity::changed($room, $change));

        return redirect()->to($this->address($room).'#change-'.$change->id)->with('room_flash', 'room_change_sent');
    }

    /**
     * The client accepts a priced change — its price becomes a payment stage
     * of its own — or declines it, saying why. Only the price they were shown
     * (v: when the change was last edited) can be accepted, and only once.
     */
    public function decideChange(Request $request, string $token, int $change): RedirectResponse
    {
        $room = $this->clientRoom($request, $token);
        $c = $room->changes()->whereKey($change)->firstOrFail();
        $data = $request->validate([
            'decision' => ['required', Rule::in([RoomChange::ACCEPTED, RoomChange::DECLINED])],
            'note' => ['nullable', 'required_if:decision,'.RoomChange::DECLINED, 'string', 'max:2000'],
            'v' => ['required', 'integer'],
        ]);
        $back = redirect()->to($this->address($room).'#change-'.$c->id);

        if ($c->isOpen() && (int) $data['v'] !== $c->updated_at->timestamp) {
            return $back->with('room_flash', 'room_change_moved');
        }

        $accepted = $data['decision'] === RoomChange::ACCEPTED;
        $done = DB::transaction(function () use ($room, $c, $data, $accepted, $request) {
            $done = RoomChange::query()->whereKey($c->id)->where('status', RoomChange::PRICED)->update([
                'status' => $data['decision'],
                'decline_note' => $accepted ? null : trim($data['note']),
                'decided_at' => now(),
                'decided_ip' => $request->ip(),
                'seen_at' => null,
                'updated_at' => now(),
            ]);
            if ($done && $accepted && $c->price > 0) {
                $payment = $room->payments()->create([
                    'label' => mb_substr(str_replace('{title}', $c->title, bival('room_change_pay_label')), 0, 160),
                    'amount' => $c->price,
                    'sort_order' => (int) $room->payments()->max('sort_order') + 1,
                ]);
                RoomChange::query()->whereKey($c->id)->update(['room_payment_id' => $payment->id]);
            }

            return $done > 0;
        });

        if ($done) {
            $this->tellStudio(RoomActivity::changed($room, $c->refresh()));
        }

        return $back->with('room_flash', 'room_decided');
    }

    /**
     * The client picks one option of a finish, with a note if they like. The
     * pick is final (the studio can reopen it), and taken only for the
     * options and prices they were shown; an extra cost becomes a payment
     * stage of its own.
     */
    public function choose(Request $request, string $token, int $choice): RedirectResponse
    {
        $room = $this->clientRoom($request, $token);
        $c = $room->choices()->whereKey($choice)->with('options')->firstOrFail();
        $data = $request->validate([
            'option' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
            'v' => ['required', 'string'],
        ]);
        $back = redirect()->to($this->address($room).'#choice-'.$c->id);
        $option = $c->options->firstWhere('id', (int) $data['option']);
        abort_unless($option, 422);

        if ($c->isPending() && $data['v'] !== $c->version()) {
            return $back->with('room_flash', 'room_choice_moved');
        }

        $done = DB::transaction(function () use ($room, $c, $option, $data, $request) {
            $done = RoomChoice::query()->whereKey($c->id)->where('status', RoomChoice::PENDING)->update([
                'status' => RoomChoice::CHOSEN,
                'chosen_option_id' => $option->id,
                'client_note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
                'decided_at' => now(),
                'decided_ip' => $request->ip(),
                'seen_at' => null,
                'updated_at' => now(),
            ]);
            if ($done && $option->extra_cost > 0) {
                $payment = $room->payments()->create([
                    'label' => mb_substr($c->title.': '.$option->name, 0, 160),
                    'amount' => $option->extra_cost,
                    'sort_order' => (int) $room->payments()->max('sort_order') + 1,
                ]);
                RoomChoice::query()->whereKey($c->id)->update(['room_payment_id' => $payment->id]);
            }

            return $done > 0;
        });

        if ($done) {
            $this->tellStudio(RoomActivity::chose($room, $c->refresh()->load('chosen')));
        }

        return $back->with('room_flash', 'room_decided');
    }

    public function optionImage(Request $request, string $token, int $option): Response
    {
        $room = $this->viewableRoom($request, $token);
        $o = RoomChoiceOption::query()->whereKey($option)
            ->whereHas('choice', fn ($q) => $q->where('client_room_id', $room->id))->firstOrFail();
        abort_unless($o->imageExists(), 404);

        return $this->file($o->image_path, basename($o->image_path), false);
    }

    public function changeFile(Request $request, string $token, int $change): Response
    {
        $room = $this->viewableRoom($request, $token);
        $c = $room->changes()->whereKey($change)->firstOrFail();
        abort_unless($c->fileExists(), 404);

        return $this->file($c->file_path, $c->file_name ?: 'change.'.pathinfo($c->file_path, PATHINFO_EXTENSION), $request->has('download'));
    }

    public function drawing(Request $request, string $token, int $drawing): Response
    {
        $room = $this->viewableRoom($request, $token);
        $d = $room->drawings()->whereKey($drawing)->firstOrFail();
        abort_unless($d->fileExists(), 404);

        return $this->file($d->file_path, $d->downloadName(), $request->has('download'));
    }

    public function receipt(Request $request, string $token, int $payment): Response
    {
        $room = $this->viewableRoom($request, $token);
        $p = $room->payments()->whereKey($payment)->firstOrFail();
        abort_unless($p->receiptExists(), 404);

        return $this->file($p->receipt_path, $p->receipt_name ?: 'receipt.'.pathinfo($p->receipt_path, PATHINFO_EXTENSION), $request->has('download'));
    }

    private function room(string $token): ClientRoom
    {
        $room = ClientRoom::query()->where('token', $token)->with('project')->first();
        abort_if(! $room || ! $room->project, 404);

        return $room;
    }

    /** Let in by the PIN on this phone. */
    private function unlocked(Request $request, ClientRoom $room): bool
    {
        $key = (string) $request->cookie($room->accessCookie());

        return $key !== '' && hash_equals($room->accessKey(), $key);
    }

    private function preview(): bool
    {
        return auth()->check();
    }

    /** A room the client is in, and may act in. */
    private function clientRoom(Request $request, string $token): ClientRoom
    {
        $room = $this->room($token);
        abort_unless($room->is_open && $this->unlocked($request, $room), 403);

        return $room;
    }

    /** A room whose files this visitor may open: its client, or the studio. */
    private function viewableRoom(Request $request, string $token): ClientRoom
    {
        $room = $this->room($token);
        abort_unless(($room->is_open && $this->unlocked($request, $room)) || $this->preview(), 403);

        return $room;
    }

    /** The room in the language the request came in. */
    private function address(ClientRoom $room): string
    {
        return site_url('/room/'.$room->token);
    }

    private function file(string $path, string $name, bool $download): Response
    {
        return Storage::disk(RoomDrawing::DISK)->response($path, $name, [
            'X-Content-Type-Options' => 'nosniff',
        ], $download ? 'attachment' : 'inline');
    }

    /** After the response: the client is not kept waiting on the mail server. */
    private function tellStudio(RoomActivity $mail): void
    {
        if (StudioMail::enabled()) {
            defer(fn () => StudioMail::send($mail));
        }
    }
}
