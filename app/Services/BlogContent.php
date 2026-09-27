<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class BlogContent
{
    private const IMAGE_PATTERN = '/!\[([^\]]*)\]\(([^\s)]+)(?:\s+"([^"]*)")?\)(\{\.tryable\})?/';

    /** @return array<int, array{id: string, title: string}> */
    public function styleEntries(string $markdown): array
    {
        preg_match_all('/^#{2,3}\s+(.+?)\s+\{\.style-entry(?:\s+#([a-z0-9-]+))?\}\s*$/mi', $markdown, $matches, PREG_SET_ORDER);

        $used = [];

        return collect($matches)->map(function (array $match) use (&$used): array {
            $title = trim((string) $match[1]);
            $base = $match[2] ?? Str::slug(preg_replace('/^\d+[.)]\s*/', '', $title));
            $id = $base;
            $suffix = 2;

            while (in_array($id, $used, true)) {
                $id = $base.'-'.$suffix++;
            }

            $used[] = $id;

            return ['id' => $id, 'title' => $title];
        })->all();
    }

    /** @return array<int, array{question: string, answer: string}> */
    public function faqItems(string $markdown): array
    {
        if (! preg_match('/^##\s+FAQ\s*$(.*?)(?=^##\s|\z)/ims', $markdown, $section)) {
            return [];
        }

        preg_match_all('/^###\s+(.+?)\s*$\R(.*?)(?=^###\s|\z)/ims', $section[1], $items, PREG_SET_ORDER);

        return collect($items)->map(fn (array $item): array => [
            'question' => trim($item[1]),
            'answer' => trim(strip_tags($this->markdown(trim($item[2])))),
        ])->filter(fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== '')->values()->all();
    }

    /**
     * The markdown for a FAQ section, in exactly the shape `faqItems()` reads
     * back out. Every writing path that composes a body - the seeder, the
     * backfill - goes through here, so the one heading level the parser and
     * the FAQPage schema depend on cannot drift post by post.
     *
     * @param  array<int, array{question?: string, answer?: string}>  $items
     */
    public function faqSection(array $items): string
    {
        $questions = collect($items)
            ->map(fn (array $item): array => [
                'question' => trim($item['question'] ?? ''),
                'answer' => trim($item['answer'] ?? ''),
            ])
            ->filter(fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== '')
            ->map(fn (array $item): string => '### '.$item['question']."\n<p>".$item['answer'].'</p>');

        return $questions->isEmpty() ? '' : "## FAQ\n\n".$questions->implode("\n\n");
    }

    /**
     * The procedure a guide teaches, if it declares one.
     *
     *     :::steps "How to brief a barber"
     *     ### Book with a photo
     *     Take one on a good day, straight after a cut.
     *     :::
     *
     * Parsed rather than stored so the steps a reader follows and the HowTo
     * schema a crawler reads can never disagree - the same reasoning as
     * `faqItems()`. Only the first block counts: two HowTo nodes on one URL
     * describe two procedures, and the page only teaches one.
     *
     * @return array{name: string, steps: array<int, array{name: string, text: string}>}|null
     */
    public function howTo(string $markdown): ?array
    {
        if (! preg_match('/:::steps\s+"([^"]+)"\s*\R(.*?)\R:::/is', $markdown, $block)) {
            return null;
        }

        preg_match_all('/^###\s+(.+?)\s*$\R(.*?)(?=^###\s|\z)/ims', $block[2], $items, PREG_SET_ORDER);

        $steps = collect($items)
            ->map(fn (array $item): array => [
                'name' => trim($item[1]),
                'text' => trim(strip_tags($this->markdown(trim($item[2])))),
            ])
            ->filter(fn (array $step): bool => $step['name'] !== '' && $step['text'] !== '')
            ->values()
            ->all();

        return $steps === [] ? null : ['name' => trim($block[1]), 'steps' => $steps];
    }

    /**
     * Every authored gallery image, enriched from Media in one lookup.
     *
     * Media keeps the authored URL, while article markup may carry the same
     * local asset as an absolute canonical URL. Looking up both forms lets old
     * and newly edited articles share one row without querying once per image.
     *
     * @return array<int, array{url: string, contentUrl: string, alt: string, caption: string, tryable: bool, width: int|null, height: int|null}>
     */
    public function galleryImages(string $markdown): array
    {
        preg_match_all('/:::gallery\s*\R(.*?)\R:::/is', $markdown, $blocks);
        $images = [];

        foreach ($blocks[1] as $block) {
            preg_match_all(self::IMAGE_PATTERN, $block, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $images[] = [
                    'url' => $match[2],
                    'storedUrl' => $this->storedMediaUrl($match[2]),
                    'alt' => $match[1],
                    'caption' => trim($match[3] ?? ''),
                    'tryable' => ($match[4] ?? '') !== '',
                ];
            }
        }

        if ($images === []) {
            return [];
        }

        $media = Media::query()
            ->whereIn('url', collect($images)->pluck('storedUrl')->unique()->all())
            ->get(['url', 'width', 'height'])
            ->keyBy('url');

        return collect($images)->map(function (array $image) use ($media): array {
            $row = $media->get($image['storedUrl']);

            return [
                'url' => $image['url'],
                'contentUrl' => $this->absoluteMediaUrl($image['url']),
                'alt' => $image['alt'],
                'caption' => $image['caption'],
                'tryable' => $image['tryable'],
                'width' => $row?->width,
                'height' => $row?->height,
            ];
        })->all();
    }

    public function withoutFaqSection(string $markdown): string
    {
        return trim((string) preg_replace('/^##\s+FAQ\s*$.*?(?=^##\s|\z)/ims', '', $markdown));
    }

    public function faqSectionSource(string $markdown): string
    {
        return preg_match('/^##\s+FAQ\s*$.*?(?=^##\s|\z)/ims', $markdown, $section) === 1
            ? trim($section[0])
            : '';
    }

    /**
     * Every h2 and h3 in a rendered body, in document order, each with the
     * anchor it can be linked to. Reads the ids back out of the HTML that
     * `render()` wrote them into, so the outline in the sidebar and the
     * headings it points at can never drift apart.
     *
     * @return array<int, array{id: string, title: string, level: int}>
     */
    public function headings(string $html): array
    {
        preg_match_all('/<h([23])\\b([^>]*)>(.*?)<\\/h\\1>/is', $html, $matches, PREG_SET_ORDER);

        return collect($matches)->map(function (array $match): ?array {
            $id = $this->attributeId($match[2]) ?? $this->attributeId($match[3]);
            $title = $this->headingText($match[3]);

            return $id === null || $title === '' ? null : [
                'id' => $id,
                'title' => $title,
                'level' => (int) $match[1],
            ];
        })->filter()->values()->all();
    }

    /**
     * The same headings as a two-level tree: h3s hang off the h2 above them,
     * and an h3 that arrives before any h2 is promoted rather than dropped.
     *
     * @return array<int, array{id: string, title: string, children: array<int, array{id: string, title: string}>}>
     */
    public function outline(string $html): array
    {
        $tree = [];

        foreach ($this->headings($html) as $heading) {
            if ($heading['level'] === 3 && $tree !== []) {
                $tree[array_key_last($tree)]['children'][] = ['id' => $heading['id'], 'title' => $heading['title']];

                continue;
            }

            $tree[] = ['id' => $heading['id'], 'title' => $heading['title'], 'children' => []];
        }

        return $tree;
    }

    public function render(string $markdown, ?string $audience = null, ?string $look = null): string
    {
        $used = [];

        return $this->anchorHeadings($this->compose($markdown, $audience, $look), $used);
    }

    public function renderEditable(string $markdown, ?string $audience = null, ?string $look = null): string
    {
        $used = [];
        $html = '';

        foreach ($this->blocks($markdown) as $index => $block) {
            $html .= $this->annotate(
                $this->anchorHeadings($this->compose($block, $audience, $look), $used),
                $index,
                $block,
                $this->isDirective($block),
            );
        }

        $whole = $this->render($markdown, $audience, $look);

        if ($this->comparable($this->withoutAnnotations($html)) !== $this->comparable($whole)) {
            return $this->annotate($whole, 0, trim($markdown), false);
        }

        return $html;
    }

    /**
     * The article split into the top-level pieces an editor moves around: a
     * paragraph, a heading, a list, a table, a fenced code sample, a directive.
     *
     * A blank line ends a block, with the exceptions that would otherwise
     * change what the markdown means - a list or a quote whose next non-blank
     * line still belongs to it, and the inside of a fence or a `:::` directive,
     * where blank lines are content.
     *
     * @return array<int, string>
     */
    public function blocks(string $markdown): array
    {
        $lines = preg_split('/\R/', trim(str_replace("\r\n", "\n", $markdown))) ?: [];
        $total = count($lines);
        $blocks = [];
        $index = 0;

        while ($index < $total) {
            if (trim($lines[$index]) === '') {
                $index++;

                continue;
            }

            $start = $index;
            $index = $this->endOfBlock($lines, $index, $total);
            $block = rtrim(implode("\n", array_slice($lines, $start, $index - $start)));

            if ($block !== '') {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Where the block starting at `$start` ends - the index of the first line
     * that belongs to whatever comes next.
     *
     * @param  array<int, string>  $lines
     */
    private function endOfBlock(array $lines, int $start, int $total): int
    {
        $line = $lines[$start];
        $index = $start + 1;

        if (preg_match('/^\s*(```|~~~)/', $line, $fence) === 1) {
            while ($index < $total && preg_match('/^\s*'.preg_quote($fence[1], '/').'\s*$/', $lines[$index]) !== 1) {
                $index++;
            }

            return min($index + 1, $total);
        }

        if (str_starts_with(ltrim($line), ':::')) {
            /* :::face-ratio::: and :::youtube ID::: close on their own line. */
            if (preg_match('/^\s*:::\S.*:::\s*$/', $line) === 1) {
                return $index;
            }

            while ($index < $total && preg_match('/^\s*:::(endsection)?\s*$/', $lines[$index]) !== 1) {
                $index++;
            }

            return min($index + 1, $total);
        }

        while ($index < $total) {
            $current = $lines[$index];

            if (trim($current) === '') {
                if (! $this->continuesPastBlankLine($lines, $index, $total, $line)) {
                    return $index;
                }

                $index++;

                continue;
            }

            /* A fence or a directive starts its own block wherever it appears. */
            if (preg_match('/^\s*(```|~~~|:::)/', $current) === 1) {
                return $index;
            }

            $index++;
        }

        return $total;
    }

    /**
     * Whether a blank line inside a list or a quote is a gap in one block
     * rather than the end of it. A loose list - items with blank lines between
     * them - is a single `<ul>` to CommonMark, and splitting it here would
     * render it as several.
     *
     * @param  array<int, string>  $lines
     */
    private function continuesPastBlankLine(array $lines, int $blank, int $total, string $opening): bool
    {
        $next = $blank;

        while ($next < $total && trim($lines[$next]) === '') {
            $next++;
        }

        if ($next >= $total) {
            return false;
        }

        $following = $lines[$next];

        if (preg_match('/^\s*([-*+]|\d+[.)])\s/', $opening) === 1) {
            return preg_match('/^\s*([-*+]|\d+[.)])\s/', $following) === 1
                || preg_match('/^ {2,}\S/', $following) === 1;
        }

        if (str_starts_with(ltrim($opening), '>')) {
            return str_starts_with(ltrim($following), '>');
        }

        return false;
    }

    private function isDirective(string $block): bool
    {
        return str_starts_with(ltrim($block), ':::');
    }

    private function annotate(string $html, int $index, string $source, bool $atomic): string
    {
        $attributes = ' data-md-block="'.$index.'" data-md-source="'.base64_encode($source).'"'
            .($atomic ? ' data-md-atomic="1" contenteditable="false"' : '');

        $fragment = ltrim($html);

        if (! str_starts_with($fragment, '<')) {
            return '<div'.$attributes.'>'.$html.'</div>';
        }

        return (string) preg_replace_callback(
            '/^<([a-z][a-z0-9]*)/i',
            fn (array $match): string => '<'.$match[1].$attributes,
            $fragment,
            1
        );
    }

    private function withoutAnnotations(string $html): string
    {
        return (string) preg_replace(
            '/\s(?:data-md-block|data-md-source|data-md-atomic|contenteditable)="[^"]*"/',
            '',
            $html
        );
    }

    private function comparable(string $html): string
    {
        return trim((string) preg_replace('/>\s+</', '><', $html));
    }

    private function compose(string $markdown, ?string $audience = null, ?string $look = null): string
    {
        $entries = $this->styleEntries($markdown);
        $entryIndex = 0;

        $markdown = preg_replace_callback(
            '/^#{2,3}\s+(.+?)\s+\{\.style-entry(?:\s+#[a-z0-9-]+)?\}\s*$/mi',
            function (array $match) use ($entries, &$entryIndex): string {
                $entry = $entries[$entryIndex++] ?? ['id' => Str::slug($match[1]), 'title' => $match[1]];

                return '## <span id="'.e($entry['id']).'" class="scroll-mt-24">'.e($entry['title']).'</span>';
            },
            $markdown
        ) ?? $markdown;

        /*
         * Sections must recurse while their source still carries renderer
         * instructions such as `{.tryable}`. Stripping those instructions
         * first makes the child silently forget which pictures have offers.
         * Rendering sections before gallery scanning also keeps this level's
         * gallery index from consuming metadata that belongs to a child.
         */
        $markdown = preg_replace_callback('/:::section\s+(intro|core|conclusion)\s*\R(.*?)\R:::endsection/is', fn (array $match): string => '<section data-article-section="'.$match[1].'">'.$this->compose(trim($match[2]), $audience, $look).'</section>', $markdown) ?? $markdown;

        $galleryImages = collect($this->galleryImages($markdown));
        $galleryIndex = 0;

        /*
         * Where a "try this on me" lands, and which images offer it. Read off
         * the source before anything rewrites it, then the markers come out:
         * `{.tryable}` is an instruction to this renderer, and left in place
         * it would print as literal braces in the middle of the prose.
         *
         * `$tryUrl` is the :::try-on aside's. The tiles and figures build their
         * own below, without the post's look, and the viewer only reads
         * `data-gallery-tryable` as a yes or no.
         */
        $tryUrl = route('marketing.home');

        /*
         * Inside the app's article shell (see DetectInAppShell) the body may
         * not sell either: every "try this on me" offer, the mid-article drop
         * zone and the :::try-on aside render as if the author never asked
         * for them. An empty tryable map silences the prose and viewer
         * offers; the gallery tiles and the two directives check on their own.
         */
        $shell = (bool) session('in_app_shell');

        $tryableUrls = $shell ? [] : $this->tryableUrls($markdown);
        $markdown = $this->withoutTryableMarkers($markdown);

        $markdown = preg_replace_callback('/:::gallery\s*\R(.*?)\R:::/is', function (array $match) use ($galleryImages, &$galleryIndex, $shell): string {
            preg_match_all(self::IMAGE_PATTERN, $match[1], $images, PREG_SET_ORDER);
            $tiles = collect($images)->map(function () use ($galleryImages, &$galleryIndex, $shell): string {
                $image = $galleryImages->get($galleryIndex++);

                if ($image === null) {
                    return '';
                }

                $dimensions = $image['width'] !== null && $image['height'] !== null ? ' width="'.$image['width'].'" height="'.$image['height'].'"' : '';
                $caption = $image['caption'] !== '' ? '<figcaption>'.e($image['caption']).'</figcaption>' : '';

                /*
                 * The tile's offer is always visible because hover never
                 * fires on a phone, where most of this audience reads. It is
                 * a compact bottom-corner label to leave the face visible.
                 *
                 * Every offer enters the regular onboarding without a reference.
                 */
                $tryable = $image['tryable'] && ! $shell;
                $offer = $tryable
                    ? '<a class="blog-gallery-try blog-try-button blog-try-bottom-right" href="'.e(route('marketing.home')).'" data-track-cta="blog_gallery" data-track-cta-variant="tile">Create a drawing</a>'
                    : '';

                return '<figure class="blog-gallery-item"><span class="blog-gallery-frame">'
                    .'<img src="'.e($image['url']).'" alt="'.e($image['alt']).'" loading="lazy" decoding="async"'.$dimensions.$this->viewerAttributes($image['url'], $image['caption'], $image['alt'], $tryable ? route('marketing.home') : null).'>'
                    .$offer.'</span>'.$caption.'</figure>';
            })->filter();

            if ($tiles->isEmpty()) {
                return '';
            }

            /*
             * The header is what turns a run of pictures into a set: it names
             * the thing, says how much of it there is, and answers the
             * question a cropped tile raises - whether there is a bigger one
             * behind it - without waiting for a hover that may never come.
             */
            $count = $tiles->count();

            return '<div class="blog-gallery" data-image-gallery role="group" aria-label="Project gallery">'
                .'<p class="blog-gallery-head"><span class="eyebrow">Gallery</span>'
                .'<span class="blog-gallery-hint">'.$count.' '.($count === 1 ? 'photo' : 'photos').' &middot; tap to enlarge</span></p>'
                .'<div class="blog-gallery-grid">'.$tiles->implode('').'</div></div>';
        }, $markdown) ?? $markdown;

        /*
         * :::face-ratio - the measurement two of the face-shape guides tell the
         * reader to take. Rendered from a Blade component rather than inline
         * like the smaller directives: a form with three outputs is not
         * readable as a string inside a regex callback.
         */
        $markdown = (string) preg_replace_callback(
            /* [ \t] not \s: \s would eat the blank line after the directive and pull the next heading into the HTML block. */
            '/^:::face-ratio:::[ \t]*$/im',
            /*
             * Flattened to one line. A Blade view arrives with its own
             * indentation, and markdown reads four leading spaces as a code
             * block - which rendered the whole widget as escaped source. The
             * config heredocs avoid this only because PHP strips their indent.
             */
            fn (): string => (string) preg_replace('/\s*\R\s*/', ' ', trim('')),
            $markdown
        );

        /*
         * :::upload uses the same marketing CTA as the looks page.
         * Flatten the Blade markup so markdown does not treat it as code.
         */
        $markdown = (string) preg_replace_callback(
            /* [ \t] not \s, so the blank line after the directive survives. */
            '/^:::upload:::[ \t]*$/im',
            fn (): string => $shell ? '' : (string) preg_replace('/\s*\R\s*/', ' ', trim(view('components.blog.upload-cta', ['look' => $look])->render())),
            $markdown
        );

        /*
         * :::brief - the sentence a reader says in the chair.
         */
        $markdown = preg_replace_callback('/:::brief(?:\s+"([^"]+)")?\s*\R(.*?)\R:::/is', function (array $match): string {
            $brief = trim(preg_replace('/\s+/', ' ', strip_tags($match[2])) ?? '');

            if ($brief === '') {
                return '';
            }

            $label = trim($match[1]) ?: 'Project brief';

            return '<div class="blog-brief"><p class="eyebrow">'.e($label).'</p><p class="brief-text">'.e($brief).'</p></div>';
        }, $markdown) ?? $markdown;

        /*
         * Steps render as a plain ordered list rather than as headings: they
         * belong to the reading order, but five h3s per procedure would bury
         * the article's own sections in the outline.
         */
        $markdown = preg_replace_callback('/:::steps\s+"([^"]+)"\s*\R(.*?)\R:::/is', function (array $match): string {
            preg_match_all('/^###\s+(.+?)\s*$\R(.*?)(?=^###\s|\z)/ims', $match[2], $items, PREG_SET_ORDER);

            $steps = collect($items)->map(fn (array $item): string => '<li><span class="step-name">'.e(trim($item[1])).'</span>'.$this->markdown(trim($item[2])).'</li>')->implode('');

            return $steps === '' ? '' : '<div class="blog-steps"><p class="eyebrow">'.e(trim($match[1])).'</p><ol>'.$steps.'</ol></div>';
        }, $markdown) ?? $markdown;

        $markdown = preg_replace_callback('/:::try-on\s*\R?(.*?)\R?:::/is', function (array $match) use ($shell, $tryUrl, $audience): string {
            if ($shell) {
                return '';
            }

            $panel = view('components.blog.article-cta', [
                'eyebrow' => 'Plan before you build',
                'text' => new HtmlString(trim($match[1])),
                'ctaText' => 'Create a drawing',
                'ctaUrl' => $tryUrl,
                'audience' => $audience,
            ])->render();

            return '<aside class="blog-try-on blog-article-cta">'.preg_replace('/\s*\R\s*/', ' ', trim($panel)).'</aside>';
        }, $markdown) ?? $markdown;

        $markdown = preg_replace_callback('/:::youtube\s+([A-Za-z0-9_-]{6,20})(?:\s+"([^"]+)")?\s*:::/i', fn (array $match): string => '<div class="youtube-facade" data-youtube-id="'.e($match[1]).'"><button type="button" aria-label="Play '.e($match[2] ?? 'video').'"><img src="https://i.ytimg.com/vi/'.e($match[1]).'/hqdefault.jpg" alt="" loading="lazy" width="480" height="360"><span aria-hidden="true">▶</span></button></div>', $markdown) ?? $markdown;

        return $this->responsiveImages($this->markdown($markdown), $tryableUrls);
    }

    /**
     * The images an author marked `{.tryable}`, as a URL lookup.
     *
     * The fragment is dropped because that is where an inline image carries
     * its placement - `![Alt](/a.webp#left){.tryable}` is the same file as
     * `/a.webp`, and the rebuilt tag has already had the hash taken off it.
     *
     * @return array<string, true>
     */
    private function tryableUrls(string $markdown): array
    {
        preg_match_all(self::IMAGE_PATTERN, $markdown, $matches, PREG_SET_ORDER);

        $urls = [];

        foreach ($matches as $match) {
            if (($match[4] ?? '') !== '') {
                $urls[(string) preg_replace('/#[^#]*$/', '', $match[2])] = true;
            }
        }

        return $urls;
    }

    private function withoutTryableMarkers(string $markdown): string
    {
        return (string) preg_replace('/(!\[[^\]]*\]\([^)]*\))\{\.tryable\}/', '$1', $markdown);
    }

    private function viewerAttributes(string $url, string $caption, string $alt, ?string $tryUrl): string
    {
        $name = $caption !== '' ? $caption : ($alt !== '' ? $alt : 'this image');

        return ' data-gallery-image data-gallery-src="'.e($url).'" data-gallery-caption="'.e($caption).'"'
            .($tryUrl === null ? '' : ' data-gallery-tryable="'.e($tryUrl).'"')
            .' tabindex="0" role="button" aria-label="Enlarge '.e($name).'"';
    }

    /**
     * Give every h2 and h3 an id, so any heading can be linked to and the
     * outline has something to point at. A heading that already carries one -
     * a style entry writes its own, and the "jump to a hairstyle" list and the
     * ItemList schema both link to it - keeps it and only reserves the slug.
     *
     * The ids handed out so far come in and go out by reference, so an article
     * rendered a block at a time - see `renderEditable()` - numbers its
     * duplicate headings the same way a whole-document render does.
     *
     * @param  array<int, string>  $used
     */
    private function anchorHeadings(string $html, array &$used): string
    {
        return (string) preg_replace_callback('/<h([23])\b([^>]*)>(.*?)<\/h\1>/is', function (array $match) use (&$used): string {
            if (preg_match('/\bdata-no-outline\b/', $match[2]) === 1) {
                return $match[0];
            }

            $existing = $this->attributeId($match[2]) ?? $this->attributeId($match[3]);

            if ($existing !== null) {
                $used[] = $existing;

                return $match[0];
            }

            $text = $this->headingText($match[3]);

            if ($text === '') {
                return $match[0];
            }

            $id = $this->uniqueId(Str::slug(preg_replace('/^\d+[.)]\s*/', '', $text)) ?: 'section', $used);
            $used[] = $id;

            return '<h'.$match[1].' id="'.e($id).'"'.$match[2].'>'.$match[3].'</h'.$match[1].'>';
        }, $html);
    }

    /**
     * @param  array<int, string>  $used
     */
    private function uniqueId(string $base, array $used): string
    {
        $id = $base;
        $suffix = 2;

        while (in_array($id, $used, true)) {
            $id = $base.'-'.$suffix++;
        }

        return $id;
    }

    private function attributeId(string $html): ?string
    {
        return preg_match('/\bid="([^"]+)"/i', $html, $match) === 1 ? $match[1] : null;
    }

    private function headingText(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function markdown(string $markdown): string
    {
        return (string) (new GithubFlavoredMarkdownConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]))->convert($markdown);
    }

    /**
     * @param  array<string, true>  $tryableUrls
     */
    private function responsiveImages(string $html, array $tryableUrls = []): string
    {
        $html = preg_replace_callback('/<img\b([^>]*)\ssrc="([^"]+)"([^>]*)>/i', function (array $matches) use ($tryableUrls): string {
            if (str_contains($matches[1].$matches[3], 'data-gallery-image')) {
                return $matches[0];
            }

            if (str_contains($matches[1].$matches[3], ' sizes=')) {
                return $matches[0];
            }

            $attributes = (string) preg_replace('/\s(?:class|loading|decoding|width|height)="[^"]*"/i', '', $matches[1].' '.$matches[3]);

            /*
             * Markdown emits a self-closing <img />, whose slash would
             * otherwise end up in the middle of the attribute list of the tag
             * rebuilt below.
             */
            $attributes = trim((string) preg_replace('/\s*\/\s*$/', '', $attributes));

            /*
             * A markdown title - ![alt](url "Front view") - is a caption, not a
             * tooltip. It is lifted out of the tag and printed under the image,
             * where a reader looking at the picture will actually see it.
             */
            /*
             * Decoded on the way out of the tag because it goes straight back
             * into one - a caption reaching e() already escaped would print
             * its ampersands as &amp;amp;.
             */
            $caption = preg_match('/\stitle="([^"]*)"/i', ' '.$attributes, $title) === 1
                ? trim(html_entity_decode($title[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : '';

            if ($caption !== '') {
                $attributes = trim((string) preg_replace('/\stitle="[^"]*"/i', '', ' '.$attributes));
            }

            $hash = parse_url($matches[2], PHP_URL_FRAGMENT) ?: 'default';
            $url = preg_replace('/#[^#]*$/', '', $matches[2]);

            /*
             * Placement classes. A bare picture carries them itself; one with
             * a caption or an offer hands them to the <figure> instead, so
             * the caption sits under the photograph and the offer stays on it.
             * On the img they floated the picture out of its own figure and
             * left the caption hanging beside it in the wrapped text.
             */
            $class = match ($hash) {
                'left' => 'float-left mr-6 mb-4 blog-inline-image rounded-box',
                'right' => 'float-right ml-6 mb-4 blog-inline-image rounded-box',
                'small' => 'block mx-auto my-6 max-w-xs rounded-box',
                default => 'block mx-auto my-8 w-full rounded-box',
            };
            $figureClass = match ($hash) {
                'left' => 'float-left mr-6 mb-4 blog-inline-image',
                'right' => 'float-right ml-6 mb-4 blog-inline-image',
                'small' => 'mx-auto my-6 max-w-xs',
                default => 'mx-auto my-8 w-full',
            };
            $dimensions = $this->dimensions($url);
            $webpSrcset = $this->srcset($url, 'webp');
            $avifSrcset = $this->srcset($url, 'avif');
            $sources = ($avifSrcset ? '<source type="image/avif" srcset="'.e($avifSrcset).'">' : '')
                .($webpSrcset ? '<source type="image/webp" srcset="'.e($webpSrcset).'">' : '');

            /*
             * A picture in the prose opens in the same viewer a gallery tile
             * does. An article's images are one set to a reader looking for
             * the haircut in them, whether or not the author grouped them.
             */
            $alt = preg_match('/\salt="([^"]*)"/i', ' '.$attributes, $described) === 1
                ? html_entity_decode($described[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : '';
            $tryable = $tryableUrls[$url] ?? false;
            $viewer = $this->viewerAttributes($url, $caption, $alt, $tryable ? route('marketing.home') : null);

            $wrapped = $caption !== '' || $tryable;
            $imgClass = $wrapped ? 'block w-full rounded-box' : $class;

            $picture = '<picture>'.$sources.'<img '.$attributes.' src="'.e($url).'" class="'.$imgClass.' border border-base-300" loading="lazy" decoding="async" width="'.$dimensions[0].'" height="'.$dimensions[1].'" sizes="(min-width: 768px) 768px, 100vw"'.$viewer.'></picture>';

            $offer = $tryable
                ? '<a class="blog-figure-try blog-try-button blog-try-bottom-right" href="'.e(route('marketing.home')).'" data-track-cta="blog_prose_image" data-track-cta-variant="figure">Create a drawing</a>'
                : '';

            if (! $wrapped) {
                return $picture;
            }

            $frame = $tryable ? '<span class="blog-figure-frame">'.$picture.$offer.'</span>' : $picture;

            return '<figure class="'.$figureClass.'">'.$frame
                .($caption === '' ? '' : '<figcaption>'.e($caption).'</figcaption>')
                .'</figure>';
        }, $html);

        /*
         * A standalone image is wrapped in a paragraph by markdown, and a
         * <figure> inside a <p> is markup no parser keeps intact. Only a
         * paragraph that holds nothing but the figure is unwrapped, so a
         * captioned image sitting inside a sentence is left alone.
         */
        $html = (string) preg_replace('/<p>(<figure\b[^>]*>.*?<\/figure>)<\/p>/is', '$1', (string) $html);

        return $html;
    }

    private function storedMediaUrl(string $url): string
    {
        $origin = rtrim((string) config('app.url'), '/');

        return str_starts_with($url, $origin.'/') ? substr($url, strlen($origin)) : $url;
    }

    private function absoluteMediaUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return str_starts_with($url, '/')
            ? rtrim((string) config('app.url'), '/').$url
            : asset('storage/'.$url);
    }

    private function srcset(string $url, string $format): ?string
    {
        if (! preg_match('/^(.*)-1440\.webp$/', $url, $match)) {
            return null;
        }

        $items = [];

        foreach ([480, 960, 1440] as $width) {
            $candidate = $match[1].'-'.$width.'.'.$format;
            $path = parse_url($candidate, PHP_URL_PATH);

            if (is_string($path) && str_starts_with($path, '/storage/') && is_file(storage_path('app/public/'.substr($path, 9)))) {
                $items[] = $candidate.' '.$width.'w';
            }
        }

        return $items === [] ? null : implode(', ', $items);
    }

    /** @return array{int, int} */
    private function dimensions(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) && str_starts_with($path, '/storage/')) {
            $local = storage_path('app/public/'.substr($path, 9));
            $size = is_file($local) ? @getimagesize($local) : false;

            if (is_array($size)) {
                return [(int) $size[0], (int) $size[1]];
            }
        }

        return [1200, 800];
    }
}
