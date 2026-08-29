<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;

/**
 * Give every request a clean set of SEO builders.
 *
 * The SEOTools facades are backed by container singletons that accumulate:
 * OpenGraph::addImage() appends rather than replaces, and JsonLd::addValue()
 * has no counterpart that removes. Under PHP-FPM that is invisible, because a
 * request is a process and a process renders one page - but the state is
 * per-worker, not per-request, so the moment the app runs on Octane (or in a
 * test that renders two pages in one process) the second page carries the
 * first one's og:image, and a page with no revision date inherits the
 * dateModified of whichever legal page rendered before it.
 *
 * Forgetting the instances makes each request rebuild them from
 * config/seotools.php, which is what everything downstream already assumes.
 */
class ResetSeoState
{
    /**
     * The SEOTools container bindings.
     *
     * Both caches have to be cleared, and clearing only one is worse than
     * clearing neither: the facades memoise their resolved instance, while
     * SEOTools::generate() re-resolves from the container. Forget the
     * container binding alone and the two drift apart - the page writes its
     * title into the facade's old instance while generate() renders a brand
     * new one, so every page comes out with the config defaults.
     *
     * @var list<string>
     */
    private const BINDINGS = [
        'seotools.metatags',
        'seotools.opengraph',
        'seotools.twitter',
        'seotools.json-ld',
        'seotools.json-ld-multi',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::BINDINGS as $binding) {
            app()->forgetInstance($binding);
            Facade::clearResolvedInstance($binding);
        }

        return $next($request);
    }
}
