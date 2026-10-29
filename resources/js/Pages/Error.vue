<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import BrandLogo from '@/Components/Brand/BrandLogo.vue'
import TechnicalVisual from '@/Components/Workflow/TechnicalVisual.vue'

const props = defineProps<{
    status: number
}>()

const titles: Record<number, string> = {
    403: 'This view is restricted.',
    404: 'This drawing view does not exist.',
    500: 'Something went wrong on our side.',
    503: 'We are briefly down for maintenance.',
}

const descriptions: Record<number, string> = {
    403: 'You do not have permission to open this page. Return to the CarpDraw AI landing page or open the workspace.',
    404: 'The page may have moved, or the link may be incomplete. Return to the CarpDraw AI landing page or open the workspace.',
    500: 'Please try again in a moment. Return to the CarpDraw AI landing page or open the workspace.',
    503: 'We are making a short improvement to the service. Please check back in a few minutes.',
}

const visualLabels: Record<number, string> = {
    403: '403 / RESTRICTED',
    404: '404 / NOT FOUND',
    500: '500 / SERVER ERROR',
    503: '503 / MAINTENANCE',
}

const title = computed(() => titles[props.status] ?? titles[500])
const description = computed(() => descriptions[props.status] ?? descriptions[500])
const visualLabel = computed(() => visualLabels[props.status] ?? `${props.status} / ERROR`)
</script>

<template>
    <main id="main-content" class="not-found-page">
        <Head :title="`Error ${status}`" />
        <div class="not-found-inner">
            <BrandLogo />
            <span class="eyebrow">Error {{ status }}</span>
            <h1>{{ title }}</h1>
            <p>{{ description }}</p>
            <div>
                <!-- Plain <a>: home is the Blade marketing page, not an
                     Inertia route. -->
                <a class="secondary-button" href="/">
                    <ArrowLeft :size="16" aria-hidden="true" />
                    Back home
                </a>
                <Link class="primary-button" href="/app">Open workspace</Link>
            </div>
        </div>
        <TechnicalVisual :label="visualLabel" />
    </main>
</template>
