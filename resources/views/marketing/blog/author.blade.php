<x-seo :title="$author->name" :description="$author->bio" :noindex="true" schema-type="ProfilePage" />
<x-layouts.marketing>
@php
    \Artesaos\SEOTools\Facades\JsonLdMulti::addValue('mainEntity', array_filter([
        '@type' => 'Person', '@id' => $author->authorUrl().'#person',
        'name' => $author->name, 'url' => $author->authorUrl(),
        'description' => $author->bio, 'jobTitle' => $author->role,
    ]));
@endphp
<main id="main-content" class="container-shell blog-page"><a href="{{ route('marketing.blog.authors') }}">All writers</a><h1>{{ $author->name }}</h1><p>{{ $author->role }}</p><p>{{ $author->bio }}</p><x-blog.author-links :author="$author" /><h2>Published guides</h2><div class="blog-grid">@foreach ($posts as $post)<x-blog.post-card :post="$post" />@endforeach</div>{{ $posts->links() }}</main></x-layouts.marketing>
