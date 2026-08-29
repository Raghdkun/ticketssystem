<?php

namespace App\Support;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Renders ticket QR codes as SVG on the server.
 *
 * Server-side keeps the client bundle free of a QR library and means the code
 * is present in the initial HTML, so it never reflows in after paint.
 */
final class QrCode
{
    public static function svgDataUri(string $data): string
    {
        $result = (new SvgWriter)->write(
            new EndroidQrCode(
                data: $data,
                // Medium tolerates a phone camera at an angle in low light,
                // which is the actual condition at a venue door.
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 320,
                margin: 8,
                foregroundColor: new Color(10, 10, 10),
                backgroundColor: new Color(255, 255, 255),
            )
        );

        return 'data:image/svg+xml;base64,'.base64_encode($result->getString());
    }

    /**
     * Raw PNG bytes, for a QR an owner downloads and puts on a poster.
     *
     * High error correction rather than medium: a printed code gets folded,
     * taped over and photographed off a wall, and unlike the door scanner
     * there is nobody there to hold it straight and try again.
     */
    public static function png(string $data, int $size = 1024): string
    {
        return (new PngWriter)->write(
            new EndroidQrCode(
                data: $data,
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: $size,
                margin: (int) round($size * 0.04),
                foregroundColor: new Color(10, 10, 10),
                backgroundColor: new Color(255, 255, 255),
            )
        )->getString();
    }
}
