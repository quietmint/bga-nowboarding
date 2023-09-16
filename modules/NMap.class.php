<?php

class NMap extends APP_GameClass implements JsonSerializable
{
    public string $name;
    public array $nodes = [];
    public array $routes = [];
    public array $weather = [];

    public function __construct(int $playerCount, ?int $optionMap, array $weather)
    {
        // Build the map
        $this->addRoute('ATL', 'DEN', 3, null);
        $this->addRoute('ATL', 'DFW', 2, 'ATL');
        $this->addRoute('ATL', 'JFK', 2, null);
        $this->addRoute('MIA', 'ATL', 1, null);
        $this->addRoute('ATL', 'ORD', 2, 'ATL');
        $this->addRoute('DEN', 'DFW', 2, 'DFW');
        $this->addRoute('DEN', 'LAX', 2, 'LAX');
        $this->addRoute('ORD', 'DEN', 2, 'ORD');
        $this->addRoute('SFO', 'DEN', 2, null);
        $this->addRoute('DFW', 'LAX', 3, null);
        $this->addRoute('DFW', 'MIA', 2, 'DFW');
        $this->addRoute('JFK', 'ORD', 2, null);
        $this->addRoute('LAX', 'MIA', 4, 'LAX');
        $this->addRoute('LAX', 'SFO', 1, null);


        if ($playerCount >= 4 || $optionMap == N_MAP_SEA) {
            // 4-5 player map with Seattle
            $this->name = "map45";
            $this->addRoute('DEN', 'SEA', 2, 'SEA');
            $this->addRoute('JFK', 'SEA', 4, 'SEA');
            $this->addRoute('SEA', 'ORD', 3, 'ORD');
            $this->addRoute('SEA', 'SFO', 2, null);
        } else {
            // 2-3 player map without Seattle
            $this->name = "map23";
            $this->addRoute('SFO', 'ORD', 3, 'ORD');
        }

        // Add weather
        $this->weather = $weather;
        foreach ($this->weather as $location => $type) {
            if ($type == 'SLOW') {
                $node = @$this->nodes[$location];
                if ($node != null) {
                    $this->addStorm($node);
                    $this->weather["{$location}w"] = $type;
                }
            }
        }
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

    private function addRoute(string $a, string $z, int $distance, ?string $alliance)
    {
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
        $hopNode = new NNode("$routeId$count", $alliance);
        $this->nodes[$hopNode->id] = &$hopNode;
        return $hopNode;
    }

    private function addStorm(NNode &$hopNode): NNode
    {
        $stormNode = new NNode("{$hopNode->id}w", $hopNode->alliance);
        $this->nodes[$stormNode->id] = &$stormNode;
        $first = reset($hopNode->connections);
        $hopNode->disconnect($first);
        $stormNode->connect($first);
        $stormNode->connect($hopNode);
        return $stormNode;
    }

    // ----------------------------------------------------------------------

    public function getPossibleMoves(NPlane $plane): array
    {
        $fuelMax = $plane->speedRemain + ($plane->tempSpeed ? 1 : 0);
        // VIP Nervous
        $planeHasNervous = intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `pax` WHERE `player_id` = {$plane->id} AND `status` = 'SEAT' AND `vip` = 'NERVOUS'")) > 0;
        $visited = [];
        $best = [];
        $queue = [new NMove(0, $this->nodes[$plane->location], [], false)];
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
                    $fuel = $move->fuel + ($weather == 'FAST' ? 0 : 1);
                    $path = $pathString . $connectedNode->id . "/";

                    // VIP Nervous: Can't travel through weather
                    if ($planeHasNervous && $weather != null) {
                        continue;
                    }

                    // Can't exceed maximum fuel
                    if ($fuel > $fuelMax) {
                        continue;
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

                    $nextQueue[] = new NMove($fuel, $connectedNode, $move->path);
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
