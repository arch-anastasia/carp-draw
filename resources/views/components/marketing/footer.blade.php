{{-- Site footer - 1:1 port of components/Footer.tsx. --}}
@php
    $supportEmail = config('branding.support_email');
    $supportPhone = config('branding.support_phone');
    $supportPhoneHref = 'tel:' . preg_replace('/[^0-9+]/', '', (string) $supportPhone);

    $footerGroups = [
        [
            'title' => 'Create',
            'links' => [
                [route('workflow.drawing'), 'Photo to Drawing'],
                [route('workflow.model'), '3D Model to Drawing'],
                [route('workflow.specification'), 'Create Specification'],
            ],
        ],
        [
            'title' => 'Workspace',
            'links' => [
                [route('workspace'), 'Dashboard'],
                [route('projects.index'), 'Projects'],
                [route('billing.index'), 'Plans'],
                [route('settings'), 'Settings'],
            ],
        ],
        [
            'title' => 'Company',
            'links' => [
                [route('marketing.blog.index'), 'Journal'],
                [route('marketing.contact'), 'Contact'],
                [route('marketing.privacy'), 'Privacy Policy'],
                ['/#faq', 'FAQ'],
            ],
        ],
    ];
@endphp

<footer class="site-footer">
    <div class="container-shell">
        <div class="footer-top">
            <div class="footer-brand">
                <x-brand.logo />
                <p>
                    A focused AI workspace for product documentation, visual prototyping and clear
                    technical communication.
                </p>
                <a href="mailto:{{ $supportEmail }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    {{ $supportEmail }}
                </a>
                <a href="{{ $supportPhoneHref }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    {{ $supportPhone }}
                </a>
            </div>
            <nav class="footer-nav" aria-label="Footer navigation">
                @foreach ($footerGroups as $group)
                    <div>
                        <h3>{{ $group['title'] }}</h3>
                        <ul>
                            @foreach ($group['links'] as [$href, $label])
                                <li>
                                    <a href="{{ $href }}">{{ $label }}<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-right" aria-hidden="true"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg></a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </div>
        <div class="footer-bottom">
            <p>© {{ date('Y') }} CarpDraw. All rights reserved.</p>
            <p>Designed for makers, studios and product teams.</p>
        </div>
    </div>
</footer>
