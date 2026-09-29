<?php

namespace Tests\Unit;

use App\Http\Middleware\CollapseHtmlWhitespace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * The middleware trades Blade's indentation for parse time, and the whole
 * trade is void if it eats a space that was doing work. These tests are
 * mostly about what it must NOT touch: whitespace between inline elements,
 * anything inside a <pre> a blog article rendered from a markdown fence, and
 * script bodies where a newline ends a statement.
 */
class CollapseHtmlWhitespaceTest extends TestCase
{
    private function pipe(string $html, string $contentType = 'text/html; charset=UTF-8'): string
    {
        $middleware = new CollapseHtmlWhitespace;

        $response = $middleware->handle(
            Request::create('/'),
            fn (): Response => new Response($html, 200, ['Content-Type' => $contentType])
        );

        return (string) $response->getContent();
    }

    public function test_it_does_not_treat_utf8_bytes_inside_visible_symbols_as_newlines(): void
    {
        $html = '<span>★★★★★ · € · … · 🪑</span>';
        $this->assertSame($html, $this->pipe($html));
        $this->assertSame('<div> '.$html.' </div>', $this->pipe("<div>\n  {$html}\n</div>"));
    }

    public function test_it_collapses_indentation_between_blocks(): void
    {
        $html = "<div>\n    <p>One</p>\n\n        <p>Two</p>\n</div>";

        $this->assertSame('<div> <p>One</p> <p>Two</p> </div>', $this->pipe($html));
    }

    public function test_it_leaves_one_space_between_inline_elements(): void
    {
        $html = "<p><span>short</span>\n            <span>back and sides</span></p>";

        /* Collapsing to nothing here would read "shortback and sides". */
        $this->assertSame('<p><span>short</span> <span>back and sides</span></p>', $this->pipe($html));
    }

    public function test_it_preserves_the_contents_of_a_pre_block(): void
    {
        $code = "<pre><code>one\n    two\n        three</code></pre>";

        $this->assertStringContainsString($code, $this->pipe("<div>\n  {$code}\n</div>"));
    }

    public function test_it_preserves_script_bodies(): void
    {
        $script = "<script>\nconst a = 1\nconst b = 2\n</script>";

        $this->assertStringContainsString($script, $this->pipe("<head>\n  {$script}\n</head>"));
    }

    public function test_it_preserves_json_ld_payloads(): void
    {
        $ld = '<script type="application/ld+json">{"@context":"https://schema.org",'."\n".'"@type":"FAQPage"}</script>';

        $this->assertStringContainsString($ld, $this->pipe("<head>\n{$ld}\n</head>"));
    }

    public function test_it_preserves_textarea_contents(): void
    {
        $textarea = "<textarea>line one\nline two</textarea>";

        $this->assertStringContainsString($textarea, $this->pipe("<form>\n  {$textarea}\n</form>"));
    }

    public function test_it_collapses_around_a_protected_block_without_touching_it(): void
    {
        $html = "<div>\n    <pre>a\n  b</pre>\n    <p>after</p>\n</div>";

        $this->assertSame("<div> <pre>a\n  b</pre> <p>after</p> </div>", $this->pipe($html));
    }

    public function test_it_handles_several_protected_blocks(): void
    {
        $html = "<div>\n<pre>x\ny</pre>\n<p>mid</p>\n<script>\nz()\n</script>\n</div>";

        $this->assertSame("<div> <pre>x\ny</pre> <p>mid</p> <script>\nz()\n</script> </div>", $this->pipe($html));
    }

    public function test_it_leaves_non_html_responses_alone(): void
    {
        $json = "{\n  \"a\": 1\n}";

        $this->assertSame($json, $this->pipe($json, 'application/json'));
    }

    public function test_it_drops_a_stale_content_length(): void
    {
        $middleware = new CollapseHtmlWhitespace;

        $response = $middleware->handle(
            Request::create('/'),
            fn (): Response => new Response(
                "<div>\n    <p>x</p>\n</div>",
                200,
                ['Content-Type' => 'text/html', 'Content-Length' => '999']
            )
        );

        $this->assertFalse($response->headers->has('Content-Length'));
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['performance.collapse_html_whitespace' => false]);

        $html = "<div>\n    <p>x</p>\n</div>";

        $this->assertSame($html, $this->pipe($html));
    }

    public function test_it_does_not_alter_attribute_values_that_matter(): void
    {
        $html = '<div data-rotate-words=\'["Buzz cut","Low taper"]\'>'."\n".'<span>x</span></div>';

        $this->assertStringContainsString('data-rotate-words=\'["Buzz cut","Low taper"]\'', $this->pipe($html));
    }
}
