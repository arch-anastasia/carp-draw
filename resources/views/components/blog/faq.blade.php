@props(['items' => [], 'editable' => false])
@if(count($items) || $editable)
<section class="blog-faq" @if($editable) data-blog-editor-faq @endif><h2>Frequently asked questions</h2><div @if($editable) data-faq-list @endif>
@foreach($items as $item)<details @if($editable) data-faq-item @endif><summary @if($editable) data-faq-question @endif>{{ $item['question'] }}</summary><p @if($editable) data-faq-answer @endif>{{ $item['answer'] }}</p></details>@endforeach
</div>@if($editable)<p data-faq-controls hidden><button type="button" data-faq-add>Add a question</button></p>@endif</section>
@endif
