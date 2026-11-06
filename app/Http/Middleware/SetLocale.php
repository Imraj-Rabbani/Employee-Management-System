<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web middleware (every page): shows the app in the logged-in user's language
 * (Settings → Profile → Language). Guests and users without a choice get English.
 */
class SetLocale
{
    /** Languages the app has translations for: code => name in that language. */
    public const LOCALES = [
        'en' => 'English',
        'bn' => 'বাংলা',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->getAttribute('locale');

        if (is_string($locale) && array_key_exists($locale, self::LOCALES)) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);   // "5 minutes ago" → "৫ মিনিট আগে"
        }

        return $next($request);
    }
}
