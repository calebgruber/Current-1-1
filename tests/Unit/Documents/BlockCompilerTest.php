<?php

namespace Tests\Unit\Documents;

use App\Domain\Documents\Exceptions\TemplateException;
use App\Domain\Documents\Services\BlockCompiler;
use App\Domain\Documents\Services\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class BlockCompilerTest extends TestCase
{
    private BlockCompiler $compiler;

    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new BlockCompiler;
        $this->renderer = new TemplateRenderer;
    }

    private function compileOne(array $block): string
    {
        return $this->compiler->compile([$block]);
    }

    public function test_empty_blocks_compile_to_empty_string(): void
    {
        $this->assertSame('', $this->compiler->compile([]));
    }

    public function test_blocks_joined_with_newline(): void
    {
        $out = $this->compiler->compile([['type' => 'divider', 'id' => 'a'], ['type' => 'divider', 'id' => 'b']]);
        $this->assertSame('<hr class="st-block st-divider" data-block="a">'."\n".'<hr class="st-block st-divider" data-block="b">', $out);
    }

    public function test_heading_level_clamped_and_text_escaped_with_variables(): void
    {
        $out = $this->compileOne(['type' => 'heading', 'id' => 'h', 'props' => ['level' => 9, 'text' => 'Quote <b> for {{ job.name }}']]);
        $this->assertSame('<h4 class="st-block st-heading" data-block="h">Quote &lt;b&gt; for {{  job.name  }}</h4>', $out);

        $this->assertStringStartsWith('<h1 ', $this->compileOne(['type' => 'heading', 'props' => ['level' => -3, 'text' => 'x']]));

        $html = $this->renderer->render($out, ['job' => ['name' => 'Gala <3']]);
        $this->assertStringContainsString('Quote &lt;b&gt; for Gala &lt;3', $html);
    }

    public function test_inline_text_braces_are_encoded(): void
    {
        $out = $this->compileOne(['type' => 'heading', 'props' => ['text' => 'a { b } {% if x %}']]);
        $this->assertStringContainsString('a &#123; b &#125; &#123;% if x %&#125;', $out);
        $this->assertStringNotContainsString('{%', $out);
    }

    public function test_template_tags_inside_field_expression_rejected(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Template tags are not allowed inside a field expression');
        $this->compileOne(['type' => 'fields', 'props' => ['fields' => [['label' => 'X', 'value' => 'a %} {% for']]]]);
    }

    public function test_invalid_expression_rejected(): void
    {
        $this->expectException(TemplateException::class);
        $this->compileOne(['type' => 'totals', 'props' => ['rows' => [['label' => 'Total', 'value' => 'a ; b']]]]);
    }

    public function test_style_allowlist(): void
    {
        $out = $this->compileOne(['type' => 'divider', 'style' => [
            'align' => 'center', 'fontSize' => '12', 'color' => '#ff0000', 'background' => 'red;position:fixed',
            'marginTop' => '8', 'bold' => true, 'italic' => false, 'fontFamily' => 'comic', 'evil' => 'x',
        ]]);
        $this->assertStringContainsString('style="text-align:center;font-size:12pt;color:#ff0000;margin-top:8px;font-weight:700"', $out);
        $this->assertStringNotContainsString('position', $out);
        $this->assertStringNotContainsString('evil', $out);

        $this->assertStringNotContainsString('style=', $this->compileOne(['type' => 'divider', 'style' => ['color' => 'javascript:x', 'align' => 'middle']]));
    }

    public function test_type_and_id_attributes_escaped(): void
    {
        $out = $this->compileOne(['type' => 'divider', 'id' => '"><script>']);
        $this->assertStringContainsString('data-block="&quot;&gt;&lt;script&gt;"', $out);
    }

    public function test_image_width_validated(): void
    {
        $ok = $this->compileOne(['type' => 'image', 'props' => ['src' => 'https://x.test/a.png', 'alt' => 'A"b', 'width' => '50%']]);
        $this->assertStringContainsString('src="https://x.test/a.png"', $ok);
        $this->assertStringContainsString('alt="A&quot;b"', $ok);
        $this->assertStringContainsString('width:50%', $ok);

        $bad = $this->compileOne(['type' => 'image', 'props' => ['src' => 'x', 'width' => '10px;background:url(x)']]);
        $this->assertStringContainsString('width:auto', $bad);
    }

    public function test_default_items_table_and_render(): void
    {
        $tpl = $this->compileOne(['type' => 'items_table', 'id' => 't']);
        $this->assertStringContainsString('{% for item in items %}', $tpl);
        $this->assertStringContainsString('{% else %}', $tpl);
        $this->assertStringContainsString('colspan="4"', $tpl);
        $this->assertStringContainsString('No items.', $tpl);

        $html = $this->renderer->render($tpl, ['items' => [['name' => 'Lustr', 'required' => 20, 'spare' => 5, 'pull' => 25]]]);
        $this->assertStringContainsString('<td style="text-align:left">Lustr</td>', $html);
        $this->assertStringContainsString('<td style="text-align:right">25</td>', $html);
        $this->assertStringContainsString('<th style="text-align:right">Spare</th>', $html);

        $this->assertStringContainsString('No items.', $this->renderer->render($tpl, ['items' => []]));
    }

    public function test_items_table_custom_columns_formats_and_options(): void
    {
        $tpl = $this->compileOne(['type' => 'items_table', 'props' => [
            'source' => 'lines',
            'columns' => [
                ['label' => 'Name', 'value' => 'item.name', 'width' => '40%', 'align' => 'bogus'],
                ['label' => 'Price', 'value' => 'item.price', 'format' => 'currency', 'align' => 'right', 'width' => 'evil'],
            ],
            'empty_text' => 'Nothing <here>',
            'indent_accessories' => true,
        ]]);
        $this->assertStringContainsString('{% for item in lines %}', $tpl);
        $this->assertStringContainsString('<th style="text-align:left;width:40%">Name</th>', $tpl);
        $this->assertStringContainsString('<th style="text-align:right">Price</th>', $tpl);
        $this->assertStringContainsString('{{ item.price | currency }}', $tpl);
        $this->assertStringContainsString('Nothing &lt;here&gt;', $tpl);
        $this->assertStringContainsString('{% if item.is_accessory %}st-accessory{% endif %}', $tpl);

        $html = $this->renderer->render($tpl, ['lines' => [['name' => 'Clamp', 'price' => 3, 'is_accessory' => true]]]);
        $this->assertStringContainsString('class="st-accessory"', $html);
        $this->assertStringContainsString('$3.00', $html);
    }

    public function test_items_table_grouped_by_section(): void
    {
        $tpl = $this->compileOne(['type' => 'items_table', 'props' => ['group_by_section' => true]]);
        $this->assertStringContainsString('{% for section in sections %}', $tpl);
        $this->assertStringContainsString('{% for item in section.items %}', $tpl);

        $html = $this->renderer->render($tpl, ['sections' => [
            ['name' => 'Lighting', 'items' => [['name' => 'Lustr']]],
            ['name' => 'Audio', 'items' => [['name' => 'K2']]],
        ]]);
        $this->assertStringContainsString('<h3 class="st-section">Lighting</h3>', $html);
        $this->assertStringContainsString('<h3 class="st-section">Audio</h3>', $html);
        $this->assertStringContainsString('K2', $html);
    }

    public function test_totals_default_to_currency(): void
    {
        $tpl = $this->compileOne(['type' => 'totals', 'props' => ['rows' => [
            ['label' => 'Subtotal', 'value' => 'totals.subtotal'],
            ['label' => 'Total', 'value' => 'totals.total', 'emphasis' => true],
            ['label' => 'Items', 'value' => 'totals.count', 'format' => 'number'],
        ]]]);
        $html = $this->renderer->render($tpl, ['totals' => ['subtotal' => 100, 'total' => 1234.5, 'count' => 2000]]);
        $this->assertStringContainsString('<tr><th>Subtotal</th><td>$100.00</td></tr>', $html);
        $this->assertStringContainsString('<tr class="st-total-strong"><th>Total</th><td>$1,234.50</td></tr>', $html);
        $this->assertStringContainsString('<td>2,000</td>', $html);
    }

    public function test_fields_layout(): void
    {
        $tpl = $this->compileOne(['type' => 'fields', 'props' => ['columns' => 3, 'fields' => [
            ['label' => 'Job', 'value' => 'job.number'],
            ['label' => 'Start', 'value' => 'job.starts_at', 'format' => 'date'],
            ['label' => 'Client', 'value' => 'client.name', 'format' => 'upper'],
            ['label' => 'Notes', 'value' => 'job.notes', 'format' => 'multiline'],
        ]]]);
        $this->assertSame(4, substr_count($tpl, 'width:33%'));
        $this->assertSame(2, substr_count($tpl, '<tr>'));
        $this->assertStringContainsString('<td></td><td></td></tr>', $tpl, 'last row padded');

        $html = $this->renderer->render($tpl, ['job' => ['number' => 'J-1', 'starts_at' => '2026-01-02', 'notes' => "a\nb"], 'client' => ['name' => 'acme']]);
        $this->assertStringContainsString('J-1', $html);
        $this->assertStringContainsString('Jan 2, 2026', $html);
        $this->assertStringContainsString('ACME', $html);
        $this->assertStringContainsString("a<br />\nb", $html);
    }

    public function test_conditional_block_wraps_children(): void
    {
        $tpl = $this->compileOne(['type' => 'conditional', 'props' => [
            'condition' => "job.stage == 'quote'",
            'blocks' => [['type' => 'heading', 'props' => ['text' => 'Draft quote']]],
        ]]);
        $this->assertStringStartsWith("{% if job.stage == 'quote' %}", $tpl);
        $this->assertStringEndsWith('{% endif %}', $tpl);
        $this->assertStringContainsString('Draft quote', $this->renderer->render($tpl, ['job' => ['stage' => 'quote']]));
        $this->assertStringNotContainsString('Draft quote', $this->renderer->render($tpl, ['job' => ['stage' => 'closed']]));
    }

    public function test_columns_spacer_signature_page_break_logo(): void
    {
        $cols = $this->compileOne(['type' => 'columns', 'props' => ['columns' => [
            ['width' => '30%', 'blocks' => [['type' => 'divider']]],
            ['width' => 'calc(1)', 'blocks' => []],
        ]]]);
        $this->assertStringContainsString('width:30%', $cols);
        $this->assertStringContainsString('width:auto', $cols);
        $this->assertStringContainsString('<hr ', $cols);

        $this->assertStringContainsString('style="height:0px"', $this->compileOne(['type' => 'spacer', 'props' => ['height' => -20]]));
        $this->assertStringContainsString('style="height:16px"', $this->compileOne(['type' => 'spacer']));

        $sig = $this->compileOne(['type' => 'signature', 'props' => ['lines' => [['label' => 'Client'], ['label' => 'Crew']], 'show_date' => true]]);
        $this->assertSame(2, substr_count($sig, 'st-signature-box'));
        $this->assertSame(2, substr_count($sig, 'Date: ____________'));
        $this->assertStringContainsString('>Signature<', $this->compileOne(['type' => 'signature']));

        $this->assertSame('<div class="st-page-break" style="page-break-after:always"></div>', $this->compileOne(['type' => 'page_break']));

        $logo = $this->compileOne(['type' => 'logo', 'props' => ['height' => 40]]);
        $this->assertStringContainsString('max-height:40px', $logo);
        $this->assertStringContainsString('<img src="https://x.test/l.png"', $this->renderer->render($logo, ['organization' => ['logo_url' => 'https://x.test/l.png', 'name' => 'O']]));
        $this->assertStringContainsString('<strong class="st-org">Org</strong>', $this->renderer->render($logo, ['organization' => ['name' => 'Org']]));
    }

    public function test_text_and_html_blocks_decode_entities_inside_tags(): void
    {
        $tpl = $this->compileOne(['type' => 'text', 'props' => ['html' => "<p>{% if n &gt; 1 %}many&gt;{% endif %}{{&nbsp;name }}</p>"]]);
        $this->assertStringContainsString('{% if n > 1 %}many&gt;{% endif %}', $tpl);
        $this->assertSame('<p>many&gt;Bob</p>', strip_tags($this->renderer->render($tpl, ['n' => 2, 'name' => 'Bob']), '<p>'));

        $this->assertSame('<b>{{ x }}</b>', $this->compileOne(['type' => 'html', 'props' => ['html' => '<b>{{ x }}</b>']]));
    }

    public function test_unknown_type_compiles_to_empty(): void
    {
        $this->assertSame('', $this->compileOne(['type' => 'php', 'props' => ['code' => 'system("id")']]));
        $this->assertSame('', $this->compileOne([]));
    }
}
