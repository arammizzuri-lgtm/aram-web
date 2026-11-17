{{-- The heritage map as a poster: every published landmark on one A2 sheet (594 x 420 mm, landscape), numbered
     era by era from the oldest, with the outline of Greater Kurdistan from the site's own map and the rivers and lakes
     from Natural Earth (resources/heritage/poster-map.json, made by tools/heritage/poster-map.mjs). The sheet is
     drawn in millimetres and scaled to the screen (poster.css); printed, it is A2. The PDFs to download are this page
     printed by tools/heritage/poster-pdf.mjs. Texts are written here in the three languages: $t(English, Kurdish, Arabic). --}}
@php
    use App\Models\Landmark;
    /** @var \Illuminate\Support\Collection $landmarks */
    $lang = site_lang();
    $t = fn (string $en, string $ku, string $ar) => bi($en, $ku, $ar);
    $digits = fn ($s) => Landmark::digits((string) $s, $lang);
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $headline = $t('The heritage map, as a poster', 'نەخشەی میرات، وەک پۆستەر', 'خريطة التراث، ملصقاً');
    $title = $headline.' — '.bival('landmarks_label').' | '.$brand;
    $description = $t('Every landmark of the collection on one A2 sheet, drawn in the studio’s style: a free PDF to download and print, in English, Kurdish and Arabic.',
        'هەموو شوێنەوارەکانی کۆکراوەکە لەسەر یەک پەڕەی A2، بە شێوازی ستۆدیۆکە کێشراوە: PDFێکی بێبەرامبەر بۆ داگرتن و چاپکردن، بە ئینگلیزی و کوردی و عەرەبی.',
        'كل معالم المجموعة على ورقة A2 واحدة، مرسومة بأسلوب المكتب: ملف PDF مجاني للتنزيل والطباعة، بالإنكليزية والكوردية والعربية.');
    $path = Landmark::PATH.'/poster';
    $pdf = fn (string $l) => is_file(public_path("downloads/heritage-poster-{$l}.pdf")) ? asset("downloads/heritage-poster-{$l}.pdf").'?v='.filemtime(public_path("downloads/heritage-poster-{$l}.pdf")) : null;

    // numbered era by era, the oldest first
    $eras = array_keys(Landmark::ERAS);
    $list = $landmarks->filter(fn ($l) => $l->lat !== null)
        ->sortBy(fn ($l) => [array_search($l->era, $eras) === false ? 99 : array_search($l->era, $eras), $l->year_from ?? 0])->values();
    $list->each(fn ($l, $i) => $l->n = $i + 1);

    // the projection: plain longitude/latitude, longitude shortened by the cosine of the middle latitude,
    // fitted into the map panel (380 x 396 mm) with 10 mm to spare
    $map = json_decode(file_get_contents(resource_path('heritage/poster-map.json')), true);
    [$W, $H, $pad] = [380, 396, 10];
    $pts = array_merge(...$map['outline']);
    [$lng0, $lng1] = [min(array_column($pts, 0)), max(array_column($pts, 0))];
    [$lat0, $lat1] = [min(array_column($pts, 1)), max(array_column($pts, 1))];
    $k = cos(deg2rad(($lat0 + $lat1) / 2));
    $s = min(($W - 2 * $pad) / (($lng1 - $lng0) * $k), ($H - 2 * $pad) / ($lat1 - $lat0));
    $ox = ($W - ($lng1 - $lng0) * $k * $s) / 2;
    $oy = ($H - ($lat1 - $lat0) * $s) / 2;
    $xy = fn ($lng, $lat) => [round($ox + ($lng - $lng0) * $k * $s, 1), round($oy + ($lat1 - $lat) * $s, 1)];
    $line = fn (array $ring) => 'M'.implode('L', array_map(fn ($p) => implode(' ', $xy($p[0], $p[1])), $ring));
    $outline = implode('', array_map(fn ($r) => $line($r).'Z', $map['outline']));
    $rivers = implode('', array_map(fn ($r) => $line($r['line']), $map['rivers']));
    $lakes = implode('', array_map(fn ($l) => $line($l['ring']).'Z', $map['lakes']));
    $grat = '';
    $gratLabels = [];
    for ($g = ceil($lng0 / 2) * 2; $g <= $lng1; $g += 2) {
        [$x] = $xy($g, 0);
        $grat .= "M{$x} 0V{$H}";
        $gratLabels[] = [$x + 1.2, $H - 2.5, $g.'°E'];
    }
    for ($g = ceil($lat0 / 2) * 2; $g <= $lat1; $g += 2) {
        [, $y] = $xy(0, $g);
        $grat .= "M0 {$y}H{$W}";
        $gratLabels[] = [2, $y - 1.2, $g.'°N'];
    }
    // 100 km, at the middle latitude
    $km100 = round(100 / 111.32 * $s, 1);

    // the towns, to find one's way
    $towns = [[36.19, 44.01, 'Erbil', 'هەولێر', 'أربيل'], [35.56, 45.43, 'Sulaymaniyah', 'سلێمانی', 'السليمانية'], [36.87, 42.99, 'Duhok', 'دهۆک', 'دهوك'],
        [35.47, 44.39, 'Kirkuk', 'کەرکووک', 'كركوك'], [36.34, 43.13, 'Mosul', 'مووسڵ', 'الموصل'], [38.50, 43.38, 'Van', 'وان', 'وان'],
        [34.31, 47.07, 'Kermanshah', 'کرماشان', 'كرمانشاه'], [36.76, 45.72, 'Mahabad', 'مەهاباد', 'مهاباد'], [37.05, 41.23, 'Qamishli', 'قامیشلۆ', 'القامشلي'],
        [35.31, 46.99, 'Sanandaj', 'سنە', 'سنندج']];
    $waters = [[38.62, 42.85, 'Lake Van', 'دەریاچەی وان', 'بحيرة وان'], [37.75, 45.55, 'Lake Urmia', 'دەریاچەی ورمێ', 'بحيرة أرومية'],
        [35.1, 43.6, 'Tigris', 'دیجلە', 'دجلة'], [35.82, 39.25, 'Euphrates', 'فورات', 'الفرات']];

    // the numbers: on the place, pushed apart where places crowd, a leader back to the place when moved
    $r = 3.3;
    $pins = $list->map(fn ($l) => ['n' => $l->n, 'at' => $xy($l->lng, $l->lat), 'p' => $xy($l->lng, $l->lat)])->all();
    for ($it = 0; $it < 120; $it++) {
        foreach ($pins as $i => &$a) {
            foreach ($pins as $j => &$b) {
                if ($j <= $i) { continue; }
                $dx = $b['p'][0] - $a['p'][0]; $dy = $b['p'][1] - $a['p'][1]; $d = hypot($dx, $dy);
                if ($d < 2 * $r + .9) {
                    [$ux, $uy] = $d > .01 ? [$dx / $d, $dy / $d] : [cos($i + $j), sin($i + $j)];
                    $push = (2 * $r + .9 - $d) / 2;
                    $a['p'] = [$a['p'][0] - $ux * $push, $a['p'][1] - $uy * $push];
                    $b['p'] = [$b['p'][0] + $ux * $push, $b['p'][1] + $uy * $push];
                }
            }
            unset($b);
        }
        unset($a);
    }
    // the printed sheet points at the live site, wherever the PDF was made (tools/heritage/poster-pdf.mjs runs locally)
    $live = preg_replace('#^'.preg_quote(url('/'), '#').'#', 'https://arammizuri.com', site_url(Landmark::PATH));
    $qr = \App\Http\Controllers\BoardController::qr($live.'?utm_source=poster');
    // a town's name gives way to a numbered place on top of it
    $named = fn ($x, $y) => collect($pins)->every(fn ($p) => hypot($p['p'][0] - $x - 6, $p['p'][1] - $y - 2.5) > 7);

    $schema = ['@context' => 'https://schema.org', '@type' => 'Map', 'name' => $headline, 'description' => $description, 'url' => site_url($path),
        'inLanguage' => $lang, 'author' => \App\Support\Architect::reference(), 'mapType' => 'https://schema.org/VenueMap',
        'about' => ['@type' => 'ItemList', 'numberOfItems' => $list->count(), 'itemListElement' => $list->map(fn ($l) => ['@type' => 'ListItem', 'position' => $l->n, 'name' => $l->tr('name'), 'url' => $l->url()])->all()]];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => $path, 'css' => 'heritage.css'])
    <link rel="stylesheet" href="{{ asset('poster.css') }}?v={{ filemtime(public_path('poster.css')) }}">
