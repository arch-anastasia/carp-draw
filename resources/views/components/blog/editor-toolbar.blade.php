{{-- The editor's toolbar, on the article itself.

     An admin opening any /blog/{slug} gets this; a reader never does, and an
     unpublished article 404s for everybody else. The page underneath is not a
     preview or an approximation - it is the article, rendered exactly as it
     publishes - which is the whole argument for editing here rather than on a
     form beside a textarea. The same argument the preview bar made before this
     replaced it.

     Everything the admin form can change, this can: the headline and the prose
     in place, the pictures through a right-click, and the rest - slug,
     excerpt, keywords, meta, byline, categories, the closing offer, the
     dateline - from the panels this bar opens. Publishing, scheduling and
     approving a pending rewrite live here too, which is what makes this one
     control surface rather than a second one beside the old bar.

     The bar is icons in groups - write, describe, commit, leave - because
     eight worded buttons in a row wrapped onto three lines and read as a
     sentence rather than as a set of controls. Two of the groups are earned
     rather than always there: the insert button arrives with edit mode, and
     Save and Discard arrive with the first unsaved change, so an admin only
     reading the page sees five marks. Every one of them names itself in an
     `sr-only` span and in the tooltip drawn from `data-tip`.

     Server-rendered and inert until resources/js/blog/inlineEditor.ts picks it
     up, which only happens on a page carrying this element, so no reader ever
     downloads an editor. Without JavaScript it degrades to a status, the
     publish form, and a link to the admin desk - which is very nearly the bar
     it replaced. --}}
@props([
    'post',

    'revision' => null,
])

@if ($revision)
    {{-- Review mode. The page is showing the revision's wording on a model that
         was never stored, so there is nothing here to type into: editing it
         would write those words onto the live article, which is approving a
         rewrite by previewing it. Read it, compare it, approve it. --}}
    <div class="blog-editor-bar" role="region" aria-label="Pending version">
        <div class="editor-rail">
            <p class="editor-status">
                <span class="editor-badge" data-status="pending">New version</span>
                <span>Readers still get the live article.</span>
                <span class="editor-revision-note">{{ $revision->note ?: 'Approve it to replace what is live.' }}</span>
            </p>

            <span class="editor-divider" aria-hidden="true"></span>

            <div class="editor-group">
                <a href="{{ $post->url() }}" class="editor-button editor-button-icon" data-tip="Live version">
                    <x-blog.editor-icon name="eye" /><span class="sr-only">Live version</span>
                </a>
                <a href="{{ route('admin.posts.revisions.diff', [$post->id, $revision->id]) }}" class="editor-button editor-button-icon" data-tip="See changes">
                    <x-blog.editor-icon name="compare" /><span class="sr-only">See changes</span>
                </a>
                <a href="{{ route('admin.posts.revisions.edit', [$post->id, $revision->id]) }}" class="editor-button editor-button-icon" data-tip="Edit this version">
                    <x-blog.editor-icon name="edit" /><span class="sr-only">Edit this version</span>
                </a>
            </div>

            <span class="editor-divider" aria-hidden="true"></span>

            <form method="POST" action="{{ route('admin.posts.revisions.apply', [$post->id, $revision->id]) }}">
                @csrf
                <button type="submit" class="editor-button editor-button-icon editor-primary-button" data-tip="Approve &amp; replace">
                    <x-blog.editor-icon name="check" /><span class="sr-only">Approve &amp; replace</span>
                </button>
            </form>
        </div>
    </div>

@else

@php
    $bootstrap = app(\App\Services\BlogEditorContext::class)->bootstrap($post);
    $status = $bootstrap['post']['status'];
    $schedules = $bootstrap['post']['publishSchedules'];
    $publishesAt = $bootstrap['post']['publishesAt'];

    $label = match ($status) {
        'published' => 'Live',
        'scheduled' => 'Scheduled',
        default => 'Draft',
    };

    /*
     * The panels, in the order they sit on the bar. Five became three when the
     * closing offer moved under the article it closes and the publish button
     * folded into the panel that schedules it: the bar had two controls for
     * publishing, and a panel of its own for two fields about a button.
     */
    $panels = [
        'article' => ['icon' => 'article', 'label' => 'Article, byline and ending'],
        'seo' => ['icon' => 'search', 'label' => 'Search and social'],
        'publishing' => ['icon' => 'publish', 'label' => 'Publishing'],
    ];
@endphp

<div
    data-blog-editor="{{ json_encode($bootstrap) }}"
    class="blog-editor-bar"
    role="region"
    aria-label="Article editor"
