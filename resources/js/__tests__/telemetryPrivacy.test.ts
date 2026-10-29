import { describe, expect, it } from 'vitest'
import { scrubTelemetry } from '@/plugins/telemetryPrivacy'

describe('browser telemetry privacy', () => {
    it('removes credentials, customer inputs and signed URL query strings recursively', () => {
        expect(scrubTelemetry({ status: 500, context: {
            password: 'private', sourceImage: 'private', email: 'private',
            url: 'https://assets.example.com/drawing.png?signature=private#fragment',
        } })).toEqual({ status: 500, context: {
            password: '[redacted]', sourceImage: '[redacted]', email: '[redacted]',
            url: 'https://assets.example.com/drawing.png',
        } })
    })
    it('omits binary values and stops circular or excessively nested data', () => {
        const circular: { self?: unknown } = {}
        circular.self = circular
        expect(JSON.stringify(scrubTelemetry(circular))).toContain('[omitted]')
        expect(scrubTelemetry('data:image/png;base64,AAAA')).toBe('[binary omitted]')
    })
})
