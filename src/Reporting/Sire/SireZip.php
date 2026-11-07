<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use RuntimeException;
use ZipArchive;

/** Empaqueta el TXT de SIRE en el ZIP que exige SUNAT (un solo archivo con su nombre base). */
final class SireZip
{
    public static function create(string $entryName, string $content): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sire_');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal del ZIP.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo abrir el archivo ZIP.');
            }
            $zip->addFromString($entryName, $content);
            $zip->close();

            $bytes = file_get_contents($tmp);
            if ($bytes === false) {
                throw new RuntimeException('No se pudo leer el archivo ZIP generado.');
            }

            return $bytes;
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
