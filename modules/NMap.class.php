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
        $this->addRoute('ATL', 'DFW', 2, 'GREEN');
        $this->addRoute('ATL', 'JFK', 2, null);
        $this->addRoute('ATL', 'MIA', 1, null);
        $this->addRoute('ATL', 'ORD', 2, 'GREEN');
        $this->addRoute('DEN', 'DFW', 2, 'PURPLE');
        $this->addRoute('DEN', 'LAX', 2, 'ORANGE');
        $this->addRoute('DEN', 'ORD', 2, 'RED');
        $this->addRoute('DEN', 'SFO', 2, null);
        $this->addRoute('DFW', 'LAX', 3, null);
        $this->addRoute('DFW', 'MIA', 3, 'PURPLE');
        $this->addRoute('JFK', 'ORD', 2, null);
        $this->addRoute('LAX', 'MIA', 4, 'ORANGE');
        $this->addRoute('LAX', 'SFO', 1, null);

        if ($playerCount >= 4) {
            // 4-5 player map with Seattle
            $this->addRoute('SEA', 'DEN', 2, 'BLUE');
            $this->addRoute('SEA', 'JFK', 4, 'BLUE');
            $this->addRoute('SEA', 'ORD', 3, 'RED');
            $this->addRoute('SEA', 'SFO', 2, null);
        } else {
            // 2-3 player map without Seattle
            $this->addRoute('ORD', 'SFO', 4, 'RED');
        }

        // Add weather
        foreach ($dbrows as $dbrow) {
            $weatherId = intval($dbrow['weather_id']);
            $token = $dbrow['token'];
            $nodeId = $dbrow['location'];
            if ($nodeId != null) {
                $node = $this->nodes[$nodeId];
                $node->setWeather($token);
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

    protected function addRoute(string $port1, string $port2, int $distance, ?string $color)
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
            $nextNode = $this->addHop($routeId, $color);
            $nextNode->connect($priorNode);
            $route[$nextNode->getId()] = $nextNode;
            $priorNode = $nextNode;
        }
        $priorNode->connect($zNode);
    }

    protected function addPort(string $nodeId): NNodePort
    {
        $portNode = new NNodePort($nodeId);
        $this->nodes[$portNode->getId()] = &$portNode;
        return $portNode;
    }

    protected function addHop(string $nodeId, ?string $color): NNodeHop
    {
        $count = count($this->routes[$nodeId]) + 1;
        $hopNode = new NNodeHop("$nodeId-$count", $color, null);
        $this->nodes[$hopNode->getId()] = &$hopNode;
        return $hopNode;
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
                $this->nodes[$nodeId]->setWeather(null);
            }
            $routeId = array_pop($routeIds);
            $route = &$this->routes[$routeId];
            $node = $route[array_rand($route)];
            $node->setWeather($token);
            $this->weather[$weatherId] = $node;
            self::DbQuery("UPDATE weather SET current_node = {$node->getId()} WHERE weather_id = $weatherId");
        }
    }
}
