<?php

class NMap extends APP_GameClass implements JsonSerializable
{
    public string $name;
    public array $nodes = [];
    public array $routes = [];
    public array $weather = [];

    public function __construct(int $playerCount, int $optionMap, array $weather)
    {
        // Build the map
        $this->addRoute('ATL', 'DEN', 3, null);
        $this->addRoute('ATL', 'DFW', 2, 'ATL');
        $this->addRoute('ATL', 'JFK', 2, null);
        $this->addRoute('ATL', 'MIA', 1, null);
        $this->addRoute('ATL', 'ORD', 2, 'ATL');
        $this->addRoute('DEN', 'DFW', 2, 'DFW');
        $this->addRoute('DEN', 'LAX', 2, 'LAX');
        $this->addRoute('DEN', 'ORD', 2, 'ORD');
        $this->addRoute('DEN', 'SFO', 2, null);
        $this->addRoute('DFW', 'LAX', 3, null);
        $this->addRoute('DFW', 'MIA', 2, 'DFW');
        $this->addRoute('JFK', 'ORD', 2, null);
        $this->addRoute('LAX', 'MIA', 4, 'LAX');
        $this->addRoute('LAX', 'SFO', 1, null);

        if ($playerCount >= 4 || $optionMap == N_MAP_SEA) {
            // 4-5 player map with Seattle
            $this->name = "map45";
            $this->addRoute('SEA', 'DEN', 2, 'SEA');
            $this->addRoute('SEA', 'JFK', 4, 'SEA');
            $this->addRoute('SEA', 'ORD', 3, 'ORD');
            $this->addRoute('SEA', 'SFO', 2, null);
        } else {
            // 2-3 player map without Seattle
            $this->name = "map23";
            $this->addRoute('ORD', 'SFO', 3, 'ORD');
        }

        $this->weather = $weather;
    }

    public function jsonSerialize(): array
    {
        $nodes = [];
        foreach ($this->nodes as $id => $node) {
            $nodes[$id] = $node->alliance;
        }
        return [
            'name' => $this->name,
            'nodes' => $nodes,
            'weather' => $this->weather,
        ];
    }

    private function addRoute(string $port1, string $port2, int $distance, ?string $alliance)
    {
        $a = min($port1, $port2);
        $z = max($port1, $port2);
        $routeId = "$a$z";
        if (!array_key_exists($a, $this->nodes)) {
            $this->addPort($a);
        }
        if (!array_key_exists($z, $this->nodes)) {
            $this->addPort($z);
        }
        $aNode = &$this->nodes[$a];
        $zNode = &$this->nodes[$z];

        $route = [];
        $this->routes[$routeId] = &$route;
        $priorNode = $aNode;
        for ($i = 1; $i <= $distance; $i++) {
            $nextNode = $this->addHop($routeId, $alliance);
            $nextNode->connect($priorNode);
            $route[$nextNode->id] = $nextNode;
            $priorNode = $nextNode;
        }
        $priorNode->connect($zNode);
    }

    private function addPort(string $nodeId): NNode
    {
        $portNode = new NNode($nodeId);
        $this->nodes[$portNode->id] = &$portNode;
        return $portNode;
    }

    private function addHop(string $routeId, ?string $alliance): NNode
    {
        $count = count($this->routes[$routeId]) + 1;
        $hopNode = new NNode("$routeId$count", $alliance, null);
        $this->nodes[$hopNode->id] = &$hopNode;
        return $hopNode;
    }

    // ----------------------------------------------------------------------

    public function getPossibleMoves(NPlane $plane): array
    {
        $fuelMax = $plane->speedRemain;
        if ($plane->tempSpeed) {
            $fuelMax++;
        }
        // VIP Nervous
        $planeHasNervous = intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `pax` WHERE `player_id` = {$plane->id} AND `status` = 'SEAT' AND `vip` = 'NERVOUS'")) > 0;
        $visited = [];
        $best = [];
        $queue = [new NMove(intval($plane->speedPenalty), $this->nodes[$plane->location], [], false)];
        while (!empty($queue)) {
            $nextQueue = [];
            foreach ($queue as $move) {
                $pathString = $move->getPathString();
                $visited[$pathString] = true;
                if (!array_key_exists($move->location, $best) || $best[$move->location]->fuel > $move->fuel) {
                    self::debug("getPossibleMoves best: $move // ");
                    $best[$move->location] = $move;
                }
                foreach ($move->node->connections as $connectedNode) {
                    $weather = $this->getNodeWeather($connectedNode);
                    $fuel = $move->fuel + N_REF_WEATHER_SPEED[$weather];
                    $path = $pathString . $connectedNode->id . "/";
                    $penalty = false;

                    // VIP Nervous: Can't travel through weather
                    if ($planeHasNervous && $weather != null) {
                        continue;
                    }

                    // Can't exceed maximum fuel
                    if ($fuel > $fuelMax) {
                        if ($weather == 'SLOW' && $fuel - 1 == $fuelMax) {
                            // Except for the final move onto a storm, allow them to move 1 now + 1 penalty later
                            $penalty = true;
                            $fuel -= 1;
                        } else {
                            continue;
                        }
                    }

                    // Can't travel on special routes without the alliance
                    if ($connectedNode->alliance != null && !in_array($connectedNode->alliance, $plane->alliances)) {
                        continue;
                    }

                    // Can't backtrace
                    if (in_array($connectedNode->id, $move->path)) {
                        continue;
                    }

                    // Can't repeat exact same path
                    if (array_key_exists($path, $visited)) {
                        continue;
                    }

                    $nextQueue[] = new NMove($fuel, $connectedNode, $move->path, $penalty);
                }
            }
            $queue = $nextQueue;
        }
        self::debug("getPossibleMoves complete: " . count($visited) . " iterations // ");
        // Remove your current location
        unset($best[$plane->location]);
        // Remove fast weather
        foreach ($this->weather as $location => $type) {
            if ($type == 'FAST' && array_key_exists($location, $best)) {
                unset($best[$location]);
            }
        }
        return $best;
    }

    private function getNodeWeather(NNode $node): ?string
    {
        $weather = null;
        if (array_key_exists($node->id, $this->weather)) {
            $weather = $this->weather[$node->id];
        }
        return $weather;
    }
}
