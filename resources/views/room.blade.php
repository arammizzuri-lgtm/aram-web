@php
    /**
     * @var \App\Models\ClientRoom $room
     * @var \App\Models\Project $project
     * @var \Illuminate\Support\Collection<int, \App\Models\ProjectUpdate> $updates
     * @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\RoomComment>> $comments
     * @var bool $preview  a signed-in studio account, looking without the PIN
     */
    $lang = site_lang();
    $isKu = site_rtl($lang);   // right to left: Kurdish or Arabic
    $name = bi($project->name, $project->name_ku, $project->name_ar);
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $base = site_url('/room/'.$room->token);
    $cover = $project->orderedVariantUrls('lg')[0] ?? null;
    $fill = fn (string $key, array $with) => str_replace(array_keys($with), array_values($with), bival($key));

    $drawings = $room->drawings;
    $payments = $room->payments;
    $waiting = $drawings->filter->isPending()->count();
    $changes = $room->changes;
    $openChanges = $changes->filter->isOpen()->count();
    $totals = $room->paymentTotals();
    $flash = session('room_flash');
    // every progress photo, for the viewer (each update's set steps on its own)
    $photos = $updates->flatMap(fn ($u) => $u->imageUrls('lg'))->values()->all();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.room-head', ['roomTitle' => $name.' — '.$brand])
