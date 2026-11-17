<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Response;

/** /sitemap.xml: the public pages and every published upcoming event. */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('events.index'), 'lastmod' => null],
            ['loc' => route('contact'), 'lastmod' => null],
            ['loc' => route('privacy'), 'lastmod' => null],
            ['loc' => route('terms'), 'lastmod' => null],
        ];

        Event::published()->where('ends_at', '>=', now())->orderBy('starts_at')->get()
            ->each(function (Event $event) use (&$urls) {
                $urls[] = ['loc' => route('events.show', $event), 'lastmod' => $event->updated_at?->toDateString()];
            });

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
