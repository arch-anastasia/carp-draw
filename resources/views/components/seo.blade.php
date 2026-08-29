@props([
    'title',
    'description' => null,
    'image' => null,
    'imageAlt' => null,
    'noindex' => false,
    'canonical' => null,
    'type' => 'website',
    'keywords' => null,
    /** A schema.org WebPage subtype for this page's own node: ContactPage,
        CollectionPage, AboutPage, ItemPage... Plain WebPage is right for most. */
    'schemaType' => 'WebPage',
    /** ISO-8601. Legal pages carry one so a crawler can see the policy moved. */
    'dateModified' => null,
])

@php
    use Artesaos\SEOTools\Facades\SEOMeta;
    use Artesaos\SEOTools\Facades\OpenGraph;
    use Artesaos\SEOTools\Facades\TwitterCard;
    use Artesaos\SEOTools\Facades\JsonLdMulti;

    $canonicalService = app(\App\Services\CanonicalUrl::class);

    /*
     * Title. The site name is appended unless the page already said it - the
     * comparison pages are titled "HairstyleAtlas vs FaceApp", and the default
     * suffix turned that into "HairstyleAtlas vs FaceApp | HairstyleAtlas",
     * spending the truncation budget on the brand twice.
     */
    $siteName = config('branding.name', config('app.name'));
    $separator = config('seotools.meta.defaults.separator', ' | ');

    $titleAlreadyBranded = str_contains(mb_strtolower($title), mb_strtolower($siteName));
    $brandedLength = mb_strlen($title.$separator.$siteName);

    /*
     * The suffix is spent only while the result still fits the display budget.
     * These titles are written keyword-first, so the brand is what Google was
     * cutting anyway - dropping it deliberately buys back the width instead of
     * paying for an ellipsis.
     */
    $appendSiteName = ! $titleAlreadyBranded
        && $brandedLength <= config('seo.title_budget');

    SEOMeta::setTitle($title, $appendSiteName);
    OpenGraph::setTitle($title);
    TwitterCard::setTitle($title);

    // Description
    if ($description) {
        SEOMeta::setDescription($description);
        OpenGraph::setDescription($description);
        TwitterCard::setDescription($description);
    }

    /*
     * Social art. Dimensions are declared rather than left for the scraper to
     * discover: without them the first share of a URL renders as a bare link
     * while the crawler fetches the file, which is exactly the share that
     * matters. The default card is 1200x630 - see config/seo.php.
     */
    $socialImage = $image
        ?: $canonicalService->origin().'/'.ltrim(config('seo.social_image.path'), '/');
    $socialImageAlt = $imageAlt ?? $description ?? config('branding.tagline');

    OpenGraph::addImage($socialImage, [
        'width' => config('seo.social_image.width'),
        'height' => config('seo.social_image.height'),
        'alt' => $socialImageAlt,
    ]);
    TwitterCard::setImage($socialImage);
    TwitterCard::addValue('image:alt', $socialImageAlt);

    /*
     * Canonical URL. Built from APP_URL rather than the request host:
     * url()->current() echoes whatever Host (or forwarded host) the request
     * arrived on, so a stray alias, staging domain or bare IP would mint
     * canonicals endorsing itself. Trailing slashes are normalised away to
     * match the redirect in App\Http\Middleware\RedirectTrailingSlash.
     */
    $canonicalUrl = $canonical ?? $canonicalService->for();

    SEOMeta::setCanonical($canonicalUrl);
    OpenGraph::setUrl($canonicalUrl);

    /*
     * Robots. The preview directives are the point: without
     * max-image-preview:large Google renders a thumbnail beside the result,
     * and this is a site whose entire proposition is the picture. max-snippet
     * lifts the text cap the same way. A noindex page keeps `follow` so the
     * links out of it still carry.
     */
    SEOMeta::setRobots($noindex
        ? 'noindex, follow'
        : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');

    // Keywords
    if ($keywords) {
        SEOMeta::setKeywords(is_array($keywords) ? $keywords : explode(',', $keywords));
    }

    // Open Graph Type
    OpenGraph::setType($type);

    // Twitter Card Type
    TwitterCard::setType('summary_large_image');

    // Site name & locale
    OpenGraph::setSiteName(config('app.name'));
    OpenGraph::addProperty('locale', 'en_US');

    /*
     * The page's own node in the structured-data graph.
     *
     * SEOTools emits a WebPage block on every page anyway; left to its
     * defaults it carried the site name and the site description verbatim, so
     * thirty-six pages described themselves identically. This gives it the
     * page's real title, description and canonical URL, a stable @id, and
     * references to the WebSite and Organization nodes the layout emits -
     * which is what turns a pile of disconnected blocks into one graph.
     */
    /*
     * Entities are decoded on the way in. A title written "Cuts &amp; Colours"
     * is correct inside <title> and og:title, which are HTML - but a JSON-LD
     * block is opaque to the HTML parser, so the entity would reach a crawler
     * as the literal five characters rather than an ampersand.
     */
    $decode = fn (?string $value): ?string => $value === null
        ? null
        : html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    JsonLdMulti::setType($schemaType);
    JsonLdMulti::setTitle($decode($title));

    if ($description) {
        JsonLdMulti::setDescription($decode($description));
    }

    JsonLdMulti::setUrl($canonicalUrl);
    JsonLdMulti::addValue('@id', $canonicalUrl.config('seo.schema_ids.webpage'));
    JsonLdMulti::addValue('inLanguage', 'en');
    JsonLdMulti::addValue('isPartOf', ['@id' => $canonicalService->schemaId('website')]);
    JsonLdMulti::addValue('publisher', ['@id' => $canonicalService->schemaId('organization')]);
    JsonLdMulti::addValue('primaryImageOfPage', [
        '@type' => 'ImageObject',
        'url' => $socialImage,
    ]);

    if ($dateModified) {
        JsonLdMulti::addValue('dateModified', $dateModified);
    }

@endphp
