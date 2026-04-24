<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

/**
 * Gestiona la configuración de empresas en el servicio lycet (greenter).
 *
 * Cada tenant tiene un RUC asociado. Este servicio permite registrar,
 * actualizar y eliminar la configuración de la empresa en lycet para
 * que pueda firmar y enviar comprobantes a SUNAT.
 */
interface LycetCompanyServiceInterface
{
    /**
     * Registra o actualiza la configuración de una empresa en lycet.
     *
     * @param string $ruc         RUC de la empresa (11 dígitos)
     * @param string $solUser     Usuario SOL (RUC + usuario, ej: 20000000001MODDATOS)
     * @param string $solPass     Contraseña SOL
     * @param string $certPem     Certificado digital en formato PEM (contenido, no ruta)
     * @param array<string,string> $urls URLs opcionales: FE_URL, RE_URL, GUIA_URL, AUTH_URL, API_URL
     * @param string|null $clientId     Client ID para API SUNAT (OAuth2)
     * @param string|null $clientSecret Client Secret para API SUNAT (OAuth2)
     */
    public function upsertCompany(
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
        array $urls = [],
        ?string $clientId = null,
        ?string $clientSecret = null,
    ): void;

    /**
     * Sube o reemplaza el logo de una empresa en lycet.
     *
     * @param string $ruc       RUC de la empresa
     * @param string $imageData Contenido binario de la imagen (PNG/JPEG)
     */
    public function uploadCompanyLogo(string $ruc, string $imageData): void;

    /**
     * Elimina la configuración de una empresa en lycet.
     *
     * @param string $ruc RUC de la empresa
     */
    public function removeCompany(string $ruc): void;
}
