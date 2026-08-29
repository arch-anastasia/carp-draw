Your latest {{ config('branding.name') }} payment of €{{ $amountFormatted }} didn't go through.

This usually means an expired card or a bank decline. You can update your payment method here:

{!! $billingPortalUrl !!}

Your workspace stays open while we retry. If the payment keeps failing, the subscription pauses until the card is updated.

— The {{ config('branding.name') }} team
