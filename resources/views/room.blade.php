@php
    /**
     * @var \App\Models\ClientRoom $room
     * @var \App\Models\Project $project
     * @var \Illuminate\Support\Collection<int, \App\Models\ProjectUpdate> $updates
     * @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\RoomComment>> $comments
     * @var bool $preview  a signed-in studio account, looking without the PIN
     */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $name = bi($project->name, $project->name_ku);
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $base = site_url('/room/'.$room->token);
    $cover = $project->orderedVariantUrls('lg')[0] ?? null;
    $fill = fn (string $key, array $with) => str_replace(array_keys($with), array_values($with), bival($key));

    $drawings = $room->drawings;
    $payments = $room->payments;
    $waiting = $drawings->filter->isPending()->count();
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

    @include('partials.page-top', ['otherUrl' => site_url('/room/'.$room->token, $isKu ? 'en' : 'ku'), 'backHref' => site_url('/'), 'backLabel' => bival('careers_home'), 'withSaved' => false])

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
                        @php $file = $base.'/drawings/'.$d->id; @endphp
                        <article class="rm-drawing rm-drawing--{{ $d->status }}" id="drawing-{{ $d->id }}">
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

    <script src="{{ asset('project.js') }}?v={{ filemtime(public_path('project.js')) }}" defer></script>
</body>
</html>
