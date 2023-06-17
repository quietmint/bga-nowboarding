<?php

class NPlane extends APP_GameClass implements JsonSerializable
{
    protected int $id;
    protected ?string $color;
    protected array $colors;
    protected int $cash;
    protected ?string $currentNode;
    protected bool $extraSeat;
    protected bool $extraSpeed;
    protected int $fuel;
    protected string $name;
    protected ?string $priorNode;
    protected int $seats;
    protected int $speed;

    public static function loadAll(): array
    {
        $dbrows = self::getCollectionFromDb("SELECT p.*, b.player_name FROM plane p JOIN player b ON (b.player_id = p.player_id)");
        return array_map(function ($dbrow) {
            return new NPlane($dbrow);
        }, $dbrows);
    }

    public static function loadById(int $id): NPlane
    {
        $dbrow = self::getObjectFromDB("SELECT p.*, b.player_name FROM plane p JOIN player b ON (b.player_id = p.player_id) WHERE p.player_id = $id");
        return new NPlane($dbrow);
    }

    protected function __construct(array $dbrow)
    {
        $this->id = intval($dbrow['player_id']);
        $this->cash = intval($dbrow['cash']);
        $this->color = $dbrow['color'];
        $this->colors = $dbrow['colors'] == null ? [] : explode(',', $dbrow['colors']);
        $this->currentNode = $dbrow['current_node'];
        $this->extraSeat = $dbrow['extra_seat'] != null;
        $this->extraSpeed = $dbrow['extra_speed'] != null;
        $this->fuel = intval($dbrow['fuel']);
        $this->name = $dbrow['player_name'];
        $this->priorNode = $dbrow['prior_node'];
        $this->seats = intval($dbrow['seats']);
        $this->speed = intval($dbrow['speed']);
    }

