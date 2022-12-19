<?php

namespace App\Dashboard\Domain;

interface DashboardRepositoryInterface
{
    public function getDailySales(string $localId): array;

    public function getWeeklySales(string $localId): array;

    public function getMonthlySales(string $localId): array;

    public function getMonthlyInvoicing(string $localId): array;

    public function getProductRotation(string $localId, string $lapse): array;
}