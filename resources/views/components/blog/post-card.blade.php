@props(['post'])
<article class="blog-card">
    <a href="{{ $post->url() }}">
        @if ($post->coverImageUrl())<img src="{{ $post->coverImageUrl() }}" alt="{{ $post->cover?->alt ?? '' }}" loading="lazy" width="640" height="400">@endif
        <h3>{{ $post->title }}</h3>
    </a>
    <p>{{ $post->excerpt }}</p>
    <x-blog.byline :authors="$post->byline()" :published-at="$post->published_at" :reading-minutes="$post->reading_minutes" :show-avatars="false" />
</article>
