<?php
namespace App\Domain;

class BloodPressureRecord
{
    public function __construct(
        private string $date,
        private ?float $weight,
        private ?array $morning,
        private ?array $evening
    ) {}

    public function getDate(): string { return $this->date; }
    public function getWeight(): ?float { return $this->weight; }
    public function getMorning(): ?array { return $this->morning; }
    public function getEvening(): ?array { return $this->evening; }

    /**
     * 指定した月のデータを扱うためのヘルパーなど、ビジネスロジックをここに集約できます。
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'weight' => $this->weight,
            'morning' => $this->morning,
            'evening' => $this->evening,
        ];
    }
}