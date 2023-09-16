<?php

class NPlane extends APP_GameClass implements JsonSerializable
{
    public int $id;
    public array $alliances;
    public int $cash;
    public int $debt;
    public ?string $location;
    public string $name;
    public ?string $origin;
    public int $seat;
    public int $moves;
    public int $seatRemain;
    public int $speed;
    public int $speedRemain;
    public bool $tempSeat;
    public bool $tempSpeed;

    public function __construct(array $dbrow)
    {
        $this->id = intval($dbrow['player_id']);
        $this->alliances = empty($dbrow['alliances']) ? [] : explode(',', $dbrow['alliances']);
        $this->cash = intval($dbrow['cash']);
        $this->debt = intval($dbrow['debt']);
        $this->location = $dbrow['location'];
        $this->name = $dbrow['player_name'];
        $this->origin = $dbrow['origin'];
        $this->seat = intval($dbrow['seat']);
        $this->seatRemain = intval($dbrow['seat_remain']);
        $this->speed = intval($dbrow['speed']);
        $this->speedRemain = intval($dbrow['speed_remain']);
        $this->tempSeat = boolval($dbrow['temp_seat']);
        $this->tempSpeed = boolval($dbrow['temp_speed']);
    }

    public function __toString(): string
    {
        return "NPlane({$this->id})";
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'alliances' => $this->alliances,
            'cash' => $this->cash,
            'cashRemain' => $this->getCashRemain(),
            'debt' => $this->debt,
            'location' => $this->location,
            'origin' => $this->origin,
            'seat' => $this->seat,
            'seatRemain' => $this->seatRemain,
            'speed' => $this->speed,
            'speedRemain' => $this->speedRemain,
            'tempSeat' => $this->tempSeat,
            'tempSpeed' => $this->tempSpeed,
        ];
    }

    public function getAlliancesSql(): string
    {
        return empty($this->alliances) ? '' : join(',', $this->alliances);
    }

    public function getCashRemain(): int
    {
        return $this->cash - $this->debt;
    }
}
