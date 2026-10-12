@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\JobOpening> $openings */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('careers_label').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('careers_intro'), 160);
    $ogImage = asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg'));

    // Each open position as a JobPosting, so it can appear in Google's job search.
    $employment = ['full-time' => 'FULL_TIME', 'part-time' => 'PART_TIME', 'internship' => 'INTERN', 'contract' => 'CONTRACTOR'];
    $postings = $openings->map(fn ($job) => array_filter([
        '@type' => 'JobPosting',
        'title' => $job->field('title', $lang),
        'description' => collect([$job->field('summary', $lang), $job->field('description', $lang)])->filter()
            ->map(fn ($t) => '<p>'.nl2br(e($t)).'</p>')->implode('') ?: e($job->field('title', $lang)),
        'datePosted' => $job->created_at?->toDateString(),
        'validThrough' => $job->closes_at?->endOfDay()->toAtomString(),
        'employmentType' => $employment[$job->type] ?? null,
        'directApply' => true,
        'url' => site_url('/careers').'#'.$job->slug,
        'hiringOrganization' => ['@type' => 'Organization', 'name' => 'Aram Mizuri Architecture', 'sameAs' => url('/'), 'logo' => asset('apple-touch-icon.png')],
        'jobLocation' => ['@type' => 'Place', 'address' => [
            '@type' => 'PostalAddress', 'streetAddress' => \App\Support\SiteText::en('contact_map_addr'),
            'addressLocality' => 'Erbil', 'addressRegion' => 'Kurdistan Region', 'addressCountry' => 'IQ',
        ]],
    ]))->values()->all();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.seo-alternates', ['path' => '/careers'])
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aram Mizuri Architecture">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ site_url('/careers') }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    @if ($postings)
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $postings], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
    <link rel="stylesheet" href="{{ asset('careers.css') }}?v={{ filemtime(public_path('careers.css')) }}">
    @include('partials.kurdish-font')
