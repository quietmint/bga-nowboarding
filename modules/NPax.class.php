<?php

require_once 'constants.inc.php';

class NPax extends APP_GameClass implements JsonSerializable
{
    public int $id;
    public int $anger;
    public int $cash;
    public ?string $destination;
    public ?string $location;
    public int $moves;
    public string $origin;
    public int $optimal;
    public ?int $playerId;
    public string $status;
    public ?string $vip;

    public function __construct(array $dbrow)
    {
        $this->id = intval($dbrow['pax_id']);
        $this->anger = intval($dbrow['anger']);
        $this->cash = intval($dbrow['cash']);
        $this->destination = $dbrow['destination'];
        $this->location = $dbrow['location'];
        $this->moves = intval($dbrow['moves']);
        $this->optimal = intval($dbrow['optimal']);
        $this->origin = $dbrow['origin'];
        $this->playerId = $dbrow['player_id'] == null ? null : intval($dbrow['player_id']);
        $this->status = $dbrow['status'];
        $this->vip = $dbrow['vip'];
    }

    public function __toString(): string
    {
        return "NPax({$this->id} {$this->status} @ {$this->location})";
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
            'vipInfo' => $this->getVipInfo(),
        ];
    }

    public function getPlayerIdSql(): string
    {
        return $this->playerId ?? 'NULL';
    }

    public function getVipInfo(): ?array
    {
        $vipInfo = null;
        if ($this->vip != null) {
            $parts = explode('_', $this->vip);
            $vipInfo = [
                'key' => $parts[0],
                'name' => N_REF_VIP[$parts[0]]['name'],
                'desc' => N_REF_VIP[$parts[0]]['desc'],
            ];
            if (count($parts) > 1) {
                unset($parts[0]);
                $vipInfo['args'] = $parts;
            } else {
                $vipInfo['args'] = null;
            }
        }
        return $vipInfo;
    }

    public function getVipTitleMessage(): ?array
    {
        $args = null;
        $vipInfo = $this->getVipInfo();
        if ($vipInfo) {
            $args = [
                'i18n' => ['desc', 'vip'],
                'log' => N_REF_MSG['vipWelcome'],
                'location' => $this->origin,
            ];
            if ($vipInfo['args']) {
                $args['desc'] = [
                    'log' => $vipInfo['desc'],
                    'args' => $vipInfo['args'],
                ];
                $args['vip'] = [
                    'log' => $vipInfo['name'],
                    'args' => $vipInfo['args'],
                ];
            } else {
                $args['desc'] = $vipInfo['desc'];
                $args['vip'] = $vipInfo['name'];
            }
        }
        return $args;
    }

    public function resetAnger(int $optionAnger): void
    {
        if ($this->vip == 'GRUMPY') {
            $this->anger = $optionAnger + 1;
        } else if ($this->vip != 'IMPATIENT') {
            $this->anger = $optionAnger;
        }
    }
}
