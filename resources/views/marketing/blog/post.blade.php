@php
    $preview = ! $post->isPublished() || $revision !== null || request()->is('api/*');
    $faq = $post->faqItems();
@endphp
<x-seo :title="$post->meta_title ?: $post->title" :description="$post->meta_description ?: $post->excerpt" :image="$post->coverImageUrl()" :image-width="$post->cover?->width" :image-height="$post->cover?->height" :image-alt="$post->cover?->alt" :noindex="$preview" :canonical="$post->url()" type="article" :date-modified="$post->displayedUpdatedAt()?->toIso8601String()" />
<x-layouts.marketing>
<x-schema.breadcrumb :canonical-url="$post->url()" :items="[['name' => 'Journal', 'url' => route('marketing.blog.index')], ['name' => $post->title]]" />
@if ($howTo = $post->howTo())<x-schema.how-to :name="$howTo['name']" :steps="$howTo['steps']" />@endif
<x-schema.article :canonical-url="$post->url()" :headline="$post->title" :description="$post->excerpt" :image="$post->coverImageUrl()" :authors="$post->byline()" :date-published="$post->published_at?->toIso8601String()" :date-modified="$post->displayedUpdatedAt()?->toIso8601String()" />
@if (count($faq))<x-schema.faq :items="$faq" />@endif
@if (app(\App\Services\PreviewAccess::class)->isAdmin(request()))<x-blog.editor-toolbar :post="$post" :revision="$revision" />@endif
<main id="main-content" class="container-shell blog-page">
<nav aria-label="Breadcrumb"><a href="{{ route('marketing.home') }}">Home</a> / <a href="{{ route('marketing.blog.index') }}">Journal</a> / {{ $post->title }}</nav>
@if ($preview)<p class="blog-preview">Preview — not indexed by search engines.</p>@endif
<article data-image-gallery>
<header><h1 @if($editable) data-blog-editor-title @endif>{{ $post->title }}</h1><p>{{ $post->excerpt }}</p><x-blog.byline :authors="$post->byline()" :published-at="$post->published_at" :updated-at="$post->displayedUpdatedAt()" :updated-is-explicit="$post->dated_at !== null" :reading-minutes="$post->reading_minutes" /></header>
<x-blog.article-cta />
@if ($post->coverImageUrl())<figure class="blog-cover"><img src="{{ $post->coverImageUrl() }}" alt="{{ $post->cover?->alt ?? '' }}" @if($post->cover?->width) width="{{ $post->cover->width }}" height="{{ $post->cover->height }}" @endif fetchpriority="high" @if($editable) data-blog-editor-cover @endif></figure>@endif
@if ($editable)<div data-blog-editor-cover-empty @if($post->coverImageUrl()) hidden @endif class="blog-preview">Use the editor's Search and social panel to add a cover image.</div>@endif
<div class="blog-article-layout"><aside><x-blog.table-of-contents :items="$post->outline()" /></aside><div>
<div class="blog-prose" data-article-body @if($editable) data-blog-editor-body @endif>{!! $editable ? $post->editableBody() : $post->renderedBody() !!}</div>
<x-blog.faq :items="$faq" :editable="$editable" />
<x-blog.article-cta :text="config('blog.ending.text')" :cta-text="$post->cta_text ?: config('blog.ending.cta_text')" :cta-url="$post->cta_url ?: config('blog.ending.cta_url')" />
@foreach ($post->byline() as $author)<x-blog.author-bio :author="$author" />@endforeach
</div></div></article>
@if ($related->isNotEmpty())<section><h2>Keep reading</h2><div class="blog-grid">@foreach ($related as $relatedPost)<x-blog.post-card :post="$relatedPost" />@endforeach</div></section>@endif
</main>
</x-layouts.marketing>
