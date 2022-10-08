<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfExporter
{
    /**
     * @throws \Exception
     */
    public function export(string $content, $filename): StreamedResponse
    {
        $descriptorSpec = array(
            0 => array('pipe', 'r'), // stdin
            1 => array('pipe', 'w'), // stdout
            2 => array('pipe', 'w'), // stderr
        );
        $process = proc_open('wkhtmltopdf -q - -', $descriptorSpec, $pipes);
        // Send the HTML on stdin
        fwrite($pipes[0], $content);
        fclose($pipes[0]);
        // Read the outputs
        $pdf = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        // Close the process
        fclose($pipes[1]);
        proc_close($process);
        // Output the results
        if ($errors) {
            throw new \Exception('PDF generation failed: ' . $errors);
        } else {
            $response = new StreamedResponse(function () use ($pdf) {
                echo $pdf;
            });
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Length', strlen($pdf));
            $response->headers->set('Content-Disposition', sprintf('inline; filename="%s.pdf"', $filename));

            return $response;
        }
    }
}