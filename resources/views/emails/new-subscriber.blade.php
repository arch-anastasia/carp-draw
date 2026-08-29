New paying subscriber on {{ config('branding.name') }}.

Email: {{ $user->email }}
Name: {{ $user->name !== '' ? $user->name : '(not set)' }}
Signed up: {{ $user->created_at?->toDayDateTimeString() }}

Plan price id: {{ $context['plan'] ?? 'unknown' }}
Interval: {{ $context['interval'] ?? 'unknown' }}
Trial: {{ ($context['trial'] ?? false) ? 'yes' : 'no' }}
