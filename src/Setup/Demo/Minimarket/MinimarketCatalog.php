<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

/**
 * Static data of the minimarket demo: what the shop sells, who buys on credit, who works there.
 *
 * Prices include IGV (that is how the till rings them up); costs are set so that the gross margin
 * (before IGV) lands where a neighbourhood shop's does, about a fifth of sales. `weight` is how often a product ends
 * up in a basket; weight 0 is stock that does not move, on purpose, for the "what is not selling"
 * question. `finalStock` is what the shelf shows after the whole story has played out.
 *
 * @phpstan-type Product array{
 *     code: string, name: string, category: string, cost: string, price: string,
 *     finalStock: int, minStock: int, weight: int, maxQty: int
 * }
 */
final class MinimarketCatalog
{
    public const string TIMEZONE = 'America/Lima';

    /** @return list<string> */
    public static function categories(): array
    {
        return [
            'Bebidas',
            'Abarrotes',
            'Lácteos y huevos',
            'Snacks y golosinas',
            'Limpieza',
            'Cuidado personal',
            'Panadería',
        ];
    }

    /** @return list<Product> */
    public static function products(): array
    {
        return [
            // Bebidas
            self::p('INK15', 'Inca Kola 1.5 L', 'Bebidas', '4.41', '6.50', 14, 24, 10, 3), // star, running low
            self::p('COC15', 'Coca-Cola 1.5 L', 'Bebidas', '4.41', '6.50', 40, 24, 7, 3),
            self::p('AGU625', 'Agua San Luis 625 ml', 'Bebidas', '1.02', '1.50', 90, 48, 9, 6),
            self::p('GAT500', 'Gatorade 500 ml', 'Bebidas', '2.37', '3.50', 36, 24, 4, 3),
            self::p('CRI330', 'Cerveza Cristal 330 ml', 'Bebidas', '3.05', '4.50', 48, 24, 5, 6),
            // Abarrotes
            self::p('ARR5', 'Arroz Costeño 5 kg', 'Abarrotes', '17.73', '24.90', 18, 10, 3, 1),
            self::p('ACE1', 'Aceite Primor 1 L', 'Abarrotes', '8.19', '11.50', 6, 12, 4, 2), // running low
            self::p('AZU1', 'Azúcar rubia 1 kg', 'Abarrotes', '3.06', '4.30', 30, 20, 5, 3),
            self::p('FID5', 'Fideos Don Vittorio 500 g', 'Abarrotes', '2.35', '3.30', 44, 24, 4, 4),
            self::p('ATU170', 'Atún Florida 170 g', 'Abarrotes', '3.70', '5.20', 28, 24, 4, 4),
            self::p('LEC400', 'Leche Gloria evaporada 400 g', 'Abarrotes', '3.20', '4.50', 5, 36, 8, 6), // star, almost out
            // Lácteos y huevos
            self::p('HUE12', 'Huevos pardos x 12', 'Lácteos y huevos', '5.76', '8.50', 8, 10, 6, 2),
            self::p('YOG1', 'Yogurt Gloria 1 L', 'Lácteos y huevos', '5.29', '7.80', 14, 12, 3, 2),
            self::p('QUE250', 'Queso fresco 250 g', 'Lácteos y huevos', '6.10', '9.00', 5, 6, 2, 2),
            // Snacks y golosinas
            self::p('PIQ', 'Piqueo Snax 100 g', 'Snacks y golosinas', '1.15', '2.00', 60, 36, 6, 4),
            self::p('GAL', 'Galleta Soda San Jorge', 'Snacks y golosinas', '0.69', '1.20', 80, 48, 7, 6),
            self::p('CHO', 'Chocolate Sublime', 'Snacks y golosinas', '1.15', '2.00', 50, 36, 5, 4),
            self::p('PAP', 'Papas Lays 42 g', 'Snacks y golosinas', '1.44', '2.50', 40, 30, 5, 4),
            self::p('CHI', 'Chicle Trident', 'Snacks y golosinas', '0.58', '1.00', 70, 36, 4, 6),
            // Limpieza
            self::p('DET800', 'Detergente Bolívar 800 g', 'Limpieza', '8.69', '13.50', 12, 8, 2, 2),
            self::p('LEJ1', 'Lejía Clorox 1 L', 'Limpieza', '3.54', '5.50', 14, 10, 2, 2),
            self::p('PAH4', 'Papel higiénico x 4', 'Limpieza', '3.86', '6.00', 25, 12, 3, 2),
            // Cuidado personal
            self::p('JAB', 'Jabón Protex 110 g', 'Cuidado personal', '2.38', '3.90', 30, 18, 2, 3),
            self::p('SHA375', 'Shampoo Head & Shoulders 375 ml', 'Cuidado personal', '12.14', '19.90', 4, 6, 1, 1),
            // Panadería
            self::p('PAN', 'Pan francés (unidad)', 'Panadería', '0.18', '0.35', 120, 60, 14, 10),
            // Stock that does not move
            self::p('MER300', 'Mermelada Fanny 300 g', 'Abarrotes', '5.13', '7.20', 42, 6, 0, 1),
            self::p('ESP3', 'Esponja Scotch-Brite x 3', 'Limpieza', '4.19', '6.50', 30, 6, 0, 1),
            self::p('TE25', 'Té herbal Hierbas Andinas x 25', 'Abarrotes', '3.49', '4.90', 35, 6, 0, 1),
        ];
    }

