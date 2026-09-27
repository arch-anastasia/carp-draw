{{-- Who wrote it, when, and how long it takes to read.

     One component for every surface that prints a byline - the article header,
     a card, a hub - so a co-written guide credits the same people in the same
     order everywhere. Names link to the writer's profile: a reader deciding
     whether to trust a haircut recommendation should be one click from finding
     out who is making it.

     `authors` is Post::byline(), which is never empty while a house account
     exists. If it somehow is, the brand is credited rather than nobody. --}}
@props([
    /** @var iterable<\App\Models\User> */
    'authors' => [],
    'publishedAt' => null,
    'updatedAt' => null,

    'updatedIsExplicit' => false,
    'readingMinutes' => null,
    'showAvatars' => true,

    'linked' => true,
])

@php
    $people = collect($authors)->filter()->values();
    $last = $people->count() - 1;

    /*
     * "Updated" only when it is true and worth saying. A guide edited the day
     * after it went out was not updated in any sense the reader cares about,
     * and stamping every article with today's date is how a blog teaches
     * people to ignore its dates.
     *
     * A hand-picked date skips the test. Someone answered this question on
     * purpose, and second-guessing them is how the field stops meaning
     * anything - it is also the only date a draft has, which is what makes a
     * preview show a dateline at all.
     */
    $showUpdated = $updatedAt && ($updatedIsExplicit
        || ($publishedAt && $updatedAt->gt($publishedAt->copy()->addDays(2))));

    $stamp = $showUpdated
        ? 'Updated '.$updatedAt->format('j F Y')
        : $publishedAt?->format('j F Y');
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-3 gap-y-2 text-[13px] text-base-content/45']) }}>
    @if ($showAvatars && $people->isNotEmpty())
        <div class="flex shrink-0 -space-x-2" aria-hidden="true">
            @foreach ($people->take(3) as $person)
                @if ($person->avatarUrl())
                    <img
                        src="{{ $person->avatarUrl() }}"
                        alt=""
                        width="28"
                        height="28"
                        loading="lazy"
                        class="h-7 w-7 rounded-full border-2 border-base-100 object-cover"
                    >
                @else
                    <span class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-base-100 bg-base-200 text-[10px] font-medium text-base-content/60">
                        {{ $person->initials() }}
                    </span>
                @endif
            @endforeach
        </div>
    @endif

    <p class="min-w-0">
        @if ($people->isNotEmpty())
            @foreach ($people as $index => $person)
                @if ($index > 0){{ $index === $last ? ' and ' : ', ' }}@endif
                @if ($linked && $person->profile_is_published && $person->authorUrl())
                    <a href="{{ $person->authorUrl() }}" class="text-base-content/70 underline underline-offset-4 transition-colors hover:text-base-content">{{ $person->name }}</a>
                @else
                    <span class="text-base-content/70">{{ $person->name }}</span>
                @endif
            @endforeach
        @else
            <span class="text-base-content/70">{{ config('branding.name') }}</span>
        @endif

        @if ($stamp)
            <span aria-hidden="true"> · </span>{{ $stamp }}
        @endif

        @if ($readingMinutes)
            <span aria-hidden="true"> · </span>{{ $readingMinutes }} min read
        @endif
    </p>
</div>
