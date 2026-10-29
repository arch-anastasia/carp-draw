<script setup lang="ts">
/**
 * The article editor. Shared by create and edit so the two can never drift -
 * a field added for a new post is a field an old post can be given.
 *
 * The form is owned here rather than handed in as a prop: a child that mutates
 * its parent's object is a data flow nothing can follow, and both callers want
 * exactly the same fields anyway.
 */
import { Link, useForm } from '@inertiajs/vue3'
import FormField from '@/Components/Admin/FormField.vue'

export interface PostFormValues {
    title: string
    slug: string
    /** Byline, in printed order - the order the names were picked. */
    authors: string[]
    excerpt: string
    body: string
    cover_image_path: string
    meta_title: string
    meta_description: string
    audience: string | null
    /** The cut the offer beside the article shows. Absent on a version form,
        which rewrites wording and does not own the article's presentation. */
    cta_text: string
    cta_url: string
    published_at: string
    /** The dateline the article prints, independent of the row's updated_at. */
    dated_at: string
    categories: string[]
    /** Only a pending version carries one - see `noteField`. */
    note?: string
}

const props = defineProps<{
    initial: PostFormValues
    authors: { id: string; name: string; email: string }[]
    categories: { id: string; name: string }[]
    audiences: { value: string; label: string }[]
    /** Only the cuts with a pre-generated tile behind them, so a pick always
        has a picture. Omitted on the version form, which hides the field. */
    action: string
    method: 'post' | 'put'
    submitLabel: string
    publicUrl?: string
    /** "Preview" for a post no reader can reach yet - the URL is the same one
        either way, because the preview IS the article's page. */
    publicUrlLabel?: string
    isKilled?: boolean
    /** Off when editing a version rather than the article. Slug and publish
        date are the article's identity: a version rewrites the wording, and
        offering to move a live URL from inside a draft invites exactly the
        accident the versioning is there to prevent. */
    hideIdentityFields?: boolean
    /** A version's "why", shown in the article's history. */
    noteField?: boolean
}>()

const form = useForm<PostFormValues>({ ...props.initial })

const inputClass =
    'w-full rounded-field border border-base-300 bg-base-100 px-3 py-2.5 text-sm text-base-content outline-none transition-colors placeholder:text-base-content/35 focus:border-base-content/40'

/**
 * Picking a name appends it, so the order of the byline is the order the editor
 * clicked - "Dana and Sam" is not the same credit as "Sam and Dana", and a
 * checkbox list that silently sorted by name would take that choice away.
 */
function toggleAuthor(id: string): void {
    form.authors = form.authors.includes(id)
        ? form.authors.filter((value) => value !== id)
        : [...form.authors, id]
}

function bylinePosition(id: string): number {
    return form.authors.indexOf(id) + 1
}


function toggleCategory(id: string): void {
    form.categories = form.categories.includes(id)
        ? form.categories.filter((value) => value !== id)
        : [...form.categories, id]
}

function submit(): void {
    if (props.method === 'put') {
        form.put(props.action)

        return
    }

    form.post(props.action)
}
</script>

