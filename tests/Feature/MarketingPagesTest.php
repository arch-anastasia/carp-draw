<?php

namespace Tests\Feature;

use App\Notifications\ContactMessageNotification;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_the_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('From a reference to a clear technical drawing.', escape: false)
            ->assertSee('3D Model to Drawing')
            ->assertSee('Create Specification')
            ->assertSee('rel="canonical"', escape: false);
    }

    public function test_contact_and_privacy_render(): void
    {
        $this->get('/contact')->assertOk()->assertSee('Send message');
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy');
    }

    public function test_contact_form_validates_and_sends(): void
    {
        Notification::fake();

        $this->postJson('/contact', [
            'name' => 'A',
            'email' => 'not-an-email',
            'subject' => '',
            'message' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);

        $this->postJson('/contact', [
            'name' => 'Lena Maker',
            'email' => 'lena@example.com',
            'subject' => 'product',
            'message' => 'I would like to know more about the drawing workflows you offer.',
        ])->assertOk()->assertJson(['status' => 'sent']);

        Notification::assertSentOnDemand(ContactMessageNotification::class);
    }

    public function test_contact_form_works_without_javascript(): void
    {
        Notification::fake();

        // A plain form POST (no JSON Accept header) gets the redirect +
        // server-rendered success card.
        $this->post('/contact', [
            'name' => 'Lena Maker',
            'email' => 'lena@example.com',
            'subject' => 'product',
            'message' => 'I would like to know more about the drawing workflows you offer.',
        ])->assertRedirect(route('marketing.contact'));

        $this->get('/contact')->assertSee('Message sent');

        Notification::assertSentOnDemand(ContactMessageNotification::class);
    }

    public function test_auth_email_links_survive_plaintext_rendering(): void
    {
        // Signed URLs carry ?expires=…&signature=…; an HTML-escaped ampersand
        // in the text email breaks the signature and 403s every link.
        $rendered = view('emails.magic-link', [
            'magicUrl' => 'https://example.com/magic-link/x?expires=1&signature=abc',
        ])->render();

        $this->assertStringContainsString('&signature=', $rendered);
        $this->assertStringNotContainsString('&amp;', $rendered);
    }

    public function test_technical_seo_contract(): void
    {
        config(['app.url' => 'http://localhost']);

        $home = $this->get('/')->assertOk()->getContent();

        // One canonical, matching the served URL exactly — the root keeps
        // its slash so canonical, sitemap and the WebSite schema node agree.
        $this->assertSame(1, substr_count($home, 'rel="canonical"'));
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/"', $home);
        $this->assertStringContainsString('<meta property="og:url" content="http://localhost/"', $home);

        // Social card with declared dimensions matching the real file.
        $this->assertStringContainsString('og:image:width" content="1536"', $home);
        $this->assertStringContainsString('og:image:height" content="1024"', $home);

        // The structured-data graph: page node linked to site and publisher.
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $home, $blocks);
        $types = [];
        foreach ($blocks[1] as $block) {
            $decoded = json_decode($block, true);
            $this->assertNotNull($decoded, 'Every JSON-LD block must be valid JSON');
            $types[] = $decoded['@type'] ?? null;
        }
        $this->assertContains('WebPage', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('Organization', $types);

        // Icons and the preloaded primary face.
        $this->assertStringContainsString('rel="icon" href="/favicon.svg" type="image/svg+xml"', $home);
        $this->assertStringContainsString('rel="apple-touch-icon" href="/apple-touch-icon.png"', $home);
        $this->assertStringContainsString('rel="preload" href="/fonts/InterVariable.woff2" as="font"', $home);

        // Canonicals drop tracking queries.
        $contact = $this->get('/contact?utm_source=x')->getContent();
        $this->assertStringContainsString('rel="canonical" href="http://localhost/contact"', $contact);

        // The sitemap's <loc> values are absolute and match the canonicals.
        $sitemap = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('<loc>http://localhost/</loc>', $sitemap);
        $this->assertStringContainsString('<loc>http://localhost/contact</loc>', $sitemap);
        $this->assertStringNotContainsString('<loc>/', $sitemap);
    }

    public function test_seo_endpoints_answer(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /app')
            ->assertSee('Sitemap:');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/contact');

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('CarpDraw');
    }

    public function test_unknown_urls_render_the_404_design(): void
    {
        $this->get('/definitely-not-a-page')
            ->assertNotFound()
            ->assertSee('This drawing view does not exist.');
    }

    public function test_trailing_slashes_redirect_to_the_canonical_url(): void
    {
        // Through the kernel directly: the MakesHttpRequests helper strips a
        // trailing slash before the middleware ever sees it.
        $kernel = $this->app->make(Kernel::class);
        $response = $kernel->handle(Request::create('/contact/', 'GET'));

        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/contact', (string) $response->headers->get('Location'));
    }
}
