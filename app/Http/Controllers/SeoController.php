<?php

namespace App\Http\Controllers;

use App\Models\JobOpening;
use App\Models\Project;
use App\Support\CostEstimate;
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
        $body = "User-agent: *\nDisallow:\n\nSitemap: ".route('sitemap')."\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
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

        foreach ($projects as $project) {
            $this->page($xml, '/projects/'.$project->slug, $project->updated_at, $project->orderedVariantUrls('lg'));
        }

        $this->page($xml, '/careers', JobOpening::query()->max('updated_at') ? Carbon::parse(JobOpening::query()->max('updated_at')) : null);

        if (CostEstimate::enabled()) {
            $this->page($xml, '/estimate', null);
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
        foreach (['en', 'ku'] as $lang) {
            $xml->startElement('url');
            $xml->writeElement('loc', site_url($path, $lang));
            if ($modified) {
                $xml->writeElement('lastmod', $modified->toAtomString());
            }
            foreach (['en' => 'en', 'ku' => 'ku', 'x-default' => 'en'] as $hreflang => $target) {
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
