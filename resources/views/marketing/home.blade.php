<x-seo
    title="Technical Drawings from Photos and 3D Models"
    description="Create technical drawings from product photos and 3D models, then prepare clear product specifications with CarpDraw AI."
/>

@php
    $steps = [
        [
            'number' => '01',
            'icon' => 'scan-line',
            'title' => 'Upload your reference',
            'copy' => 'Add a clear product photo, hand sketch or project image. The guided workspace keeps the source and technical decisions together.',
            'label' => 'INPUT / PHOTO',
            'image' => '/examples/shelving-interior.webp',
            'alt' => 'Interior reference image for a fitted shelving project',
        ],
        [
            'number' => '02',
            'icon' => 'ruler',
            'title' => 'Define scale and intent',
            'copy' => 'Set one reliable measurement, select the drawing views and clarify what the workshop or project team needs to receive.',
            'label' => 'MEASURE / MM',
            'image' => '/examples/shelving-measurement.webp',
            'alt' => 'Interior shelving reference with guided dimension controls',
        ],
        [
            'number' => '03',
            'icon' => 'file-spreadsheet',
            'title' => 'Review the technical output',
            'copy' => 'Check dimensions, notes and views before preparing a drawing set or a structured product specification.',
            'label' => 'OUTPUT / DRAWING',
            'image' => '/examples/shelving-technical-sheet.webp',
            'alt' => 'Completed technical drawing and specification for custom shelving',
        ],
    ];

    $testimonials = [
        [
            'avatar' => '/avatars/profile-1.jpg',
            'name' => 'Lena M.',
            'role' => 'Furniture designer',
            'text' => 'The measuring step was realy clear. I sent the first sheet to our workshop same day and they understand the idea.',
        ],
        [
            'avatar' => '/avatars/profile-2.jpg',
            'name' => 'Aiden K.',
            'role' => 'Prototype engineer',
            'text' => 'We use it for early drawings before CAD cleanup. Its quick, simple and nobody on the team gets lost in menus.',
        ],
        [
            'avatar' => '/avatars/profile-3.jpg',
            'name' => 'Eva R.',
            'role' => 'Studio lead',
            'text' => 'The model-to-drawing route saves us a lot of setup. Views are organised in one place, which is what we needed.',
        ],
        [
            'avatar' => '/avatars/profile-4.jpg',
            'name' => 'Noah S.',
            'role' => 'Independent maker',
            'text' => 'I dont work in CAD every day, but this flow still make sense. Adding one dimension is much easier then starting a sheet.',
        ],
        [
            'avatar' => '/avatars/profile-5.jpg',
            'name' => 'Tomas B.',
            'role' => 'Fabrication planner',
            'text' => 'Specifications look calm and readable. We can mark what is missing before it goes into production.',
        ],
        [
            'avatar' => '/avatars/profile-6.jpg',
            'name' => 'Maya I.',
            'role' => 'Interior designer',
            'text' => 'Photo, 3D model and specification feels like one connected workflow. Thats useful for small teams like ours.',
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
            'summary' => 'The missing bathroom drawing completed from the reference.',
            'photo' => '/examples/gallery/bathroom-travertine-reference.webp',
            'photoAlt' => 'Travertine double vanity reference photograph',
            'photoPosition' => 'center 57%',
            'drawing' => '/examples/gallery/bathroom-travertine-line-drawing.webp',
            'drawingAlt' => 'Black and white technical drawing of the travertine double vanity',
        ],
    ];

    $projectBrands = [
        ['name' => 'ABB', 'logo' => '/brands/abb.svg', 'href' => 'https://global.abb/'],
        ['name' => 'EGGER', 'logo' => '/brands/egger.svg', 'href' => 'https://www.egger.com/en/'],
        ['name' => 'Blum', 'logo' => '/brands/blum.png', 'href' => 'https://www.blum.com/'],
        ['name' => 'Hettich', 'logo' => '/brands/hettich.svg', 'href' => 'https://www.hettich.com/'],
        ['name' => 'Häfele', 'logo' => '/brands/hafele.svg', 'href' => 'https://www.hafele.com/'],
        ['name' => 'Kronospan', 'logo' => '/brands/kronospan.svg', 'href' => 'https://kronospan.com/'],
    ];

    $featureChecks = [
        'Photo to Drawing',
        '3D Model to Drawing',
        'Create Specification',
        'PDF, DXF and DWG preparation',
    ];
