<?php

declare(strict_types=1);

namespace App\Setup\Service;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Named, Peru-shaped sample data for a commercial mini-ERP demonstration.
 * Kept separate from TestDataSeeder so load-test junk never reaches a prospect.
 */
final class PresentationDemoCatalog
{
    public const string TIMEZONE = 'America/Lima';

    /**
     * @return list<array{name: string, children: list<string>}>
     */
    public static function categories(): array
    {
        return [
            ['name' => 'Tecnología', 'children' => ['Computo', 'Periféricos', 'Impresión']],
            ['name' => 'Oficina', 'children' => ['Papelería', 'Mobiliario']],
            ['name' => 'Consumo', 'children' => ['Bebidas', 'Snacks']],
        ];
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     category: string,
     *     purchasePrice: string,
     *     salePrice: string,
     *     stock: string,
     *     minStock: string
     * }>
     */
    public static function products(): array
    {
        return [
            [
                'code' => 'LAP-HP15',
                'name' => 'Laptop HP 15 Intel i5 16GB',
                'category' => 'Computo',
                'purchasePrice' => '2100.00',
                'salePrice' => '2690.00',
                'stock' => '12',
                'minStock' => '3',
            ],
            [
                'code' => 'MON-24FHD',
                'name' => 'Monitor LED 24" Full HD',
                'category' => 'Computo',
                'purchasePrice' => '380.00',
                'salePrice' => '529.00',
                'stock' => '18',
                'minStock' => '4',
            ],
            [
                'code' => 'MOU-LOG',
                'name' => 'Mouse inalámbrico Logitech M185',
                'category' => 'Periféricos',
                'purchasePrice' => '28.00',
                'salePrice' => '49.90',
                'stock' => '60',
                'minStock' => '15',
            ],
            [
                'code' => 'TEC-MEC',
                'name' => 'Teclado mecánico RGB',
                'category' => 'Periféricos',
                'purchasePrice' => '95.00',
                'salePrice' => '159.00',
                'stock' => '22',
                'minStock' => '6',
            ],
            [
                'code' => 'WEB-HD',
                'name' => 'Webcam HD 1080p',
                'category' => 'Periféricos',
                'purchasePrice' => '72.00',
                'salePrice' => '119.00',
                'stock' => '16',
                'minStock' => '5',
            ],
            [
                'code' => 'IMP-L3150',
                'name' => 'Impresora Epson EcoTank L3150',
                'category' => 'Impresión',
                'purchasePrice' => '680.00',
                'salePrice' => '899.00',
                'stock' => '8',
                'minStock' => '2',
            ],
            [
                'code' => 'TIN-667',
                'name' => 'Tinta HP 667 negra',
                'category' => 'Impresión',
                'purchasePrice' => '32.00',
                'salePrice' => '54.90',
                'stock' => '4',
                'minStock' => '10',
            ],
            [
                'code' => 'PAP-A4',
                'name' => 'Resma papel A4 75g',
                'category' => 'Papelería',
                'purchasePrice' => '12.50',
                'salePrice' => '18.90',
                'stock' => '80',
                'minStock' => '20',
            ],
            [
                'code' => 'CUA-A4',
                'name' => 'Cuaderno universitario A4',
                'category' => 'Papelería',
                'purchasePrice' => '3.80',
                'salePrice' => '7.50',
                'stock' => '120',
                'minStock' => '30',
            ],
            [
                'code' => 'LAP-AZUL',
                'name' => 'Lapicero azul x12',
                'category' => 'Papelería',
                'purchasePrice' => '4.20',
                'salePrice' => '8.90',
                'stock' => '3',
                'minStock' => '12',
            ],
            [
                'code' => 'SILL-ERG',
                'name' => 'Silla ergonómica de oficina',
                'category' => 'Mobiliario',
                'purchasePrice' => '290.00',
                'salePrice' => '449.00',
                'stock' => '7',
                'minStock' => '2',
            ],
            [
                'code' => 'ESC-120',
                'name' => 'Escritorio 120 cm',
                'category' => 'Mobiliario',
                'purchasePrice' => '340.00',
                'salePrice' => '519.00',
                'stock' => '5',
                'minStock' => '2',
            ],
            [
                'code' => 'AGU-625',
                'name' => 'Agua San Luis 625 ml',
                'category' => 'Bebidas',
                'purchasePrice' => '0.90',
                'salePrice' => '1.50',
                'stock' => '200',
                'minStock' => '40',
            ],
            [
                'code' => 'GAS-500',
                'name' => 'Inca Kola 500 ml',
                'category' => 'Bebidas',
                'purchasePrice' => '1.80',
                'salePrice' => '3.50',
                'stock' => '2',
                'minStock' => '24',
            ],
            [
                'code' => 'GAL-SODA',
                'name' => 'Galletas soda pack',
                'category' => 'Snacks',
                'purchasePrice' => '2.10',
                'salePrice' => '3.90',
                'stock' => '48',
                'minStock' => '12',
            ],
            [
                'code' => 'CAB-USB',
                'name' => 'Cable USB-C 1 m',
                'category' => 'Periféricos',
                'purchasePrice' => '6.50',
                'salePrice' => '14.90',
                'stock' => '35',
                'minStock' => '10',
            ],
            [
                'code' => 'AUD-BT',
                'name' => 'Audífonos Bluetooth',
                'category' => 'Periféricos',
                'purchasePrice' => '42.00',
                'salePrice' => '79.90',
                'stock' => '14',
                'minStock' => '4',
            ],
            [
                'code' => 'SSD-512',
                'name' => 'Disco SSD 512 GB',
                'category' => 'Computo',
                'purchasePrice' => '145.00',
                'salePrice' => '229.00',
                'stock' => '11',
                'minStock' => '3',
            ],
        ];
    }