</head>
<body class="pp pp--careers">

    @include('partials.page-top', ['otherUrl' => site_url('/careers', $other), 'backHref' => site_url('/'), 'backLabel' => bival('careers_home')])

    <main>
        <section class="cr-hero">
            <p class="pp-label">{{ bival('careers_label') }}</p>
            <h1 class="cr-hero__title">{{ bival('careers_title') }}</h1>
            <p class="cr-hero__intro">{{ bival('careers_intro') }}</p>
        </section>

        <section class="cr-openings" aria-labelledby="crOpenHeading">
            <h2 class="pp-label" id="crOpenHeading">{{ bival('careers_open') }}</h2>
            @forelse ($openings as $job)
                <article class="cr-job" id="{{ $job->slug }}">
                    <div class="cr-job__head">
                        <h3 class="cr-job__title" dir="auto">{{ $job->field('title', $lang) }}</h3>
                        <p class="cr-job__meta">
                            <span class="cr-chip">{{ $job->typeLabel($lang) }}</span>
                            @if (filled($job->field('location', $lang)))<span dir="auto">{{ $job->field('location', $lang) }}</span>@endif
                            @if ($job->closes_at)<span>{{ bival('careers_closes') }} {{ site_date($job->closes_at, $lang) }}</span>@endif
                        </p>
                    </div>
                    @if (filled($job->field('summary', $lang)))
                        <p class="cr-job__summary" dir="auto">{{ $job->field('summary', $lang) }}</p>
                    @endif
                    @if ($blocks = $job->descriptionBlocks($lang))
                        <details class="cr-job__more">
                            <summary>{{ bival('careers_more') }}</summary>
                            <div class="cr-job__body" dir="auto">
                                @foreach ($blocks as $block)
                                    @if ($block['type'] === 'ul')
                                        <ul>@foreach ($block['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                                    @else
                                        <p>{{ $block['text'] }}</p>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                    @endif
                    <a class="cr-job__apply" href="#apply" data-apply="{{ $job->id }}">
                        <span>{{ bival('careers_apply_role') }}</span>
                        <span class="pp-arrow" aria-hidden="true">→</span>
                    </a>
                </article>
            @empty
                <p class="cr-none">{{ bival('careers_none') }}</p>
            @endforelse
        </section>

        {{-- The application. The CV goes to the private disk; only the studio's
             admin can open it. Messages are in the page's language (careers.js). --}}
        <section class="cr-apply" id="apply">
            <h2 class="cr-apply__title">{{ bival('careers_form') }}</h2>
            <form class="cr-form" id="careersForm" novalidate
                  data-msg-sending="{{ bival('form_sending') }}"
                  data-msg-check="{{ bival('form_check') }}"
                  data-msg-cv="{{ bival('careers_cv_needed') }}"
                  data-msg-big="{{ bival('careers_cv_big') }}"
                  data-msg-failed="{{ bival('form_failed') }}"
                  data-msg-ok="{{ bival('careers_success') }}"
                  data-max-bytes="{{ \App\Http\Controllers\CareersController::MAX_CV_KB * 1024 }}">
                <input type="hidden" name="lang" value="{{ $lang }}">
                <div class="cr-field cr-field--full">
                    <label for="crPosition">{{ bival('careers_position') }}</label>
                    <select id="crPosition" name="position">
                        <option value="">{{ bival('careers_general') }}</option>
                        @foreach ($openings as $job)
                            <option value="{{ $job->id }}">{{ $job->field('title', $lang) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cr-field">
                    <label for="crName">{{ bival('form_name') }}</label>
                    <input type="text" id="crName" name="name" required autocomplete="name">
                </div>
                <div class="cr-field">
                    <label for="crEmail">{{ bival('form_email') }}</label>
                    <input type="email" id="crEmail" name="email" required autocomplete="email" dir="ltr">
                </div>
                <div class="cr-field">
                    <label for="crPhone">{{ bival('form_phone') }}</label>
                    <input type="tel" id="crPhone" name="phone" autocomplete="tel" inputmode="tel" dir="ltr">
                </div>
                <div class="cr-field">
                    <label for="crPortfolio">{{ bival('careers_portfolio') }}</label>
                    <input type="url" id="crPortfolio" name="portfolio" placeholder="https://" dir="ltr">
                </div>
                <div class="cr-field cr-field--full">
                    <label for="crMessage">{{ bival('careers_message') }}</label>
                    <textarea id="crMessage" name="message" rows="5"></textarea>
                </div>
                <div class="cr-field cr-field--full cr-field--file">
                    <span class="cr-field__label">{{ bival('careers_cv') }}</span>
                    <label class="cr-file" for="crCv">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 17v-6M9.5 13.5 12 11l2.5 2.5"/></svg>
                        <span class="cr-file__name" id="crCvName" data-empty="{{ bival('careers_cv_choose') }}">{{ bival('careers_cv_choose') }}</span>
                    </label>
                    <input class="cr-file__input" type="file" id="crCv" name="cv" accept="application/pdf,.pdf" required>
                </div>
                {{-- spam trap: off-screen, out of the tab order --}}
                <div class="cr-hp" aria-hidden="true">
                    <label for="crWebsite">Website</label>
                    <input type="text" id="crWebsite" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="cr-field--full cr-form__foot">
                    <button type="submit" class="pp-btn cr-form__send">
                        <span class="cr-form__send-label">{{ bival('careers_send') }}</span>
                        <span class="pp-arrow" aria-hidden="true">→</span>
                    </button>
                    <p class="cr-form__status" id="crStatus" role="status" aria-live="polite" hidden></p>
                </div>
            </form>
        </section>
    </main>

    @include('partials.page-foot')

    <script src="{{ asset('careers.js') }}?v={{ filemtime(public_path('careers.js')) }}" defer></script>
</body>
</html>
