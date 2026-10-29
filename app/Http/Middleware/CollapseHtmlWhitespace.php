<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Collapses the indentation Blade leaves in rendered HTML.
 *
 * Upstream HA measured on 2026-08-20: 883 KB of a 1,513 KB document was
 * whitespace, and 839 KB of that sat in runs containing a newline - the
 * indentation of nested components, repeated once per rendered element. The
 * browser has to tokenise all of it. Collapsing those runs takes the document
 * to roughly 693 KB, which is more than restructuring the try-on demo's
 * duplicated markup would have saved, at a fraction of the risk.
 *
 * gzip already handles indentation well, so this is not about transfer size -
 * the win is parse time and it lands hardest on the mid-range phones that
 * make up most of the audience.
 *
 * Runs collapse to a single space rather than to nothing, because whitespace
 * between inline elements is meaningful: `</span>\n <span>` has to stay one
 * space wide or words run together. Content where every space is load-bearing
 * is skipped outright - see PROTECTED_ELEMENTS.
 */
class CollapseHtmlWhitespace
{
    /**
     * Elements whose contents are passed through untouched: `pre` and
     * `textarea` render whitespace literally, and `script`/`style` can hold
     * syntax where a newline terminates a statement or a comment.
     *
     * Blog articles render markdown code fences as `<pre>`, so this is load
     * bearing on real pages, not defensive.
     */
    private const PROTECTED_ELEMENTS = 'pre|textarea|script|style';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldCollapse($response)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $collapsed = $this->collapse($content);

        /*
         * Illuminate\Http\Response::setContent() overwrites `original` with
         * whatever it is handed, so writing a plain string here would throw
         * away the View behind the response - and with it assertViewIs(), plus
         * anything else that reads the original. Put it back afterwards.
         */
        $original = $response instanceof \Illuminate\Http\Response ? $response->original : null;

        /*
         * Content-Length is set by some upstream responses and would now
         * disagree with the body, which truncates the page in the browser.
         */
        $response->setContent($collapsed);
        $response->headers->remove('Content-Length');

        if ($response instanceof \Illuminate\Http\Response) {
            $response->original = $original;
        }

        return $response;
    }

    /**
     * Only complete, non-streamed HTML documents. A streamed response has no
     * buffered content to rewrite, and touching a download or a JSON payload
     * would corrupt it.
     */
    private function shouldCollapse(Response $response): bool
    {
        if (! config('performance.collapse_html_whitespace')) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        if ($response->headers->has('Content-Disposition')) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    /**
     * Collapse every newline-bearing whitespace run outside protected
     * elements down to one space.
     */
    private function collapse(string $html): string
    {
        $segments = preg_split(
            '/(<('.self::PROTECTED_ELEMENTS.')\b[^>]*>.*?<\/\2\s*>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($segments === false) {
            return $html;
        }

        $out = '';

        foreach ($segments as $index => $segment) {
            /*
             * PREG_SPLIT_DELIM_CAPTURE emits the whole protected element and
             * then its captured tag name, so every third slot from index 1 is
             * content to leave alone and the one after it is a duplicate name
             * to drop.
             */
            $isProtectedElement = $index % 3 === 1;
            $isCapturedTagName = $index % 3 === 2;

            if ($isCapturedTagName) {
                continue;
            }

            if ($isProtectedElement) {
                $out .= $segment;

                continue;
            }

            // Unicode mode is essential: byte 0x85 inside UTF-8 ★ is not
            // a newline. Without /u this silently corrupts visible text.
            $out .= preg_replace('/[ \t]*\R[ \t\r\n]*/u', ' ', $segment) ?? $segment;
        }

        return $out;
    }
}