</head>
<body class="pp pp--room">

    @include('partials.page-top', ['otherUrl' => site_url('/room/'.$room->token, $isKu ? 'en' : 'ku'), 'backHref' => site_url('/'), 'backLabel' => bival('careers_home'), 'withSaved' => false, 'withMenu' => false])

    @if ($preview)
        <p class="rm-preview" dir="ltr">
            <b>Studio preview</b> — this is what {{ $room->client_name }} sees once they enter the PIN.
            Approving drawings and commenting are left to them.
        </p>
    @endif

    <main>
        <section class="pp-hero rm-hero">
            @if ($cover)
                <img class="pp-hero__img" src="{{ $cover }}" alt="" decoding="async" style="object-position: {{ $project->coverPosition() }}">
            @endif
            <div class="pp-hero__shade" aria-hidden="true"></div>
            <div class="pp-hero__text">
                <p class="pp-label">{{ bival('room_label') }}</p>
                <h1 class="pp-hero__title"><bdi>{{ $name }}</bdi></h1>
                <p class="rm-hero__hello">{{ $fill('room_welcome', ['{name}' => $room->client_name]) }}</p>
                @if (filled($room->note))
                    <p class="rm-hero__note" dir="auto">{{ $room->note }}</p>
                @endif
            </div>
        </section>

        <nav class="rm-tabs" aria-label="{{ $name }}">
            <a class="rm-tab" href="#progress">{{ bival('room_nav_progress') }} <span class="rm-tab__n">{{ $updates->count() }}</span></a>
            <a class="rm-tab" href="#drawings">{{ bival('room_nav_drawings') }}
                @if ($waiting)<span class="rm-tab__n rm-tab__n--ask">{{ $waiting }}</span>@else<span class="rm-tab__n">{{ $drawings->count() }}</span>@endif
            </a>
            <a class="rm-tab" href="#changes">{{ bival('room_nav_changes') }}
                @if ($openChanges)<span class="rm-tab__n rm-tab__n--ask">{{ $openChanges }}</span>@elseif ($changes->isNotEmpty())<span class="rm-tab__n">{{ $changes->count() }}</span>@endif
            </a>
            <a class="rm-tab" href="#payments">{{ bival('room_nav_payments') }}
                @if ($payments->isNotEmpty())<span class="rm-tab__n">{{ $totals['percent'] }}%</span>@endif
            </a>
        </nav>

        @if ($flash)
            <p class="rm-flash" role="status">{{ bival($flash) }}</p>
        @endif

        {{-- Progress: every update of the project, the private ones marked, each
             with this room's conversation under it. --}}
        <section class="rm-section" id="progress">
            <h2 class="pp-label">{{ bival('room_nav_progress') }}</h2>
            @forelse ($updates as $u)
                @php $big = $u->imageUrls('lg'); $small = $u->imageUrls('thumb'); @endphp
                <article class="rm-update" id="update-{{ $u->id }}">
                    <p class="rm-update__meta">
                        <time datetime="{{ $u->date->toDateString() }}">{{ site_date($u->date, $lang) }}</time>
                        @if ($u->is_private)<span class="rm-badge">{{ bival('room_private') }}</span>@endif
                    </p>
                    @if (filled($u->title($lang)))
                        <h3 class="rm-update__title" dir="auto">{{ $u->title($lang) }}</h3>
                    @endif
                    @if (filled($u->note($lang)))
                        <p class="rm-update__note" dir="auto">{{ $u->note($lang) }}</p>
                    @endif
                    @if ($big)
                        <div class="pp-step__photos" data-images='@json($big)'>
                            @foreach ($small as $i => $src)
                                <button class="pp-step__photo" type="button" data-open="{{ $i }}" aria-label="{{ site_date($u->date, $lang) }} — {{ $i + 1 }} / {{ count($small) }}">
                                    <img src="{{ $src }}" alt="" loading="lazy" decoding="async">
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="rm-thread">
                        @foreach ($comments[$u->id] ?? [] as $c)
                            <div class="rm-msg {{ $c->from_studio ? 'rm-msg--studio' : 'rm-msg--client' }}">
                                <span class="rm-msg__who">{{ $c->from_studio ? bival('room_studio') : bival('room_you') }} · {{ site_date($c->created_at, $lang) }}</span>
                                <p class="rm-msg__body" dir="auto">{{ $c->body }}</p>
                            </div>
                        @endforeach
                        @unless ($preview)
                            <form class="rm-reply" method="post" action="{{ $base }}/updates/{{ $u->id }}/comments">
                                @csrf
                                <textarea name="body" rows="1" maxlength="2000" required dir="auto" placeholder="{{ bival('room_comment_ph') }}" aria-label="{{ bival('room_comment_ph') }}"></textarea>
                                <button class="pp-act" type="submit">{{ bival('room_comment_send') }}</button>
                            </form>
                        @endunless
                    </div>
                </article>
            @empty
                <p class="rm-empty">{{ bival('room_no_updates') }}</p>
            @endforelse
        </section>

        {{-- Drawings to approve, or send back with what to change. A decision
             stands as given; a revision comes as a new drawing. --}}
        <section class="rm-section" id="drawings">
            <h2 class="pp-label">{{ bival('room_nav_drawings') }}</h2>
            @if ($drawings->isEmpty())
                <p class="rm-empty">{{ bival('room_no_drawings') }}</p>
            @else
                <div class="rm-drawings">
                    @foreach ($drawings as $d)
                        @php
                            $file = $base.'/drawings/'.$d->id;
                            $viewer = ['id' => $d->id, 'title' => $d->title, 'file' => $file, 'pdf' => ! $d->isImage(), 'post' => $file.'/pins', 'pins' => $d->pinData($lang)];
                        @endphp
                        <article class="rm-drawing rm-drawing--{{ $d->status }}" id="drawing-{{ $d->id }}" data-drawing='@json($viewer)'>
                            <a class="rm-drawing__file" href="{{ $file }}" target="_blank" rel="noopener noreferrer" aria-label="{{ bival('room_open') }}: {{ $d->title }}">
                                @if ($d->isImage())
                                    <img src="{{ $file }}" alt="" loading="lazy" decoding="async">
                                @else
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l5 5v15H6z"/><path d="M14 2v5h5M9 13h7M9 17h7M9 9h2"/></svg>
                                    <span>PDF</span>
                                @endif
                            </a>
                            <div class="rm-drawing__body">
                                <h3 class="rm-drawing__title" dir="auto">{{ $d->title }}</h3>
                                <p class="rm-drawing__date">{{ site_date($d->created_at, $lang) }}</p>
                                @if (filled($d->note))
                                    <p class="rm-drawing__note" dir="auto">{{ $d->note }}</p>
                                @endif
                                <p class="rm-drawing__links">
                                    <a href="{{ $file }}" target="_blank" rel="noopener noreferrer">{{ bival('room_open') }}</a>
                                    <a href="{{ $file }}?download=1">{{ bival('room_download') }}</a>
                                </p>
                                {{-- comments pinned to spots on it (room-pins.js), numbered --}}
                                @if (! $preview || $d->comments->isNotEmpty())
                                    <button class="pp-act rm-pins-open" type="button" data-pins-open>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                        {{ bival('room_pins_open') }} <span class="rm-tab__n" data-count hidden></span>
                                    </button>
                                @endif
                                <ol class="rm-marks"></ol>

                                @if ($d->isPending())
                                    <p class="rm-status rm-status--pending">{{ bival('room_waiting') }}</p>
                                    @unless ($preview)
                                        <div class="rm-decide">
                                            <form method="post" action="{{ $base }}/drawings/{{ $d->id }}"
                                                  onsubmit="return confirm(this.dataset.sure)" data-sure="{{ bival('room_approve_sure') }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="approved">
                                                <button class="pp-btn rm-approve" type="submit">✓ {{ bival('room_approve') }}</button>
                                            </form>
                                            <details class="rm-changes" @if ($errors->has('note') && old('drawing') == $d->id) open @endif>
                                                <summary class="pp-act">{{ bival('room_changes') }}</summary>
                                                <form method="post" action="{{ $base }}/drawings/{{ $d->id }}">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="changes">
                                                    <input type="hidden" name="drawing" value="{{ $d->id }}">
                                                    <textarea name="note" rows="3" maxlength="2000" required dir="auto" placeholder="{{ bival('room_changes_ph') }}" aria-label="{{ bival('room_changes_ph') }}"></textarea>
                                                    <button class="pp-act" type="submit">{{ bival('room_changes_send') }}</button>
                                                </form>
                                            </details>
                                        </div>
                                    @endunless
                                @else
                                    <p class="rm-status rm-status--{{ $d->status }}">
                                        {{ $d->status === \App\Models\RoomDrawing::APPROVED ? '✓' : '✎' }}
                                        {{ $fill($d->status === \App\Models\RoomDrawing::APPROVED ? 'room_approved' : 'room_changes_asked', ['{date}' => site_date($d->decided_at ?? $d->updated_at, $lang)]) }}
                                    </p>
                                    @if (filled($d->decision_note))
                                        <blockquote class="rm-drawing__said" dir="auto">{{ $d->decision_note }}</blockquote>
                                    @endif
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Changes with a price: offered by the studio, or asked for here and
             priced by it. Accepted, the price becomes a payment stage of its own. --}}
        <section class="rm-section" id="changes">
            <h2 class="pp-label">{{ bival('room_nav_changes') }}</h2>
            @if ($changes->isEmpty())
                <p class="rm-empty">{{ bival('room_no_changes') }}</p>
            @else
                <div class="rm-drawings">
                    @foreach ($changes as $c)
                        @php $file = $c->fileExists() ? $base.'/changes/'.$c->id : null; @endphp
                        <article class="rm-drawing rm-drawing--{{ $c->isOpen() ? 'pending' : $c->status }} @unless ($file) rm-drawing--text @endunless" id="change-{{ $c->id }}">
                            @if ($file)
                                <a class="rm-drawing__file" href="{{ $file }}" target="_blank" rel="noopener noreferrer" aria-label="{{ bival('room_open') }}: {{ $c->title }}">
                                    @if ($c->isImage())
                                        <img src="{{ $file }}" alt="" loading="lazy" decoding="async">
                                    @else
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l5 5v15H6z"/><path d="M14 2v5h5M9 13h7M9 17h7M9 9h2"/></svg>
                                        <span>PDF</span>
                                    @endif
                                </a>
                            @endif
                            <div class="rm-drawing__body">
                                <h3 class="rm-drawing__title" dir="auto">{{ $c->title }}</h3>
                                <p class="rm-drawing__date">{{ site_date($c->created_at, $lang) }}@if ($c->asked_by_client) · {{ bival('room_change_by_you') }}@endif</p>
                                @if (filled($c->details))
                                    <p class="rm-drawing__note" dir="auto">{{ $c->details }}</p>
                                @endif
                                @if ($c->price !== null)
                                    <p class="rm-change__price">
                                        <bdi>{{ $c->price > 0 ? $room->money($c->price, $lang) : bival('room_change_free') }}</bdi>
                                        {{-- "+7" isolated left to right, or Kurdish shows "7+" --}}
                                        @if ($c->extra_days)<span>{!! str_replace('{days}', '<bdi dir="ltr">+'.(int) $c->extra_days.'</bdi>', e(bival('room_change_days'))) !!}</span>@endif
                                    </p>
                                @endif
                                @if (filled($c->note))
                                    <div class="rm-msg rm-msg--studio rm-change__note">
                                        <span class="rm-msg__who">{{ bival('room_studio') }}</span>
                                        <p class="rm-msg__body" dir="auto">{{ $c->note }}</p>
                                    </div>
                                @endif
                                @if ($file)
                                    <p class="rm-drawing__links">
                                        <a href="{{ $file }}" target="_blank" rel="noopener noreferrer">{{ bival('room_open') }}</a>
                                        <a href="{{ $file }}?download=1">{{ bival('room_download') }}</a>
                                    </p>
                                @endif

                                @if ($c->status === \App\Models\RoomChange::ASKED)
                                    <p class="rm-status rm-status--pending">{{ bival('room_change_wait') }}</p>
                                @elseif ($c->isOpen())
                                    <p class="rm-status rm-status--pending">{{ bival('room_waiting') }}</p>
                                    @unless ($preview)
                                        <div class="rm-decide">
                                            <form method="post" action="{{ $base }}/changes/{{ $c->id }}"
                                                  onsubmit="return confirm(this.dataset.sure)" data-sure="{{ bival('room_change_accept_sure') }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="accepted">
                                                <input type="hidden" name="v" value="{{ $c->updated_at->timestamp }}">
                                                <button class="pp-btn rm-approve" type="submit">✓ {{ bival('room_change_accept') }}</button>
                                            </form>
                                            <details class="rm-changes" @if ($errors->has('note') && old('change') == $c->id) open @endif>
                                                <summary class="pp-act">{{ bival('room_change_decline') }}</summary>
                                                <form method="post" action="{{ $base }}/changes/{{ $c->id }}">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="declined">
                                                    <input type="hidden" name="change" value="{{ $c->id }}">
                                                    <input type="hidden" name="v" value="{{ $c->updated_at->timestamp }}">
                                                    <textarea name="note" rows="3" maxlength="2000" required dir="auto" placeholder="{{ bival('room_change_decline_ph') }}" aria-label="{{ bival('room_change_decline_ph') }}"></textarea>
                                                    <button class="pp-act" type="submit">{{ bival('room_changes_send') }}</button>
                                                </form>
                                            </details>
                                        </div>
                                    @endunless
                                @else
                                    @php $yes = $c->status === \App\Models\RoomChange::ACCEPTED; @endphp
                                    <p class="rm-status rm-status--{{ $c->status }}">
                                        {{ $yes ? '✓' : '✕' }}
                                        {{ $fill($yes ? 'room_change_accepted' : 'room_change_declined', ['{date}' => site_date($c->decided_at ?? $c->updated_at, $lang)]) }}@if ($yes && $c->room_payment_id) · <a href="#payments">{{ bival('room_change_in_payments') }}</a>@endif
                                    </p>
                                    @if (filled($c->decline_note))
                                        <blockquote class="rm-drawing__said" dir="auto">{{ $c->decline_note }}</blockquote>
                                    @endif
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
            @unless ($preview)
                <details class="rm-changes rm-change-ask" @if ($errors->has('title')) open @endif>
                    <summary class="pp-act">+ {{ bival('room_change_ask') }}</summary>
                    <form method="post" action="{{ $base }}/changes">
                        @csrf
                        <input name="title" maxlength="160" required dir="auto" value="{{ old('title') }}" placeholder="{{ bival('room_changes_ph') }}" aria-label="{{ bival('room_changes_ph') }}">
                        <textarea name="details" rows="3" maxlength="2000" dir="auto" placeholder="{{ bival('room_change_details_ph') }}" aria-label="{{ bival('room_change_details_ph') }}">{{ old('details') }}</textarea>
                        <button class="pp-act" type="submit">{{ bival('room_changes_send') }}</button>
                    </form>
                </details>
            @endunless
        </section>

        {{-- Payment stages: the studio's record, shown — nothing is paid here. --}}
        <section class="rm-section" id="payments">
            <h2 class="pp-label">{{ bival('room_nav_payments') }}</h2>
            @if ($payments->isEmpty())
                <p class="rm-empty">{{ bival('room_no_payments') }}</p>
            @else
                <div class="rm-paybar" role="img" aria-label="{{ $totals['percent'] }}%"><span style="width: {{ $totals['percent'] }}%"></span></div>
                {{-- each amount isolated, or "$19,000" reads "19,000$" inside Kurdish --}}
                <p class="rm-paysum">{!! str_replace(['{paid}', '{total}'],
                    ['<bdi>'.e($room->money($totals['paid'], $lang)).'</bdi>', '<bdi>'.e($room->money($totals['total'], $lang)).'</bdi>'],
                    e(bival('room_paid_of'))) !!}</p>
                <ol class="rm-pays">
                    @foreach ($payments as $p)
                        <li class="rm-pay rm-pay--{{ $p->state() }}">
                            <span class="rm-pay__dot" aria-hidden="true"></span>
                            <span class="rm-pay__label" dir="auto">{{ $p->label }}</span>
                            <span class="rm-pay__amount"><bdi>{{ $room->money($p->amount, $lang) }}</bdi></span>
                            <span class="rm-pay__state">
                                @if ($p->isPaid())
                                    {{ $fill('room_pay_paid', ['{date}' => site_date($p->paid_on, $lang)]) }}
                                @elseif ($p->isOverdue())
                                    {{ $fill('room_pay_overdue', ['{date}' => site_date($p->due_on, $lang)]) }}
                                @elseif ($p->due_on)
                                    {{ $fill('room_pay_due', ['{date}' => site_date($p->due_on, $lang)]) }}
                                @else
                                    {{ bival('room_pay_due_open') }}
                                @endif
                                @if ($p->receiptExists())
                                    · <a href="{{ $base }}/receipts/{{ $p->id }}" target="_blank" rel="noopener noreferrer">{{ bival('room_pay_receipt') }}</a>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        @if ($wa = whatsapp_url($fill('room_wa_text', ['{name}' => $room->client_name, '{project}' => $name])))
            <section class="rm-cta">
                <a class="pp-btn pp-btn--wa" href="{{ $wa }}" target="_blank" rel="noopener noreferrer">
                    @include('partials.icon-whatsapp')
                    <span>{{ bival('room_wa') }}</span>
                </a>
            </section>
        @endif
    </main>

    @include('partials.page-foot')

    {{-- The progress photos' viewer (project.js), as on a project page. --}}
    <dialog class="pp-lightbox" id="ppLightbox" data-images='@json($photos)' aria-label="{{ $name }}">
        <img class="pp-lightbox__img" alt="">
        <span class="pp-lightbox__count" aria-live="polite"></span>
        <button class="pp-lightbox__btn pp-lightbox__close" type="button" aria-label="Close">✕</button>
        <button class="pp-lightbox__btn pp-lightbox__prev" type="button" aria-label="Previous image">←</button>
        <button class="pp-lightbox__btn pp-lightbox__next" type="button" aria-label="Next image">→</button>
    </dialog>

    {{-- A drawing, with the comments pinned to it (room-pins.js); read-only in the studio's preview. --}}
    @if ($drawings->isNotEmpty())
        <dialog class="rm-pins" id="rmPins" aria-label="{{ bival('room_nav_drawings') }}" @if ($preview) data-readonly @endif
                data-pdfjs="{{ asset('vendor/presentations/pdf.min.js') }}" data-worker="{{ asset('vendor/presentations/pdf.worker.min.js') }}"
                data-error="{{ bival('room_pins_error') }}" data-open-label="{{ bival('room_open') }}">
            <header class="rm-pins__bar">
                <h2 class="rm-pins__title" dir="auto"></h2>
                <span class="rm-pins__pages" dir="ltr" hidden>
                    <button class="rm-pins__btn" type="button" data-go="-1" aria-label="Previous page">‹</button>
                    <span class="rm-pins__page"></span>
                    <button class="rm-pins__btn" type="button" data-go="1" aria-label="Next page">›</button>
                </span>
                <span class="rm-pins__zoom" dir="ltr">
                    <button class="rm-pins__btn" type="button" data-zoom="-1" aria-label="Zoom out">−</button>
                    <button class="rm-pins__btn" type="button" data-zoom="1" aria-label="Zoom in">+</button>
                </span>
                <button class="rm-pins__btn" type="button" data-close aria-label="Close">✕</button>
            </header>
            <div class="rm-pins__view">
                <div class="rm-pins__sheet"></div>
                <p class="rm-pins__loading">{{ bival('room_pins_loading') }}</p>
            </div>
            <aside class="rm-pins__side">
                <p class="rm-pins__hint">
                    @if ($preview)
                        <span dir="ltr">Studio preview — the client's comments, numbered on the drawing. Tap a number to read one; answer it in the admin, under Conversation.</span>
                    @else
                        {{ bival('room_pins_hint') }}
                    @endif
                </p>
                <div class="rm-thread rm-pins__thread"></div>
                @unless ($preview)
                    <form class="rm-reply rm-pins__form" hidden>
                        @csrf
                        <textarea name="body" rows="2" maxlength="2000" required dir="auto" placeholder="{{ bival('room_pins_ph') }}" aria-label="{{ bival('room_pins_ph') }}"></textarea>
                        <button class="pp-act" type="submit">{{ bival('room_comment_send') }}</button>
                    </form>
                    <p class="rm-pins__error" role="alert" hidden></p>
                @endunless
            </aside>
        </dialog>
        <script src="{{ asset('room-pins.js') }}?v={{ filemtime(public_path('room-pins.js')) }}" defer></script>
    @endif

    <script src="{{ asset('project.js') }}?v={{ filemtime(public_path('project.js')) }}" defer></script>
</body>
</html>
