<x-seo title="Privacy Policy" description="Privacy information for CarpDraw AI." date-modified="2026-08-27" />

@php
    $supportEmail = config('branding.support_email');
    $supportPhone = config('branding.support_phone');
    $supportPhoneHref = 'tel:' . preg_replace('/[^0-9+]/', '', (string) $supportPhone);
@endphp

<x-layouts.marketing>
    <main id="main-content" class="legal-page section-pad">
        <article class="container-shell">
            <span class="eyebrow">Last updated August 27, 2026</span>
            <h1 class="section-title">Privacy Policy</h1>
            <p class="legal-lead">
                This policy describes how CarpDraw AI collects, uses and protects your information
                when you create an account, upload references and prepare technical drawings and
                specifications in the workspace.
            </p>
            <section>
                <h2>1. Information you provide</h2>
                <p>
                    We collect the account details you register with (name, email address and, when
                    you set one, a password), messages you send through the contact form, and the
                    photos, sketches and 3D models you upload for processing. Uploaded files are
                    stored privately in your workspace and are only used to produce your drawings
                    and specifications.
                </p>
            </section>
            <section>
                <h2>2. How information is used</h2>
                <p>
                    Your data is used to provide the photo-to-drawing, model-to-drawing and
                    specification workflows, manage your account and subscription, respond to
                    support requests and improve service reliability. We do not sell your data.
                </p>
            </section>
            <section>
                <h2>3. File processing</h2>
                <p>
                    Files upload over an encrypted connection and are processed to generate your
                    technical outputs. Uploads and generated results stay private to your account
                    and are retained until you delete the related project or your account, at which
                    point they are removed from our storage.
                </p>
            </section>
            <section>
                <h2>4. Service providers</h2>
                <p>
                    Authentication, hosting, file storage and email delivery providers process
                    limited data on behalf of CarpDraw AI. Payments and subscriptions are handled by
                    Stripe — card details are sent directly to Stripe and are never stored on our
                    servers. Analytics run only with your consent.
                </p>
            </section>
            <section>
                <h2>5. Your choices</h2>
                <p>
                    You can access and correct your account data, request a copy of your stored
                    information or delete your account and its projects at any time. Cookie and
                    analytics preferences can be declined without affecting the core workflows.
                </p>
            </section>
            <section>
                <h2>6. Contact</h2>
                <p>
                    For privacy questions, email
                    <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                    or call <a href="{{ $supportPhoneHref }}">{{ $supportPhone }}</a>.
                </p>
            </section>
        </article>
    </main>
</x-layouts.marketing>