>
    <div class="editor-rail">
        {{-- The one thing on the bar that is a readout rather than a control,
             so it is printed rather than revealed: with JavaScript off this is
             still how an admin learns the article is not out yet. The sentence
             explaining what that means is in the Publishing panel. --}}
        <p class="editor-status">
            <span class="editor-badge" data-editor-badge data-status="{{ $status }}">{{ $label }}</span>
            <span data-editor-message>
                @if ($status === 'published')
                    Published {{ $post->published_at?->format('j M Y') }}
                @elseif ($status === 'scheduled')
                    {{-- Named, because a time without a clock is a guess: the
                         bar is read on phones set to whatever timezone their
                         owner is in, and the article goes out on the site's. --}}
                    Publishes {{ $post->published_at?->format('j M Y, H:i T') }}
                @else
                    Not published
                @endif
            </span>
        </p>

        {{-- Hidden until the bundle arrives: a button that edits nothing is
             worse than no button. --}}
        <div class="editor-actions" data-editor-actions hidden>
            <span class="editor-divider" aria-hidden="true"></span>

            <div class="editor-group">
                <button type="button" data-editor-toggle class="editor-button editor-button-icon" aria-pressed="false" data-tip="Edit the article">
                    <x-blog.editor-icon name="edit" /><span class="sr-only" data-editor-toggle-label>Edit the article</span>
                </button>

                {{-- Inserting turns editing on anyway, so it arrives with edit
                     mode rather than sitting there inviting a click that
                     changes the mode as a side effect. --}}
                <button type="button" class="editor-button editor-button-icon" data-editor-panel-open="insert" aria-expanded="false" data-tip="Insert a block" data-editor-while-editing hidden>
                    <x-blog.editor-icon name="plus" /><span class="sr-only">Insert a block</span>
                </button>
            </div>

            <span class="editor-divider" aria-hidden="true"></span>

            <div class="editor-group">
                @foreach ($panels as $panel => $meta)
                    <button
                        type="button"
                        class="editor-button editor-button-icon"
                        data-editor-panel-open="{{ $panel }}"
                        aria-expanded="false"
                        data-tip="{{ $meta['label'] }}"
                    >
                        <x-blog.editor-icon :name="$meta['icon']" /><span class="sr-only">{{ $meta['label'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Both arrive with the first unsaved change and leave with the
                 save, so the bar stays quiet while there is nothing to commit.
                 They are disabled as well as hidden, because Cmd-S reaches
                 Save whether or not it is on screen. --}}
            <span class="editor-divider" data-editor-commit aria-hidden="true" hidden></span>

            <div class="editor-group" data-editor-commit hidden>
                <button type="button" data-editor-discard class="editor-button editor-button-icon" data-tip="Discard changes" disabled>
                    <x-blog.editor-icon name="undo" /><span class="sr-only">Discard changes</span>
                </button>
                <button type="button" data-editor-save class="editor-button editor-button-icon editor-primary-button" data-tip="Save changes" disabled>
                    <x-blog.editor-icon name="check" /><span class="sr-only" data-editor-save-label>Save changes</span>
                </button>
            </div>
        </div>

        <span class="editor-divider" aria-hidden="true"></span>

        {{-- Kept as a form, exactly as it was on the preview bar: publishing is
             a session-and-CSRF affair, and this way it is the one control that
             still works with JavaScript off. The editor hides it on boot,
             because the Publishing panel offers the same click with the date
             it would use printed beside it - two publish buttons on one bar
             was the duplication worth removing. --}}
        @if ($status !== 'published')
            <form method="POST" action="{{ $bootstrap['endpoints']['publish'] }}" data-editor-publish-form data-editor-fallback>
                @csrf
                <button type="submit" class="editor-button editor-primary-button">
                    {{ $schedules ? 'Schedule for '.$publishesAt : 'Publish now' }}
                </button>
            </form>
        @endif

        <a href="{{ $bootstrap['endpoints']['admin'] }}" class="editor-button editor-button-icon editor-button-quiet" data-tip="The admin desk">
            <x-blog.editor-icon name="open" /><span class="sr-only">The admin desk</span>
        </a>
    </div>

    {{-- A rewrite of this article is waiting. Printed rather than left to the
         bundle so it is visible with JavaScript off, which is the state in
         which an editor is most likely to start a second rewrite on top of the
         first without knowing it. --}}
    @if ($bootstrap['revision'])
        <p class="editor-revision">
            <span class="editor-badge" data-status="pending">Version pending</span>
            <span class="editor-revision-note">{{ $bootstrap['revision']['note'] }}</span>

            <a href="{{ $bootstrap['revision']['previewUrl'] }}" class="editor-button editor-button-icon" data-tip="Preview it">
                <x-blog.editor-icon name="eye" /><span class="sr-only">Preview it</span>
            </a>
            <a href="{{ $bootstrap['revision']['diffUrl'] }}" class="editor-button editor-button-icon" data-tip="See changes">
                <x-blog.editor-icon name="compare" /><span class="sr-only">See changes</span>
            </a>
            <a href="{{ $bootstrap['revision']['editUrl'] }}" class="editor-button editor-button-icon" data-tip="Edit it">
                <x-blog.editor-icon name="edit" /><span class="sr-only">Edit it</span>
            </a>
        </p>
    @endif

    <p class="editor-hint" data-editor-hint hidden></p>
</div>

@endif
