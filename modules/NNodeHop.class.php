<?php

class NNodeHop extends NNode implements JsonSerializable
{
    public ?string $alliance;

    public function __construct(string $id, ?string $alliance)
    {
        parent::__construct($id);
        $this->alliance = $alliance;
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
        ];
    }
}
