<?php

namespace Tests\Unit\Documents;

use App\Domain\Documents\Exceptions\TemplateException;
use App\Domain\Documents\Services\TemplateRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $r;

    protected function setUp(): void
    {
        parent::setUp();
        $this->r = new TemplateRenderer;
    }

    private function render(string $tpl, array $ctx = [], array $options = []): string
    {
        return $this->r->render($tpl, $ctx, $options);
    }

    public function test_plain_text_passes_through(): void
    {
        $this->assertSame('<p>Hello</p>', $this->render('<p>Hello</p>'));
        $this->assertSame('', $this->render(''));
    }

    public function test_variables_and_nested_properties(): void
    {
        $ctx = ['job' => ['name' => 'Gala', 'client' => ['name' => 'ACME']], 'items' => [['name' => 'Lustr']], 'key' => 'name'];
        $this->assertSame('Gala / ACME', $this->render('{{ job.name }} / {{ job.client.name }}', $ctx));
        $this->assertSame('Lustr', $this->render('{{ items[0][key] }}', $ctx));
        $this->assertSame('', $this->render('{{ missing.deep.value }}', $ctx));
        $this->assertSame('', $this->render('{{ job }}', $ctx), 'arrays render as empty string');
    }

    public function test_output_is_html_escaped_by_default(): void
    {
        $ctx = ['name' => '<script>alert("x")</script> & \'q\''];
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &#039;q&#039;', $this->render('{{ name }}', $ctx));
    }

    public function test_raw_filter_disables_escaping(): void
    {
        $this->assertSame('<b>Logo</b>', $this->render('{{ html | raw }}', ['html' => '<b>Logo</b>']));
    }

    public function test_escape_and_nl2br_filters(): void
    {
        $this->assertSame('&lt;i&gt;', $this->render('{{ v | escape }}', ['v' => '<i>']));
        $this->assertSame("a&lt;b&gt;<br />\nc", $this->render('{{ v | nl2br }}', ['v' => "a<b>\nc"]));
    }

    public function test_literals_and_booleans(): void
    {
        $this->assertSame('hi', $this->render("{{ 'hi' }}"));
        $this->assertSame('say &quot;x&quot;', $this->render('{{ "say \"x\"" }}'));
        $this->assertSame('true', $this->render('{{ true }}'));
        $this->assertSame('', $this->render('{{ false }}'));
        $this->assertSame('', $this->render('{{ null }}'));
        $this->assertSame('0.5', $this->render('{{ .5 }}'));
    }

    public function test_arithmetic_and_calculated_values(): void
    {
        $ctx = ['item' => ['price' => '12.50', 'qty' => 4]];
        $this->assertSame('50', $this->render('{{ item.price * item.qty }}', $ctx));
        $this->assertSame('$50.00', $this->render('{{ item.price * item.qty | currency }}', $ctx));
        $this->assertSame('7', $this->render('{{ 1 + 2 * 3 }}'));
        $this->assertSame('9', $this->render('{{ (1 + 2) * 3 }}'));
        $this->assertSame('1', $this->render('{{ 10 % 3 }}'));
        $this->assertSame('-4', $this->render('{{ -(2 + 2) }}'));
        $this->assertSame('2.5', $this->render('{{ 5 / 2 }}'));
        $this->assertSame('0', $this->render('{{ 5 / 0 }}'), 'division by zero is safe');
        $this->assertSame('0', $this->render('{{ 5 % 0 }}'));
        $this->assertSame('3', $this->render("{{ 'abc' + 3 }}"), 'non numeric strings are 0');
        $this->assertSame('ab1', $this->render("{{ 'a' ~ 'b' ~ 1 }}"));
    }

    public function test_comparisons_and_logic(): void
    {
        $ctx = ['stage' => 'quote', 'n' => '10', 'list' => ['a', 'b']];
        $this->assertSame('true', $this->render("{{ stage == 'quote' }}", $ctx));
        $this->assertSame('true', $this->render("{{ stage != 'draft' }}", $ctx));
        $this->assertSame('true', $this->render('{{ n == 10.0 }}', $ctx));
        $this->assertSame('true', $this->render('{{ n > 9 and n <= 10 }}', $ctx), 'numeric comparison, not string');
        $this->assertSame('true', $this->render('{{ n >= 10 && n < 11 }}', $ctx));
        $this->assertSame('', $this->render('{{ not (n > 9) }}', $ctx));
        $this->assertSame('true', $this->render('{{ !false }}', $ctx));
        $this->assertSame('fallback', $this->render("{{ missing or 'fallback' }}", $ctx));
        $this->assertSame('quote', $this->render("{{ stage || 'x' }}", $ctx));
        $this->assertSame('true', $this->render("{{ 'a' in list }}", $ctx));
        $this->assertSame('', $this->render("{{ 'z' in list }}", $ctx));
        $this->assertSame('true', $this->render("{{ 'uo' in stage }}", $ctx));
        $this->assertSame('true', $this->render("{{ 'b' > 'a' }}"));
    }

    public function test_conditionals(): void
    {
        $tpl = "{% if stage == 'quote' %}Q{% elseif stage == 'draft' %}D{% else %}O{% endif %}";
        $this->assertSame('Q', $this->render($tpl, ['stage' => 'quote']));
        $this->assertSame('D', $this->render($tpl, ['stage' => 'draft']));
        $this->assertSame('O', $this->render($tpl, ['stage' => 'closed']));
        $this->assertSame('', $this->render('{% if x %}yes{% endif %}', []));
        $this->assertSame('', $this->render("{% if '0' %}yes{% endif %}"), "'0' is falsy");
        $this->assertSame('E', $this->render('{% if a %}A{% elif b %}B{% else %}E{% endif %}', ['a' => [], 'b' => 0]));
        $this->assertSame('in', $this->render('{% if a %}{% if b %}in{% endif %}{% endif %}', ['a' => 1, 'b' => 1]));
    }

    public function test_for_loops_with_loop_variable_and_else(): void
    {
        $ctx = ['items' => [['name' => 'A'], ['name' => 'B'], ['name' => 'C']]];
        $this->assertSame(
            '1.A(first) 2.B 3.C(last) ',
            $this->render('{% for item in items %}{{ loop.index }}.{{ item.name }}{% if loop.first %}(first){% endif %}{% if loop.last %}(last){% endif %} {% endfor %}', $ctx),
        );
        $this->assertSame('0/3 1/3 2/3 ', $this->render('{% for i in items %}{{ loop.index0 }}/{{ loop.length }} {% endfor %}', $ctx));
        $this->assertSame('None', $this->render('{% for i in items %}x{% else %}None{% endfor %}', ['items' => []]));
        $this->assertSame('None', $this->render('{% for i in items %}x{% else %}None{% endfor %}', []));
        $this->assertSame('a=1;b=2;', $this->render('{% for k, v in map %}{{ k }}={{ v }};{% endfor %}', ['map' => ['a' => 1, 'b' => 2]]));
        $this->assertSame('12', $this->render('{% for n in [1, 2] %}{{ n }}{% endfor %}'));
    }

    public function test_loop_variables_restored_after_loop(): void
    {
        $out = $this->render('{% for item in items %}{{ item }}{% endfor %}|{{ item }}|{{ loop.index }}', ['items' => [1, 2], 'item' => 'outer']);
        $this->assertSame('12|outer|', $out);
    }

    public function test_nested_loops(): void
    {
        $ctx = ['sections' => [['name' => 'L', 'items' => ['a', 'b']], ['name' => 'S', 'items' => ['c']]]];
        $this->assertSame('L:a,b,;S:c,;', $this->render('{% for s in sections %}{{ s.name }}:{% for i in s.items %}{{ i }},{% endfor %};{% endfor %}', $ctx));
    }

    public function test_set_and_functions(): void
    {
        $ctx = ['items' => [['total' => 10, 'qty' => 2], ['total' => '5.5', 'qty' => 3]]];
        $this->assertSame('15.5', $this->render("{% set total = sum(items, 'total') %}{{ total }}", $ctx));
        $this->assertSame('2', $this->render('{{ count(items) }}', $ctx));
        $this->assertSame('0', $this->render('{{ count(nothing) }}', $ctx));
        $this->assertSame('2.5', $this->render("{{ avg(items, 'qty') }}", $ctx));
        $this->assertSame('3.14', $this->render('{{ round(3.14159, 2) }}'));
        $this->assertSame('4', $this->render('{{ ceil(3.1) }}'));
        $this->assertSame('3', $this->render('{{ floor(3.9) }}'));
        $this->assertSame('1', $this->render('{{ min(3, 1, 2) }}'));
        $this->assertSame('9', $this->render('{{ max([3, 9, 2]) }}'));
        $this->assertSame('0', $this->render('{{ max([]) }}'));
        $this->assertSame('ab3', $this->render("{{ concat('a', 'b', 3) }}"));
        $this->assertSame('6', $this->render('{{ sum([1, 2, 3]) }}'));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $this->render("{{ now('Y') }}"));
    }

    public function test_set_inside_loop_accumulates(): void
    {
        $this->assertSame('6', $this->render('{% set t = 0 %}{% for n in [1,2,3] %}{% set t = t + n %}{% endfor %}{{ t }}'));
    }

    public function test_filters(): void
    {
        $this->assertSame('GALA', $this->render('{{ v | upper }}', ['v' => 'gala']));
        $this->assertSame('gala', $this->render('{{ v | lower }}', ['v' => 'GALA']));
        $this->assertSame('Big Show', $this->render('{{ v | title }}', ['v' => 'big show']));
        $this->assertSame('n/a', $this->render("{{ v | default('n/a') }}", ['v' => '']));
        $this->assertSame('x', $this->render("{{ v | default('n/a') }}", ['v' => 'x']));
        $this->assertSame('1,234,568', $this->render('{{ v | number }}', ['v' => 1234567.89]));
        $this->assertSame('1,234,567.89', $this->render('{{ v | number(2) }}', ['v' => '1234567.891']));
        $this->assertSame('3.1', $this->render('{{ v | round(1) }}', ['v' => 3.14]));
        $this->assertSame('€1,234.50', $this->render("{{ v | currency('€') }}", ['v' => 1234.5]));
        $this->assertSame('-$5.00', $this->render('{{ v | currency }}', ['v' => -5]));
        $this->assertSame('£7.00', $this->render('{{ v | currency }}', ['v' => 7], ['currency' => '£']));
        $this->assertSame('12.5%', $this->render('{{ v | percent(1) }}', ['v' => 0.125]));
        $this->assertSame('12%', $this->render('{{ v | percent(0, true) }}', ['v' => 12]));
        $this->assertSame('a; b', $this->render("{{ v | join('; ') }}", ['v' => ['a', 'b']]));
        $this->assertSame('3', $this->render('{{ v | length }}', ['v' => [1, 2, 3]]));
        $this->assertSame('4', $this->render('{{ v | length }}', ['v' => 'café']));
        $this->assertSame('5', $this->render('{{ v | abs }}', ['v' => -5]));
        $this->assertSame('Hello…', $this->render('{{ v | truncate(5) }}', ['v' => 'Hello world']));
        $this->assertSame('Hi', $this->render('{{ v | truncate(5) }}', ['v' => 'Hi']));
        $this->assertSame('Yes', $this->render('{{ v | yesno }}', ['v' => 1]));
        $this->assertSame('Off', $this->render("{{ v | yesno('On', 'Off') }}", ['v' => 0]));
        $this->assertSame('ABC', $this->render('{{ v | lower | upper }}', ['v' => 'aBc']), 'filters chain');
    }

    public function test_filter_output_still_escaped(): void
    {
        $this->assertSame('&lt;B&gt;', $this->render('{{ v | upper }}', ['v' => '<b>']));
        $this->assertSame('&lt;x&gt;', $this->render("{{ v | default('<x>') }}", ['v' => null]));
    }

    public function test_date_filter(): void
    {
        $ctx = ['d' => '2026-03-05 14:30:00'];
        $this->assertSame('Mar 5, 2026', $this->render('{{ d | date }}', $ctx));
        $this->assertSame('2026-03-05', $this->render("{{ d | date('iso') }}", $ctx));
        $this->assertSame('Thursday, March 5, 2026', $this->render("{{ d | date('long') }}", $ctx));
        $this->assertSame('2:30 PM', $this->render("{{ d | date('time') }}", $ctx));
        $this->assertSame('Mar 5, 2026 2:30 PM', $this->render("{{ d | date('datetime') }}", $ctx));
        $this->assertSame('05/03/2026', $this->render("{{ d | date('d/m/Y') }}", $ctx));
        $this->assertSame('05.03.2026', $this->render('{{ d | date }}', $ctx, ['date_format' => 'd.m.Y']));
        $this->assertSame('1970-01-01', $this->render("{{ d | date('iso') }}", ['d' => 0]));
        $this->assertSame('', $this->render('{{ d | date }}', ['d' => null]));
        $this->assertSame('Mar 5, 2026', $this->render('{{ d | date }}', ['d' => new \DateTimeImmutable('2026-03-05 12:00', new \DateTimeZone('UTC'))]));
    }

    public function test_comments_are_removed(): void
    {
        $this->assertSame('ab', $this->render('a{# secret {{ x }} #}b'));
    }

    public function test_compile_produces_tree_and_lint(): void
    {
        $tree = $this->r->compile('Hi {{ name }}');
        $this->assertSame(['text', 'Hi '], $tree[0]);
        $this->assertSame('out', $tree[1][0]);

        $this->assertNull($this->r->lint('{% if a %}{{ b | upper }}{% endif %}'));
        $this->assertSame('Line 1: Unclosed {% if %} block', $this->r->lint('{% if a %}'));
    }

    public static function invalidTemplates(): array
    {
        return [
            'unclosed if' => ['{% if a %}x', 'Unclosed {% if %} block'],
            'unclosed for' => ['{% for a in b %}x', 'Unclosed {% for %} block'],
            'stray endif' => ['{% endif %}', 'Unexpected {% endif %}'],
            'stray endfor' => ['{% endfor %}', 'Unexpected {% endfor %}'],
            'stray else' => ['{% else %}', 'Unexpected {% else %}'],
            'double else' => ['{% if a %}{% else %}{% else %}{% endif %}', 'Unexpected {% else %}'],
            'elseif after else' => ['{% if a %}{% else %}{% elseif b %}{% endif %}', 'Unexpected {% elseif %}'],
            'elseif outside if' => ['{% elseif b %}', 'Unexpected {% elseif %}'],
            'mismatched' => ['{% if a %}{% endfor %}', 'Unexpected {% endfor %}'],
            'bad for' => ['{% for in items %}{% endfor %}', 'Invalid for loop'],
            'bad set' => ['{% set = 3 %}', 'Invalid set'],
            'unknown tag' => ['{% include "x" %}', 'Unknown tag "include"'],
            'php tag' => ['{% php %}echo 1;{% endphp %}', 'Unknown tag "php"'],
            'unterminated string' => ["{{ 'abc }}", 'Unterminated string'],
            'bad character' => ['{{ a; b }}', 'Unexpected character ";"'],
            'dollar' => ['{{ $x }}', 'Unexpected character "$"'],
            'backtick' => ['{{ `ls` }}', 'Unexpected character "`"'],
            'trailing tokens' => ['{{ a b }}', 'Unexpected "b"'],
            'empty expression' => ['{{ }}', 'Unexpected end of expression'],
            'missing paren' => ['{{ (a + b }}', 'Expected ")"'],
            'filter without name' => ['{{ a | }}', 'Expected filter name'],
            'property after dot' => ['{{ a.+ }}', 'Expected property name'],
        ];
    }

    #[DataProvider('invalidTemplates')]
    public function test_invalid_templates_throw_template_exception(string $template, string $expected): void
    {
        try {
            $this->render($template, ['a' => 1, 'b' => [], 'items' => []]);
            $this->fail('Expected TemplateException');
        } catch (TemplateException $e) {
            $this->assertStringContainsString($expected, $e->getMessage());
        }
    }

    public function test_exception_reports_line_number(): void
    {
        try {
            $this->render("line one\nline two\n{{ a ; }}");
            $this->fail('Expected TemplateException');
        } catch (TemplateException $e) {
            $this->assertSame(3, $e->templateLine);
            $this->assertStringStartsWith('Line 3: ', $e->getMessage());
        }
    }

    public function test_unknown_filter_and_function_throw_at_render(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Unknown filter "shell"');
        $this->render('{{ a | shell }}', ['a' => 'ls']);
    }

    public function test_unknown_function_throws(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Unknown function "system"');
        $this->render("{{ system('id') }}");
    }

    public static function dangerousFunctions(): array
    {
        return [['exec'], ['shell_exec'], ['phpinfo'], ['eval'], ['file_get_contents'], ['passthru'], ['call_user_func'], ['assert']];
    }

    #[DataProvider('dangerousFunctions')]
    public function test_no_arbitrary_php_functions(string $fn): void
    {
        $this->expectException(TemplateException::class);
        $this->render('{{ '.$fn."('echo hacked') }}");
    }

    public function test_no_method_calls_on_objects(): void
    {
        $obj = new class
        {
            public string $secret = 'hidden';

            public function boom(): string
            {
                return 'called';
            }
        };
        // Property/method access on objects resolves to nothing.
        $this->assertSame('', $this->render('{{ o.secret }}{{ o.boom }}', ['o' => $obj]));
        $this->expectException(TemplateException::class);
        $this->render('{{ o.boom() }}', ['o' => $obj]);
    }

    public function test_php_code_in_text_is_not_executed(): void
    {
        $this->assertSame('<?php echo 1; ?>', $this->render('<?php echo 1; ?>'));
    }

    public function test_iteration_cap(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Too many loop iterations.');
        $this->render('{% for a in big %}{% for b in big %}{% endfor %}{% endfor %}', ['big' => range(1, 300)]);
    }
}
