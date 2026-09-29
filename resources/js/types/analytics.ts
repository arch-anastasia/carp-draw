/** Neutral browser telemetry contract shared with the upstream foundation. */
export interface SentryConfig {
    dsn: string | null
    environment: string | null
    release: string | null
    tracesSampleRate: number
    ignoreErrors: string[]
}

export interface AnalyticsUser {
    distinctId: string
    properties?: {
        email: string
        plan: string | null
        signedUpAt: string | null
        isWaitlisted: boolean
    }
}
