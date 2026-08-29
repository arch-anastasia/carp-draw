<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Code2, Coins, Globe2, LogOut, Pencil, UserRound } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { PageProps } from '@/types/inertia.d'

const props = defineProps<{
    credits: number
    language: string
}>()

const page = usePage<PageProps>()

const user = computed(() => page.props.auth.user)

const initials = computed(() => {
    const currentUser = user.value
    if (!currentUser) return ''
    const parts = currentUser.name.trim().split(/\s+/).filter(Boolean)
    if (parts.length) {
        return parts
            .slice(0, 2)
            .map((part) => part[0])
            .join('')
            .toUpperCase()
    }
    return currentUser.email.slice(0, 2).toUpperCase()
})

const editing = ref(false)
const status = ref('')
const language = ref(props.language)

const profileForm = useForm({
    name: user.value?.name ?? '',
    language: props.language,
})

// Language only: sending the name too made the language switch 422 for
// every passwordless/social account whose name is still empty.
const languageForm = useForm({
    language: props.language,
})

const startEditing = () => {
    profileForm.name = user.value?.name ?? ''
    editing.value = true
}

const saveProfile = () => {
    if (!profileForm.name.trim()) {
        status.value = 'Name is required.'
        return
    }

    profileForm.language = language.value
    profileForm.patch(route('settings.profile'), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false
            status.value = 'Profile saved.'
        },
    })
}

const changeLanguage = () => {
    languageForm.language = language.value
    languageForm.patch(route('settings.profile'), {
        preserveScroll: true,
        onSuccess: () => {
            status.value =
                language.value === 'en'
                    ? 'English is active.'
                    : 'Russian translation is prepared for a future release.'
        },
    })
}

const signOut = () => {
    router.post('/signout')
}
</script>

<template>
    <AppLayout title="Settings">
        <div class="settings-page app-page-narrow">
            <div class="app-page-heading">
                <div>
                    <span class="eyebrow">Workspace preferences</span>
                    <h1>Settings</h1>
                    <p>Manage your profile, credits and interface preferences.</p>
                </div>
            </div>
            <div class="settings-stack">
                <section class="settings-card profile-settings-card">
                    <div class="settings-profile">
                        <span class="settings-avatar">{{ initials }}</span>
                        <div>
                            <strong>{{ user?.name }}</strong>
                            <small>{{ user?.email }}</small>
                        </div>
                    </div>
                    <form v-if="editing" class="settings-edit-form" @submit.prevent="saveProfile">
                        <div class="form-field">
                            <label for="profile-name">Display name</label>
                            <input id="profile-name" v-model="profileForm.name" required />
                        </div>
                        <div class="settings-edit-actions">
                            <button class="secondary-button" type="button" @click="editing = false">
                                Cancel
                            </button>
                            <button
                                class="primary-button"
                                type="submit"
                                :disabled="profileForm.processing"
                            >
                                Save profile
                            </button>
                        </div>
                    </form>
                    <div v-else class="settings-card-row">
                        <div>
                            <UserRound :size="19" aria-hidden="true" />
                            <span>
                                <strong>Account</strong>
                                <small>Your profile and preview sign-in</small>
                            </span>
                        </div>
                        <div class="settings-row-actions">
                            <button class="secondary-button" type="button" @click="startEditing">
                                <Pencil :size="16" aria-hidden="true" />
                                Edit profile
                            </button>
                            <button class="secondary-button" type="button" @click="signOut">
                                <LogOut :size="16" aria-hidden="true" />
                                Sign out
                            </button>
                        </div>
                    </div>
                </section>

                <section class="settings-card settings-card-row">
                    <div>
                        <Coins :size="19" aria-hidden="true" />
                        <span>
                            <strong>Credits</strong>
                            <small>{{ props.credits }} credits available</small>
                        </span>
                    </div>
                    <Link class="primary-button" :href="route('billing.index')">
                        Get more credits
                    </Link>
                </section>

                <section class="settings-card settings-api-card">
                    <div class="settings-card-row">
                        <div>
                            <Code2 :size="19" aria-hidden="true" />
                            <span>
                                <strong>API keys</strong>
                                <small>For a future public API and automation workflow</small>
                            </span>
                        </div>
                        <a class="secondary-button" :href="route('marketing.contact')">
                            Contact us
                        </a>
                    </div>
                    <p>
                        API credentials are not generated in this front-end preview. Connect an
                        authentication service and secure backend before enabling them.
                    </p>
                </section>

                <section class="settings-card language-card">
                    <div>
                        <Globe2 :size="19" aria-hidden="true" />
                        <span>
                            <strong>Language</strong>
                            <small>Choose the interface language</small>
                        </span>
                    </div>
                    <select
                        v-model="language"
                        aria-label="Interface language"
                        @change="changeLanguage"
                    >
                        <option value="en">English</option>
                        <option value="ru">Русский</option>
                    </select>
                </section>
                <p v-if="status" class="settings-status" role="status">
                    {{ status }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