    public function __toString(): string
    {
        return "NPlane({$this->id})";
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'cash' => $this->cash,
            'color' => $this->color,
            'colorHex' => $this->color == null ? null : N_COLOR_REF[$this->color]['hex'],
            'colors' => $this->colors,
            'currentNode' => $this->currentNode,
            'extraSeat' => $this->extraSeat,
            'extraSpeed' => $this->extraSpeed,
            'fuel' => $this->fuel,
            'priorNode' => $this->priorNode,
            'seats' => $this->seats,
            'speed' => $this->speed,
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBuys(): array
    {
        $buys = [];

        // Colors
        if ($this->cash >= 7) {
            if ($this->color == null) {
                $claimedColors = self::getObjectListFromDB("SELECT color FROM plane WHERE color IS NOT NULL", true);
            } else {
                $claimedColors = $this->colors;
            }
            $unclaimedColors = array_diff(array_keys(N_COLOR_REF), $claimedColors);
            foreach ($unclaimedColors as $color) {
                $buys[] = [
                    'type' => 'COLOR',
                    'cost' => 7,
                    'color' => $color
                ];
            }
        }

        // Seat
        if ($this->seats < 5) {
            $seat = $this->seats + 1;
            $cost = N_SEAT_REF[$seat];
            if ($this->cash >= $cost) {
                $buys[] = [
                    'type' => 'SEAT',
                    'cost' => $cost,
                    'seat' => $seat
                ];
            }
        }

        // Speed
        if ($this->speed < 9) {
            $speed = $this->speed + 1;
            $cost = N_SPEED_REF[$speed];
            if ($this->cash >= $cost) {
                $buys[] = [
                    'type' => 'SPEED',
                    'cost' => $cost,
                    'speed' => $speed
                ];
            }
        }

        // Extra seat
        if ($this->cash >= 2) {
            $buys[] = [
                'type' => 'EXTRA_SEAT',
                'cost' => 2,
            ];
        }

        // Extra speed
        if ($this->cash >= 1) {
            $buys[] = [
                'type' => 'EXTRA_SPEED',
                'cost' => 1,
            ];
        }

        return $buys;
    }

    public function getCash(): int
    {
        return $this->cash;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getColors(): array
    {
        return $this->colors;
    }

    public function getColorsCount(): int
    {
        return count($this->colors);
    }

    public function getCurrentNode(): string
    {
        return $this->currentNode;
    }

    public function isExtraSeat(): bool
    {
        return $this->extraSeat;
    }

    public function isExtraSpeed(): bool
    {
        return $this->extraSpeed;
    }

    public function getFuel(): int
    {
        return $this->fuel;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriorNode(): ?string
    {
        return $this->priorNode;
    }

    public function getSeats(): int
    {
        return $this->seats;
    }

    public function getSpeed(): int
    {
        return $this->speed;
    }

    // ----------------------------------------------------------------------

    public function buyColor(string $color): int
    {
        if (in_array($color, $this->colors)) {
            throw new BgaUserException("You already a member of this airline alliance ($color)");
        }
        $this->colors[] = $color;
        $cost = 7;
        $this->cash -= $cost;
        if ($this->cash < 0) {
            throw new BgaUserException("Not enough money to join an airline alliance (\${$cost})");
        }
        $val = join(',', $this->colors);
        self::DbQuery("UPDATE plane SET cash = {$this->cash}, colors = '$val' WHERE player_id = {$this->id}");
        if ($this->color == null) {
            // Assign the player's primary color
            $owner = self::getUniqueValueFromDB("SELECT player_id FROM plane WHERE color = '$color' LIMIT 1");
            if ($owner != null) {
                throw new BgaUserException("Another player already selected $color as their primary airline alliance.");
            }
            $this->color = $color;
            $this->currentNode = N_COLOR_REF[$color]['startNode'];
            $hex = N_COLOR_REF[$color]['hex'];
            self::DbQuery("UPDATE plane SET color = '$color', current_node = '{$this->currentNode}' WHERE player_id = {$this->id}");
            self::DbQuery("UPDATE player SET player_color = '$hex' WHERE player_id = {$this->id}");
        }
        return $cost;
    }

    public function buyExtraSeat(): int
    {
        if ($this->extraSeat) {
            throw new BgaUserException("You already own the temporary seat");
        }
        $owner = self::getUniqueValueFromDB("SELECT player_id FROM plane WHERE extra_seat = 1");
        if ($owner != null) {
            throw new BgaUserException("Another player already owns the temporary seat");
        }
        $this->extraSeat = true;
        $cost = 2;
        $this->cash -= $cost;
        if ($this->cash < 0) {
            throw new BgaUserException("Not enough money to purchase the temporary seat (\${$cost})");
        }
        self::DbQuery("UPDATE plane SET cash = {$this->cash}, extra_seat = 1 WHERE player_id = {$this->id}");
        return $cost;
    }

    public function buyExtraSpeed(): int
    {
        if ($this->extraSpeed) {
            throw new BgaUserException("You already own the temporary speed");
        }
        $owner = self::getUniqueValueFromDB("SELECT player_id FROM plane WHERE extra_seat = 1");
        if ($owner != null) {
            throw new BgaUserException("Another player already owns the temporary speed");
        }
        $this->extraSpeed = true;
        $cost = 1;
        $this->cash -= $cost;
        if ($this->cash < 0) {
            throw new BgaUserException("Not enough money to purchase the temporary speed (\${$cost})");
        }
        self::DbQuery("UPDATE plane SET cash = {$this->cash}, extra_speed = 1 WHERE player_id = {$this->id}");
        return $cost;
    }

    public function buySeat(): int
    {
        $this->seats++;
        if ($this->seats > 5) {
            throw new BgaUserException("You already own the maximum seats (5)");
        }
        $cost = N_SEAT_REF[$this->seats];
        $this->cash -= $cost;
        if ($this->cash < 0) {
            throw new BgaUserException("Not enough money to purchase seat {$this->seats} (\${$cost})");
        }
        self::DbQuery("UPDATE plane SET cash = {$this->cash}, seats = {$this->seats} WHERE player_id = {$this->id}");
        return $cost;
    }

    public function buySpeed(): int
    {
        $this->speed++;
        if ($this->speed > 9) {
            throw new BgaUserException("You already own the maximum speed (9)");
        }
        $cost = N_SPEED_REF[$this->speed];
        $this->cash -= $cost;
        if ($this->cash < 0) {
            throw new BgaUserException("Not enough money to purchase speed {$this->speed} (\${$cost})");
        }
        self::DbQuery("UPDATE plane SET cash = {$this->cash}, speed = {$this->speed} WHERE player_id = {$this->id}");
        return $cost;
    }

    public function move(string $currentNode, int $fuelCost): void
    {
        $this->priorNode = $this->currentNode;
        $this->currentNode = $currentNode;
        $this->fuel = -$fuelCost;
        if ($this->fuel == -1 && $this->extraSpeed) {
            // Return extra speed
            $this->fuel = 0;
            $this->extraSpeed = false;
            self::DbQuery("UPDATE plane SET extra_speed = NULL WHERE player_id = {$this->id}");
        }
        if ($this->fuel < 0) {
            throw new BgaUserException("Not enough fuel to reach $currentNode ($fuelCost moves)");
        }
        self::DbQuery("UPDATE plane SET prior_node = {$this->priorNode}, curent_node = {$this->currentNode}, fuel = {$this->fuel} WHERE player_id = {$this->id}");
    }

    public function pickPassenger(NPax $pax): void
    {
        if ($this->currentNode != $pax->getCurrentNode()) {
            throw new BgaVisibleSystemException("Passenger cannot be picked up from a distant location ({$pax->getCurrentNode()} vs. {$this->currentNode})");
        }
        $count = self::getUniqueValueFromDB("SELECT COUNT(1) FROM pax WHERE player_id = {$this->id} AND status IN ('SEAT')");
        if ($count == $this->seats) {
            if ($this->extraSeat && intval(self::getUniqueValueFromDB("SELECT COUNT(1) FROM pax WHERE status IN ('EXTRA_SEAT')")) == 0) {
                // Extra seat is owned and empty
                self::DbQuery("UPDATE pax SET anger = 0, player_id = {$this->id}, status = 'EXTRA_SEAT' WHERE pax_id = {$pax->getId()}");
            } else {
                throw new BgaUserException("No empty seats");
            }
        } else {
            self::DbQuery("UPDATE pax SET anger = 0, player_id = {$this->id}, status = 'SEAT' WHERE pax_id = {$pax->getId()}");
        }
    }

    public function dropPassenger(NPax $pax): void
    {
        if ($this->currentNode == $pax->getCurrentNode()) {
            throw new BgaUserException("Passenger cannot be returned to the same location ({$pax->getCurrentNode()})");
        }
        if ($pax->getStatus() == 'EXTRA_SEAT') {
            // Return extra seat
            $this->extraSeat = false;
            self::DbQuery("UPDATE plane SET extra_seat = NULL WHERE player_id = {$this->id}");
        }
        if ($this->currentNode == $pax->getEndNode()) {
            // Deliver the passenger for cash
            $this->cash += $pax->getCash();
            self::DbQuery("UPDATE pax SET current_node = NULL, player_id = {$this->id}, status = 'DELIVERED' WHERE pax_id = {$pax->getId()}");
            self::DbQuery("UPDATE plane SET cash = {$this->cash} WHERE player_id = {$this->id}");
        } else {
            // Return to port
            self::DbQuery("UPDATE pax SET current_node = '{$this->currentNode}', player_id = NULL, status = 'PORT' WHERE pax_id = {$pax->getId()}");
        }
    }

    public function reset(): void
    {
        // Refuel the plane
        $this->fuel = $this->speed;
        self::DbQuery("UPDATE plane SET fuel = {$this->fuel} WHERE player_id = {$this->id}");

        // Reconcile cash
        $cash = intval(self::getUniqueValueFromDB("SELECT SUM(cash) FROM pax WHERE player_id = NULL AND status = 'DELIVERED'"));
        if ($this->cash != $cash) {
            // Spend the minimum number of passengers
            // TODO
        }
    }
}
