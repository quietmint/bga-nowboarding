<?php

class NPax extends APP_GameClass implements JsonSerializable
{
    protected int $id;
    protected int $anger;
    protected string $beginNode;
    protected int $cash;
    protected ?string $currentNode;
    protected string $endNode;
    protected int $order;
    protected ?int $playerId;
    protected string $status;

    public static function loadAll(): array
    {
        $dbrows = self::getCollectionFromDb("SELECT * FROM pax");
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, $dbrows);
    }

    public static function loadById(int $id): NPax
    {
        $dbrow = self::getObjectFromDB("SELECT * FROM pax WHERE pax_id = $id");
        return new NPax($dbrow);
    }

    protected function __construct(array $dbrow)
    {
        $this->id = intval($dbrow['pax_id']);
        $this->anger = intval($dbrow['anger']);
        $this->beginNode = $dbrow['begin_node'];
        $this->cash = intval($dbrow['cash']);
        $this->currentNode = $dbrow['current_node'];
        $this->endNode = $dbrow['end_node'];
        $this->order = intval($dbrow['order']);
        $this->playerId = $dbrow['player_id'] == null ? null : intval($dbrow['player_id']);
        $this->status = $dbrow['status'];
    }

    public function __toString(): string
    {
        return "NPax({$this->id} {$this->beginNode}-{$this->endNode})";
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'anger' => $this->anger,
            'beginNode' => $this->beginNode,
            'cash' => $this->cash,
            'currentNode' => $this->currentNode,
            'endNode' => $this->endNode,
            'order' => $this->order,
            'playerId' => $this->playerId,
            'status' => $this->status
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAnger(): int
    {
        return $this->anger;
    }

    public function getBeginNode(): string
    {
        return $this->beginNode;
    }

    public function getCash(): int
    {
        return $this->cash;
    }

    public function getCurrentNode(): ?string
    {
        return $this->currentNode;
    }

    public function getEndNode(): string
    {
        return $this->endNode;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getPlayerId(): ?int
    {
        return $this->playerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    // ----------------------------------------------------------------------

}
