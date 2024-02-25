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
    public int $pax;
    public int $seat;
    public int $seatRemain;
    public int $seatX;
    public int $speed;
    public int $speedRemain;
    public int $tempSeat;
    public int $tempSpeed;
    public array $wallet;

    public function __construct(array $dbrow)
    {
        $this->id = intval($dbrow['player_id']);
        $this->alliances = empty($dbrow['alliances']) ? [] : explode(',', $dbrow['alliances']);
        $this->debt = intval($dbrow['debt']);
        $this->location = $dbrow['location'];
        $this->name = $dbrow['player_name'];
        $this->origin = $dbrow['origin'];
        $this->pax = intval($dbrow['pax']);
        $this->seat = intval($dbrow['seat']);
        $this->seatX = intval($dbrow['seat_x']);
        $this->speed = intval($dbrow['speed']);
        $this->speedRemain = intval($dbrow['speed_remain']);
        $this->tempSeat = intval($dbrow['temp_seat']);
        $this->tempSpeed = intval($dbrow['temp_speed']);
        $this->wallet = [];
        if (!empty($dbrow['wallet'])) {
            foreach (explode(',', $dbrow['wallet']) as $w) {
                $kv = explode('=', $w);
                $this->wallet[intval($kv[0])] = intval($kv[1]);
            }
        }
        $this->cash = array_sum($this->wallet);
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
            'seatRemain' => $this->getSeatRemain(),
            'speed' => $this->speed,
            'speedRemain' => $this->speedRemain,
            'tempSeat' => $this->tempSeat,
            'tempSpeed' => $this->tempSpeed,
            'wallet' => $this->wallet,
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

    public function getSeatRemain(): int
    {
        return max(0, $this->seat + $this->seatX + ($this->tempSeat == 1 ? 1 : 0) - $this->pax);
    }
}
