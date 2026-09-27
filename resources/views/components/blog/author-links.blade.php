{{-- A writer's links: where their work actually lives.

     Proof of expertise for an advice site - a shop page, an Instagram grid, a
     press mention - so the list is whatever the writer put in it rather than a
     fixed set of social networks. `rel="nofollow"` because a profile page is
     not an endorsement of the whole domain. --}}
@props(['author'])

@php
    $links = $author->profileLinks();
@endphp

@if ($links !== [])
    <ul {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-2 text-sm']) }}>
        @foreach ($links as $link)
            <li>
                <a
                    href="{{ $link['url'] }}"
                    rel="nofollow noopener"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 text-base-content/70 underline underline-offset-4 transition-colors hover:text-base-content"
                >
                    {{ $link['label'] }}
                    <svg class="h-3 w-3 shrink-0 text-base-content/35" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                        <path d="M4 2h6v6M10 2 3 9" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            </li>
        @endforeach
    </ul>
@endif
