<?php

namespace App\Dashboard\Domain;

interface DashboardRepositoryInterface
{
    /**
     * @return array<int, mixed>
     */
    public function getDailySales(string $localId): array;

    /**
     * @return array<int, mixed>
     */
    public function getWeeklySales(string $localId): array;

    /**
     * @return array<int, mixed>
     */
    public function getMonthlySales(string $localId): array;

    /**
     * @return array<int, mixed>
     */
    public function getMonthlyInvoicing(): array;

    /**
     * @return array<int, mixed>
     */
    public function getProductRotation(string $localId, string $lapse): array;
}
