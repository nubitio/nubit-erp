<?php

namespace App\Service;

use mikehaertl\wkhtmlto\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfExporter
{
    public function export(string $content, string $filename, array $options = []): StreamedResponse
    {
        $pdf = new Pdf($content);
        $pdf->setOptions($options);

        // Output the results
        $response = new StreamedResponse(function () use ($pdf) {
            echo $pdf->toString();
        });
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s.pdf"', $filename));

        return $response;
    }
}