    /**
     * @return list<array{ruc: string, name: string, address: string, phone: string, email: string}>
     */
    public static function customers(): array
    {
        return [
            [
                'ruc' => '20100100101',
                'name' => 'Andina Retail SAC',
                'address' => 'Av. Javier Prado 1250, San Isidro',
                'phone' => '+51 987111001',
                'email' => 'compras@andinaretail.pe',
            ],
            [
                'ruc' => '20100100128',
                'name' => 'Servicios Norte SRL',
                'address' => 'Av. España 450, Trujillo',
                'phone' => '+51 987111002',
                'email' => 'admin@serviciosnorte.pe',
            ],
            [
                'ruc' => '20100100145',
                'name' => 'Colegio Santa Lucía',
                'address' => 'Jr. Unión 220, Arequipa',
                'phone' => '+51 987111003',
                'email' => 'tesoreria@santalucia.edu.pe',
            ],
            [
                'ruc' => '20100100162',
                'name' => 'Clínica del Sur SAC',
                'address' => 'Av. La Marina 890, Miraflores',
                'phone' => '+51 987111004',
                'email' => 'logistica@clinicadelsur.pe',
            ],
            [
                'ruc' => '20100100189',
                'name' => 'Constructora Pacífico EIRL',
                'address' => 'Av. Argentina 3100, Callao',
                'phone' => '+51 987111005',
                'email' => 'obras@pacifico.pe',
            ],
            [
                'ruc' => '20100100205',
                'name' => 'Estudio Contable Vargas',
                'address' => 'Av. Arequipa 2650, Lince',
                'phone' => '+51 987111006',
                'email' => 'contacto@vargascontadores.pe',
            ],
            [
                'ruc' => '10123456',
                'name' => 'María Elena Quispe Rojas',
                'address' => 'Jr. Cusco 118, Lima',
                'phone' => '+51 987222001',
                'email' => 'maria.quispe@correo.pe',
            ],
            [
                'ruc' => '10456789',
                'name' => 'Carlos Alberto Huamán Díaz',
                'address' => 'Av. Brasil 540, Pueblo Libre',
                'phone' => '+51 987222002',
                'email' => 'carlos.huaman@correo.pe',
            ],
            [
                'ruc' => '10789123',
                'name' => 'Rosa Isabel Torres Vega',
                'address' => 'Calle Los Pinos 33, Surco',
                'phone' => '+51 987222003',
                'email' => 'rosa.torres@correo.pe',
            ],
            [
                'ruc' => '10234567',
                'name' => 'Luis Fernando Paredes Soto',
                'address' => 'Av. Universitaria 1800, Los Olivos',
                'phone' => '+51 987222004',
                'email' => 'luis.paredes@correo.pe',
            ],
            [
                'ruc' => '20100100222',
                'name' => 'Farmacia Central SAC',
                'address' => 'Av. Grau 150, Piura',
                'phone' => '+51 987111007',
                'email' => 'compras@farmaciacentral.pe',
            ],
            [
                'ruc' => '20100100249',
                'name' => 'Hotel Costa Azul SRL',
                'address' => 'Malecón 120, Miraflores',
                'phone' => '+51 987111008',
                'email' => 'abastecimiento@costaazul.pe',
            ],
        ];
    }

