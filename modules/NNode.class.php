<?php

class NNode extends APP_GameClass
{
    public string $id;
    public ?string $alliance;
    public array $connections = [];

    public function __construct(string $id, ?string $alliance = null)
    {
        $this->id = $id;
        $this->alliance = $alliance;
    }

    public function __toString(): string
    {
        return "NNode({$this->id})";
    }

    public function connect(NNode $other): void
    {
        $this->connections[$other->id] = $other;
        $other->connections[$this->id] = $this;
    }

    public function disconnect(NNode $other): void
    {
        unset($this->connections[$other->id]);
        unset($other->connections[$this->id]);
    }
}
