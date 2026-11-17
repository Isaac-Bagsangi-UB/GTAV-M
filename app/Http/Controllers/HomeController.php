<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        // The brand intro plays once per visit (session), never on later visits to the home page.
        $showIntro = ! $request->session()->has('intro_seen');
        $request->session()->put('intro_seen', true);

        $events = Event::published()
            ->whereNull('parent_id')
            ->where('ends_at', '>=', now())
            ->with('chapter')
            ->withCount('confirmedRegistrations')
            ->orderBy('starts_at')
            ->take(6)
            ->get();

        return view('welcome', [
            'events' => $events,
            'showIntro' => $showIntro,
            // These two numbers change rarely, so they are cached for five minutes.
            'chapterCount' => Cache::remember('home.chapters', 300, fn () => Chapter::count()),
            'upcomingCount' => Cache::remember('home.upcoming', 300, fn () => Event::published()->where('ends_at', '>=', now())->count()),
        ]);
    }

    /** Sends each role to its own home screen. */
    public function dashboard(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->homeRoute());
    }
}
