<x-seo :title="$category->headline()" :description="$category->description" :noindex="true" schema-type="CollectionPage" />
<x-layouts.marketing>
<x-schema.breadcrumb :items="[['name' => 'Journal', 'url' => route('marketing.blog.index')], ['name' => $category->headline()]]" />
<x-schema.collection-page :posts="$posts" /><main id="main-content" class="container-shell blog-page">
<a href="{{ route('marketing.blog.index') }}">All guides</a><h1>{{ $category->headline() }}</h1><p>{{ $category->intro ?? $category->description }}</p>
<div class="blog-grid">@forelse ($posts as $post)<x-blog.post-card :post="$post" />@empty<p>No published guides in this category yet.</p>@endforelse</div>
</main></x-layouts.marketing>
