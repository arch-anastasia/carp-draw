<?php

namespace Tests\Feature\Blog;

use Tests\TestCase;

class FaqSchemaTest extends TestCase
{
    public function test_faq_schema_preserves_context_and_content_without_compiling_blade_directives(): void
    {
        $items = [
            ['question' => 'Which units?', 'answer' => 'Use millimeters.'],
            ['question' => 'What does @context mean?', 'answer' => '</script><script>alert("test")</script>'],
        ];
        $html = view('components.schema.faq', ['items' => $items])->render();

        $this->assertSame(1, preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches));
        $schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $schema['@context'] ?? null);
        $this->assertSame('FAQPage', $schema['@type']);
        $this->assertCount(2, $schema['mainEntity']);
        foreach ($items as $index => $item) {
            $this->assertSame([
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $schema['mainEntity'][$index]);
        }
        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertStringNotContainsString('<?php', json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_empty_faq_does_not_emit_schema(): void
    {
        $this->assertSame('', trim(view('components.schema.faq', ['items' => []])->render()));
    }
}
