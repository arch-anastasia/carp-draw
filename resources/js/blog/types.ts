/**
 * The shapes the article editor and the server agree on.
 *
 * Both halves are printed by App\Services\BlogEditorContext - `bootstrap()`
 * into the page, `post()` into the response of every save - so a field added
 * there is a field added here.
 */

export interface EditorOption {
    value: string
    label: string
}

export interface FaqItem {
    question: string
    answer: string
}

export interface EditorPost {
    id: string
    title: string
    slug: string
    url: string
    status: 'draft' | 'scheduled' | 'published'
    excerpt: string | null
    targetKeyword: string | null
    secondaryKeywords: string[]
    metaTitle: string | null
    metaDescription: string | null
    audience: string | null
    resolvedAudience: string | null
    ctaText: string | null
    ctaUrl: string | null
    publishedAt: string | null
    publishedAtLabel: string | null
    datedAt: string | null
    /** What publishing would do with the dateline, as the button has to say it. */
    publishesAt: string
    publishSchedules: boolean
    coverUrl: string | null
    coverPath: string | null
    coverAlt: string | null
    categories: string[]
    authors: string[]
    faq: FaqItem[]
    readingMinutes: number | null
    viewsCount: number | null
    updatedAt: string | null
}

export interface EditorBootstrap {
    post: EditorPost
    options: {
        categories: EditorOption[]
        authors: EditorOption[]
        audiences: EditorOption[]
    }
    endpoints: {
        save: string
        image: string
        destroy: string
        publish: string
        admin: string
        index: string
    }
    limits: {
        metaTitle: number
        metaDescription: number
        excerpt: number
        faqQuestions: number
    }
    /**
     * The clock every date in the editor is written in - `config('app.timezone')`.
     * The publish date and the dateline are printed and parsed in it, so the
     * panel has to be able to say which timezone it is asking about.
     */
    timezone: string
    /** A rewrite waiting on this article, and the links that resolve it. */
    revision: {
        id: string
        note: string
        previewUrl: string
        diffUrl: string
        editUrl: string
        applyUrl: string
    } | null
    csrf: string
}

/** An outline entry, as BlogContent::outline() builds it. */
export interface OutlineEntry {
    id: string
    title: string
    children: Array<{ id: string; title: string }>
}

export interface SaveResponse {
    post: EditorPost
    html: string
    outline: OutlineEntry[]
}

/** One picture, as the image endpoint hands it back. */
export interface UploadedImage {
    id: string
    url: string
    alt: string | null
    markdown: string
    mime: string | null
    width: number | null
    height: number | null
    bytes: number | null
}

/**
 * The fields a save may carry, named as the API names them. Everything is
 * optional: a save sends what changed and nothing else.
 */
export interface SavePayload {
    title?: string
    slug?: string
    excerpt?: string | null
    target_keyword?: string | null
    secondary_keywords?: string[]
    dated_at?: string | null
    body?: string
    cover_image_path?: string | null
    cover_image_alt?: string | null
    meta_title?: string | null
    meta_description?: string | null
    audience?: string | null
    cta_text?: string | null
    cta_url?: string | null
    published_at?: string | null
    categories?: string[]
    authors?: string[]
    faq?: FaqItem[]
}
