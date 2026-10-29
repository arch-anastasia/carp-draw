{{-- One mark from the editor's icon set.

     The toolbar is icons now: a bar of eight worded buttons wrapped onto three
     lines on a laptop and read as a paragraph rather than as a set of controls.
     Every button here carries its name in a `sr-only` span and in the tooltip
     the bar draws from `data-tip`, so the accessible name and the hover text
     still say "Search and social" - only the pixels got shorter.

     Material's 24x24 marks, filled with currentColor, in the same shape
     x-blog.share uses for the network logos. --}}
@props(['name'])

@php
    $marks = [
        'edit' => 'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25Zm17.71-9.96a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83Z',
        'plus' => 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2Z',
        'article' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm2 16H8v-2h8v2Zm0-4H8v-2h8v2Zm-3-5V3.5L18.5 9H13Z',
        'search' => 'M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5Zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14Z',
        'publish' => 'M5 4h14v2H5V4Zm7 4 6 6h-4v6h-4v-6H6l6-6Z',
        'undo' => 'M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8Z',
        'check' => 'm9 16.17-4.17-4.17-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17Z',
        'open' => 'M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7ZM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7Z',
        'eye' => 'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5Zm0 12.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z',
        'compare' => 'M9.01 14H2v2h7.01v3L13 15l-3.99-4v3Zm5.98-1v-3H22V8h-7.01V5L11 9l3.99 4Z',
    ];
@endphp

<svg class="editor-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
    <path d="{{ $marks[$name] ?? '' }}" />
</svg>
