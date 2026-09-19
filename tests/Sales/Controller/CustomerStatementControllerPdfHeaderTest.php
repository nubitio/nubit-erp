<?php

declare(strict_types=1);

namespace App\Tests\Sales\Controller;

use App\Organization\Entity\Company;
use App\Sales\Report\CustomerStatement\CustomerStatement;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * The letterhead on `report/customer_statement.html.twig` (company name, RUC,
 * address, phone, and a conditional logo) is composed purely from the
 * `company`/`printLogo` values {@see \App\Sales\Controller\CustomerStatementController::pdf()}
 * passes into the render call. This renders the real template directly — this
 * codebase has no HTTP-kernel test harness to exercise the controller/WeasyPrint
 * pipeline end to end.
 */
final class CustomerStatementControllerPdfHeaderTest extends TestCase
{
    public function testLetterheadShowsLogoWhenPrintLogoEnabled(): void
    {
        $html = $this->render($this->makeCompany(), printLogo: true);

        self::assertStringContainsString('<img src="data:image/png;base64,FAKE"', $html);
        self::assertStringContainsString('Andina Retail SAC', $html);
        self::assertStringContainsString('RUC: 20100100101', $html);
        self::assertStringContainsString('Av. Siempre Viva 123', $html);
        self::assertStringContainsString('Tel.: +51987111001', $html);
    }

    public function testLetterheadHidesLogoWhenPrintLogoDisabled(): void
    {
        $html = $this->render($this->makeCompany(), printLogo: false);

        self::assertStringNotContainsString('<img src="data:image/png;base64,FAKE"', $html);
        self::assertStringContainsString('Andina Retail SAC', $html);
    }

    public function testLetterheadOmitsPhoneLineWhenCompanyHasNone(): void
    {
        $company = $this->makeCompany();
        $company->setPhone(null);

        $html = $this->render($company, printLogo: false);

        self::assertStringNotContainsString('Tel.:', $html);
    }

    private function render(Company $company, bool $printLogo): string
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../../templates');
        $twig = new Environment($loader);
        $twig->addFunction(new TwigFunction('embedded_logo', static fn(): string => 'data:image/png;base64,FAKE'));

        return $twig->render('report/customer_statement.html.twig', [
            'statement' => $this->makeStatement(),
            'company' => $company,
            'printLogo' => $printLogo,
        ]);
    }

    private function makeCompany(): Company
    {
        return new Company()
            ->setRuc('20100100101')
            ->setBusinessName('Andina Retail SAC')
            ->setFiscalAddress('Av. Siempre Viva 123')
            ->setPhone('+51987111001');
    }

    private function makeStatement(): CustomerStatement
    {
        return new CustomerStatement(
            customerId: 1,
            customerName: 'Cliente de Prueba',
            customerDoc: '10456789123',
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            openingBalance: '0.00',
            lines: [],
            totalCharges: '0.00',
            totalPayments: '0.00',
            closingBalance: '0.00',
            aging: ['0-30' => '0.00', '31-60' => '0.00', '61-90' => '0.00', '90+' => '0.00'],
        );
    }
}
