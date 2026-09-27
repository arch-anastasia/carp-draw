{{-- The "who wrote this" box under an article. One per author, because a guide
     written by a barber and an editor should say what each of them is. --}}
@props(['author'])

<div {{ $attributes->merge(['class' => 'flex gap-4']) }}>
    @if ($author->avatarUrl())
        <img src="{{ $author->avatarUrl() }}" alt="" width="48" height="48" loading="lazy" class="h-12 w-12 shrink-0 rounded-full object-cover">
    @else
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-base-200 text-sm font-medium text-base-content/60" aria-hidden="true">
            {{ $author->initials() }}
        </span>
    @endif

    <div class="min-w-0">
        <p class="text-[15px] text-base-content">
            @if ($author->authorUrl())
                <a href="{{ $author->authorUrl() }}" class="underline underline-offset-4">{{ $author->name }}</a>
            @else
                {{ $author->name }}
            @endif
        </p>

        @if ($author->roleLine())
            <p class="eyebrow mt-1 text-base-content/40">{{ $author->roleLine() }}</p>
        @endif

        @if ($author->bio)
            <p class="mt-2 text-sm leading-relaxed text-base-content/55">{{ $author->bio }}</p>
        @endif

        <x-blog.author-links :author="$author" class="mt-3" />
    </div>
</div>
