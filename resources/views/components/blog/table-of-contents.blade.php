{{-- The article outline, as a printed contents page: a numbered section index
     down the left, the title beside it, and the number carrying every piece of
     state - where each section sits, which ones have been read, and which one
     the reader is in.

     Beside the body on large screens, inside the sticky rail the post template
     builds, and a collapsed accordion above the body on small ones - where a
     fixed column has nowhere to go, but a reader still wants to know what the
     article covers before committing to it.

     It ships open so a visitor without JavaScript still sees and can use it;
     marketing.ts collapses it on narrow screens, colors the numbers behind
     the reader, keeps the label's "03 / 08" in step, and fades whichever edge
     of the capped list has more behind it. Items come from Post::outline(),
     which reads the ids back out of the rendered body, so every link here has
     a heading to land on. --}}
@props([
    /** @var array<int, array{id: string, title: string, children: array<int, array{id: string, title: string}>}> */
    'items' => [],
    'heading' => 'In this article',
])

@php
    /* Two digits is the floor, so 01-09 keep the column the wider ones set. */
    $pad = max(2, strlen((string) count($items)));
@endphp

@if (count($items))
    <details
        open
        data-article-outline
        {{ $attributes->merge(['class' => 'group rounded-box border border-base-300 bg-base-200/50 p-5 lg:rounded-none lg:border-x-0 lg:border-b-0 lg:border-t lg:bg-transparent lg:p-0 lg:pt-5']) }}
    >
        <summary class="flex shrink-0 list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden lg:pointer-events-none">
            <span class="eyebrow text-base-content/55">{{ $heading }}</span>

            <span class="flex shrink-0 items-center gap-3">
                {{-- Which section of how many. The reader's position stays
                     visible even when the list has scrolled past their row.
                     Hidden from screen readers on purpose: it changes on every
                     scroll, and aria-current on the link already says where
                     the reader is without narrating a counter at them. --}}
                <span class="outline-position" aria-hidden="true">
                    <span data-outline-current>{{ str_pad('1', $pad, '0', STR_PAD_LEFT) }}</span>
                    <span>/</span>
                    <span>{{ str_pad((string) count($items), $pad, '0', STR_PAD_LEFT) }}</span>
                </span>

                <svg class="h-4 w-4 text-base-content/40 transition-transform group-open:rotate-180 lg:hidden" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </summary>

        {{-- The list scrolls, not the card: a long outline stays inside the
             screen without taking the label with it, and without pushing the
             offer under it out of view. --}}
        <nav aria-label="{{ $heading }}" class="outline-scroll mt-4 max-h-[50vh] lg:max-h-56 lg:min-h-0 lg:flex-1">
            <ol class="text-[13px] leading-snug">
                @foreach ($items as $index => $item)
                    <li class="outline-branch">
                        <a href="#{{ $item['id'] }}" data-outline-link="{{ $item['id'] }}" class="outline-link">
                            <span class="outline-index" aria-hidden="true">{{ str_pad((string) ($index + 1), $pad, '0', STR_PAD_LEFT) }}</span>
                            <span class="outline-title">{{ $item['title'] }}</span>
                        </a>

                        @if (count($item['children']))
                            <ol class="outline-children">
                                @foreach ($item['children'] as $child)
                                    <li class="outline-branch outline-branch-nested">
                                        <a href="#{{ $child['id'] }}" data-outline-link="{{ $child['id'] }}" class="outline-link outline-link-nested">
                                            {{ $child['title'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    </details>
@endif