<template>
    <form class="space-y-8" @submit.prevent="submit">
        <!-- The article itself -->
        <section class="space-y-5 rounded-box border border-base-300 bg-base-100 p-6">
            <FormField label="Title" for-id="title" :error="form.errors.title">
                <input id="title" v-model="form.title" type="text" :class="inputClass" required>
            </FormField>

            <FormField
                v-if="!hideIdentityFields"
                label="Slug"
                for-id="slug"
                optional
                hint="Leave empty to derive it from the title. Changing it changes the live URL, so add a redirect if the old one was indexed."
                :error="form.errors.slug"
            >
                <input id="slug" v-model="form.slug" type="text" :class="inputClass" placeholder="auto-generated">
            </FormField>

            <FormField
                label="Excerpt"
                for-id="excerpt"
                optional
                hint="The summary in listings, and the meta description when none is set."
                :error="form.errors.excerpt"
            >
                <textarea id="excerpt" v-model="form.excerpt" rows="2" :class="inputClass" maxlength="500" />
            </FormField>

            <FormField
                label="Body"
                for-id="body"
                hint="GitHub-flavored markdown. HTML is allowed. Position an image with a hash: ![alt](url#left), #right, #small or #full - and add a title, ![alt](url &quot;Three weeks in&quot;), to print it as a caption. Fenced code blocks get a copy button."
                :error="form.errors.body"
            >
                <textarea
                    id="body"
                    v-model="form.body"
                    rows="24"
                    required
                    class="w-full rounded-field border border-base-300 bg-base-100 px-3 py-2.5 font-mono text-[13px] leading-relaxed text-base-content outline-none transition-colors focus:border-base-content/40"
                />
            </FormField>
        </section>

        <!-- Filing -->
        <section class="space-y-5 rounded-box border border-base-300 bg-base-100 p-6">
            <p class="eyebrow text-base-content/45">Filing</p>

            <FormField
                label="Byline"
                for-id="authors"
                optional
                hint="Pick one name, or several for a co-written guide - they print in the order you pick them. Left empty, the article is credited to the house byline; nothing publishes unsigned."
                :error="form.errors.authors"
            >
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="author in authors"
                        :key="author.id"
                        class="flex items-center gap-2 rounded-field border px-3 py-2 text-sm transition-colors"
                        :class="form.authors.includes(author.id)
                            ? 'border-primary bg-primary/10 text-base-content'
                            : 'border-base-300 text-base-content/70 hover:border-base-content/40'"
                    >
                        <input
                            type="checkbox"
                            class="h-4 w-4"
                            :checked="form.authors.includes(author.id)"
                            @change="toggleAuthor(author.id)"
                        >
                        <span v-if="form.authors.includes(author.id)" class="tabular-nums text-base-content/50">{{ bylinePosition(author.id) }}.</span>
                        {{ author.name }}
                        <span class="text-base-content/40">{{ author.email }}</span>
                    </label>
                </div>
            </FormField>

            <FormField label="Categories" for-id="categories" optional :error="form.errors.categories">
                <div v-if="categories.length === 0" class="text-sm text-base-content/50">
                    None yet - <Link href="/admin/categories/create" class="underline underline-offset-4">create one</Link>.
                </div>
                <div v-else class="flex flex-wrap gap-2">
                    <label
                        v-for="category in categories"
                        :key="category.id"
                        class="flex items-center gap-2 rounded-field border px-3 py-2 text-sm transition-colors"
                        :class="form.categories.includes(category.id)
                            ? 'border-primary bg-primary/10 text-base-content'
                            : 'border-base-300 text-base-content/70 hover:border-base-content/40'"
                    >
                        <input
                            type="checkbox"
                            class="h-4 w-4"
                            :checked="form.categories.includes(category.id)"
                            @change="toggleCategory(category.id)"
                        >
                        {{ category.name }}
                    </label>
                </div>
            </FormField>

            <FormField
                label="Audience"
                for-id="audience"
                optional
                hint="Which side of the catalog this guide is about. Left unset it inherits the first category's side, and neutral is a real answer."
                :error="form.errors.audience"
            >
                <select id="audience" v-model="form.audience" :class="inputClass">
                    <option :value="null">Inherit from category</option>
                    <option v-for="audience in audiences" :key="audience.value" :value="audience.value">
                        {{ audience.label }}
                    </option>
                </select>
            </FormField>


        </section>

        <!-- Presentation and SEO -->
        <section class="space-y-5 rounded-box border border-base-300 bg-base-100 p-6">
            <p class="eyebrow text-base-content/45">Presentation &amp; SEO</p>

            <FormField label="Cover image" for-id="cover_image_path" optional hint="A full URL, or a path inside storage/app/public." :error="form.errors.cover_image_path">
                <input id="cover_image_path" v-model="form.cover_image_path" type="text" :class="inputClass" placeholder="/images/demo/example.webp">
            </FormField>

            <FormField label="Meta title" for-id="meta_title" optional hint="Falls back to the title." :error="form.errors.meta_title">
                <input id="meta_title" v-model="form.meta_title" type="text" :class="inputClass">
            </FormField>

            <FormField label="Meta description" for-id="meta_description" optional hint="Falls back to the excerpt." :error="form.errors.meta_description">
                <textarea id="meta_description" v-model="form.meta_description" rows="2" maxlength="300" :class="inputClass" />
            </FormField>

            <div class="grid gap-5 sm:grid-cols-2">
                <FormField label="Call to action" for-id="cta_text" optional hint="The button under the article." :error="form.errors.cta_text">
                    <input id="cta_text" v-model="form.cta_text" type="text" :class="inputClass" placeholder="See it on yourself first">
                </FormField>
                <FormField label="Call to action URL" for-id="cta_url" optional :error="form.errors.cta_url">
                    <input id="cta_url" v-model="form.cta_url" type="text" :class="inputClass" placeholder="/onboarding">
                </FormField>
            </div>
        </section>

        <!-- Publishing -->
        <section class="space-y-5 rounded-box border border-base-300 bg-base-100 p-6">
            <p class="eyebrow text-base-content/45">Publishing</p>

            <FormField
                v-if="noteField"
                label="Note"
                for-id="note"
                optional
                hint="What this rewrite is for. It shows in the article's version history."
                :error="form.errors.note"
            >
                <input
                    id="note"
                    v-model="form.note"
                    type="text"
                    maxlength="500"
                    :class="inputClass"
                    placeholder="Refreshed the 2026 sections and added the taper FAQ"
                >
            </FormField>

            <FormField
                v-if="!hideIdentityFields"
                label="Publish at"
                for-id="published_at"
                optional
                :hint="isKilled ? 'Un-kill this post before publishing or scheduling it.' : 'Empty keeps it a draft. A future time schedules it - nothing before then is public, and it stays out of the sitemap.'"
                :error="form.errors.published_at"
            >
                <input id="published_at" v-model="form.published_at" type="datetime-local" :class="inputClass" :disabled="isKilled">
            </FormField>

            <FormField
                label="Dateline"
                for-id="dated_at"
                optional
                hint="The date the article prints as updated, and the one crawlers are given. Empty uses the date the record last changed, which counts typo fixes as revisions. Any date is allowed, including a future one - and publishing from the preview page files the article for this date, so a future dateline schedules it rather than putting it live."
                :error="form.errors.dated_at"
            >
                <input id="dated_at" v-model="form.dated_at" type="datetime-local" :class="inputClass">
            </FormField>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-primary-content transition-opacity hover:opacity-85 disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Saving...' : submitLabel }}
            </button>

            <Link v-if="!hideIdentityFields" href="/admin/posts" class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                Cancel
            </Link>

            <a v-if="publicUrl" :href="publicUrl" target="_blank" rel="noopener" class="ml-auto text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                {{ publicUrlLabel ?? 'View on site' }} &rarr;
            </a>
        </div>
    </form>
</template>
