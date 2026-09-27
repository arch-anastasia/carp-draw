<x-seo title="Furniture & Technical Drawing Guides" description="Practical guides to furniture planning, technical drawings and product specifications from CarpDraw." :noindex="$search !== '' || request()->has('page')" schema-type="CollectionPage" />
<x-layouts.marketing>
<main id="main-content" class="container-shell blog-page">
    <span class="eyebrow">CarpDraw Journal</span><h1>Plan it. Draw it. Build it.</h1>
    <p>Furniture planning, drawings and specifications, explained.</p>
    <form action="{{ route('marketing.blog.index') }}" method="GET" class="blog-search"><label for="blog-search">Search guides</label><input id="blog-search" name="q" value="{{ $search }}"><button type="submit" class="primary-button">Search</button></form>
    <nav class="blog-links" aria-label="Blog categories">@foreach ($categories as $category)<a href="{{ $category->url() }}">{{ $category->name }}</a>@endforeach<a href="{{ route('marketing.blog.authors') }}">Writers</a></nav>
    @if ($unpublished->isNotEmpty())<section><h2>Drafts & scheduled guides</h2><div class="blog-grid">@foreach ($unpublished as $post)<x-blog.post-card :post="$post" />@endforeach</div></section>@endif
    @if ($search !== '')
        <h2>Search results</h2><div class="blog-grid">@forelse ($posts as $post)<x-blog.post-card :post="$post" />@empty<p>No guides match your search.</p>@endforelse</div>{{ $posts->links() }}
    @else
        @if ($startHere->isNotEmpty())<h2>Start here</h2><div class="blog-grid">@foreach ($startHere as $post)<x-blog.post-card :post="$post" />@endforeach</div>@endif
        @forelse ($sections as $section)<section><h2>{{ $section['label'] }}</h2>@if ($section['url'])<a href="{{ $section['url'] }}">View all {{ $section['total'] }} guides</a>@endif<div class="blog-grid">@foreach ($section['posts'] as $post)<x-blog.post-card :post="$post" />@endforeach</div></section>@empty<p>Our first guides are on the way.</p>@endforelse
    @endif
</main>
</x-layouts.marketing>
