<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ArrowRight, LockKeyhole, Mail } from 'lucide-vue-next'
import TechnicalVisual from '@/Components/Workflow/TechnicalVisual.vue'

const props = defineProps<{
    socialProviders: string[]
}>()

const page = usePage()
const flash = computed(() => page.props.flash)

const step = ref<'email' | 'password'>('email')
const checking = ref(false)
const clientError = ref('')
const message = ref('')

const form = useForm({
    email: '',
    password: '',
})

const hasGoogle = computed(() => props.socialProviders.includes('google'))
const hasApple = computed(() => props.socialProviders.includes('apple'))

const errorText = computed(
    () => clientError.value || form.errors.email || form.errors.password || ''
)

const getXsrfToken = (): string =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? ''
    )

const postJson = async (url: string, body: Record<string, string>) =>
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify(body),
    })

const sendEmailLink = async (email: string) => {
    const response = await postJson(route('signin.email'), { email })
    if (!response.ok) {
        clientError.value = 'An error occurred. Please try again.'
        return
    }
    message.value = `A secure sign-in link has been sent to ${email}.`
}

const checkEmail = async () => {
    if (checking.value) return
    const cleanEmail = form.email.trim()
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cleanEmail)) {
        clientError.value = 'Enter a valid email address.'
        message.value = ''
        return
    }
    clientError.value = ''
    message.value = ''
    checking.value = true
    try {
        const response = await postJson(route('signin.check-email'), { email: cleanEmail })
        const data = (await response.json()) as {
            exists?: boolean
            hasPassword?: boolean
            errors?: Record<string, string[]>
        }
        if (!response.ok) {
            clientError.value = data.errors?.email?.[0] ?? 'An error occurred. Please try again.'
            return
        }
        if (data.hasPassword) {
            step.value = 'password'
        } else {
            await sendEmailLink(cleanEmail)
        }
    } catch {
        clientError.value = 'A network error occurred. Please try again.'
    } finally {
        checking.value = false
    }
}

const submitLogin = () => {
    clientError.value = ''
    form.post(route('login.attempt'))
}

const handleSubmit = () => {
    if (step.value === 'email') {
        void checkEmail()
    } else {
        submitLogin()
    }
}
</script>

<template>
    <div class="auth-page">
        <Head title="Sign in" />
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
                <h2 id="auth-title">Welcome to CarpDraw AI</h2>
                <p>Sign in to create your first technical concept.</p>
                <p v-if="flash.error" class="error-text" role="alert">
                    {{ flash.error }}
                </p>
                <div v-if="socialProviders.length" class="auth-providers">
                    <a
                        v-if="hasGoogle"
                        class="secondary-button"
                        :href="route('social.redirect', 'google')"
                    >
                        <span class="provider-g" aria-hidden="true">G</span>
                        Continue with Google
                    </a>
                    <a
                        v-if="hasApple"
                        class="secondary-button"
                        :href="route('social.redirect', 'apple')"
                    >
                        <span aria-hidden="true">●</span>
                        Continue with Apple
                    </a>
                </div>
                <div v-if="socialProviders.length" class="auth-divider">
                    <span />
                    <small>or</small>
                    <span />
                </div>
                <form novalidate @submit.prevent="handleSubmit">
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
                                :aria-describedby="errorText ? 'auth-error' : undefined"
                                :aria-invalid="Boolean(errorText)"
                                required
                                autofocus
                            />
                        </div>
                    </div>
                    <div v-if="step === 'password'" class="form-field">
                        <label for="auth-password">Password</label>
                        <div class="input-with-icon">
                            <LockKeyhole :size="18" aria-hidden="true" />
                            <input
                                id="auth-password"
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                placeholder="Your password"
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
                        :disabled="checking || form.processing"
                    >
                        {{ step === 'email' ? 'Continue' : 'Sign in' }}
                    </button>
                    <p v-if="step === 'password'" class="auth-forgot">
                        <Link :href="route('password.request')">Forgot password?</Link>
                    </p>
                </form>
                <p v-if="message" class="auth-message" role="status">
                    {{ message }}
                </p>
                <div class="auth-secure">
                    <LockKeyhole :size="14" aria-hidden="true" />
                    Your data stays private to your workspace.
                </div>
            </div>
        </section>
    </div>
</template>
