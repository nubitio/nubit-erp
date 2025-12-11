<?php

namespace App\Entity;

use DateTimeInterface;
use App\Repository\TimeDimensionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimeDimensionRepository::class)]
#[ORM\Table(name: 'time_dimension')]
class TimeDimension
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTimeInterface $date = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $year = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $month = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $day = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quarter = null;

    #[ORM\Column(name: 'week', type: 'integer', nullable: true)]
    private ?int $week = null;

    #[ORM\Column(name: 'day_name', length: 255, nullable: true)]
    private ?string $dayName = null;

    #[ORM\Column(name: 'month_name', length: 255, nullable: true)]
    private ?string $monthName = null;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $holiday = null;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $weekend = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $event = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(?int $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getDay(): ?int
    {
        return $this->day;
    }

    public function setDay(?int $day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getQuarter(): ?int
    {
        return $this->quarter;
    }

    public function setQuarter(?int $quarter): static
    {
        $this->quarter = $quarter;

        return $this;
    }

    public function getWeek(): ?int
    {
        return $this->week;
    }

    public function setWeek(?int $week): static
    {
        $this->week = $week;

        return $this;
    }

    public function getDayName(): ?string
    {
        return $this->dayName;
    }

    public function setDayName(?string $dayName): static
    {
        $this->dayName = $dayName;

        return $this;
    }

    public function getMonthName(): ?string
    {
        return $this->monthName;
    }

    public function setMonthName(?string $monthName): static
    {
        $this->monthName = $monthName;

        return $this;
    }

    public function isHoliday(): ?bool
    {
        return $this->holiday;
    }

    public function setHoliday(?bool $holiday): static
    {
        $this->holiday = $holiday;

        return $this;
    }

    public function isWeekend(): ?bool
    {
        return $this->weekend;
    }

    public function setWeekend(?bool $weekend): static
    {
        $this->weekend = $weekend;

        return $this;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(?string $event): static
    {
        $this->event = $event;

        return $this;
    }
}
