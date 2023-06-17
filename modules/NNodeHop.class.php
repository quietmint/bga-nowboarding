<?php

class NNodeHop extends NNode implements JsonSerializable
{
    protected ?string $color;
    protected ?string $weather;

    public function __construct(string $id, ?string $color, ?string $weather)
    {
        parent::__construct($id);
        $this->color = $color;
        $this->weather = $weather;
    }

    public function __toString(): string
    {
        return "NNodeHop({$this->id})";
    }

    public function jsonSerialize(): array
    {
        return parent::jsonSerialize() + [
            'color' => $this->color,
            'type' => 'HOP',
            'weather' => $this->weather,
        ];
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getWeather(): ?string
    {
        return $this->weather;
    }

    public function setWeather(string $weather): void
    {
        $this->weather = $weather;
    }
}
