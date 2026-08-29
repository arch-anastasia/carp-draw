<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Check, X } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { Plan } from '@/types/billing'
import type { PageProps } from '@/types/inertia.d'

const props = defineProps<{
    plans: Plan[]
    subscription: { plan: string; on_grace_period: boolean; ends_at: string | null } | null
}>()

const page = usePage<PageProps>()

const notice = computed(
    () => page.props.flash.error ?? page.props.flash.success ?? page.props.flash.info ?? ''
)

const subscribeForm = useForm({ plan: '', interval: 'monthly' })
const resumeForm = useForm({})
const cancelForm = useForm({})

// An active, not-already-cancelled subscription can be cancelled here —
// "Change or cancel anytime" has to be true on this page, not only in the
// Stripe portal.
const canCancel = computed(
    () => props.subscription !== null && !props.subscription.on_grace_period
)

const formatPrice = (cents: number) => `€${cents / 100}`

const choosePlan = (plan: Plan) => {
    if (plan.current || subscribeForm.processing) return
    subscribeForm.plan = plan.key
    subscribeForm.post(route('billing.subscribe'))
}

const ctaLabel = (plan: Plan) => {
    if (plan.current) return 'Current plan'
    if (subscribeForm.processing && subscribeForm.plan === plan.key) return 'Redirecting…'
    return `Choose ${plan.name}`
}

const resumePlan = () => {
    if (resumeForm.processing) return
    resumeForm.post(route('billing.resume'), { preserveScroll: true })
}

const cancelPlan = () => {
    if (cancelForm.processing) return
    if (!window.confirm('Cancel your plan? You keep access until the end of the billing period.'))
        return
    cancelForm.post(route('billing.cancel'), { preserveScroll: true })
}
</script>

<template>
    <AppLayout title="Plans" hide-flash>
        <div class="plans-page app-page-narrow">
            <div class="plans-heading">
                <span class="eyebrow">Flexible workspace</span>
                <h1>Choose your plan</h1>
                <p>Upgrade for more creation credits. Change or cancel anytime.</p>
            </div>
            <div class="plans-grid">
                <article
                    v-for="plan in props.plans"
                    :key="plan.key"
                    :class="`plan-card${plan.popular ? ' is-popular' : ''}`"
                >
                    <span v-if="plan.popular" class="popular-label">Most popular</span>
                    <h2>{{ plan.name }}</h2>
                    <p>{{ plan.description }}</p>
                    <div class="plan-price">
                        <strong>{{ formatPrice(plan.price) }}</strong>
                        <span>/ month</span>
                    </div>
                    <ul>
                        <li
                            v-for="feature in plan.features"
                            :key="feature.label"
                            :class="feature.included ? '' : 'is-muted'"
                        >
                            <Check v-if="feature.included" :size="17" aria-hidden="true" />
                            <X v-else :size="17" aria-hidden="true" />
                            {{ feature.label }}
                        </li>
                    </ul>
                    <button
                        :class="plan.popular ? 'primary-button' : 'secondary-button'"
                        type="button"
                        :disabled="plan.current || subscribeForm.processing"
                        @click="choosePlan(plan)"
                    >
                        {{ ctaLabel(plan) }}
                    </button>
                </article>
            </div>
            <p v-if="notice" class="plan-notice" role="status">
                {{ notice }}
            </p>
            <p v-if="props.subscription?.on_grace_period" class="plan-notice">
                Your plan ends {{ props.subscription.ends_at }}.
                <button
                    class="secondary-button"
                    type="button"
                    :disabled="resumeForm.processing"
                    @click="resumePlan"
                >
                    Resume plan
                </button>
            </p>
            <p v-else-if="canCancel" class="plan-notice">
                <button
                    class="secondary-button"
                    type="button"
                    :disabled="cancelForm.processing"
                    @click="cancelPlan"
                >
                    Cancel plan
                </button>
                <a class="text-button" :href="route('billing.portal')">Manage billing</a>
            </p>
        </div>
    </AppLayout>
</template>
