<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ArrowRight } from 'lucide-vue-next'
import TechnicalVisual from '@/Components/Workflow/TechnicalVisual.vue'
</script>

<template>
    <div class="auth-page">
        <Head title="Link expired" />
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
                <h2 id="auth-title">This link has expired</h2>
                <p>Sign in again to receive a fresh secure link for your account.</p>
                <Link class="primary-button auth-submit" :href="route('signin')">
                    Back to sign in
                </Link>
            </div>
        </section>
    </div>
</template>
