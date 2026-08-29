import type { PageProps as InertiaPageProps } from '@inertiajs/core'

export interface User {
    id: string
    name: string
    email: string
    is_admin: boolean
    has_password: boolean
    language: string
}

export interface Auth {
    user: User | null
}

export interface Branding {
    name: string
    tagline: string
    supportEmail: string
    supportPhone: string
}

export interface Flash {
    success: string | null
    error: string | null
    status: string | null
    info: string | null
    project: string | null
}

export interface PageProps extends InertiaPageProps {
    auth: Auth
    branding: Branding
    /** Balance for the authed user, null for guests. */
    credits: number | null
    flash: Flash
    ziggy?: unknown
}

declare module '@inertiajs/vue3' {
    export function usePage<T extends PageProps>(): {
        props: T
        url: string
        component: string
        version: string | null
        scrollRegions: Array<{ top: number; left: number }>
        rememberedState: Record<string, unknown>
    }
}
