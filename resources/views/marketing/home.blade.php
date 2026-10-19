<x-seo
    title="Furniture Drawing Concepts from Photos"
    description="Explore furniture drawing concepts, compare reference photos with sample drawings, and plan your next project with CarpDraw."
/>

@php
    $steps = [
        [
            'number' => '01',
            'title' => 'Upload your reference',
            'copy' => 'Add a clear product photo, hand sketch or project image. The guided workspace keeps the source and technical decisions together.',
            'image' => '/examples/shelving-interior.webp',
            'alt' => 'Interior reference image for a fitted shelving project',
        ],
        [
            'number' => '02',
            'title' => 'Define scale and intent',
            'copy' => 'Set one reliable measurement, select the drawing views and clarify what the workshop or project team needs to receive.',
            'image' => '/examples/shelving-measurement.webp',
            'alt' => 'Interior shelving reference with guided dimension controls',
        ],
        [
            'number' => '03',
            'title' => 'Review the technical output',
            'copy' => 'Check dimensions, notes and views before preparing a drawing set or a structured product specification.',
            'image' => '/examples/shelving-technical-sheet.webp',
            'alt' => 'Completed technical drawing and specification for custom shelving',
        ],
    ];

    $examples = [
        [
            'code' => '01',
            'title' => 'Oak sideboard',
            'summary' => 'Product reference paired with clean measured cabinet views.',
            'photo' => '/examples/gallery/oak-sideboard-reference.webp',
            'photoAlt' => 'Oak sideboard reference photograph',
            'photoPosition' => 'center 58%',
            'drawing' => '/examples/gallery/oak-sideboard-line-drawing.webp',
            'drawingAlt' => 'Black and white technical drawing of the oak sideboard',
        ],
        [
            'code' => '02',
            'title' => 'Fitted kitchen',
            'summary' => 'Kitchen cabinetry translated into a simple drawing set.',
            'photo' => '/examples/gallery/kitchen-cabinetry-reference.webp',
            'photoAlt' => 'Fitted kitchen cabinetry reference photograph',
            'photoPosition' => 'center 55%',
            'drawing' => '/examples/gallery/kitchen-cabinetry-line-drawing.webp',
            'drawingAlt' => 'Black and white technical drawing of the fitted kitchen',
        ],
        [
            'code' => '03',
            'title' => 'Walnut bathroom vanity',
            'summary' => 'Vanity, mirror cabinet and counter shown as matching views.',
            'photo' => '/examples/gallery/bathroom-walnut-reference.webp',
            'photoAlt' => 'Walnut bathroom vanity reference photograph',
            'photoPosition' => 'center 58%',
            'drawing' => '/examples/gallery/bathroom-walnut-line-drawing.webp',
            'drawingAlt' => 'Black and white technical drawing of the walnut bathroom vanity',
        ],
        [
            'code' => '04',
            'title' => 'Travertine double vanity',
            'summary' => 'Double-basin proportions and cabinet details in a matching drawing set.',
            'photo' => '/examples/gallery/bathroom-travertine-reference.webp',
            'photoAlt' => 'Travertine double vanity reference photograph',
            'photoPosition' => 'center 57%',
            'drawing' => '/examples/gallery/bathroom-travertine-line-drawing.webp',
            'drawingAlt' => 'Black and white technical drawing of the travertine double vanity',
        ],
    ];

@endphp

