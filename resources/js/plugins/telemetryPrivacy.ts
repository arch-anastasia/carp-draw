const sensitiveKey = /password|secret|token|authorization|cookie|email|contents|base64|prompt|photo|image|selfie|card|cvc|iban/i

/** Keep diagnostics, not form data, signed URLs or customer uploads. */
export function scrubTelemetry(value: unknown, depth = 0): unknown {
    if (depth > 8) return '[omitted]'
    if (typeof value === 'string') {
        if (/data:[^;]+;base64,|[A-Za-z0-9+/=]{400,}/.test(value)) return '[binary omitted]'
        return value.replace(/https?:\/\/[^\s)]+/g, (match) => {
            try {
                const url = new URL(match)
                return `${url.origin}${url.pathname}`
            } catch { return '[url omitted]' }
        }).slice(0, 2000)
    }
    if (Array.isArray(value)) return value.slice(0, 50).map(item => scrubTelemetry(item, depth + 1))
    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, item]) => [
            key, sensitiveKey.test(key) ? '[redacted]' : scrubTelemetry(item, depth + 1),
        ]))
    }
    return value
}
