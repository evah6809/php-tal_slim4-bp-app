<?php
namespace App\Domain;

interface BloodPressureRepositoryInterface
{
    public function findByDate(string $date, string $userId): ?BloodPressureRecord;
    public function findByMonth(int $year, int $month, string $userId): array;
    public function save(BloodPressureRecord $record, string $userId): void;
}