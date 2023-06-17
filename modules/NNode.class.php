<?php

abstract class NNode extends APP_GameClass implements JsonSerializable
{
    protected string $id;
    protected array $connections = [];

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

    public function getId(): string
    {
        return $this->id;
    }

    public function getFirstConnection(): NNode
    {
        return array_values($this->connections)[0];
    }

    public function getConnections(): array
    {
        return $this->connections;
    }
}
