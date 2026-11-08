<?php

namespace App\Http\Controllers;

use App\Models\JobOpening;
use App\Models\Project;
use App\Support\Architect;
use App\Support\CostEstimate;
use App\Support\SiteText;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use XMLWriter;

/**
 * What search engines read before they read the site: robots.txt, and the
 * sitemap it points to. Both are generated, so the sitemap always lists the
 * projects that are live right now — in both languages, each page naming its
 * translation, with its photos for image search.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        // Nothing is disallowed: the admin and the ERP sit behind logins, and
        // naming their paths here would only advertise them.
        //
        // The AI assistants' crawlers are welcomed by name as well as by the
        // "*" — some hosts and plugins block them unless a site says so, and
        // being read by them is how the studio is named in their answers.
        $assistants = [
            'OAI-SearchBot', 'ChatGPT-User', 'GPTBot',        // ChatGPT
            'ClaudeBot', 'Claude-SearchBot', 'Claude-User',   // Claude
            'PerplexityBot', 'Perplexity-User',               // Perplexity
            'Google-Extended', 'Applebot-Extended', 'Bingbot', // Gemini, Apple, Bing/Copilot
        ];
        $body = "User-agent: *\nDisallow:\n\n"
            .implode('', array_map(fn ($bot) => "User-agent: {$bot}\n", $assistants))."Allow: /\n\n"
            .'Sitemap: '.route('sitemap')."\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * /llms.txt — the site in brief, in plain Markdown, for AI assistants
     * (the llms.txt convention): who the studio and the architect are, and
     * every project with a line about it and its address. Written from the
     * same Site Content and projects the pages show, so it never goes stale.
     */
    public function llms(): Response
    {
        $clean = fn (?string $t) => trim(preg_replace('/\s+/u', ' ', strip_tags((string) $t)));
        $name = $clean(SiteText::en('arch_name'));
        $lines = fn (string $key) => Architect::lines($key, 'en');

        $out = ['# Aram Mizuri Architecture', ''];
        $out[] = '> '.$clean(SiteText::en('seo_description'));
        $out[] = '';
        $out[] = "Aram Mizuri Architecture is an architecture studio in Erbil, Kurdistan Region of Iraq, founded and led by the architect {$name} ("
            .$clean(SiteText::ku('arch_name')).'). This file summarises the studio, the architect and the work; the pages linked below are the source.';
        $out[] = '';

        $out[] = "## The architect: {$name}";
        $out[] = '';
        foreach (Architect::bio('en') as $paragraph) {
            $out[] = $clean($paragraph);
            $out[] = '';
        }
        $career = Architect::rows('arch_timeline', 4, 'en');
        if ($career) {
            $out[] = '### Career';
            $out[] = '';
            foreach ($career as [$when, $role, $where, $what]) {
                $out[] = '- '.collect([$clean($when), collect([$clean($role), $clean($where)])->filter()->implode(', ')])->filter()->implode(': ')
                    .($clean($what) !== '' ? ' — '.$clean($what) : '');
            }
            $out[] = '';
        }
        $sections = [
            'Areas of work' => $lines('arch_expertise'),
            'Education' => $lines('arch_education'),
            'Membership' => $lines('arch_membership'),
            'Awards and recognition' => $lines('arch_awards'),
            'Publications and talks' => $lines('arch_press'),
        ];
        foreach (array_filter($sections) as $heading => $items) {
            $out[] = "### {$heading}";
            $out[] = '';
            foreach ($items as $item) {
                $out[] = '- '.$clean($item);
            }
            $out[] = '';
        }
        $out[] = "- [{$name} — profile](".site_url(Architect::PATH, 'en').')';
        foreach (Architect::profiles() as $profile) {
            $out[] = "- <{$profile}>";
        }
        $out[] = '';

        $out[] = '## Projects';
        $out[] = '';
        $out[] = "Every project below was designed by {$name} and Aram Mizuri Architecture.";
        $out[] = '';
        foreach (Project::publishedOrdered()->get()->filter->hasPage() as $project) {
            $facts = collect([implode(', ', $project->categoryLabelsIn('en')), $project->cityLabel('en'), $project->endYear()])->filter()->implode(' · ');
            $about = $clean($project->desc) ?: $clean($project->typology);
            $out[] = '- ['.$clean($project->name).']('.$project->pageUrl('en').')'
                .($facts !== '' ? ' — '.$facts : '')
                .($about !== '' ? ': '.\Illuminate\Support\Str::limit($about, 220) : '');
        }
        $out[] = '';

        $out[] = '## Contact';
        $out[] = '';
        $out[] = '- Studio: Erbil, Kurdistan Region, Iraq';
        $out[] = '- Email: '.setting('contact_email_new', 'Info@arammizuri.com');
        $out[] = '- Website: '.site_url('/', 'en').' (English), '.site_url('/', 'ku').' (Kurdish), '.site_url('/', 'ar').' (Arabic)';
        $out[] = '- [Every project]('.site_url('/projects', 'en').')';
        $out[] = '';

        return response(implode("\n", $out), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(): Response
    {
        $projects = Project::publishedOrdered()->get()->filter->hasPage()->values();

        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        $xml->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

        $homeModified = $projects->max('updated_at');
        $this->page($xml, '/', $homeModified);
        $this->page($xml, '/projects', $homeModified);
        $portrait = Architect::portrait();
        $this->page($xml, Architect::PATH, $homeModified, $portrait ? [$portrait] : []);

        foreach ($projects as $project) {
            $this->page($xml, '/projects/'.$project->slug, $project->updated_at, $project->orderedVariantUrls('lg'));
        }

        $this->page($xml, '/careers', JobOpening::query()->max('updated_at') ? Carbon::parse(JobOpening::query()->max('updated_at')) : null);

        if (CostEstimate::enabled()) {
            $this->page($xml, '/estimate', null);
        }

        // room by room, once photographs have their rooms
        if (\App\Support\Rooms::photos($projects)->isNotEmpty()) {
            $this->page($xml, '/photos', $homeModified);
        }

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * One page, written once per language. Each entry names both versions
     * (and English as the default for everyone else), which is how Google
     * learns that /ku/… is the Kurdish of the same page rather than a copy.
     *
     * @param  array<int, string>  $images
     */
    private function page(XMLWriter $xml, string $path, $modified, array $images = []): void
    {
        foreach (['en', 'ku', 'ar'] as $lang) {
            $xml->startElement('url');
            $xml->writeElement('loc', site_url($path, $lang));
            if ($modified) {
                $xml->writeElement('lastmod', $modified->toAtomString());
            }
            foreach (['en' => 'en', 'ku' => 'ku', 'ar' => 'ar', 'x-default' => 'en'] as $hreflang => $target) {
                $xml->startElement('xhtml:link');
                $xml->writeAttribute('rel', 'alternate');
                $xml->writeAttribute('hreflang', $hreflang);
                $xml->writeAttribute('href', site_url($path, $target));
                $xml->endElement();
            }
            foreach ($images as $src) {
                $xml->startElement('image:image');
                $xml->writeElement('image:loc', url($src));
                $xml->endElement();
            }
            $xml->endElement();
        }
    }
}
