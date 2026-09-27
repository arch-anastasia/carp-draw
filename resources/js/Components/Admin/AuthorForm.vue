<script setup lang="ts">
/**
 * The writer editor. Shared by create and edit, like PostForm and CategoryForm,
 * so a field added for a new writer is a field an existing one can be given.
 *
 * The optional account link is association, not ownership: the byline remains
 * an editorial row even when that account later disappears.
 */
import { Link, useForm } from '@inertiajs/vue3'
import FormField from '@/Components/Admin/FormField.vue'

export interface AuthorLink {
    label: string
    url: string
}

export interface AuthorFormValues {
    name: string
    username: string
    role: string
    bio: string
    avatar: File | null
    avatar_url: string
    website_url: string
    links: AuthorLink[]
    profile_is_published: boolean
    user_id: string
}

export interface UserOption { id: string; name: string; email: string }

const props = defineProps<{
    initial: AuthorFormValues
    action: string
    method: 'post' | 'put'
    submitLabel: string
    publicUrl?: string | null
    users: UserOption[]
}>()

const form = useForm<AuthorFormValues>({ ...props.initial, links: props.initial.links.map((link) => ({ ...link })) })

const inputClass =
    'w-full rounded-field border border-base-300 bg-base-100 px-3 py-2.5 text-sm text-base-content outline-none transition-colors placeholder:text-base-content/35 focus:border-base-content/40'

function addLink(): void {
    form.links = [...form.links, { label: '', url: '' }]
}

function removeLink(index: number): void {
    form.links = form.links.filter((_, position) => position !== index)
}

function submit(): void {
    if (props.method === 'put') {
        form.transform((data) => ({ ...data, _method: 'put' })).post(props.action, { forceFormData: true })

        return
    }

    form.post(props.action, { forceFormData: true })
}

function chooseAvatar(event: Event): void {
    form.avatar = (event.target as HTMLInputElement).files?.[0] ?? null
}
</script>

<template>
    <form class="space-y-8" @submit.prevent="submit">
        <!-- Who they are -->
        <section class="space-y-5 rounded-box border border-base-300 bg-base-100 p-6">
            <FormField label="Name" for-id="name" hint="The byline, exactly as it should print." :error="form.errors.name">
                <input id="name" v-model="form.name" type="text" :class="inputClass" required>
            </FormField>

            <FormField
                label="Handle"
                for-id="username"
                hint="The profile URL: /blog/authors/handle, and the identifier used by the write API."
                :error="form.errors.username"
            >
                <input id="username" v-model="form.username" type="text" :class="inputClass" placeholder="dana-reeve">
            </FormField>

            <FormField
                label="Role"
                for-id="role"
                optional
                hint="The line under the name - &quot;Barber, eleven years behind the chair&quot;. This is the part a reader weighs before trusting a recommendation."
                :error="form.errors.role"
            >
                <input id="role" v-model="form.role" type="text" :class="inputClass">
            </FormField>

            <FormField label="Bio" for-id="bio" optional hint="Shown on the profile page and under every article they wrote." :error="form.errors.bio">
                <textarea id="bio" v-model="form.bio" rows="4" maxlength="1000" :class="inputClass" />
            </FormField>

            <FormField label="Avatar" for-id="avatar" optional hint="Upload a raster image. It is normalized into the existing media library; initials are used when empty." :error="form.errors.avatar">
                <div class="flex items-center gap-4">
                    <img v-if="form.avatar_url" :src="form.avatar_url" alt="" class="h-14 w-14 rounded-full object-cover">
                    <input id="avatar" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" :class="inputClass" @change="chooseAvatar">
                </div>
            </FormField>

            <FormField label="Linked account" for-id="user_id" optional hint="For a writer who also logs in. Removing the account never removes this byline." :error="form.errors.user_id">
                <select id="user_id" v-model="form.user_id" :class="inputClass">
                    <option value="">No linked account</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} - {{ user.email }}</option>
                </select>
            </FormField>
        </section>

        <!-- Where their work lives -->
        <section class="space-y-5 rounded-box border border-base-300 bg-base-100 p-6">
            <p class="eyebrow text-base-content/45">Links</p>

            <FormField label="Website" for-id="website_url" optional hint="Printed first on the profile." :error="form.errors.website_url">
                <input id="website_url" v-model="form.website_url" type="url" :class="inputClass" placeholder="https://">
            </FormField>

            <FormField
                label="Other links"
                for-id="links"
                optional
                hint="Anywhere the writer's work is: an Instagram grid, a shop page, a press mention. Rows with no URL are dropped when you save."
                :error="form.errors.links"
            >
                <div class="space-y-3">
                    <div v-for="(link, index) in form.links" :key="index" class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="link.label"
                            type="text"
                            placeholder="Instagram"
                            class="w-40 rounded-field border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content outline-none transition-colors focus:border-base-content/40"
                        >
                        <input
                            v-model="link.url"
                            type="url"
                            placeholder="https://instagram.com/..."
                            class="min-w-0 flex-1 rounded-field border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content outline-none transition-colors focus:border-base-content/40"
                        >
                        <button
                            type="button"
                            class="text-sm text-base-content/55 underline underline-offset-4 transition-colors hover:text-error"
                            @click="removeLink(index)"
                        >
                            Remove
                        </button>
                    </div>

                    <button
                        type="button"
                        class="rounded-btn border border-base-300 px-3 py-1.5 text-[13px] text-base-content/70 transition-colors hover:border-base-content/40 hover:text-base-content"
                        @click="addLink"
                    >
                        Add a link
                    </button>
                </div>
            </FormField>
        </section>

        <!-- Publishing -->
        <section class="space-y-5 rounded-box border border-base-300 bg-base-100 p-6">
            <p class="eyebrow text-base-content/45">Publishing</p>

            <FormField
                label="Published profile"
                for-id="profile_is_published"
                optional
                hint="Listed on /blog/authors with a live profile page. Switching it off takes the page down and leaves every article they wrote signed."
                :error="form.errors.profile_is_published"
            >
                <label class="flex items-center gap-2 text-sm text-base-content/70">
                    <input id="profile_is_published" v-model="form.profile_is_published" type="checkbox" class="h-4 w-4">
                    Publish this writer's profile
                </label>
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

            <Link href="/admin/authors" class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                Cancel
            </Link>

            <a v-if="publicUrl" :href="publicUrl" target="_blank" rel="noopener" class="ml-auto text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                View profile &rarr;
            </a>
        </div>
    </form>
</template>
