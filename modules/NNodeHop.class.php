<?php

class NNodeHop extends NNode implements JsonSerializable
{
    public ?string $alliance;
    public ?string $weather;

    public function __construct(string $id, ?string $alliance, ?string $weather)
    {
        parent::__construct($id);
        $this->alliance = $alliance;
        $this->weather = $weather;
    }

    public function __toString(): string
    {
        return "NNodeHop({$this->id})";
    }

    public function jsonSerialize(): array
    {
        return parent::jsonSerialize() + [
            'alliance' => $this->alliance,
            'type' => 'HOP',
            'weather' => $this->weather,
        ];
    }
}
