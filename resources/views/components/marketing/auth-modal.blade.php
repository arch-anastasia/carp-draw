{{--
    Sign-in modal - 1:1 port of components/AuthModal.tsx. Hidden by default;
    opened by any [data-auth-open] trigger, closed via backdrop mousedown,
    Escape or the close button (resources/js/marketing.ts). Provider buttons
    render only for the providers the server actually has configured -
    $socialProviders is a view-shared variable provided by the integrator.
--}}
@php
    $providers = $socialProviders ?? [];
@endphp

<div class="auth-backdrop" role="presentation" data-auth-modal hidden style="display: none">
    <section class="auth-dialog" role="dialog" aria-modal="true" aria-labelledby="auth-title">
        <button class="auth-close" type="button" aria-label="Close sign-in dialog" data-auth-close>
            <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
        <div class="auth-visual">
            <x-technical-visual label="CARPDRAW / A3" />
            <div class="auth-visual-copy">
                <span>Photo input</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                <span>Technical output</span>
            </div>
        </div>
        <div class="auth-form-panel">
            <div class="auth-mark" aria-hidden="true">CD</div>
            <h2 id="auth-title">Welcome to CarpDraw AI</h2>
            <p>Sign in to create your first technical concept.</p>
            @if (count($providers))
                <div class="auth-providers">
                    @foreach ($providers as $provider)
                        @if ($provider === 'google')
                            <a class="secondary-button" href="{{ route('social.redirect', 'google') }}">
                                <span class="provider-g" aria-hidden="true">G</span>
                                Continue with Google
                            </a>
                        @elseif ($provider === 'apple')
                            <a class="secondary-button" href="{{ route('social.redirect', 'apple') }}">
                                <span aria-hidden="true">●</span>
                                Continue with Apple
                            </a>
                        @endif
                    @endforeach
                </div>
                <div class="auth-divider">
                    <span></span>
                    <small>or</small>
                    <span></span>
                </div>
            @endif
            <a class="primary-button auth-submit" href="{{ route('register') }}">Create account</a>
            <p class="auth-forgot">Already have an account? <a href="{{ route('signin') }}">Sign in</a></p>
            <div class="auth-secure">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock-keyhole" aria-hidden="true"><circle cx="12" cy="16" r="1"/><rect x="3" y="10" width="18" height="12" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3"/></svg>
                Your data stays private.
            </div>
        </div>
    </section>
</div>
