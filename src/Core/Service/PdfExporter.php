<?php

namespace App\Core\Service;

use Pontedilana\PhpWeasyPrint\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfExporter
{
    public const int TIMEOUT = 30;

    /**
     * @param array<int|string, mixed> $options
     */
    public function export(string $content, string $filename, array $options = []): StreamedResponse
    {
        $pdf = new Pdf($_ENV['WEASYPRINT_BINARY']);
        $pdf->setOptions($options);
        $pdf->setTimeout(self::TIMEOUT);

        $response = new StreamedResponse(function () use ($pdf, $content): void {
            echo $pdf->getOutputFromHtml($content);
        });

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s.pdf"', $filename));

        return $response;
    }
}
