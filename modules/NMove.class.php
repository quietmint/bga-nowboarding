<?php

class NMove extends APP_GameClass implements JsonSerializable
{
    public int $fuel;
    public string $location;
    public NNode $node;
    public array $path;
    public bool $penalty;

    public function __construct(int $fuel, NNode $node, array $path, bool $penalty)
    {
        $this->fuel = $fuel;
        $this->location = $node->id;
        $this->node = $node;
        $this->path = $path;
        $this->path[] = $node->id;
        $this->penalty = $penalty;
    }

    public function __toString(): string
    {
        return "NMove({$this->fuel} {$this->getPathString()})";
    }

    public function jsonSerialize(): array
    {
        return [
            'fuel' => $this->fuel,
            'location' => $this->location,
        ];
    }

    public function getOrigin(): string
    {
        end($this->path);
        return prev($this->path);
    }

    public function getPathString(): string
    {
        return join('/', $this->path);
    }
}
