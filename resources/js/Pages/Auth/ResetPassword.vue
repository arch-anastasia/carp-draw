<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ArrowRight, LockKeyhole, Mail } from 'lucide-vue-next'
import TechnicalVisual from '@/Components/Workflow/TechnicalVisual.vue'

const props = defineProps<{
    token: string
    email: string
}>()

const page = usePage()
const flash = computed(() => page.props.flash)

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

const errorText = computed(
    () => form.errors.email || form.errors.password || form.errors.password_confirmation || ''
)

const submit = () => {
    form.post(route('password.update'))
}
</script>

<template>
    <div class="auth-page">
        <Head title="Reset password" />
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
                <h2 id="auth-title">Choose a new password</h2>
                <p>Set a new password for your workspace account.</p>
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
                                required
                            />
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="auth-password">New password</label>
                        <div class="input-with-icon">
                            <LockKeyhole :size="18" aria-hidden="true" />
                            <input
                                id="auth-password"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                placeholder="New password"
                                required
                                autofocus
                            />
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="auth-password-confirmation">Confirm password</label>
                        <div class="input-with-icon">
                            <LockKeyhole :size="18" aria-hidden="true" />
                            <input
                                id="auth-password-confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Repeat the password"
                                required
                            />
                        </div>
                    </div>
                    <p v-if="errorText" id="auth-error" class="error-text" role="alert">
                        {{ errorText }}
                    </p>
                    <button
                        class="primary-button auth-submit"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Reset password
                    </button>
                </form>
                <p v-if="flash.status" class="auth-message" role="status">
                    {{ flash.status }}
                </p>
            </div>
        </section>
    </div>
</template>
