<x-seo title="Contact" description="Contact the CarpDraw AI team." />

@php
    $supportEmail = config('branding.support_email');
    $supportPhone = config('branding.support_phone');
    $supportPhoneHref = 'tel:' . preg_replace('/[^0-9+]/', '', (string) $supportPhone);
@endphp

<x-layouts.marketing>
    <main id="main-content" class="contact-page section-pad">
        <div class="container-shell contact-grid">
            <div class="contact-copy">
                <span class="eyebrow">Contact CarpDraw AI</span>
                <h1 class="section-title">Let’s discuss your drawing workflow.</h1>
                <p class="section-copy">
                    Ask about the product, a studio plan or a future technical integration. We will
                    respond using the details below.
                </p>
                <div class="contact-details">
                    <a href="mailto:{{ $supportEmail }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <span>
                            <small>Email</small>
                            {{ $supportEmail }}
                        </span>
                    </a>
                    <a href="{{ $supportPhoneHref }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <span>
                            <small>Phone</small>
                            {{ $supportPhone }}
                        </span>
                    </a>
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                        <span>
                            <small>Availability</small>
                            Central European Time
                        </span>
                    </div>
                </div>
            </div>
            @if (session('contact_sent'))
                {{-- Server-rendered success: the no-JavaScript submit path. --}}
                <div class="contact-success" role="status">
                    <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    <h2>Message sent</h2>
                    <p>Thanks — we typically reply within one business day.</p>
                    <a class="secondary-button" href="{{ route('marketing.contact') }}">Send another message</a>
                </div>
            @else
            <form
                class="contact-form"
                action="{{ route('marketing.contact.store') }}"
                method="post"
                novalidate
                data-contact-form
            >
                @csrf
                <div class="contact-form-row">
                    <div class="form-field">
                        <label for="contact-name">Name</label>
                        <input id="contact-name" name="name" autocomplete="name" value="{{ old('name') }}" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" required>
                        <p id="contact-name-error" class="error-text" role="alert" data-contact-error="name" @if (! $errors->has('name')) hidden style="display: none" @endif>{{ $errors->first('name') }}</p>
                    </div>
                    <div class="form-field">
                        <label for="contact-email">Email</label>
                        <input id="contact-email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" required>
                        <p id="contact-email-error" class="error-text" role="alert" data-contact-error="email" @if (! $errors->has('email')) hidden style="display: none" @endif>{{ $errors->first('email') }}</p>
                    </div>
                </div>
                <div class="form-field">
                    <label for="contact-subject">Topic</label>
                    <select id="contact-subject" name="subject" aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}" required>
                        <option value="" disabled @selected(old('subject') === null)>Select a topic</option>
                        <option value="product" @selected(old('subject') === 'product')>Product question</option>
                        <option value="studio" @selected(old('subject') === 'studio')>Studio plan</option>
                        <option value="integration" @selected(old('subject') === 'integration')>API or integration</option>
                        <option value="other" @selected(old('subject') === 'other')>Other</option>
                    </select>
                    <p class="error-text" role="alert" data-contact-error="subject" @if (! $errors->has('subject')) hidden style="display: none" @endif>{{ $errors->first('subject') }}</p>
                </div>
                <div class="form-field">
                    <label for="contact-message">Message</label>
                    <textarea id="contact-message" name="message" rows="7" placeholder="Tell us about your workflow..." aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}" required>{{ old('message') }}</textarea>
                    <p id="contact-message-error" class="error-text" role="alert" data-contact-error="message" @if (! $errors->has('message')) hidden style="display: none" @endif>{{ $errors->first('message') }}</p>
                </div>
                <button class="primary-button" type="submit">
                    <span data-contact-submit-label>Send message</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true" data-contact-submit-icon><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </form>
            <div class="contact-success" role="status" data-contact-success hidden style="display: none">
                <svg xmlns="http://www.w3.org/2000/svg" width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                <h2>Message sent</h2>
                <p>Thanks — we typically reply within one business day.</p>
                <button class="secondary-button" type="button" data-contact-reset>Send another message</button>
            </div>
            @endif
        </div>
    </main>
</x-layouts.marketing>
