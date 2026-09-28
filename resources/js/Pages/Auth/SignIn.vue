<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ArrowRight, LockKeyhole, Mail } from 'lucide-vue-next'
import TechnicalVisual from '@/Components/Workflow/TechnicalVisual.vue'

const props = defineProps<{ socialProviders: string[]; email?: string; registering?: boolean }>()
const page = usePage()
const flash = computed(() => page.props.flash)
const form = useForm({ email: props.email ?? '', password: '', password_confirmation: '' })
const hasGoogle = computed(() => props.socialProviders.includes('google'))
const hasApple = computed(() => props.socialProviders.includes('apple'))
const errorText = computed(() => form.errors.email || form.errors.password || form.errors.password_confirmation || '')
const handleSubmit = () => {
    form.transform((data) => ({ ...data, email: data.email.trim().toLowerCase() }))
        .post(route(props.registering ? 'register.store' : 'login.attempt'), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        })
}
</script>

<template>
    <div class="auth-page">
        <Head :title="registering ? 'Create account' : 'Sign in'" />
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
                <h2 id="auth-title">{{ registering ? 'Create your account' : 'Welcome back' }}</h2>
                <p>{{ registering ? 'Start creating technical concepts. No email link required.' : 'Sign in with your email and password.' }}</p>
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
                    <div class="form-field">
                        <label for="auth-password">Password</label>
                        <div class="input-with-icon">
                            <LockKeyhole :size="18" aria-hidden="true" />
                            <input
                                id="auth-password"
                                v-model="form.password"
                                type="password"
                                :autocomplete="registering ? 'new-password' : 'current-password'"
                                placeholder="Your password"
                                required
                            />
                        </div>
                    </div>
                    <div v-if="registering" class="form-field">
                        <label for="auth-password-confirmation">Confirm password</label>
                        <div class="input-with-icon">
                            <LockKeyhole :size="18" aria-hidden="true" />
                            <input id="auth-password-confirmation" v-model="form.password_confirmation"
                                type="password" autocomplete="new-password" placeholder="Repeat your password" required />
                        </div>
                        <small>Use at least 8 characters.</small>
                    </div>
                    <p v-if="errorText" id="auth-error" class="error-text" role="alert">
                        {{ errorText }}
                    </p>
                    <button
                        class="primary-button auth-submit"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Please wait…' : registering ? 'Create account' : 'Sign in' }}
                    </button>
                    <p v-if="!registering" class="auth-forgot">
                        <Link :href="route('password.request')">Forgot password?</Link>
                    </p>
                </form>
                <p class="auth-forgot">
                    {{ registering ? 'Already have an account?' : 'New to CarpDraw?' }}
                    <Link :href="route(registering ? 'signin' : 'register')" :key="registering ? 'signin' : 'register'">
                        {{ registering ? 'Sign in' : 'Create account' }}
                    </Link>
                </p>
                <div class="auth-secure">
                    <LockKeyhole :size="14" aria-hidden="true" />
                    Your data stays private to your workspace.
                </div>
            </div>
        </section>
    </div>
</template>
