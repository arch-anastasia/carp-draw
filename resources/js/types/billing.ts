export interface PlanFeature {
    label: string
    included: boolean
}

export interface Plan {
    key: string
    name: string
    /** Price in cents. */
    price: number
    currency: 'EUR'
    interval: 'month'
    description: string
    features: PlanFeature[]
    popular: boolean
    current: boolean
}

export interface SubscriptionState {
    plan: string
    on_grace_period: boolean
    ends_at: string | null
}
