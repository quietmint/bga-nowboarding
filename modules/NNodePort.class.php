<?php

class NNodePort extends NNode implements JsonSerializable
{
    public function __construct(string $id)
    {
        parent::__construct($id);
    }

    public function __toString(): string
    {
        return "NNodePort({$this->id})";
    }

    public function jsonSerialize(): array
    {
        return parent::jsonSerialize() + [
            'type' => 'PORT',
        ];
    }
}
