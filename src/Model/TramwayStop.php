<?php

declare(strict_types=1);

namespace App\Model;

use DateTime;

class TramwayStop
{
    private string $course;
    private ?string $stopCode = null;
    private int $stopId;
    private int $routeShortName;
    private string $tripHeadsign;
    private int $directionId;
    private \DateTimeInterface $departureTime;
    private bool $isTheorical;
    private int $delaySec;
    private int $destARCode;

    public function getCourse(): string
    {
        return $this->course;
    }

    public function setCourse(string $course): self
    {
        $this->course = $course;

        return $this;
    }

    public function getStopCode(): ?string
    {
        return $this->stopCode;
    }

    public function setStopCode(string $stopCode): self
    {
        $this->stopCode = $stopCode;

        return $this;
    }

    public function getStopId(): int
    {
        return $this->stopId;
    }

    public function setStopId(int $stopId): self
    {
        $this->stopId = $stopId;

        return $this;
    }

    public function getRouteShortName(): int
    {
        return $this->routeShortName;
    }

    public function setRouteShortName(int $routeShortName): self
    {
        $this->routeShortName = $routeShortName;

        return $this;
    }

    public function getTripHeadsign(): string
    {
        return $this->tripHeadsign;
    }

    public function setTripHeadsign(string $tripHeadsign): self
    {
        $this->tripHeadsign = $tripHeadsign;

        return $this;
    }

    public function getDirectionId(): int
    {
        return $this->directionId;
    }

    public function setDirectionId(int $directionId): self
    {
        $this->directionId = $directionId;

        return $this;
    }

    public function getDepartureTime(): \DateTimeInterface
    {
        return $this->departureTime;
    }

    public function setDepartureTime(string $departureTime): self
    {
        $this->departureTime = new DateTime($departureTime);

        return $this;
    }

    public function isTheorical(): bool
    {
        return $this->isTheorical;
    }

    public function setIsTheorical(bool $isTheorical): self
    {
        $this->isTheorical = $isTheorical;

        return $this;
    }

    public function getDelaySec(): int
    {
        return $this->delaySec;
    }

    public function setDelaySec(int $delaySec): self
    {
        $this->delaySec = $delaySec;

        return $this;
    }

    public function getDestARCode(): int
    {
        return $this->destARCode;
    }

    public function setDestARCode(int $destARCode): self
    {
        $this->destARCode = $destARCode;

        return $this;
    }

}
