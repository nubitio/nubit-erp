<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

/** Resultado de generar un archivo de SIRE para un periodo. */
final readonly class SireExportResult
{
    /**
     * @param list<array{document: string, reason: string}> $excluded Documentos que no van en el archivo y por qué
     * @param list<string> $warnings Problemas a revisar antes de presentar
     */
    public function __construct(
        public string $txtName,
        public string $zipName,
        public string $content,
        public string $zip,
        public int $recordCount,
        public array $excluded,
        public array $warnings,
    ) {}
}
