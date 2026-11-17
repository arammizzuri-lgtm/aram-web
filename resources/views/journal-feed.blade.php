@php
    /** The journal as an Atom feed (JournalController@feed): the same entries as /journal, in the page's language. */
    /** @var \Illuminate\Support\Collection<int, array> $entries */
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $items = $entries->map(fn ($e) => match (true) {
        isset($e['post']) => ['title' => $e['post']->title($lang) ?: $e['post']->project->tr('name'), 'url' => $e['post']->journalUrl(),
            'summary' => $e['post']->note($lang)],
        isset($e['essay']) => ['title' => $e['essay']->tr('title'), 'url' => $e['essay']->url(), 'summary' => $e['essay']->tr('summary')],
        default => ['title' => bi(...$e['article']['title']), 'url' => \App\Support\Articles::url($e['article']), 'summary' => bi(...$e['article']['summary'])],
    } + ['date' => $e['date']->copy()->startOfDay()->toAtomString()]);
@endphp
{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="{{ $lang === 'ku' ? 'ckb' : $lang }}">
    <title>{{ bival('journal_label') }} — {{ $brand }}</title>
    <subtitle>{{ bival('journal_intro') }}</subtitle>
    <link rel="self" href="{{ site_url('/journal/feed') }}"/>
    <link rel="alternate" type="text/html" href="{{ site_url('/journal') }}"/>
    <id>{{ site_url('/journal') }}</id>
    <updated>{{ $items->max('date') ?? now()->toAtomString() }}</updated>
    <author><name>{{ $brand }}</name><uri>{{ site_url('/') }}</uri></author>
    <icon>{{ asset('favicon.ico') }}</icon>
@foreach ($items as $item)
    <entry>
        <title>{{ $item['title'] }}</title>
        <link rel="alternate" type="text/html" href="{{ $item['url'] }}"/>
        <id>{{ $item['url'] }}</id>
        <updated>{{ $item['date'] }}</updated>
        <published>{{ $item['date'] }}</published>
@if (filled($item['summary']))
        <summary>{{ $item['summary'] }}</summary>
@endif
    </entry>
@endforeach
</feed>