</head>
<body class="pp pp--heritage pp--poster">
    @include('partials.page-top', ['otherUrl' => null])

    <main class="po">
        <header class="po-head">
            <nav class="jr-crumbs" aria-label="Breadcrumb">
                <a href="{{ site_url(Landmark::PATH) }}">{{ bival('landmarks_label') }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ $t('Poster', 'پۆستەر', 'ملصق') }}</span>
            </nav>
            <h1 class="po-head__title">{{ $headline }}</h1>
            <p class="po-head__lead">{{ $description }}</p>
            <div class="po-head__get">
                @foreach (['en' => 'English', 'ku' => 'کوردی', 'ar' => 'العربية'] as $l => $name)
                    @if ($href = $pdf($l))
                        <a class="pp-btn{{ $l === $lang ? '' : ' pp-btn--line' }}" href="{{ $href }}" download>{{ $t('PDF', 'PDF', 'PDF') }} · {{ $name }}</a>
                    @endif
                @endforeach
                <button type="button" class="pp-btn pp-btn--line" onclick="window.print()">{{ $t('Print this page', 'چاپی ئەم پەڕەیە', 'اطبع هذه الصفحة') }}</button>
            </div>
            <p class="po-head__note">{{ $t('A2 landscape, 594 × 420 mm. It also prints well at A3. Free to print and to give away; please don’t sell it.',
                'A2 بە پانی، ٥٩٤ × ٤٢٠ ملم. لە A3یشدا باش چاپ دەبێت. بۆ چاپکردن و بەخشین بێبەرامبەرە؛ تکایە مەیفرۆشە.',
                'A2 أفقي، ٥٩٤ × ٤٢٠ مم. ويُطبع جيداً بمقاس A3 أيضاً. مجاني للطباعة والإهداء؛ نرجو ألا يُباع.') }}</p>
        </header>

        <div class="po-frame">
            <article class="po-sheet" aria-label="{{ $headline }}">
                <svg class="po-map" viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ bival('landmarks_label') }}">
                    <defs><clipPath id="po-clip"><rect width="{{ $W }}" height="{{ $H }}"/></clipPath></defs>
                    <g clip-path="url(#po-clip)">
                        <path class="po-grat" d="{{ $grat }}"/>
                        <path class="po-land" d="{{ $outline }}"/>
                        <path class="po-lake" d="{{ $lakes }}"/>
                        <path class="po-river" d="{{ $rivers }}"/>
                        <path class="po-edge" d="{{ $outline }}"/>
                        @foreach ($gratLabels as [$x, $y, $label])
                            <text class="po-grat__label" x="{{ $x }}" y="{{ $y }}">{{ $label }}</text>
                        @endforeach
                        @foreach ($waters as [$la, $ln, $en, $ku, $ar])
                            @php [$x, $y] = $xy($ln, $la); @endphp
                            <text class="po-water" x="{{ $x }}" y="{{ $y }}" text-anchor="middle">{{ bi_record($lang, $en, $ku, $ar) }}</text>
                        @endforeach
                        @foreach ($towns as [$la, $ln, $en, $ku, $ar])
                            @php [$x, $y] = $xy($ln, $la); @endphp
                            <rect class="po-town" x="{{ $x - .9 }}" y="{{ $y - .9 }}" width="1.8" height="1.8"/>
                            @if ($named($x, $y))<text class="po-town__label" x="{{ $x + 2.2 }}" y="{{ $y + 3.6 }}">{{ bi_record($lang, $en, $ku, $ar) }}</text>@endif
                        @endforeach
                        @foreach ($pins as $p)
                            @if (hypot($p['p'][0] - $p['at'][0], $p['p'][1] - $p['at'][1]) > $r * .6)
                                <path class="po-leader" d="M{{ $p['at'][0] }} {{ $p['at'][1] }}L{{ round($p['p'][0], 1) }} {{ round($p['p'][1], 1) }}"/>
                                <circle class="po-dot" cx="{{ $p['at'][0] }}" cy="{{ $p['at'][1] }}" r=".9"/>
                            @endif
                        @endforeach
                        @foreach ($pins as $p)
                            <circle class="po-pin" cx="{{ round($p['p'][0], 1) }}" cy="{{ round($p['p'][1], 1) }}" r="{{ $r }}"/>
                            <text class="po-pin__n" x="{{ round($p['p'][0], 1) }}" y="{{ round($p['p'][1] + 1.25, 1) }}" text-anchor="middle">{{ $digits($p['n']) }}</text>
                        @endforeach
                    </g>
                    {{-- north and 100 km, bottom left --}}
                    <g class="po-key" transform="translate(14 {{ $H - 34 }})">
                        <path class="po-key__line" d="M4 14L4 2M1 6L4 1L7 6"/>
                        <text class="po-key__n" x="4" y="20" text-anchor="middle">N</text>
                        <path class="po-key__line" d="M0 28H{{ $km100 }}M0 26V30M{{ $km100 / 2 }} 27V29M{{ $km100 }} 26V30"/>
                        <text class="po-key__n" x="{{ $km100 + 2 }}" y="29.2">{{ $digits(100) }} km</text>
                    </g>
                    <rect class="po-border" x=".2" y=".2" width="{{ $W - .4 }}" height="{{ $H - .4 }}"/>
                </svg>

                <div class="po-side">
                    <header class="po-title">
                        <p class="po-title__label">{{ bival('landmarks_label') }}</p>
                        <h2 class="po-title__name">{{ bival('landmarks_title') }}</h2>
                        <p class="po-title__sub">{{ str_replace(':n', $digits($list->count()), $t(':n landmarks of Greater Kurdistan, numbered era by era from the oldest.', ':n شوێنەواری کوردستانی گەورە، سەردەم بە سەردەم لە کۆنترینەوە ژمارە کراون.', ':n معلماً في كوردستان الكبرى، مرقّمة حقبةً حقبة من الأقدم.')) }}</p>
                    </header>

                    <div class="po-legend">
                        @foreach ($list->groupBy('era') as $era => $group)
                            <section class="po-era">
                                <h3 class="po-era__name">{{ Landmark::eraName($era) }}</h3>
                                <ol>
                                    @foreach ($group as $l)
                                        <li>
                                            <span class="po-era__n">{{ $digits($l->n) }}</span>
                                            <span><strong>{{ $l->tr('name') }}</strong> <em>{{ $l->tr('place') }} · {{ Landmark::separators($l->tr('period'), $lang) }}</em></span>
                                        </li>
                                    @endforeach
                                </ol>
                            </section>
                        @endforeach
                    </div>

                    <footer class="po-foot">
                        <div class="po-foot__qr">{!! $qr !!}</div>
                        <div>
                            <p class="po-foot__brand">Aram Mizuri Architecture</p>
                            <p class="po-foot__url" dir="ltr">{{ preg_replace('#^https://#', '', $live) }}</p>
                            <p class="po-foot__src">{{ $t('Each landmark has its own page, with photographs and sources. Rivers and lakes: Natural Earth. The border is drawn as on the site’s map, as a cultural region, not a state’s.',
                                'هەر شوێنەوارێک پەڕەی خۆی هەیە، بە وێنە و سەرچاوەوە. ڕووبار و دەریاچەکان: Natural Earth. سنوورەکە وەک نەخشەی ماڵپەڕەکە کێشراوە، وەک ناوچەیەکی کولتووری، نەک هی دەوڵەتێک.',
                                'لكل معلم صفحته، بالصور والمصادر. الأنهار والبحيرات: Natural Earth. والحدود مرسومة كما في خريطة الموقع، إقليماً ثقافياً لا حدود دولة.') }}</p>
                        </div>
                    </footer>
                </div>
            </article>
        </div>

        <p class="po-back"><a href="{{ site_url(Landmark::PATH) }}"><span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span> {{ bival('landmarks_back') }}</a></p>
    </main>

    @include('partials.page-foot')
</body>
</html>
