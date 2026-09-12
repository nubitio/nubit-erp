<?php

declare(strict_types=1);

namespace App\Settings\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final readonly class TenantCertificateRequest
{
    public function __construct(
        private TenantCertificatePemDecoder $decoder,
    ) {}

    /** @return array<string, mixed> */
    public function payload(Request $request): array
    {
        $payload = $request->request->all();
        if ($payload !== []) {
            return $payload;
        }

        try {
            $decoded = $request->toArray();
            $keys = array_map(static fn(string|int $key): string => (string) $key, array_keys($decoded));
            return array_combine($keys, array_values($decoded));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $payload */
    public function certificatePem(Request $request, array $payload): string
    {
        if ($request->files->get('certificate') instanceof UploadedFile) {
            /** @var UploadedFile $uploaded */
            $uploaded = $request->files->get('certificate');
            $contents = file_get_contents($uploaded->getPathname());
            if (!is_string($contents) || $contents === '') {
                throw new \InvalidArgumentException('El archivo de certificado esta vacio.');
            }

            return $this->decoder->decode($contents, $this->optionalPassword($payload));
        }

        foreach (['certificatePem', 'certificateBase64'] as $key) {
            /** @var mixed $value */
            $value = $payload[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $contents = $key === 'certificateBase64' ? base64_decode($value, strict: true) : trim($value);
            if (!is_string($contents) || $contents === '') {
                throw new \InvalidArgumentException('El certificado base64 no es valido.');
            }

            return $this->decoder->decode($contents, $this->optionalPassword($payload));
        }

        throw new \InvalidArgumentException('Debe adjuntar el certificado SUNAT.');
    }

    /** @param array<string, mixed> $payload */
    private function optionalPassword(array $payload): string
    {
        /** @var mixed $password */
        $password = $payload['certificatePassword'] ?? $payload['pfxPassword'] ?? '';
        return is_string($password) ? $password : '';
    }
}
