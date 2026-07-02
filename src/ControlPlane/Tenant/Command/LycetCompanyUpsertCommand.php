<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\ControlPlane\Tenant\Port\ElectronicBillingCompanyPortInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Registra o actualiza la configuración de una empresa en lycet (greenter).
 *
 * Uso:
 *   php bin/console app:lycet:company:upsert <ruc> --sol-user=... --sol-pass=... --cert-file=...
 *   php bin/console app:lycet:company:upsert <ruc> --sol-user=... --sol-pass=... --cert-file=... --logo-file=...
 *   php bin/console app:lycet:company:remove <ruc>
 */
#[AsCommand(
    name: 'app:lycet:company:upsert',
    description: 'Registra o actualiza la configuración de una empresa en lycet.',
)]
class LycetCompanyUpsertCommand extends Command
{
    public function __construct(
        private readonly ElectronicBillingCompanyPortInterface $electronicBillingCompany,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ruc', InputArgument::REQUIRED, 'RUC de la empresa (11 dígitos)')
            ->addOption(
                'sol-user',
                null,
                InputOption::VALUE_REQUIRED,
                'Usuario SOL (RUC + usuario, ej: 20000000001MODDATOS)',
            )
            ->addOption('sol-pass', null, InputOption::VALUE_REQUIRED, 'Contraseña SOL')
            ->addOption('cert-file', null, InputOption::VALUE_REQUIRED, 'Ruta al archivo .pem del certificado digital')
            ->addOption('logo-file', null, InputOption::VALUE_OPTIONAL, 'Ruta al archivo de logo (PNG/JPEG) — opcional')
            ->addOption('fe-url', null, InputOption::VALUE_OPTIONAL, 'URL del servicio FE (producción o OSE)')
            ->addOption('re-url', null, InputOption::VALUE_OPTIONAL, 'URL del servicio RE')
            ->addOption('guia-url', null, InputOption::VALUE_OPTIONAL, 'URL del servicio de guías')
            ->addOption('auth-url', null, InputOption::VALUE_OPTIONAL, 'URL del servicio de autenticación SUNAT')
            ->addOption('api-url', null, InputOption::VALUE_OPTIONAL, 'URL del API SUNAT')
            ->addOption('client-id', null, InputOption::VALUE_OPTIONAL, 'Client ID OAuth2 SUNAT')
            ->addOption('client-secret', null, InputOption::VALUE_OPTIONAL, 'Client Secret OAuth2 SUNAT');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ruc = (string) $input->getArgument('ruc');
        $solUser = (string) ($input->getOption('sol-user') ?? '');
        $solPass = (string) ($input->getOption('sol-pass') ?? '');
        $certFile = (string) ($input->getOption('cert-file') ?? '');

        if ($solUser === '' || $solPass === '' || $certFile === '') {
            $io->error('--sol-user, --sol-pass y --cert-file son obligatorios.');

            return Command::FAILURE;
        }

        if (!file_exists($certFile)) {
            $io->error(sprintf('Archivo de certificado no encontrado: %s', $certFile));

            return Command::FAILURE;
        }

        $certPem = (string) file_get_contents($certFile);

        $urls = [];
        foreach ([
            'fe-url' => 'FE_URL',
            're-url' => 'RE_URL',
            'guia-url' => 'GUIA_URL',
            'auth-url' => 'AUTH_URL',
            'api-url' => 'API_URL',
        ] as $opt => $key) {
            $val = $input->getOption($opt);
            if ($val !== null && $val !== '') {
                $urls[$key] = (string) $val;
            }
        }

        $clientId = $input->getOption('client-id') !== null ? (string) $input->getOption('client-id') : null;
        $clientSecret = $input->getOption('client-secret') !== null
            ? (string) $input->getOption('client-secret')
            : null;

        try {
            $io->text(sprintf('Registrando empresa RUC %s en lycet...', $ruc));
            $this->electronicBillingCompany->upsertCompany(
                $ruc,
                $solUser,
                $solPass,
                $certPem,
                $urls,
                $clientId,
                $clientSecret,
            );
            $io->success(sprintf('Empresa %s registrada/actualizada en lycet.', $ruc));

            $logoFile = $input->getOption('logo-file');
            if ($logoFile !== null && $logoFile !== '') {
                if (!file_exists((string) $logoFile)) {
                    $io->warning(sprintf('Archivo de logo no encontrado: %s — se omite.', $logoFile));
                } else {
                    $io->text('Subiendo logo...');
                    $this->electronicBillingCompany->uploadCompanyLogo(
                        $ruc,
                        (string) file_get_contents((string) $logoFile),
                    );
                    $io->success('Logo subido.');
                }
            }
        } catch (\Throwable $e) {
            $io->error('Error al registrar empresa en lycet: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
