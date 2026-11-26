<?php

namespace Tests\Unit\Documents;

use App\Support\Html\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = new HtmlSanitizer;
    }

    public function test_empty_input(): void
    {
        $this->assertSame('', $this->s->sanitize(''));
        $this->assertSame('', $this->s->sanitize("   \n"));
    }

    public function test_keeps_safe_formatting(): void
    {
        $html = '<h2 class="title">Quote</h2><p style="color:#333">Hello <strong>bold</strong> <em>it</em> <u>u</u></p>'
            .'<table border="1"><thead><tr><th colspan="2">H</th></tr></thead><tbody><tr><td>a</td><td>b</td></tr></tbody></table>'
            .'<ul><li>one</li></ul><hr><br>';
        $out = $this->s->sanitize($html);
        $this->assertStringContainsString('<h2 class="title">Quote</h2>', $out);
        $this->assertStringContainsString('<p style="color:#333">Hello <strong>bold</strong> <em>it</em> <u>u</u></p>', $out);
        $this->assertStringContainsString('<th colspan="2">H</th>', $out);
        $this->assertStringContainsString('<table border="1">', $out);
        $this->assertStringContainsString('<li>one</li>', $out);
        $this->assertStringContainsString('<hr>', $out);
    }

    public function test_preserves_template_syntax_and_utf8(): void
    {
        $out = $this->s->sanitize('<p>{{ job.name }} — {% if a %}Žluťoučký{% endif %}</p>');
        $this->assertSame('<p>{{ job.name }} — {% if a %}Žluťoučký{% endif %}</p>', $out);
    }

    public static function droppedElements(): array
    {
        return [
            'script' => ['<p>a</p><script>alert(1)</script>', 'alert'],
            'iframe' => ['<iframe src="https://evil.test">x</iframe>', 'iframe'],
            'object' => ['<object data="x.swf"></object>', 'object'],
            'embed' => ['<embed src="x">', 'embed'],
            'form' => ['<form action="/steal"><input name="p"><button>Go</button></form>', 'steal'],
            'link' => ['<link rel="stylesheet" href="https://evil.test/x.css">', 'evil'],
            'meta' => ['<meta http-equiv="refresh" content="0;url=https://evil.test">', 'refresh'],
            'base' => ['<base href="https://evil.test/">', 'evil'],
            'noscript' => ['<noscript><p>x</p></noscript>', 'noscript'],
            'template' => ['<template><script>1</script></template>', 'template'],
            'textarea' => ['<textarea>secret</textarea>', 'secret'],
        ];
    }

    #[DataProvider('droppedElements')]
    public function test_dangerous_elements_removed_with_content(string $html, string $needle): void
    {
        $out = $this->s->sanitize($html);
        $this->assertStringNotContainsStringIgnoringCase($needle, $out);
        $this->assertStringNotContainsStringIgnoringCase('<script', $out);
    }

    public function test_nested_script_removed(): void
    {
        $this->assertSame('<div><p>ok</p></div>', $this->s->sanitize('<div><p>ok<script>bad()</script></p></div>'));
    }

    public function test_unknown_tags_unwrapped_keeping_children(): void
    {
        $this->assertSame('<p>Hello <b>world</b></p>', $this->s->sanitize('<p><custom-el>Hello <font color="red"><b>world</b></font></custom-el></p>'));
        $this->assertSame('a', $this->s->sanitize('<blink><marquee>a<script>x</script></marquee></blink>'));
    }

    public function test_event_handlers_removed(): void
    {
        $out = $this->s->sanitize('<img src="https://x.test/a.png" onerror="alert(1)" OnLoad="x()"><div onclick="steal()" onmouseover="x">t</div>');
        $this->assertStringNotContainsStringIgnoringCase('onerror', $out);
        $this->assertStringNotContainsStringIgnoringCase('onload', $out);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $out);
        $this->assertStringNotContainsStringIgnoringCase('onmouseover', $out);
        $this->assertStringContainsString('src="https://x.test/a.png"', $out);
        $this->assertStringContainsString('<div>t</div>', $out);
    }

    public function test_non_allowlisted_attributes_removed_data_attributes_kept(): void
    {
        $out = $this->s->sanitize('<div data-block="b1" data-custom="x" formaction="y" srcdoc="z" xlink:href="javascript:1">t</div>');
        $this->assertStringContainsString('data-block="b1"', $out);
        $this->assertStringContainsString('data-custom="x"', $out);
        $this->assertStringNotContainsString('formaction', $out);
        $this->assertStringNotContainsString('srcdoc', $out);
        $this->assertStringNotContainsString('javascript', $out);
    }

    public static function dangerousUrls(): array
    {
        return [
            ['javascript:alert(1)'],
            ['JaVaScRiPt:alert(1)'],
            [' javascript:alert(1)'],
            ["java\tscript:alert(1)"],
            ["java\nscript:alert(1)"],
            ['vbscript:msgbox(1)'],
            ['data:text/html;base64,PHNjcmlwdD4='],
            ['file:///etc/passwd'],
        ];
    }

    #[DataProvider('dangerousUrls')]
    public function test_dangerous_hrefs_removed(string $url): void
    {
        $out = $this->s->sanitize('<a href="'.htmlspecialchars($url).'">x</a>');
        $this->assertSame('<a>x</a>', $out);
    }

    public function test_entity_encoded_javascript_url_removed(): void
    {
        $this->assertSame('<a>x</a>', $this->s->sanitize('<a href="&#106;avascript:alert(1)">x</a>'));
        $this->assertSame('<a>x</a>', $this->s->sanitize('<a href="javascript&colon;alert(1)">x</a>'));
    }

    public function test_safe_urls_kept(): void
    {
        foreach (['https://example.test/a?b=1', 'http://x.test', 'mailto:a@b.test', 'tel:+123', '#top', '/relative/path'] as $url) {
            $this->assertStringContainsString('href="'.$url.'"', $this->s->sanitize('<a href="'.$url.'">x</a>'), $url);
        }
    }

    public function test_image_data_uri_only_for_images(): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgo=';
        $this->assertStringContainsString('src="'.$png.'"', $this->s->sanitize('<img src="'.$png.'">'));
        $this->assertSame('<img>', $this->s->sanitize('<img src="data:image/svg+xml,<svg onload=alert(1)>">'));
        $this->assertSame('<a>x</a>', $this->s->sanitize('<a href="'.$png.'">x</a>'));
        $this->assertSame('<img>', $this->s->sanitize('<img src="javascript:alert(1)">'));
    }

    public function test_target_blank_gets_noopener(): void
    {
        $out = $this->s->sanitize('<a href="https://x.test" target="_blank" rel="opener">x</a>');
        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
        $this->assertStringNotContainsString('rel="opener"', $out);
    }

    public function test_comments_removed(): void
    {
        $this->assertSame('<p>a</p>', $this->s->sanitize('<p>a<!-- secret --></p>'));
        $this->assertSame('<p>b</p>', $this->s->sanitize('<!--[if IE]><script>x</script><![endif]--><p>b</p>'));
    }

    public function test_style_attribute_sanitized(): void
    {
        $out = $this->s->sanitize('<div style="color:red;background:url(javascript:alert(1));width:expression(alert(1));-moz-binding:url(x)">t</div>');
        $this->assertStringNotContainsStringIgnoringCase('javascript', $out);
        $this->assertStringNotContainsStringIgnoringCase('expression', $out);
        $this->assertStringNotContainsStringIgnoringCase('-moz-binding', $out);
        $this->assertStringContainsString('color:red', $out);
    }

    public function test_style_element_sanitized_but_kept(): void
    {
        $out = $this->s->sanitize('<style>@import url(https://evil.test/x.css); .a{color:red;behavior:url(x.htc)}</style><p class="a">x</p>');
        $this->assertStringContainsString('<style>', $out);
        $this->assertStringContainsString('.a{color:red;', $out);
        $this->assertStringNotContainsString('@import', $out);
        $this->assertStringNotContainsString('behavior', $out);
    }

    public function test_svg_kept(): void
    {
        $out = $this->s->sanitize('<svg viewBox="0 0 10 10" onload="x()"><rect x="1" y="1" width="5" height="5" fill="#000"></rect></svg>');
        $this->assertStringContainsString('<rect x="1" y="1" width="5" height="5" fill="#000">', $out);
        $this->assertStringNotContainsString('onload', $out);
    }

    public function test_sanitize_css(): void
    {
        $this->assertSame('', $this->s->sanitizeCss(''));
        $this->assertSame('.a{color:red}', $this->s->sanitizeCss('.a{color:red}'));
        $this->assertSame(' .b{}', $this->s->sanitizeCss('@import "x.css"; .b{}'));
        $this->assertSame('width:(alert(1))', $this->s->sanitizeCss('width:expression (alert(1))'));
        $this->assertSame('background:url(alert(1))', $this->s->sanitizeCss('background:url(JavaScript :alert(1))'));
        $this->assertSame('background:url(x)', $this->s->sanitizeCss('background:url(vbscript:x)'));
        $this->assertSame(':url(x);url(y)', $this->s->sanitizeCss('-moz-binding:url(x);behavior:url(y)'));
        $this->assertSame('>', $this->s->sanitizeCss('</STYLE>'));
    }
}