    /**
     * @return list<array{ruc: string, name: string, address: string, phone: string}>
     */
    public static function suppliers(): array
    {
        return [
            [
                'ruc' => '20550100101',
                'name' => 'Mayorista Tecnológica del Perú SAC',
                'address' => 'Av. Argentina 2150, Callao',
                'phone' => '+51 986100001',
            ],
            [
                'ruc' => '20550100128',
                'name' => 'Papelería Industrial Lima SRL',
                'address' => 'Jr. Cañete 340, Cercado de Lima',
                'phone' => '+51 986100002',
            ],
            [
                'ruc' => '20550100145',
                'name' => 'Distribuidora Bebidas Andinas SAC',
                'address' => 'Av. Néstor Gambetta 800, Callao',
                'phone' => '+51 986100003',
            ],
            [
                'ruc' => '20550100162',
                'name' => 'Muebles Oficina Express EIRL',
                'address' => 'Av. Colonial 1900, Lima',
                'phone' => '+51 986100004',
            ],
            [
                'ruc' => '20550100189',
                'name' => 'Importadora Periféricos SAC',
                'address' => 'Av. La Marina 2400, San Miguel',
                'phone' => '+51 986100005',
            ],
            [
                'ruc' => '20550100205',
                'name' => 'Suministros Gráficos del Sur SRL',
                'address' => 'Av. Independencia 510, Arequipa',
                'phone' => '+51 986100006',
            ],
        ];
    }

    /**
     * @return list<array{dni: string, firstName: string, lastName: string, salary: string, pension: string, afp: ?string, monthsAgoHired: int}>
     */
    public static function employees(): array
    {
        return [
            [
                'dni' => '40111222',
                'firstName' => 'Ana Lucía',
                'lastName' => 'Salazar Ponce',
                'salary' => '3200.00',
                'pension' => 'AFP',
                'afp' => 'Integra',
                'monthsAgoHired' => 28,
            ],
            [
                'dni' => '40333444',
                'firstName' => 'Miguel Ángel',
                'lastName' => 'Cárdenas Ruiz',
                'salary' => '2800.00',
                'pension' => 'ONP',
                'afp' => null,
                'monthsAgoHired' => 18,
            ],
            [
                'dni' => '40555666',
                'firstName' => 'Patricia',
                'lastName' => 'Mendoza Loayza',
                'salary' => '2500.00',
                'pension' => 'AFP',
                'afp' => 'Prima',
                'monthsAgoHired' => 11,
            ],
            [
                'dni' => '40777888',
                'firstName' => 'Jorge Luis',
                'lastName' => 'Ramos Quispe',
                'salary' => '1800.00',
                'pension' => 'ONP',
                'afp' => null,
                'monthsAgoHired' => 7,
            ],
            [
                'dni' => '40999000',
                'firstName' => 'Elena',
                'lastName' => 'Vargas Chávez',
                'salary' => '2100.00',
                'pension' => 'AFP',
                'afp' => 'Habitat',
                'monthsAgoHired' => 15,
            ],
            [
                'dni' => '41222333',
                'firstName' => 'Diego',
                'lastName' => 'Flores Huerta',
                'salary' => '1600.00',
                'pension' => 'AFP',
                'afp' => 'Profuturo',
                'monthsAgoHired' => 4,
            ],
        ];
    }

