You asked to reset your {{ config('branding.name') }} password.

Use this link to choose a new one:

{!! $resetUrl !!}

The link expires in {{ $expiresInMinutes }} minutes. If you did not ask for a reset, you can ignore this email — your password stays as it is.

— The {{ config('branding.name') }} team
