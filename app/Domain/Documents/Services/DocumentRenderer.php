<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Settings\Services\SettingsRepository;
use App\Support\Html\HtmlSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Full document pipeline:
 *   blocks/html → template source → safe render → sanitise → page wrapper → (PDF)
 *
 * Used for live designer previews, generated documents and PDF downloads,
 * so a preview is always identical to the final paperwork.
 */
final class DocumentRenderer
{
    public const PAPER_SIZES = ['letter', 'legal', 'a4', 'a3', 'tabloid'];

    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly BlockCompiler $compiler,
        private readonly HtmlSanitizer $sanitizer,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @param  array{editor?: string, blocks?: ?array, html?: ?string, css?: ?string, paper_size?: ?string, orientation?: ?string, watermark?: ?string}  $template
     */
    public function source(array $template): string
    {
        return ($template['editor'] ?? 'visual') === 'code'
            ? (string) ($template['html'] ?? '')
            : $this->compiler->compile($template['blocks'] ?? []);
    }

    /** Renders and sanitises the template body. Throws TemplateException on template errors. */
    public function body(array $template, array $context): string
    {
        $html = $this->renderer->render($this->source($template), $context, [
            'currency' => (string) $this->settings->get('general.currency', 'USD'),
            'date_format' => (string) $this->settings->get('general.date_format', 'M j, Y'),
            'timezone' => (string) $this->settings->get('general.timezone', 'UTC'),
        ]);

        return $this->sanitizer->sanitize($html);
    }

    /** Complete standalone HTML document (used for iframe previews and PDF input). */
    public function document(array $template, array $context, string $title = 'Document'): string
    {
        $body = $this->body($template, $context);
        $paper = in_array($template['paper_size'] ?? null, self::PAPER_SIZES, true) ? $template['paper_size'] : 'letter';
        $orientation = ($template['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $css = $this->sanitizer->sanitizeCss((string) ($template['css'] ?? ''));
        $watermark = trim((string) ($template['watermark'] ?? ''));
        if ($watermark !== '') {
            $watermark = $this->renderer->render(str_replace(['{%', '%}'], '', $watermark), $context);
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</title>'
            .'<style>@page{size:'.$paper.' '.$orientation.';margin:14mm 12mm}'.self::baseCss().'</style>'
            .($css !== '' ? '<style>'.$css.'</style>' : '')
            .'</head><body>'
            .($watermark !== '' ? '<div class="st-watermark">'.$watermark.'</div>' : '')
            .'<main class="st-document">'.$body.'</main></body></html>';
    }

    public function documentFor(DocumentTemplate $template, array $context, string $title = 'Document'): string
    {
        return $this->document($template->only(['editor', 'blocks', 'html', 'css', 'paper_size', 'orientation', 'watermark']), $context, $title);
    }

    /** @return string PDF bytes */
    public function pdf(string $html, string $paper = 'letter', string $orientation = 'portrait'): string
    {
        return Pdf::setOption([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'chroot' => [storage_path('app/public'), public_path()],
            'defaultFont' => 'DejaVu Sans',
        ])->loadHTML($html)
            ->setPaper(in_array($paper, self::PAPER_SIZES, true) ? $paper : 'letter', $orientation === 'landscape' ? 'landscape' : 'portrait')
            ->output();
    }

    public static function baseCss(): string
    {
        return <<<'CSS'
*{box-sizing:border-box}
body{font-family:"DejaVu Sans",Helvetica,Arial,sans-serif;font-size:10pt;color:#18181b;margin:0;line-height:1.4}
.st-document{position:relative}
h1{font-size:20pt;margin:0 0 6px}h2{font-size:14pt;margin:0 0 6px}h3{font-size:11pt;margin:12px 0 4px}h4{font-size:10pt;margin:8px 0 4px}
p{margin:0 0 6px}
.st-block{margin-bottom:10px}
.st-org{font-size:16pt}
.st-field{padding:4px 8px 4px 0;vertical-align:top}
.st-field-label{font-size:7.5pt;text-transform:uppercase;letter-spacing:.04em;color:#71717a}
.st-field-value{font-size:10pt}
.st-table{width:100%;border-collapse:collapse;margin-bottom:6px}
.st-table th{font-size:8pt;text-transform:uppercase;letter-spacing:.04em;color:#52525b;border-bottom:1.5px solid #18181b;padding:5px 6px;text-align:left}
.st-table td{border-bottom:1px solid #e4e4e7;padding:5px 6px;vertical-align:top}
.st-table tr.st-accessory td:first-child{padding-left:20px;color:#52525b}
.st-section{border-left:3px solid #6366f1;padding-left:6px}
.st-empty{color:#a1a1aa;font-style:italic}
.st-totals{margin-left:auto;border-collapse:collapse;min-width:40%}
.st-totals th{text-align:left;font-weight:normal;color:#52525b;padding:3px 12px 3px 0}
.st-totals td{text-align:right;padding:3px 0}
.st-total-strong th,.st-total-strong td{font-weight:bold;border-top:1.5px solid #18181b;padding-top:6px}
.st-signatures{width:100%}
.st-signature-box{display:inline-block;width:45%;margin:24px 4% 0 0;vertical-align:top}
.st-signature-line{border-bottom:1px solid #18181b;height:32px}
.st-signature-label{font-size:8pt;color:#52525b;margin-top:3px}
.st-watermark{position:fixed;top:40%;left:0;right:0;text-align:center;font-size:72pt;font-weight:bold;color:rgba(220,38,38,.12);transform:rotate(-30deg);z-index:-1}
hr.st-block{border:0;border-top:1px solid #d4d4d8}
.st-page-break{page-break-after:always}
CSS;
    }
}
