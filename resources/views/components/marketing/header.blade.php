{{--
    Public header - 1:1 port of components/PublicHeader.tsx. All interactivity
    (product dropdown, mobile nav, auth modal openers) is wired by
    resources/js/marketing.ts through data-* hooks; the production CSP is
    script-src 'self', so no inline scripts or handlers.
--}}
<header class="public-header">
    <div class="container-shell public-header-inner">
        <x-brand.logo width="header" />
        <nav class="public-nav" aria-label="Primary navigation">
            <div class="nav-dropdown">
                <button class="nav-link" type="button" aria-expanded="false" data-nav-dropdown>
                    Product
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="product-menu" data-nav-dropdown-menu hidden style="display: none">
                    <a href="{{ route('workflow.drawing') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pen-tool" aria-hidden="true"><path d="M15.707 21.293a1 1 0 0 1-1.414 0l-1.586-1.586a1 1 0 0 1 0-1.414l5.586-5.586a1 1 0 0 1 1.414 0l1.586 1.586a1 1 0 0 1 0 1.414z"/><path d="m18 13-1.375-6.874a1 1 0 0 0-.746-.776L3.235 2.028a1 1 0 0 0-1.207 1.207L5.35 15.879a1 1 0 0 0 .776.746L13 18"/><path d="m2.3 2.3 7.286 7.286"/><circle cx="11" cy="11" r="2"/></svg>
                        <span>
                            <strong>Photo to Drawing</strong>
                            <small>Draft clear technical views</small>
                        </span>
                    </a>
                    <a href="{{ route('workflow.model') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box" aria-hidden="true"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                        <span>
                            <strong>3D Model to Drawing</strong>
                            <small>Turn 3D files into drawing sheets</small>
                        </span>
                    </a>
                    <a href="{{ route('workflow.specification') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-spreadsheet" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/></svg>
                        <span>
                            <strong>Create Specification</strong>
                            <small>Build a structured product specification</small>
                        </span>
                    </a>
                </div>
            </div>
            <a class="nav-link" href="/#workflow">Workflow</a>
            <a class="nav-link" href="{{ route('billing.index') }}">Pricing</a>
            <a class="nav-link" href="{{ route('marketing.blog.index') }}">Journal</a>
            <a class="nav-link" href="{{ route('marketing.contact') }}">Contact</a>
        </nav>
        <div class="public-actions">
            @guest
                <button class="text-button header-sign-in" type="button" data-auth-open>Sign in</button>
                <a class="primary-button header-cta" href="{{ route('workspace') }}">Start free</a>
            @else
                <a class="primary-button header-cta" href="{{ route('workspace') }}">Open workspace</a>
            @endguest
            <button
                class="mobile-menu-trigger"
                type="button"
                aria-label="Open navigation"
                aria-expanded="false"
                data-mobile-nav-toggle
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu" data-nav-icon="open"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x" data-nav-icon="close" style="display: none"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
    </div>
    <nav class="mobile-nav" aria-label="Mobile navigation" data-mobile-nav hidden style="display: none">
        <a href="{{ route('workflow.drawing') }}">Photo to Drawing</a>
        <a href="{{ route('workflow.model') }}">3D Model to Drawing</a>
        <a href="{{ route('workflow.specification') }}">Create Specification</a>
        <a href="/#workflow">Workflow</a>
        <a href="{{ route('billing.index') }}">Pricing</a>
        <a href="{{ route('marketing.contact') }}">Contact</a>
        @guest
            <button type="button" data-auth-open>Sign in</button>
        @else
            <a href="{{ route('workspace') }}">Open workspace</a>
        @endguest
    </nav>
</header>
