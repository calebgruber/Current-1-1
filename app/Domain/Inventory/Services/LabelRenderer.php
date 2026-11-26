<?php

namespace App\Domain\Inventory\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Throwable;

/**
 * Renders Code 128 barcodes and QR codes as inline SVG for printable label
 * sheets. Output comes from trusted libraries that only emit geometry
 * (rects/paths) derived from the encoded data, so it is safe for v-html.
 */
class LabelRenderer
{
    public const MAX_CODE_LENGTH = 80;

    public function barcode(string $code, float $widthFactor = 1.6, int $height = 48): ?string
    {
        $code = $this->clean($code);
        if ($code === '') {
            return null;
        }

        try {
            $svg = (new BarcodeGeneratorSVG)->getBarcode($code, BarcodeGenerator::TYPE_CODE_128, $widthFactor, $height);
        } catch (Throwable) {
            return null;
        }

        return $this->stripProlog($svg);
    }

    public function qr(string $text, int $size = 120): ?string
    {
        $text = $this->clean($text, 512);
        if ($text === '') {
            return null;
        }

        try {
            $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));
            $svg = $writer->writeString($text);
        } catch (Throwable) {
            return null;
        }

        return $this->stripProlog($svg);
    }

    /**
     * @return array{code: string, title: string, subtitle: ?string, barcode_svg: ?string, qr_svg: ?string}
     */
    public function label(string $code, string $title, ?string $subtitle = null): array
    {
        return [
            'code' => $this->clean($code),
            'title' => $title,
            'subtitle' => $subtitle,
            'barcode_svg' => $this->barcode($code),
            'qr_svg' => $this->qr($code),
        ];
    }

    private function clean(string $value, int $max = self::MAX_CODE_LENGTH): string
    {
        // Code 128 supports printable ASCII only; strip anything else.
        $value = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';

        return mb_substr(trim($value), 0, $max);
    }

    private function stripProlog(string $svg): string
    {
        $svg = preg_replace('/<\?xml.*?\?>/s', '', $svg) ?? $svg;
        $svg = preg_replace('/<!DOCTYPE.*?>/s', '', $svg) ?? $svg;
        $svg = preg_replace('/<desc>.*?<\/desc>/s', '', $svg) ?? $svg;

        return trim($svg);
    }
}
