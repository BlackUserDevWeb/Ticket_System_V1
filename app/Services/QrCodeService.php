<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Génération de QR codes (PNG/SVG) via simplesoftwareio/simple-qrcode.
 */
class QrCodeService
{
    public function png(string $content, int $size = 300): string
    {
        return QrCode::format('png')->size($size)->generate($content);
    }

    public function svg(string $content, int $size = 300): string
    {
        return QrCode::format('svg')->size($size)->generate($content);
    }

    /**
     * Data URI prête pour <img src="..."> ou DomPDF.
     */
    public function dataUri(string $content, int $size = 300): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($content, $size));
    }
}
