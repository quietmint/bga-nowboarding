<?php

abstract class NNode extends APP_GameClass implements JsonSerializable
{
    public string $id;
    public array $connections = [];

    protected function __construct(string $id)
    {
        $this->id = $id;
    }

    abstract public function __toString();

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'connections' => array_keys($this->connections),
        ];
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
