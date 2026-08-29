<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ArrowRight, Mail } from 'lucide-vue-next'
import TechnicalVisual from '@/Components/Workflow/TechnicalVisual.vue'

const page = usePage()
const flash = computed(() => page.props.flash)

const form = useForm({
    email: '',
})

const submit = () => {
    form.post(route('password.email'))
}
</script>

<template>
    <div class="auth-page">
        <Head title="Forgot password" />
        <section class="auth-dialog" aria-labelledby="auth-title">
            <div class="auth-visual">
                <TechnicalVisual label="CARPDRAW / A3" />
                <div class="auth-visual-copy">
                    <span>Photo input</span>
                    <ArrowRight :size="17" aria-hidden="true" />
                    <span>Technical output</span>
                </div>
            </div>
            <div class="auth-form-panel">
                <div class="auth-mark" aria-hidden="true">CD</div>
                <h2 id="auth-title">Reset your password</h2>
                <p>Enter your email and we will send you a reset link.</p>
                <p v-if="flash.error" class="error-text" role="alert">
                    {{ flash.error }}
                </p>
                <form novalidate @submit.prevent="submit">
                    <div class="form-field">
                        <label for="auth-email">Email</label>
                        <div class="input-with-icon">
                            <Mail :size="18" aria-hidden="true" />
                            <input
                                id="auth-email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                placeholder="you@example.com"
                                :aria-describedby="form.errors.email ? 'auth-error' : undefined"
                                :aria-invalid="Boolean(form.errors.email)"
                                required
                                autofocus
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.email" id="auth-error" class="error-text" role="alert">
                        {{ form.errors.email }}
                    </p>
                    <button
                        class="primary-button auth-submit"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Send reset link
                    </button>
                </form>
                <p v-if="flash.status" class="auth-message" role="status">
                    {{ flash.status }}
                </p>
            </div>
        </section>
    </div>
</template>
