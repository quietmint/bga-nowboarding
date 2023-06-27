<?php

class NMap extends APP_GameClass implements JsonSerializable
{
    public array $nodes = [];
    public array $routes = [];
    public array $weather = [];

    public function __construct(int $playerCount, array $dbrows)
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
        $this->addRoute('DFW', 'MIA', 3, 'DFW');
        $this->addRoute('JFK', 'ORD', 2, null);
        $this->addRoute('LAX', 'MIA', 4, 'LAX');
        $this->addRoute('LAX', 'SFO', 1, null);

        if ($playerCount >= 4) {
            // 4-5 player map with Seattle
            $this->addRoute('SEA', 'DEN', 2, 'SEA');
            $this->addRoute('SEA', 'JFK', 4, 'SEA');
            $this->addRoute('SEA', 'ORD', 3, 'ORD');
            $this->addRoute('SEA', 'SFO', 2, null);
        } else {
            // 2-3 player map without Seattle
            $this->addRoute('ORD', 'SFO', 4, 'ORD');
        }

        // Add weather
        foreach ($dbrows as $dbrow) {
            $weatherId = intval($dbrow['weather_id']);
            $token = $dbrow['token'];
            $nodeId = $dbrow['location'];
            if ($nodeId != null) {
                $node = $this->nodes[$nodeId];
                $node->weather = $token;
                $this->weather[$weatherId] = $node;
            }
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'nodes' => $this->nodes,
        ];
    }

    protected function addRoute(string $port1, string $port2, int $distance, ?string $alliance)
    {
        $a = min($port1, $port2);
        $z = max($port1, $port2);
        $routeId = "$a-$z";
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

    protected function addPort(string $nodeId): NNodePort
    {
        $portNode = new NNodePort($nodeId);
        $this->nodes[$portNode->id] = &$portNode;
        return $portNode;
    }

    protected function addHop(string $nodeId, ?string $alliance): NNodeHop
    {
        $count = count($this->routes[$nodeId]) + 1;
        $hopNode = new NNodeHop("$nodeId-$count", $alliance, null);
        $this->nodes[$hopNode->id] = &$hopNode;
        return $hopNode;
    }

    // ----------------------------------------------------------------------

    public function getPossibleMoves(NPlane $plane): array
    {
        $max = 9; //$plane->speedRemain;
        $start = $this->nodes[$plane->location];
        $visited = [];
        $best = [];
        self::debug("getPossibleMoves start: node={$start->id} // ");
        $queue = [[
            'fuel' => 0,
            'node' => $start,
            'path' => "/$start->id/",
        ]];
        while (!empty($queue)) {
            $next_queue = [];
            foreach ($queue as $q) {
                $fuel = $q['fuel'];
                $node = $q['node'];
                $path = $q['path'];
                $visited[$path] = true;
                if (!array_key_exists($node->id, $best) || $best[$node->id]['fuel'] > $fuel) {
                    self::debug("getPossibleMoves best: node={$node->id}, fuel={$fuel}, path={$path} // ");
                    $best[$node->id] = [
                        'fuel' => $fuel,
                        'path' => $path,
                    ];
                }
                foreach ($this->getConnectionsFuel($node, $q['fuel']) as $c) {
                    // check fuel
                    if ($c['fuel'] > $max) {
                        continue;
                    }
                    // check alliance
                    if ($c['alliance'] != null && !in_array($c['alliance'], $plane->alliances)) {
                        continue;
                    }
                    // check path overlap
                    if (strpos($path, "/" . $c['node']->id . "/") !== false) {
                        continue;
                    }
                    $c['path'] = $path . $c['node']->id . "/";
                    // check path visited
                    if (array_key_exists($c['path'], $visited)) {
                        continue;
                    }
                    $next_queue[] = $c;
                }
            }
            $queue = $next_queue;
        }
        self::debug("getPossibleMoves complete: " . count($visited) . " iterations // ");
        unset($best[$plane->location]);
        return $best;
    }

    private function getConnectionsFuel(NNode $node, int $distance): array
    {
        $out = [];
        $weatherSpeed = 0;
        if ($node instanceof NNodeHop) {
            $weatherSpeed = N_REF_WEATHER_SPEED[$node->weather];
        }
        foreach ($node->connections as $cNode) {
            $fuel = $distance + $weatherSpeed + 1;
            $alliance = null;
            if ($cNode instanceof NNodeHop) {
                $alliance = $cNode->alliance;
            }
            $out[] = [
                'alliance' => $alliance,
                'fuel' => $fuel,
                'node' => $cNode,
            ];
        }
        return $out;
    }

    // ----------------------------------------------------------------------

    public function resetWeather(): void
    {
        $dbrows = self::getObjectListFromDB("SELECT * FROM weather");
        // Select 2, 4, or 6 random routes
        $routeIds = array_rand($this->routes, count($dbrows));
        foreach ($dbrows as $dbrow) {
            $weatherId = intval($dbrow['weather_id']);
            $token = $dbrow['token'];
            $nodeId = $dbrow['current_node'];
            if ($nodeId != null) {
                // Remove old weather
                $this->nodes[$nodeId]->weather = null;
            }
            $routeId = array_pop($routeIds);
            $route = &$this->routes[$routeId];
            $node = $route[array_rand($route)];
            $node->weather = $token;
            $this->weather[$weatherId] = $node;
            self::DbQuery("UPDATE weather SET current_node = {$node->getId()} WHERE weather_id = $weatherId");
        }
    }
}