@endphp

<x-layouts.marketing>
    <main id="main-content">
        <section class="hero-section">
            <div class="hero-orbit hero-orbit-one"></div>
            <div class="hero-orbit hero-orbit-two"></div>
            <div class="container-shell hero-inner">
                <div class="hero-kicker">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles" aria-hidden="true"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>AI-assisted technical documentation
                </div>
                <h1 class="display-title">From a reference to a clear technical drawing.</h1>
                <p class="hero-copy">CarpDraw AI helps furniture, interior and product teams create drawing concepts from photos and 3D models, then organize the result into a practical specification.</p>
                <div class="hero-actions">
                    <a class="primary-button" href="{{ route('workspace') }}">Start creating<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
                    <a class="secondary-button" href="/#workflow">See how it works</a>
                </div>
                <div class="hero-proof" aria-label="Product highlights">
                    <div><strong>3</strong><span>focused workflows</span></div>
                    <div><strong>50 MB</strong><span>3D model upload</span></div>
                    <div><strong>PDF · DXF · DWG</strong><span>workflow ready</span></div>
                </div>
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
                        <span>CarpDraw workflow animation</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="trusted-strip" aria-label="Designed for">
            <div class="container-shell">
                <p>Designed for practical product teams</p>
                <div><span>Furniture studios</span><span>Interior teams</span><span>Independent makers</span><span>Fabrication teams</span><span>Design educators</span></div>
            </div>
        </section>

        <section class="brand-partners" aria-labelledby="brand-partners-title">
            <div class="container-shell">
                <div class="brand-partners-heading">
                    <span class="eyebrow">Project network</span>
                    <h2 id="brand-partners-title">Brands and systems across our furniture projects.</h2>
                    <p>Materials, hardware and manufacturing technologies referenced throughout our project workflows.</p>
                </div>
                <div class="brand-logo-grid">
                    @foreach ($projectBrands as $brand)
                        <a
                            class="brand-logo-link"
                            href="{{ $brand['href'] }}"
                            target="_blank"
                            rel="noreferrer"
                            aria-label="Visit {{ $brand['name'] }} website"
                        >
                            <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }} logo">
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="workflow-section section-pad" id="workflow">
            <div class="container-shell">
                <div class="section-heading-row">
                    <div><span class="eyebrow">Simple by design</span><h2 class="section-title">Photo to technical concept in three focused steps.</h2></div>
                    <p class="section-copy">The interface follows the same calm rhythm as a good drawing review: source, scale, organize and confirm.</p>
                </div>
                <div class="steps-list">
                    @foreach ($steps as $step)
                        <article class="step-row">
                            <div class="step-copy">
                                <div class="step-index">
                                    <span>{{ $step['number'] }}</span>
                                    @if ($step['icon'] === 'scan-line')
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-scan-line" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/></svg>
                                    @elseif ($step['icon'] === 'ruler')
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ruler" aria-hidden="true"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/></svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-spreadsheet" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/></svg>
                                    @endif
                                </div>
                                <h3>{{ $step['title'] }}</h3><p>{{ $step['copy'] }}</p>
                            </div>
                            <div class="step-image"><img src="{{ $step['image'] }}" alt="{{ $step['alt'] }}"><span>{{ $step['label'] }}</span></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="examples-section section-pad" aria-labelledby="furniture-gallery-title">
            <div class="container-shell examples-container">
                <div class="examples-intro">
                    <span class="eyebrow">Project examples</span>
                    <h2 class="section-title" id="furniture-gallery-title">References and technical drawings, side by side.</h2>
                    <p class="section-copy">Each kitchen, bathroom and furniture reference is paired with its own clean black-and-white drawing set.</p>
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
                                    <img src="{{ $example['photo'] }}" alt="{{ $example['photoAlt'] }}" loading="lazy" decoding="async" style="object-position: {{ $example['photoPosition'] }}">
                                    <figcaption><span>REFERENCE PHOTO</span><strong>{{ $example['title'] }}</strong></figcaption>
                                </figure>
                                <figure class="example-card example-card--drawing">
                                    <div class="drawing-sheet">
                                        <img class="drawing-sheet-art" src="{{ $example['drawing'] }}" alt="{{ $example['drawingAlt'] }}" loading="lazy" decoding="async">
                                        <div class="drawing-sheet-footer" aria-hidden="true">
                                            <img src="/brand/carpdraw-logo-orange.png" alt="">
                                        </div>
                                    </div>
                                    <figcaption><span>TECHNICAL DRAWING</span><strong>Measured views</strong></figcaption>
                                </figure>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="feature-story section-pad">
            <div class="container-shell feature-story-grid">
                <div class="feature-story-sticky"><span class="eyebrow">Three connected tools</span><h2 class="section-title">From visual reference to a usable project document.</h2></div>
                <div class="feature-story-copy">
                    <p class="section-copy">CarpDraw AI is shaped around three practical tasks: turning a photo into a drawing, translating a 3D model into readable views and preparing a structured specification.</p>
                    <p class="section-copy">Each workflow asks only for the information needed at that moment. The current preview demonstrates the complete interface while production conversion remains ready for a future CAD processing backend.</p>
                    <div class="feature-checks">
                        @foreach ($featureChecks as $item)
                            <span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>{{ $item }}</span>
                        @endforeach
                    </div>
                    <div class="feature-tags" aria-label="Workflow links">
                        <a href="{{ route('workflow.drawing') }}">Photo to Drawing</a>
                        <a href="{{ route('workflow.model') }}">3D Model to Drawing</a>
                        <a href="{{ route('workflow.specification') }}">Create Specification</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="social-proof section-pad">
            <div class="container-shell">
                <div class="metrics-heading"><span class="eyebrow">Illustrative feedback</span><h2 class="section-title">A preview of how customer stories will appear.</h2><p class="feedback-disclaimer">Demo profiles and sample quotes for interface presentation. Replace them with verified customer feedback before launch.</p></div>
                <div class="metrics-row"><div><strong>3</strong><span>creation modes</span></div><div><strong>4</strong><span>supported 3D formats</span></div><div><strong>360°</strong><span>responsive interface</span></div></div>
                <div class="testimonial-grid">
                    @foreach ($testimonials as $testimonial)
                        <figure>
                            <div class="testimonial-author"><img src="{{ $testimonial['avatar'] }}" alt="Demo profile avatar"><div><strong>{{ $testimonial['name'] }}</strong><small>{{ $testimonial['role'] }} · demo profile</small></div></div>
                            <div class="stars" aria-label="5 out of 5">★★★★★</div>
                            <blockquote>“{{ $testimonial['text'] }}”</blockquote>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="faq-section section-pad" id="faq"><div class="container-shell faq-grid"><div><span class="eyebrow">Useful details</span><h2 class="section-title">Frequently asked questions.</h2><p class="section-copy">Key details about the current product preview and the services required for production use.</p></div><x-marketing.faq /></div></section>

        <section class="final-cta"><div class="container-shell final-cta-inner"><div class="cta-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-drafting-compass"><path d="m12.99 6.74 1.93 3.44"/><path d="M19.136 12a10 10 0 0 1-14.271 0"/><path d="m21 21-2.16-3.84"/><path d="m3 21 8.02-14.26"/><circle cx="12" cy="5" r="2"/></svg></div><div><span class="eyebrow">Your next drawing starts here</span><h2>Bring one reference. Leave with a clearer technical story.</h2></div><a class="primary-button" href="{{ route('workspace') }}">Open workspace<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a></div></section>
    </main>
</x-layouts.marketing>
