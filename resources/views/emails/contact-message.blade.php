Contact form submission on {{ config('branding.name') }}.
{{-- Text/plain body: Blade escaping would garble names and messages with
     HTML entities, so user content is emitted raw — no HTML context exists. --}}
From: {!! $submission['name'] !!} <{!! $submission['email'] !!}>
Topic: {!! $submission['subject'] !!}

{!! $submission['message'] !!}
