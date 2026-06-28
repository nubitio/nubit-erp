<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

final readonly class PleZipService
{
    public function __construct(
        private SalesLedgerExporter $salesExporter,
        private PurchaseLedgerExporter $purchaseExporter,
    ) {
    }

    public function createArchive(string $period): Response
    {
        $files = [
            $this->salesExporter->export($period),
            $this->purchaseExporter->export($period),
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'ple_zip_');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal del ZIP.');
        }

        $zipPath = $tmp . '.zip';
        if (!@rename($tmp, $zipPath)) {
            @unlink($tmp);
            throw new \RuntimeException('No se pudo preparar el archivo ZIP.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new \RuntimeException('No se pudo abrir el archivo ZIP.');
        }

        foreach ($files as $file) {
            $zip->addFromString($file['filename'], $file['content']);
        }

        $zip->close();

        $content = file_get_contents($zipPath);
        @unlink($zipPath);

        if ($content === false) {
            throw new \RuntimeException('No se pudo leer el archivo ZIP generado.');
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="PLE_%s.zip"', $period),
        );
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}