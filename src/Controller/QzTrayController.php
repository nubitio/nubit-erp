<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class QzTrayController
{
    #[Route('/api/qz/certificate', name: 'qz_certificate', methods: ['GET'])]
    public function certificate(): Response
    {
        $path = $_ENV['QZ_CERT_PATH'] ?? null;
        if (!$path || !is_file($path)) {
            return new Response('QZ certificate not configured.', Response::HTTP_NOT_FOUND);
        }

        return new Response(
            (string) file_get_contents($path),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain']
        );
    }

    #[Route('/api/qz/sign', name: 'qz_sign', methods: ['POST'])]
    public function sign(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $data = is_array($payload)
            ? ($payload['data'] ?? $payload['toSign'] ?? null)
            : null;

        if (null === $data || '' === $data) {
            $data = $request->getContent();
        }

        if ('' === $data) {
            return new Response('Missing data to sign.', Response::HTTP_BAD_REQUEST);
        }

        $keyPath = $_ENV['QZ_PRIVATE_KEY_PATH'] ?? null;
        if (!$keyPath || !is_file($keyPath)) {
            return new Response('QZ private key not configured.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $passphrase = $_ENV['QZ_PRIVATE_KEY_PASSPHRASE'] ?? null;
        $privateKey = openssl_pkey_get_private(
            (string) file_get_contents($keyPath),
            $passphrase ?? ''
        );

        if (false === $privateKey) {
            return new Response('Invalid private key.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $signature = '';
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);

        return new Response(
            base64_encode($signature),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain']
        );
    }
}
