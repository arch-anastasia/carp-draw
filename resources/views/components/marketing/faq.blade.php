{{--
    FAQ accordion - 1:1 port of components/FaqList.tsx. The first item renders
    expanded server-side (the React source opened index 0 by default); the
    single-open behaviour lives in resources/js/marketing.ts via data-faq-*.
--}}
@php
    $faqItems = [
        [
            'question' => 'How does CarpDraw AI turn a photo into a drawing?',
            'answer' => 'Upload a clear photo, set at least one known measurement and choose the output views. The interface then prepares a structured drawing concept with dimensions and export options.',
        ],
        [
            'question' => 'Which image and 3D formats can I upload?',
            'answer' => 'Photo to Drawing accepts JPG, PNG, HEIC and WebP. The 3D Model to Drawing workflow accepts SKP, 3DS, OBJ and DWG files up to 50 MB.',
        ],
        [
            'question' => 'Can I use sketches as an input?',
            'answer' => 'Yes. High-contrast hand sketches and scanned line drawings work well as a starting point, especially when one reliable dimension is provided.',
        ],
        [
            'question' => 'What can I create in the workspace?',
            'answer' => 'The workspace includes Photo to Drawing, 3D Model to Drawing and Create Specification. Production PDF, DXF and DWG generation requires a CAD processing backend.',
        ],
        [
            'question' => 'Do I need previous CAD experience?',
            'answer' => 'No. The guided steps are designed to capture the decisions that matter without exposing a full CAD interface.',
        ],
        [
            'question' => 'How can I contact the CarpDraw AI team?',
            'answer' => 'Use the contact page, email ' . config('branding.support_email') . ' or call ' . config('branding.support_phone') . '.',
        ],
    ];
@endphp

<div class="faq-list" data-faq-list>
    @foreach ($faqItems as $index => $item)
        <div class="faq-item{{ $index === 0 ? ' is-open' : '' }}">
            <button
                type="button"
                aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                aria-controls="faq-answer-{{ $index }}"
                data-faq-toggle
            >
                <span>{{ $item['question'] }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            </button>
            <div id="faq-answer-{{ $index }}" @if ($index !== 0) hidden @endif>
                <p>{{ $item['answer'] }}</p>
            </div>
        </div>
    @endforeach
</div>