    /**
     * @return list<array{provider: string, mandatory: string, insurance: string, commission: string}>
     */
    public static function afpRates(): array
    {
        return [
            ['provider' => 'Habitat', 'mandatory' => '10.00', 'insurance' => '1.37', 'commission' => '1.47'],
            ['provider' => 'Integra', 'mandatory' => '10.00', 'insurance' => '1.37', 'commission' => '1.55'],
            ['provider' => 'Prima', 'mandatory' => '10.00', 'insurance' => '1.37', 'commission' => '1.60'],
            ['provider' => 'Profuturo', 'mandatory' => '10.00', 'insurance' => '1.37', 'commission' => '1.69'],
        ];
    }

    /**
     * @return list<array{bank: string, alias: string, accountNumber: string, cci: string}>
     */
    public static function bankAccounts(): array
    {
        return [
            [
                'bank' => 'BCP',
                'alias' => 'Cuenta corriente soles',
                'accountNumber' => '193-2048571-0-12',
                'cci' => '00219300204857101234',
            ],
            [
                'bank' => 'Interbank',
                'alias' => 'Cuenta de recaudación',
                'accountNumber' => '200-3124567890',
                'cci' => '00320000312456789012',
            ],
        ];
    }

    /**
     * @return list<array{description: string, cost: string, months: int, monthsAgoPurchased: int}>
     */
    public static function fixedAssets(): array
    {
        return [
            [
                'description' => 'Servidor de tienda Dell T40',
                'cost' => '4800.00',
                'months' => 36,
                'monthsAgoPurchased' => 14,
            ],
            [
                'description' => 'Impresora térmica POS Epson TM-T20',
                'cost' => '650.00',
                'months' => 24,
                'monthsAgoPurchased' => 8,
            ],
            [
                'description' => 'Estantería metálica de almacén',
                'cost' => '1200.00',
                'months' => 60,
                'monthsAgoPurchased' => 20,
            ],
        ];
    }

    /**
     * Deterministic sale timestamps: today, yesterday, last week, then a year of history.
     *
     * @return list<DateTimeImmutable>
     */
    public static function saleMoments(?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new DateTimeImmutable(
            'now',
            new DateTimeZone(self::TIMEZONE),
        ))->setTimezone(new DateTimeZone(self::TIMEZONE));
        $moments = [];

        foreach ([10, 11, 12, 14, 16, 17] as $hour) {
            $moments[] = $now->setTime($hour, 15);
        }

        foreach ([9, 13, 18, 19] as $hour) {
            $moments[] = $now->modify('-1 day')->setTime($hour, 20);
        }

        foreach ([2, 3, 4, 5, 6] as $daysAgo) {
            $moments[] = $now->modify(sprintf('-%d days', $daysAgo))->setTime(11, 0);
            $moments[] = $now->modify(sprintf('-%d days', $daysAgo))->setTime(16, 30);
        }

        for ($month = 1; $month <= 11; ++$month) {
            $anchor = $now->modify(sprintf('-%d months', $month));
            foreach ([6, 14, 22, 27] as $day) {
                $moments[] = $anchor->setDate(
                    (int) $anchor->format('Y'),
                    (int) $anchor->format('n'),
                    min($day, (int) $anchor->format('t')),
                )->setTime(12, 0);
            }
        }

        return $moments;
    }

    /**
     * @return list<string>
     */
    public static function paymentMethodRotation(): array
    {
        return ['Efectivo', 'Yape', 'Transferencia', 'Credito', 'Tarjeta débito', 'Plin'];
    }
}
