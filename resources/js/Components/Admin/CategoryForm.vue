<script setup lang="ts">
/**
 * The category editor. Shared by create and edit; owns its own form for the
 * same reason PostForm does.
 */
import { Link, useForm } from '@inertiajs/vue3'
import FormField from '@/Components/Admin/FormField.vue'

export interface CategoryFormValues {
    name: string
    slug: string
    title: string
    description: string
    audience: string | null
}

const props = defineProps<{
    initial: CategoryFormValues
    audiences: { value: string; label: string }[]
    action: string
    method: 'post' | 'put'
    submitLabel: string
}>()

const form = useForm<CategoryFormValues>({ ...props.initial })

const inputClass =
    'w-full rounded-field border border-base-300 bg-base-100 px-3 py-2.5 text-sm text-base-content outline-none transition-colors placeholder:text-base-content/35 focus:border-base-content/40'

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
        <section class="space-y-5 rounded-box border border-base-300 bg-base-100 p-6">
            <FormField label="Name" for-id="name" hint="The short label on a filter chip." :error="form.errors.name">
                <input id="name" v-model="form.name" type="text" :class="inputClass" required>
            </FormField>

            <FormField
                label="Slug"
                for-id="slug"
                optional
                hint="Leave empty to derive it from the name. Hubs and articles share the /blog space, so a slug already used by a post is refused."
                :error="form.errors.slug"
            >
                <input id="slug" v-model="form.slug" type="text" :class="inputClass" placeholder="auto-generated">
            </FormField>

            <FormField label="Headline" for-id="title" optional hint="The long page title. Falls back to the name." :error="form.errors.title">
                <input id="title" v-model="form.title" type="text" :class="inputClass">
            </FormField>

            <FormField label="Description" for-id="description" optional hint="Shown under the hub headline, and used as its meta description." :error="form.errors.description">
                <textarea id="description" v-model="form.description" rows="3" maxlength="500" :class="inputClass" />
            </FormField>

            <FormField
                label="Audience"
                for-id="audience"
                optional
                hint="Every post filed here inherits this side unless it says otherwise. Leave unset for a hub that serves everyone."
                :error="form.errors.audience"
            >
                <select id="audience" v-model="form.audience" :class="inputClass">
                    <option :value="null">Everyone</option>
                    <option v-for="audience in audiences" :key="audience.value" :value="audience.value">
                        {{ audience.label }}
                    </option>
                </select>
            </FormField>
        </section>

        <div class="flex items-center gap-3">
            <button
                type="submit"
                class="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-primary-content transition-opacity hover:opacity-85 disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Saving...' : submitLabel }}
            </button>
            <Link href="/admin/categories" class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                Cancel
            </Link>
        </div>
    </form>
</template>
