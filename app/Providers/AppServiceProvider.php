<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        // When the site's address is https://, every generated link is https too.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        /*
         | Rate limits. Each limit has its own counter, keyed so that many people sharing one
         | venue Wi-Fi address are never locked out by somebody else's mistakes.
         */
        $emailAndIp = fn (Request $request) => Str::lower((string) $request->input('email')).'|'.$request->ip();
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(6)->by($emailAndIp($request)));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($emailAndIp($request)));
        RateLimiter::for('api-token', fn (Request $request) => Limit::perMinute(10)->by($emailAndIp($request)));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('gate', fn (Request $request) => Limit::perMinute(240)->by('gate|'.($request->user()?->id ?: $request->ip())));

        // One password rule for sign-up, password reset and administrator-created accounts.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // PDFs: embed only the characters actually used (about 1 MB smaller per file), never fetch remote files.
        config([
            'dompdf.options.enable_font_subsetting' => true,
            'dompdf.options.enable_remote' => false,
        ]);

        // dompdf keeps the metrics of the PDF heading font here; a fresh Laravel install has no such folder.
        $pdfFontCache = storage_path('fonts');
        if (! is_dir($pdfFontCache)) {
            @mkdir($pdfFontCache, 0755, true);
        }

        /*
         | Authorization rules. Route middleware ("role:...") decides which
         | area a role may enter; these gates decide which records inside
         | that area a user may touch (e.g. only their own chapter's events).
         */

        // Global administrators can do everything.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        Gate::define('manage-event', fn (User $user, Event $event) => $user->hasRole(User::ROLE_MANAGER) && $user->chapter_id === $event->chapter_id);

        Gate::define('scan-event', fn (User $user, Event $event) => $user->hasRole(User::ROLE_GATE, User::ROLE_MANAGER) && $user->chapter_id === $event->chapter_id);

        Gate::define('upload-materials', fn (User $user, Event $event) => Gate::forUser($user)->allows('manage-event', $event)
            || ($user->hasRole(User::ROLE_SPEAKER) && $event->speakers()->whereKey($user->id)->exists()));

        // Workshop materials are released only after the registration status is verified.
        Gate::define('access-materials', fn (User $user, Event $event) => Gate::forUser($user)->allows('upload-materials', $event)
            || $event->registrations()
                ->where('user_id', $user->id)
                ->where('status', Registration::CONFIRMED)
                ->exists());
    }
}
