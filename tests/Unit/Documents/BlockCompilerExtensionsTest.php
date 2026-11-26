<?php

namespace Tests\Unit\Documents;

use App\Domain\Documents\Services\BlockCompiler;
use App\Domain\Documents\Services\DocumentRenderer;
use App\Domain\Documents\Services\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class BlockCompilerExtensionsTest extends TestCase
{
    private function render(array $block, array $context = []): string
    {
        return (new TemplateRenderer)->render((new BlockCompiler)->compile([$block]), $context);
    }

    public function test_signature_labels_interpolate_variables(): void
    {
        $html = $this->render(['id' => 's', 'type' => 'signature', 'props' => ['lines' => [['label' => 'For {{ organization.name }}']]]], ['organization' => ['name' => 'Acme Theatre']]);

        $this->assertStringContainsString('For Acme Theatre', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_text_block_decodes_entities_inside_template_tags(): void
    {
        $html = $this->render([
            'id' => 't', 'type' => 'text',
            'props' => ['html' => '<p>{% if totals.total &gt; 100 %}Large order{% endif %} &amp; {{ job.name }}</p>'],
        ], ['totals' => ['total' => 250], 'job' => ['name' => 'Hamlet']]);

        $this->assertStringContainsString('Large order', $html);
        $this->assertStringContainsString('&amp; Hamlet', $html);
    }

    public function test_underline_and_font_family_styles(): void
    {
        $out = (new BlockCompiler)->compile([[
            'id' => 'h', 'type' => 'heading', 'props' => ['text' => 'Hi', 'level' => 2],
            'style' => ['underline' => true, 'fontFamily' => 'serif', 'uppercase' => true],
        ]]);

        $this->assertStringContainsString('text-decoration:underline', $out);
        $this->assertStringContainsString('font-family:"DejaVu Serif"', $out);
        $this->assertStringContainsString('text-transform:uppercase', $out);
    }

    public function test_unknown_font_family_is_ignored(): void
    {
        $out = (new BlockCompiler)->compile([[
            'id' => 'h', 'type' => 'heading', 'props' => ['text' => 'Hi'], 'style' => ['fontFamily' => 'x;background:url(evil)'],
        ]]);

        $this->assertStringNotContainsString('evil', $out);
    }

    public function test_page_margins_are_clamped(): void
    {
        $this->assertSame('14mm 12mm 14mm 12mm', DocumentRenderer::margins(null));
        $this->assertSame('20mm 0mm 50mm 12.5mm', DocumentRenderer::margins(['top' => 20, 'right' => -4, 'bottom' => 99, 'left' => 12.5]));
        $this->assertSame('14mm 12mm 14mm 12mm', DocumentRenderer::margins(['top' => 'abc']));
    }
}