    /**
     * People and businesses that buy on account or ask for a factura. Everyone else is a walk-in.
     *
     * @return list<array{key: string, document: string, name: string, address: string, phone: string, email: string}>
     */
    public static function customers(): array
    {
        return [
            [
                'key' => 'carlos',
                'document' => '41256789',
                'name' => 'Carlos Mendoza Ríos',
                'address' => 'Jr. Los Pinos 214, San Juan de Lurigancho',
                'phone' => '+51 987100201',
                'email' => 'carlos.mendoza@example.pe',
            ],
            [
                'key' => 'rosa',
                'document' => '07654321',
                'name' => 'Rosa Huamán Quispe',
                'address' => 'Av. Las Flores 880, San Juan de Lurigancho',
                'phone' => '+51 987100202',
                'email' => 'rosa.huaman@example.pe',
            ],
            [
                'key' => 'restaurante',
                'document' => '20601110028',
                'name' => 'Restaurante El Buen Sabor S.A.C.',
                'address' => 'Av. Próceres de la Independencia 1450, San Juan de Lurigancho',
                'phone' => '+51 987100203',
                'email' => 'compras@elbuensabor.example.pe',
            ],
            [
                'key' => 'panaderia',
                'document' => '20601110011',
                'name' => 'Panadería La Esperanza E.I.R.L.',
                'address' => 'Jr. Cusco 320, San Juan de Lurigancho',
                'phone' => '+51 987100204',
                'email' => 'admin@laesperanza.example.pe',
            ],
        ];
    }

    /** @return list<array{ruc: string, name: string, address: string, phone: string}> */
    public static function suppliers(): array
    {
        return [
            [
                'ruc' => '20550200101',
                'name' => 'Distribuidora de Bebidas Rímac S.A.C.',
                'address' => 'Av. Argentina 2150, Callao',
                'phone' => '+51 986200001',
            ],
            [
                'ruc' => '20550200128',
                'name' => 'Mayorista Abarrotes Lima S.R.L.',
                'address' => 'Av. Aviación 1800, La Victoria',
                'phone' => '+51 986200002',
            ],
            [
                'ruc' => '20550200145',
                'name' => 'Lácteos y Derivados del Centro S.A.',
                'address' => 'Jr. Huancavelica 640, Lima',
                'phone' => '+51 986200003',
            ],
            // Not goods suppliers: the landlord and the electricity company, for the shop's fixed costs.
            [
                'ruc' => '20550200162',
                'name' => 'Inversiones Los Olivos S.A.C.',
                'address' => 'Av. Los Olivos 100, Los Olivos',
                'phone' => '+51 986200004',
            ],
            [
                'ruc' => '20550200189',
                'name' => 'Empresa de Servicios Eléctricos del Rímac S.A.',
                'address' => 'Av. Rímac 500, Lima',
                'phone' => '+51 986200005',
            ],
        ];
    }

    /**
     * The people at the till. The owner is the demo administrator and is created by the command.
     *
     * @return array<string, array{username: string, name: string, shiftStart: int, shiftEnd: int}>
     */
    public static function cashiers(): array
    {
        return [
            'maria' => ['username' => 'maria.cajera', 'name' => 'María Quispe', 'shiftStart' => 7, 'shiftEnd' => 14],
            'luis' => ['username' => 'luis.cajero', 'name' => 'Luis Rojas', 'shiftStart' => 14, 'shiftEnd' => 21],
        ];
    }

    /** @return Product */
    private static function p(
        string $code,
        string $name,
        string $category,
        string $cost,
        string $price,
        int $finalStock,
        int $minStock,
        int $weight,
        int $maxQty,
    ): array {
        return compact('code', 'name', 'category', 'cost', 'price', 'finalStock', 'minStock', 'weight', 'maxQty');
    }
}