<x-layouts.marketing>
    <main id="main-content">
        <section class="hero-section">
            <div class="container-shell hero-inner">
                <h1 class="display-title">From furniture photos to drawing concepts.</h1>
                <p class="hero-copy">Explore furniture drawing concepts from photos. Bring your reference, add a known measurement, and organize the details for your next project.</p>
                <div class="hero-actions">
                    <a class="primary-button" href="{{ route('workspace') }}">Explore workspace<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
                    <a class="secondary-button" href="#examples">See examples</a>
                </div>
                <p class="hero-note">Product preview · Sample drawings below show the intended result.</p>
                <div class="hero-product-frame">
                    <div class="hero-media-placeholder">
                        <video
                            autoplay
                            muted
                            loop
                            playsinline
                            preload="metadata"
                            poster="/examples/hero-workflow.webp"
                            aria-label="CarpDraw AI workflow animation"
                        >
                            <source src="/video/carpdraw-workflow.mp4" type="video/mp4">
                        </video>
                    </div>
                </div>
            </div>
        </section>

        <section class="examples-section section-pad" id="examples" aria-labelledby="furniture-gallery-title">
            <div class="container-shell examples-container">
                <div class="examples-intro">
                    <span class="eyebrow">See the idea</span>
                    <h2 class="section-title" id="furniture-gallery-title">From reference photo to drawing concept.</h2>
                    <p class="section-copy">Illustrative examples, not guaranteed outputs. Always check dimensions and construction details before making furniture.</p>
                </div>
                <div class="examples-grid">
                    @foreach ($examples as $example)
                        <article class="example-pair">
                            <div class="example-pair-heading">
                                <span>{{ $example['code'] }}</span>
                                <div><h3>{{ $example['title'] }}</h3><p>{{ $example['summary'] }}</p></div>
                            </div>
                            <div class="example-pair-media">
                                <figure class="example-card example-card--photo">
                                    <figcaption class="example-label">Reference photo</figcaption>
                                    <img src="{{ $example['photo'] }}" alt="{{ $example['photoAlt'] }}" loading="lazy" decoding="async" style="object-position: {{ $example['photoPosition'] }}">
                                </figure>
                                <figure class="example-card example-card--drawing">
                                    <figcaption class="example-label">Sample drawing</figcaption>
                                    <div class="drawing-sheet">
                                        <img class="drawing-sheet-art" src="{{ $example['drawing'] }}" alt="{{ $example['drawingAlt'] }}" loading="lazy" decoding="async">
                                        <div class="drawing-sheet-footer" aria-hidden="true">
                                            <span class="brand-wordmark"><img src="/brand/carpdraw-cube-logo.jpg" alt="" width="1280" height="476"></span>
                                        </div>
                                    </div>
                                </figure>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="workflow-section section-pad" id="workflow">
            <div class="container-shell">
                <div class="section-heading-row">
                    <div><span class="eyebrow">Simple by design</span><h2 class="section-title">Photo to technical concept in three focused steps.</h2></div>
                    <p class="section-copy">Start with a complete view of your furniture and one measurement you trust.</p>
                </div>
                <div class="steps-list">
                    @foreach ($steps as $step)
                        <article class="step-row">
                            <div class="step-copy">
                                <div class="step-index">
                                    <span>{{ $step['number'] }}</span>
                                </div>
                                <h3>{{ $step['title'] }}</h3><p>{{ $step['copy'] }}</p>
                            </div>
                            <div class="step-image"><img src="{{ $step['image'] }}" alt="{{ $step['alt'] }}"></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="faq-section section-pad" id="faq"><div class="container-shell faq-grid"><div><span class="eyebrow">Useful details</span><h2 class="section-title">Frequently asked questions.</h2><p class="section-copy">What to prepare, what to expect, and where to get help.</p></div><x-marketing.faq /></div></section>

        <section class="final-cta"><div class="container-shell final-cta-inner"><div class="cta-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-drafting-compass"><path d="m12.99 6.74 1.93 3.44"/><path d="M19.136 12a10 10 0 0 1-14.271 0"/><path d="m21 21-2.16-3.84"/><path d="m3 21 8.02-14.26"/><circle cx="12" cy="5" r="2"/></svg></div><div><span class="eyebrow">Have a furniture project in mind?</span><h2>Start with a photo and one known measurement.</h2></div><a class="primary-button" href="{{ route('workspace') }}">Open workspace<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a></div></section>
    </main>
</x-layouts.marketing>
