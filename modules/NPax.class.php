<?php

class NPax extends APP_GameClass implements JsonSerializable
{
    public int $id;
    public int $anger;
    public int $cash;
    public string $destination;
    public ?string $location;
    public string $origin;
    public ?int $playerId;
    public string $status;

    protected function __construct(array $dbrow)
    {
        $this->id = intval($dbrow['pax_id']);
        $this->anger = intval($dbrow['anger']);
        $this->cash = intval($dbrow['cash']);
        $this->destination = $dbrow['destination'];
        $this->location = $dbrow['location'];
        $this->origin = $dbrow['origin'];
        $this->playerId = $dbrow['player_id'] == null ? null : intval($dbrow['player_id']);
        $this->status = $dbrow['status'];
    }

    public function __toString(): string
    {
        return "NPax({$this->id} {$this->location})";
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'anger' => $this->anger,
            'cash' => $this->cash,
            'destination' => $this->destination,
            'location' => $this->location,
            'origin' => $this->origin,
            'playerId' => $this->playerId,
            'status' => $this->status,
        ];
    }
}
