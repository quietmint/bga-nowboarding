<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Now Boarding implementation : © quietmint
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';
require_once 'modules/constants.inc.php';
require_once 'modules/NGameOverException.class.php';
require_once 'modules/NMap.class.php';
require_once 'modules/NMove.class.php';
require_once 'modules/NNode.class.php';
require_once 'modules/NPax.class.php';
require_once 'modules/NPlane.class.php';

class NowBoarding extends Table
{

    public function debug_setupVips()
    {
        $optionVip = $this->getOption(N_OPTION_VIP);
        $playerCount = $this->getPlayersNumber();
        $this->setupVips($optionVip, $playerCount);
        $vips = $this->globals->get('vips');
        $this->notifyAllPlayers('vip', 'Morning: ' . join(', ', $vips['MORNING']), [
            'overall' => $this->getVipOverall($vips)
        ]);
        $this->notifyAllPlayers('message', 'Afternoon: ' . join(', ', $vips['NOON']), []);
        $this->notifyAllPlayers('message', 'Evening: ' . join(', ', $vips['NIGHT']), []);
    }

    public function debug_plans()
    {
        $this->notifyAllPlayers('plans', '', ['plans' => $this->getFlightPlans()]);
    }

    public function debug_reloadPlayersBasicInfos()
    {
        $this->reloadPlayersBasicInfos();
    }

    public function test()
    {
        // insert into pax select player_id as pax_id, 0 as anger, 44 as cash, 'DEN' as destination, null as location, 0 as moves, 1 as optimal, 'DEN' as origin, player_id, 'CASH' as status, null as vip from player;
    }

    public function __construct()
    {
        parent::__construct();
        $this->bSelectGlobalsForUpdate = true;
        $this->initGameStateLabels([]);
    }

    protected function getGameName(): string
    {
        // Used for translations and stuff. Please do not modify.
        return "nowboarding";
    }

    protected function setupNewGame($players, $options = [])
    {
        // Create players and planes
        $playerCount = count($players);
        foreach ($players as $player_id => $player) {
            $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ($player_id, '000000', '" . $player['player_canal'] . "', '" . addslashes($player['player_name']) . "', '" . addslashes($player['player_avatar']) . "')";
            $this->DbQuery($sql);
            $this->DbQuery("INSERT INTO `plane` (`player_id`) VALUES ($player_id)");
        }
        $this->reloadPlayersBasicInfos();
        $optionVip = $this->getOption(N_OPTION_VIP);
        $optionMap = $this->getOption(N_OPTION_MAP);

        // Erase beginner flags (affects giveExtraTime)
        $this->DbQuery("UPDATE `player` SET `player_beginner` = NULL");
        $this->reloadPlayersBasicInfos();

        // Table statistics
        $this->initStat('table', 'complaintPort', 0);
        $this->initStat('table', 'complaintFinale', 0);
        if ($optionVip) {
            $this->initStat('table', 'complaintVip', 0);
            $this->initStat('table', 'vipMORNING', 0);
            $this->initStat('table', 'vipNOON', 0);
            $this->initStat('table', 'vipNIGHT', 0);
        }
        $this->initStat('table', 'moves', 0);
        $this->initStat('table', 'movesFAST', 0);
        $this->initStat('table', 'movesSLOW', 0);
        $this->initStat('table', 'movesNormal', 0);
        $this->initStat('table', 'movesATL', 0);
        $this->initStat('table', 'movesDFW', 0);
        $this->initStat('table', 'movesLAX', 0);
        $this->initStat('table', 'movesORD', 0);
        if ($playerCount >= 4 || $optionMap == N_MAP_SEA) {
            $this->initStat('table', 'movesSEA', 0);
        }
        $this->initStat('table', 'pax', 0);
        $this->initStat('table', 'journeyAvg', 0);
        $this->initStat('table', 'journeyMax', 0);
        $this->initStat('table', 'alliances', 0);
        $this->initStat('table', 'seat', 0);
        $this->initStat('table', 'speed', 0);
        $this->initStat('table', 'tempSeat', 0);
        $this->initStat('table', 'tempSeatUnused', 0);
        $this->initStat('table', 'tempSpeed', 0);
        $this->initStat('table', 'tempSpeedUnused', 0);

        // Player statistics
        $this->initStat('player', 'moves', 0);
        $this->initStat('player', 'movesFAST', 0);
        $this->initStat('player', 'movesSLOW', 0);
        $this->initStat('player', 'movesNormal', 0);
        $this->initStat('player', 'movesATL', 0);
        $this->initStat('player', 'movesDFW', 0);
        $this->initStat('player', 'movesLAX', 0);
        $this->initStat('player', 'movesORD', 0);
        $this->initStat('player', 'ATL', 0);
        $this->initStat('player', 'DEN', 0);
        $this->initStat('player', 'DFW', 0);
        $this->initStat('player', 'JFK', 0);
        $this->initStat('player', 'LAX', 0);
        $this->initStat('player', 'MIA', 0);
        $this->initStat('player', 'ORD', 0);
        $this->initStat('player', 'SFO', 0);
        if ($playerCount >= 4 || $optionMap == N_MAP_SEA) {
            $this->initStat('player', 'movesSEA', 0);
            $this->initStat('player', 'SEA', 0);
        }
        $this->initStat('player', 'pax', 0);
        $this->initStat('player', 'cash', 0);
        $this->initStat('player', 'overpay', 0);
        $this->initStat('player', 'alliances', 0);
        $this->initStat('player', 'seat', 1);
        $this->initStat('player', 'speed', 3);
        $this->initStat('player', 'tempSeat', 0);
        $this->initStat('player', 'tempSeatUnused', 0);
        $this->initStat('player', 'tempSpeed', 0);
        $this->initStat('player', 'tempSpeedUnused', 0);
        $this->initStat('player', 'payCustom', 0);

        // Setup weather
        $this->globals->set('progression', 0);
        $this->globals->set('hour', 'PREFLIGHT');
        $this->setupWeather($playerCount);

        // Setup VIPs
        if ($optionVip) {
            $this->setupVips($optionVip, $playerCount);
        }

        $this->createUndo();
    }

    private function setupWeather(int $playerCount): void
    {
        // Determine how many weather tokens are needed
        $tokens = ['FAST', 'SLOW', 'FAST', 'SLOW', 'FAST', 'SLOW'];
        if ($playerCount == 2) {
            array_splice($tokens, 2);
        } else if ($playerCount == 3) {
            array_splice($tokens, 4);
        }

        // Select 6, 12, or 18 random routes
        $weather = [
            'MORNING' => [],
            'NOON' => [],
            'NIGHT' => [],
        ];
        $map = $this->getMap(false);
        $routes = $this->getRandomSlice($map->routes, count($weather) * count($tokens));
        foreach ($weather as $hour => $null) {
            foreach ($tokens as $token) {
                $route = array_pop($routes);
                // Select a random node on this route
                $node = $this->getRandomValue($route);
                $weather[$hour][$node->id] = $token;
            }
        }

        // Final round keeps the same weather
        $weather['FINALE'] = $weather['NIGHT'];
        $this->globals->set('weather', $weather);
    }

    private function setupVips(int $optionVip, int $playerCount): void
    {
        // Determine how many VIPs we need
        $optionCount = $this->getOption(N_OPTION_VIP_COUNT);
        $vipMax = N_REF_HOUR_ROUND[$playerCount]['MORNING'] +
            N_REF_HOUR_ROUND[$playerCount]['NOON'] +
            N_REF_HOUR_ROUND[$playerCount]['NIGHT'];
        $vipCount = $playerCount + 2;
        if ($optionCount == N_VIP_INCREASE) {
            $vipCount = $playerCount + 4;
        } else if ($optionCount == N_VIP_DOUBLE) {
            $vipCount = min($vipCount * 2, $vipMax);
        }

        $vips = [
            'MORNING' => [],
            'NOON' => [],
            'NIGHT' => [],
        ];
        $vipAdded = 0;
        $possible = null;
        while ($vipAdded < $vipCount) {
            if (empty($possible)) {
                $possible = $this->getPossibleVips($optionVip);
            }
            list($key, $hour) = array_pop($possible);
            if (count($vips[$hour]) < N_REF_HOUR_ROUND[$playerCount][$hour]) {
                $vips[$hour][] = $key;
                $vipAdded++;
            }
        }

        // Save the results
        $this->globals->set('vips', $vips);
        foreach ($vips as $hour => $keys) {
            $this->setStat(count($keys), "vip$hour");
        }
    }

    private function getPossibleVips(int $optionVip): array
    {
        $possible = [];
        foreach (N_REF_VIP as $key => $vip) {
            if ($optionVip == N_VIP_ALL || $optionVip == $vip['set']) {
                $vipHours = $vip['hours'];
                shuffle($vipHours);
                for ($i = 0; $i < $vip['count']; $i++) {
                    $possible[] = [$key, array_pop($vipHours)];
                }
            }
        }
        shuffle($possible);
        return $possible;
    }

    public function checkVersion(int $clientVersion): void
    {
        if ($clientVersion != $this->getOption(N_BGA_VERSION)) {
            throw new BgaUserException('!!!checkVersion');
        }
    }

    protected function getAllDatas(): array
    {
        $bgaClock = $this->getOption(N_BGA_CLOCK);
        $plans = $this->gamestate->state()['name'] == 'gameEnd' ? $this->getFlightPlans() : null;
        $players = $this->getCollectionFromDb("SELECT player_id id, player_score score FROM player");
        return [
            'complaint' => $this->countComplaint(),
            'countToWin' => $this->globals->get('countToWin') ?? '??',
            'hour' => $this->getHourInfo(),
            'hourTiming' => $this->globals->get('hourTiming'),
            'map' => $this->getMap(),
            'noTimeLimit' => in_array($bgaClock, N_REF_BGA_CLOCK_UNLIMITED),
            'pax' => $this->filterPax($this->getPaxByStatus(['SECRET', 'PORT', 'SEAT'])),
            'planes' => $this->getPlanesByIds(),
            'plans' => $plans,
            'players' => $players,
            'timer' => (in_array($bgaClock, N_REF_BGA_CLOCK_REALTIME) ? $this->getOption(N_OPTION_TIMER) : 0) * (count($players) * 5 + 20),
            'version' => $this->getOption(N_BGA_VERSION),
            'vip' => $this->getOption(N_OPTION_VIP) ? $this->getVipOverall($this->globals->get('vips')) : null,
        ];
    }

    public function getGameProgression(): int
    {
        $playerCount = $this->getPlayersNumber();
        return round($this->globals->get('progression', 0) / N_REF_PROGRESSION[$playerCount] * 100);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// States
    ////////////

    /*
     * SETUP
     * Each player builds their plane
     */
    public function stInitPrivate()
    {
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
    }

    /*
     * SETUP #1
     * Each player chooses a starting airport and alliance
     */
    public function argBuildAlliance(int $playerId): array
    {
        $buys = [];
        $claimed = [];
        $playerCount = $this->getPlayersNumber();
        if ($playerCount <= 3 && $this->getOption(N_OPTION_MAP) != N_MAP_SEA) {
            // Exclude Seattle for 2-3 players
            $claimed[] = 'SEA';
        }
        $possible = array_diff(array_keys(N_REF_ALLIANCE_COLOR), $claimed);
        foreach ($possible as $alliance) {
            $ownerId = $this->getOwnerId("SUBSTRING_INDEX(`alliances`, ',', 1) = '$alliance'");
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $alliance,
                'cost' => 0,
                'enabled' => true,
                'ownerId' => $ownerId,
            ];
        }
        return ['buys' => $buys];
    }

    /*
     * SETUP #2
     * Each player chooses a second alliance (2-player only)
     */
    public function argBuildAlliance2(int $playerId): array
    {
        $buys = [];
        $claimed = $this->getObjectListFromDB("SELECT `alliances` FROM `plane` WHERE `player_id` = $playerId", true);
        if ($this->getOption(N_OPTION_MAP) != N_MAP_SEA) {
            // Exclude Seattle for 2-3 players
            $claimed[] = 'SEA';
        }
        $possible = array_diff(array_keys(N_REF_ALLIANCE_COLOR), $claimed);
        foreach ($possible as $alliance) {
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $alliance,
                'cost' => 0,
                'enabled' => true,
            ];
        }
        return ['buys' => $buys];
    }

    /*
     * SETUP #3
     * Each player chooses a seat or speed upgrade
     */
    public function argBuildUpgrade(int $playerId): array
    {
        return [
            'buys' => [
                [
                    'type' => 'SEAT',
                    'seat' => 2,
                    'cost' => 0,
                    'enabled' => true,
                ],
                [
                    'type' => 'SPEED',
                    'speed' => 4,
                    'cost' => 0,
                    'enabled' => true,
                ],
            ],
        ];
    }

    public function autoUpgrade(NPlane $plane): bool
    {
        $optionUpgrade = $this->getOption(N_OPTION_UPGRADE);
        if ($optionUpgrade > 0) {
            if ($optionUpgrade == N_UPGRADE_SEAT) {
                $this->buySeat($plane);
            } elseif ($optionUpgrade == N_UPGRADE_SPEED) {
                $this->buySpeed($plane);
            } else if ($optionUpgrade == N_UPGRADE_BOTH) {
                $this->buySeat($plane, false);
                $this->buySpeed($plane);
            }
            return true;
        } else {
            return false;
        }
    }

    /*
     * MAINTENANCE
     * Create passengers (first turn only)
     * Increase anger and file complaints
     * Add new passengers
     */

    public function stMaintenance(): void
    {
        // Everybody stop snoozing
        $this->DbQuery("UPDATE `player` SET `snooze` = 0");
        $this->globals->delete('endTime', 'vipWelcome');
        $this->globals->inc('progression', 1);

        try {
            $hourInfo = $this->getHourInfo(true);
            if ($hourInfo['hour'] == 'PREFLIGHT') {
                // Create pax on the first turn
                $this->eraseUndo();
                $this->createPax();
                $pax = $this->getPaxByStatus('PORT');
                $this->notifyAllPlayers('pax', N_REF_MSG['addPax'], [
                    'count' => count($pax),
                    'countToWin' => $this->countToWin(),
                    'pax' => array_values($pax),
                    'location' => $this->getPaxLocations($pax),
                ]);
            } else {
                // Add anger/complaints on subsequent turns
                $this->angerPax();
            }

            if ($hourInfo['hour'] == 'FINALE') {
                // File complaint for every 2 pax
                $count = $this->countPaxByStatus(['PORT', 'SEAT']);
                $complaint = floor($count / 2);
                $this->setStat($complaint, 'complaintFinale');
                if ($complaint > 0) {
                    $this->notifyAllPlayers('complaint', N_REF_MSG['complaintFinale'], [
                        'complaint' => $complaint,
                        'count' => $count,
                        'total' => $this->countComplaint(),
                    ]);
                }

                // End the game
                $this->endGame();
            } else {
                // Advance the hour
                $hourInfo = $this->advanceHour($hourInfo);
                if ($hourInfo['hour'] != 'FINALE') {
                    $this->addPax($hourInfo);
                }
                $this->gamestate->nextState('prepare');
            }
        } catch (NGameOverException $e) {
            $this->endGame();
        }
    }

    /*
     * PREPARE
     * Reset planes
     * Add passengers
     * Players purchase upgrades
     */

    public function argPrepare()
    {
        $hourInfo = $this->getHourInfo();
        $titleMessage = null;
        if ($hourInfo['hour'] == 'FINALE') {
            $titleMessage = $this->getFinaleTitleMessage();
        }
        return [
            'i18n' => ['hourDesc'],
            'hourDesc' => $hourInfo['hourDesc'],
            'round' => array_key_exists('round', $hourInfo) ? "({$hourInfo['round']}/{$hourInfo['total']})" : '',
            'titleMessage' => $titleMessage,
        ];
    }

    public function stPrepare()
    {
        // Reset time to the full amount
        $this->giveExtraTimeAll(9999);
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
        $this->DbQuery("UPDATE `plane` SET `speed_remain` = `speed`");
        // Truly use temps
        $this->DbQuery("UPDATE `plane` SET `temp_seat` = 0 WHERE `temp_seat` = -1");
        $this->DbQuery("UPDATE `plane` SET `temp_speed` = 0 WHERE `temp_speed` = -1");
        $this->createUndo();
        $planes = $this->getPlanesByIds();
        $this->notifyAllPlayers('planes', '', [
            'planes' => array_values($planes)
        ]);

        foreach ($planes as $plane) {
            if ($plane->tempSeat == 1) {
                $this->notifyAllPlayers('message', N_REF_MSG['tempUnused'], [
                    'i18n' => ['temp'],
                    'preserve' => ['tempIcon'],
                    'player_id' => $plane->id,
                    'player_name' => $plane->name,
                    'temp' => clienttranslate('Temporary Seat'),
                    'tempIcon' => 'seat',
                ]);
                $this->incStat(1, 'tempSeatUnused');
                $this->incStat(1, 'tempSeatUnused', $plane->id);
            }
            if ($plane->tempSpeed == 1) {
                $this->notifyAllPlayers('message', N_REF_MSG['tempUnused'], [
                    'i18n' => ['temp'],
                    'preserve' => ['tempIcon'],
                    'player_id' => $plane->id,
                    'player_name' => $plane->name,
                    'temp' => clienttranslate('Temporary Speed'),
                    'tempIcon' => 'speed',
                ]);
                $this->incStat(1, 'tempSpeedUnused');
                $this->incStat(1, 'tempSpeedUnused', $plane->id);
            }
        }
    }

    public function argPrepareBuy(int $playerId): array
    {
        $plane = $this->getPlaneById($playerId);
        $cash = $plane->getCashRemain();
        $buys = [];

        // Seat
        if ($plane->seat < 5) {
            $seat = $plane->seat + 1;
            $cost = N_REF_SEAT_COST[$seat];
            $buys[] = [
                'type' => 'SEAT',
                'seat' => $seat,
                'cost' => $cost,
                'enabled' => $cash >= $cost,
            ];
        }

        // Temp Seat
        $cost = 2;
        $ownerId = $this->getOwnerId("`temp_seat` = 1");
        $buys[] = [
            'type' => 'TEMP_SEAT',
            'cost' => $cost,
            'enabled' => $cash >= $cost,
            'ownerId' => $ownerId,
        ];

        // Speed
        if ($plane->speed < 9) {
            $speed = $plane->speed + 1;
            $cost = N_REF_SPEED_COST[$speed];
            $buys[] = [
                'type' => 'SPEED',
                'speed' => $speed,
                'cost' => $cost,
                'enabled' => $cash >= $cost,
            ];
        }

        // Temp Speed
        $cost = 1;
        $ownerId = $this->getOwnerId("`temp_speed` = 1");
        $buys[] = [
            'type' => 'TEMP_SPEED',
            'cost' => $cost,
            'enabled' => $cash >= $cost,
            'ownerId' => $ownerId,
        ];

        // Alliances
        $cost = 7;
        $claimed = $plane->alliances;
        $playerCount = $this->getPlayersNumber();
        if ($playerCount <= 3 && $this->getOption(N_OPTION_MAP) != N_MAP_SEA) {
            // Exclude Seattle for 2-3 players
            $claimed[] = 'SEA';
        }
        $possible = array_diff(array_keys(N_REF_ALLIANCE_COLOR), $claimed);
        foreach ($possible as $alliance) {
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $alliance,
                'cost' => $cost,
                'enabled' => $cash >= $cost,
            ];
        }

        $args = $this->argPrepare() + [
            'buys' => $buys,
            'cash' => $cash,
            'wallet' => array_values($plane->wallet),
        ];
        if ($plane->debt > 0) {
            $ledger = $this->getLedger($playerId);
            $pay = $this->_argPreparePay($plane);
            $args['ledger'] = $ledger;
            $args['overpay'] = $pay['overpay'];
        }

        return $args;
    }

    public function argPreparePay(int $playerId): array
    {
        $plane = $this->getPlaneById($playerId);
        return $this->_argPreparePay($plane);
    }

    private function _argPreparePay(NPlane $plane): array
    {
        $walletCount = count($plane->wallet);
        $suggestion = null;
        $overpay = null;
        foreach ($this->generatePermutations($plane->wallet) as $p) {
            $thisSum = 0;
            $thisSuggestion = [];
            for ($i = $walletCount - 1; $thisSum < $plane->debt && $i >= 0; $i--) {
                $thisSuggestion[] = $p[$i];
                $thisSum += $p[$i];
            }
            $thisOverpay = $thisSum - $plane->debt;
            if ($suggestion == null || $thisOverpay < $overpay || $thisOverpay == $overpay && count($thisSuggestion) < count($suggestion)) {
                $suggestion = $thisSuggestion;
                $overpay = $thisOverpay;
            }
            if ($overpay == 0) {
                break;
            }
        }

        return [
            'debt' => $plane->debt,
            'overpay' => $overpay,
            'suggestion' => $suggestion,
            'wallet' => array_values($plane->wallet),
        ];
    }

    /*
     * FLIGHT
     * Reveal passengers
     * Start the clock
     * Players transport passengers
     */
    public function stReveal(): void
    {
        // Remove undo info
        $this->eraseUndo();

        // Reveal passengers
        $msg = '';
        $args = [];
        $pax = $this->getPaxByStatus('SECRET', null, null, true);
        $vipNew = $this->globals->get('vipNew', false);
        foreach ($pax as $x) {
            $x->status = 'PORT';

            if ($vipNew) {
                // Make them a VIP
                $vipNew = false;
                $this->globals->set('vipNew', false);
                extract($this->globals->getAll('hour', 'vips'));
                $nextVip = array_pop($vips[$hour]);
                if (!$nextVip) {
                    throw new BgaVisibleSystemException("stReveal: No VIP exists [???]");
                }
                $this->globals->set('vips', $vips);
                $this->notifyAllPlayers('vip', '', [
                    'overall' => $this->getVipOverall($vips)
                ]);
                $x->vip = $nextVip;

                // Apply starting conditions
                if ($x->vip == 'CONVENTION') {
                    // VIP Convention
                    // Choose a destination
                    $map = $this->getMap(false);
                    $optimal = $map->getOptimalMoves();
                    $invalid = $this->getObjectListFromDB("SELECT DISTINCT `origin` FROM `pax` WHERE `status` = 'SECRET' AND `vip` IS NULL", true);
                    $x->destination = $this->getRandomValue(array_diff($map->airports, $invalid));
                    // Update everyone with new destination and fare
                    foreach ($pax as $convention) {
                        $convention->cash = N_REF_FARE[$convention->origin][$x->destination];
                        $convention->destination = $x->destination;
                        $convention->optimal = $optimal[$convention->origin][$x->destination];
                        $convention->vip = $x->vip;
                        $this->DbQuery("UPDATE `pax` SET `cash` = {$convention->cash}, `destination` = '{$convention->destination}', `optimal` = {$convention->optimal}, `vip` = '{$convention->vip}' WHERE `pax_id` = {$convention->id}");
                    }
                } else if ($x->vip == 'CREW' || $x->vip == 'DISCOUNT' || $x->vip == 'RETURN') {
                    // VIP Crew/Discount/Return
                    // Reduce the fare
                    $x->cash = $x->vip == 'DISCOUNT' ? floor($x->cash / 2) : 0;
                    $this->DbQuery("UPDATE `pax` SET `cash` = {$x->cash} WHERE `pax_id` = {$x->id}");
                } else if ($x->vip == 'DOUBLE') {
                    // VIP Double
                    // Create the fugitive
                    $doubleId = $x->id * -1;
                    $optionAnger = $this->getOption(N_OPTION_ANGER, 0);
                    $this->DbQuery("INSERT INTO `pax` (`pax_id`, `anger`, `cash`, `destination`, `location`, `optimal`, `origin`, `status`, `vip`) VALUES ($doubleId, $optionAnger, 0, '{$x->destination}', '{$x->location}', {$x->optimal}, '{$x->origin}', '{$x->status}', '{$x->vip}')");
                    $args['countToWin'] = $this->countToWin();
                } else if ($x->vip == 'GRUMPY') {
                    // VIP Grumpy
                    // Starts with extra anger
                    $x->anger++;
                    $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger} WHERE `pax_id` = {$x->id}");
                } else if ($x->vip == 'LOYAL') {
                    // VIP Loyal
                    // Choose an alliance
                    $possibleAlliances = [];
                    if (array_key_exists($x->origin, N_REF_ALLIANCE_COLOR)) {
                        $possibleAlliances[] = $x->origin;
                    } else if (array_key_exists($x->destination, N_REF_ALLIANCE_COLOR)) {
                        $possibleAlliances[] = $x->destination;
                    }
                    if (empty($possibleAlliances)) {
                        $possibleAlliances = ['ATL', 'DFW', 'LAX', 'ORD'];
                        $playerCount = $this->getPlayersNumber();
                        $optionMap = $this->getOption(N_OPTION_MAP);
                        if ($playerCount >= 4 || $optionMap == N_MAP_SEA) {
                            // Include SEA with 4+ players
                            $possibleAlliances[] = 'SEA';
                        }
                    }
                    $alliance = $this->getRandomValue($possibleAlliances);
                    $x->vip = "LOYAL_$alliance";
                } else if ($x->vip == 'MYSTERY') {
                    // VIP Mystery
                    // Do not reveal
                    $x->status = 'SECRET';
                } else if ($x->vip == 'REUNION') {
                    // VIP Reunion
                    // Choose a destination
                    $y = $this->getFirstValue(array_slice($pax, 1, 1));
                    $map = $this->getMap(false);
                    $optimal = $map->getOptimalMoves();
                    $invalid = [$x->origin, $y->origin];
                    $otherReunions = $this->getObjectListFromDB("SELECT DISTINCT `destination` FROM `pax` WHERE `vip` = 'REUNION'", true);
                    $x->destination = $this->getRandomValue(array_diff($map->airports, $invalid, $otherReunions));
                    // Update both with new destination and fare
                    foreach ([$x, $y] as $reunion) {
                        $reunion->cash = N_REF_FARE[$reunion->origin][$x->destination];
                        $reunion->destination = $x->destination;
                        $reunion->optimal = $optimal[$reunion->origin][$x->destination];
                        $reunion->vip = $x->vip;
                        $this->DbQuery("UPDATE `pax` SET `cash` = {$reunion->cash}, `destination` = '{$reunion->destination}', `optimal` = {$reunion->optimal}, `vip` = '{$reunion->vip}' WHERE `pax_id` = {$reunion->id}");
                    }
                }
                $this->DbQuery("UPDATE `pax` SET `vip` = '{$x->vip}' WHERE `pax_id` = {$x->id}");

                // Notification message
                $this->globals->set('vipWelcome', $x->id);
                $args += $x->getVipTitleMessage();
                $msg = $args['log'];
                unset($args['log']);
            }

            $this->DbQuery("UPDATE `pax` SET `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");
        }
        $args['pax'] = array_values($pax);
        $this->notifyAllPlayers('pax', $msg, $args);

        // Start the timer
        if (in_array($this->getOption(N_BGA_CLOCK), N_REF_BGA_CLOCK_REALTIME)) {
            $seconds = 9999;
            $duration = $this->getOption(N_OPTION_TIMER) * ($this->getPlayersNumber() * 5 + 20);
            if ($duration) {
                $endTime = time() + $duration;
                $this->globals->set('endTime', $endTime);
                $seconds = $duration + 60;
            }
            $this->giveExtraTimeAll($seconds);
        }

        // Play the sound and begin flying
        $this->notifyAllPlayers('sound', '', [
            'sound' => 'chime',
            'suppress' => ['yourturn'],
        ]);
        $this->gamestate->nextState('fly');
    }

    public function argFly(): array
    {
        $args = [];
        extract($this->globals->getAll('endTime', 'hour', 'vipWelcome'));
        if (!empty($endTime)) {
            $args['remain'] = max(0, $endTime - time());
        }
        if (!empty($vipWelcome)) {
            $args['titleMessage'] = $this->getPaxById($vipWelcome)->getVipTitleMessage();
        } else if ($hour == 'FINALE') {
            $args['titleMessage'] = $this->getFinaleTitleMessage();
        }
        return $args;
    }

    public function argFlyPrivate(int $playerId): array
    {
        $map = $this->getMap();
        $plane = $this->getPlaneById($playerId);
        return [
            'moves' => $map->getPossibleMoves($plane),
            'paxDrop' => [],
            'paxPickup' => [],
            'speedRemain' => max(0, $plane->speedRemain) + ($plane->tempSpeed == 1 ? 1 : 0),
        ];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Actions (ajax)
    ////////////

    public function jsError($userAgent, $msg): void
    {
        $this->error("JavaScript error from User-Agent: $userAgent\n$msg // ");
    }

    public function undo(): void
    {
        $this->gamestate->checkPossibleAction('undo');
        $playerId = $this->getCurrentPlayerId();
        $this->applyUndo($playerId);

        $plane = $this->getPlaneById($playerId);
        $this->notifyAllPlayers('planes', N_REF_MSG['undo'], [
            'planes' => [$plane],
            'player_id' => $playerId,
            'player_name' => $this->getCurrentPlayerName(),
        ]);
        $this->gamestate->setPlayersMultiactive([$playerId], '');
        $this->gamestate->initializePrivateState($playerId);
    }

    public function buy(string $type, ?string $alliance): void
    {
        $this->checkAction('buy');
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        if ($type == 'ALLIANCE') {
            if (empty($plane->alliances)) {
                $this->buyAlliancePrimary($plane, $alliance);
            } else {
                $this->buyAlliance($plane, $alliance);
            }
        } else if ($type == 'SEAT') {
            $this->buySeat($plane);
        } else if ($type == 'TEMP_SEAT') {
            $this->buyTempSeat($plane);
        } else if ($type == 'SPEED') {
            $this->buySpeed($plane);
        } else if ($type == 'TEMP_SPEED') {
            $this->buyTempSpeed($plane);
        } else {
            throw new BgaVisibleSystemException("buy: Invalid type $type [???]");
        }
    }

    private function buyAlliancePrimary(NPlane $plane, string $alliance): void
    {
        $owner = $this->getOwnerName("SUBSTRING_INDEX(`alliances`, ',', 1) = '$alliance'");
        if ($owner != null) {
            $this->userException('allianceOwner', $owner, $alliance);
        }

        $color = N_REF_ALLIANCE_COLOR[$alliance];
        $plane->alliances = [$alliance];
        $plane->location = $alliance;
        $this->DbQuery("UPDATE `plane` SET `alliances` = '{$plane->getAlliancesSql()}', `location` = '{$plane->location}' WHERE `player_id` = {$plane->id}");
        $this->DbQuery("UPDATE `player` SET `player_color` = '$color' WHERE `player_id` = {$plane->id}");
        $this->reloadPlayersBasicInfos();

        // Statistics
        $this->setStat(1, 'alliances', $plane->id);

        $this->notifyAllPlayers('buildPrimary', N_REF_MSG['alliance'], [
            'alliance' => $alliance,
            'color' => $color,
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);
        $this->notifyAllPlayers('buys', '', [
            'buys' => [
                [
                    'type' => 'ALLIANCE',
                    'alliance' => $alliance,
                    'ownerId' => $plane->id,
                ]
            ],
            'state' => 'buildAlliance',
        ]);

        if ($this->getPlayersNumber() > 2) {
            if (!$this->autoUpgrade($plane)) {
                $this->gamestate->nextPrivateState($plane->id, 'buildUpgrade');
            }
        } else {
            $this->gamestate->nextPrivateState($plane->id, 'buildAlliance2');
        }
    }

    private function buyAlliance(NPlane $plane, string $alliance): void
    {
        $isBuild = $this->gamestate->state()['name'] == 'build';
        if (in_array($alliance, $plane->alliances)) {
            throw new BgaVisibleSystemException("buyAlliance: $plane already joined $alliance [???]");
        }
        $cost = $isBuild ? 0 : 7;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buyAlliance: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->alliances[] = $alliance;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `alliances` = '{$plane->getAlliancesSql()}' WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'ALLIANCE', $alliance, $cost);

        // Statistics
        $this->setStat(count($plane->alliances), 'alliances', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['alliance'], [
            'alliance' => $alliance,
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        if ($isBuild) {
            if (!$this->autoUpgrade($plane)) {
                $this->gamestate->nextPrivateState($plane->id, 'buildUpgrade');
            }
        } else {
            $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
        }
    }

    private function buySeat(NPlane $plane, bool $transition = true): void
    {
        if ($plane->seat >= 5) {
            throw new BgaVisibleSystemException("buySeat: $plane already at maximum seats 5 [???]");
        }
        $isBuild = $this->gamestate->state()['name'] == 'build';
        $cost = $isBuild ? 0 : N_REF_SEAT_COST[$plane->seat + 1];
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buySeat: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->seat++;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `seat` = {$plane->seat} WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'SEAT', $plane->seat, $cost);

        // Statistics
        $this->setStat($plane->seat, 'seat', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['seat'], [
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'seat' => $plane->seat,
        ]);

        if ($transition) {
            if ($isBuild) {
                $this->gamestate->setPlayerNonMultiactive($plane->id, 'maintenance');
            } else {
                $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
            }
        }
    }

    private function buyTempSeat(NPlane $plane): void
    {
        $owner = $this->getOwnerName("`temp_seat` = 1");
        if ($owner != null) {
            $this->userException('tempOwner', $owner, $this->_('Temporary Seat'));
        }
        $cost = 2;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buyTempSeat: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->tempSeat = 1;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `temp_seat` = 1 WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'TEMP_SEAT', null, $cost);

        // Statistics
        $this->incStat(1, 'tempSeat', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['temp'], [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Seat'),
            'tempIcon' => 'speed',
        ]);
        $this->notifyAllPlayers('buys', '', [
            'buys' => [
                [
                    'type' => 'TEMP_SEAT',
                    'ownerId' => $plane->id,
                ]
            ],
            'state' => 'prepareBuy',
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
    }

    private function buySpeed(NPlane $plane, bool $transition = true): void
    {
        if ($plane->speed >= 9) {
            throw new BgaVisibleSystemException("buySpeed: $plane already at maximum speed 9 [???]");
        }
        $isBuild = $this->gamestate->state()['name'] == 'build';
        $cost = $isBuild ? 0 : N_REF_SPEED_COST[$plane->speed + 1];
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buySpeed: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->speed++;
        $plane->speedRemain = $plane->speed;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `speed` = {$plane->speed}, `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'SPEED', $plane->speed, $cost);

        // Statistics
        $this->setStat($plane->speed, 'speed', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['speed'], [
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'speed' => $plane->speed,
        ]);

        if ($transition) {
            if ($isBuild) {
                $this->gamestate->setPlayerNonMultiactive($plane->id, 'maintenance');
            } else {
                $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
            }
        }
    }

    private function buyTempSpeed(NPlane $plane): void
    {
        $owner = $this->getOwnerName("`temp_speed` = 1");
        if ($owner != null) {
            $this->userException('tempOwner', $owner, $this->_('Temporary Speed'));
        }
        $cost = 1;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaVisibleSystemException("buyTempSpeed: $plane with $$cash cannot afford $$cost [???]");
        }

        $plane->debt += $cost;
        $plane->tempSpeed = 1;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `temp_speed` = {$plane->tempSpeed} WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'TEMP_SPEED', null, $cost);

        // Statistics
        $this->incStat(1, 'tempSpeed', $plane->id);

        $this->notifyAllPlayers('planes', N_REF_MSG['temp'], [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Speed'),
            'tempIcon' => 'speed',
        ]);
        $this->notifyAllPlayers('buys', '', [
            'buys' => [
                [
                    'type' => 'TEMP_SPEED',
                    'ownerId' => $plane->id,
                ]
            ],
            'state' => 'prepareBuy',
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
    }

    public function buyAgain(): void
    {
        $this->checkAction('buyAgain');
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->nextPrivateState($playerId, 'prepareBuy');
    }

    public function pay(array $paid): void
    {
        $this->checkAction('pay');
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);

        // Compare with suggestion
        $suggestion = $this->_argPreparePay($plane)['suggestion'];
        sort($suggestion);
        sort($paid);
        $strSuggestion = join(', ', $suggestion);
        $strPaid = join(', ', $paid);

        // Validate
        $total = 0;
        $validIds = [];
        foreach ($paid as $cash) {
            if (!$cash) {
                continue;
            }
            $paxId = array_search($cash, $plane->wallet);
            if ($paxId === false) {
                throw new BgaVisibleSystemException("pay: $plane has no \$$cash bill with validIds=" . join(',', $validIds) . " [???]");
            }
            unset($plane->wallet[$paxId]);
            $total += $cash;
            $validIds[] = $paxId;
        }
        if ($total < $plane->debt) {
            $this->userException('pay', "\${$plane->debt}");
        }

        if ($strSuggestion != $strPaid) {
            $this->warn("Pay custom amount for debt={$plane->debt}! Player paid: $strPaid (= total $total), suggestion: $strSuggestion // ");
            // $this->incStat(1, 'payCustom', $plane->id);
        }

        // Payment
        $this->DbQuery("UPDATE `pax` SET `status` = 'PAID' WHERE `pax_id` IN (" . join(',', $validIds) . ")");
        $this->DbQuery("UPDATE `plane` SET `debt` = 0 WHERE `player_id` = $playerId");

        // Statistics
        $overpay = $total - $plane->debt;
        if ($overpay > 0) {
            $this->incStat($overpay, 'overpay', $plane->id);
        }

        // Update plane gauges UI (e.g., overpayment)
        $plane = $this->getPlaneById($playerId);
        $this->notifyAllPlayers('planes', '', [
            'planes' => [$plane],
        ]);

        $this->gamestate->setPlayerNonMultiactive($playerId, 'reveal');
    }

    public function vip(bool $accept): void
    {
        $this->checkAction('vip');
        $hourInfo = $this->getHourInfo();
        $vips = $this->globals->get('vips');
        if ($accept && empty($vips[$hourInfo['hour']])) {
            throw new BgaVisibleSystemException("vip: No VIP to accept [???]");
        }
        $this->globals->set('vipNew', $accept);

        $hourInfo = $this->getHourInfo();
        $hourInfo['player_id'] = $this->getCurrentPlayerId();
        $hourInfo['player_name'] = $this->getCurrentPlayerName();
        $msg = $accept ? N_REF_MSG['vipAccept'] : N_REF_MSG['vipDecline'];
        $this->notifyAllPlayers('hour', $msg, $hourInfo);
    }

    public function prepareDone(): void
    {
        $this->checkAction('prepareDone');
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        if ($plane->debt > 0) {
            $this->gamestate->nextPrivateState($plane->id, 'preparePay');
        } else {
            $this->gamestate->setPlayerNonMultiactive($playerId, 'reveal');
        }
    }

    public function flyDone(bool $snooze = false): void
    {
        $this->checkAction('flyDone');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        $playerName = $this->getCurrentPlayerName();
        $activeCount = intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `player` WHERE `player_is_multiactive` = 1 AND `player_id` != $playerId"));
        $snoozeCount  = intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `player` WHERE `player_is_multiactive` = 0 AND `snooze` = 1 AND `player_id` != $playerId ORDER BY `player_name`"));

        if ($snooze) {
            if ($activeCount == 0 && $snoozeCount == 0) {
                // Last player cannot snooze
                $this->userException('noSnooze');
            }
            $this->DbQuery("UPDATE `player` SET `snooze` = 1 WHERE `player_id` = $playerId");
            $this->notifyAllPlayers('message', N_REF_MSG['snooze'], [
                'player_id' => $playerId,
                'player_name' => $playerName,
            ]);
            if ($activeCount == 0) {
                // Snooze deadlock -- nobody active but snoozers exist
                $this->awakenSnoozers(0, true);
            } else {
                // At least one other player is active, so we can really snooze
                $this->gamestate->setPlayerNonMultiactive($playerId, 'maintenance');
            }
        } else {
            $this->notifyAllPlayers('message', N_REF_MSG['flyDone'], [
                'player_id' => $playerId,
                'player_name' => $playerName,
            ]);
            if ($activeCount == 0 && $snoozeCount > 0) {
                // Snooze deadlock -- nobody active but snoozers exist
                $this->awakenSnoozers(0, true);
            }
            $this->gamestate->setPlayerNonMultiactive($playerId, 'maintenance');
        }
    }

    public function flyTimer(): void
    {
        $this->enforceTimer();
    }

    public function flyAgain(): void
    {
        $this->gamestate->checkPossibleAction('flyAgain');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        $playerName = $this->getCurrentPlayerName();
        $this->notifyAllPlayers('message', N_REF_MSG['flyAgain'], [
            'player_id' => $playerId,
            'player_name' => $playerName,
        ]);
        $this->gamestate->setPlayersMultiactive([$playerId], '');
        $this->gamestate->initializePrivateState($playerId);
    }

    public function move(string $from, string $to): void
    {
        $this->checkAction('move');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        if ($plane->location != $from) {
            // Race condition
            $this->notifyPlayer($playerId, 'noop', '', []);
            return;
        }
        $map = $this->getMap();
        $possible = $map->getPossibleMoves($plane);
        if (!array_key_exists($to, $possible)) {
            throw new BgaVisibleSystemException("move: $plane cannot reach $to [???]");
        }

        $move = $possible[$to];
        $plane->origin = $move->getOrigin();
        $plane->location = $to;
        $plane->speedRemain -= $move->fuel;
        if ($plane->tempSeat == -1) {
            // Truly use the temp seat
            $plane->tempSeat = 0;
        }
        if ($plane->speedRemain == -1 && $plane->tempSpeed == 1) {
            $this->useTempSpeed($plane);
        } else if ($plane->speedRemain < 0) {
            throw new BgaVisibleSystemException("move: $plane not enough fuel to reach $to with speedRemain={$plane->speedRemain}, tempSpeed={$plane->tempSpeed} [???]");
        }
        array_shift($move->path);
        $distance = count($move->path);
        $this->DbQuery("UPDATE `plane` SET `location` = '{$plane->location}', `origin` = '{$plane->origin}', `speed_remain` = {$plane->speedRemain}, `temp_seat` = {$plane->tempSeat} WHERE `player_id` = {$plane->id}");
        $this->DbQuery("UPDATE `pax` SET `moves` = `moves` + $distance WHERE `status` = 'SEAT' AND `player_id` = {$plane->id}");

        // Statistics
        $hasWeather = [];
        foreach ($move->path as $location) {
            $weather = null;
            if (array_key_exists($location, $map->weather)) {
                $weather = $map->weather[$location];
            }

            if ($weather != 'FAST') {
                $this->incStat(1, 'moves');
                $this->incStat(1, 'moves', $playerId);
            }

            if (strlen($location) == 3) {
                // Airport visit
                $this->incStat(1, $location, $playerId);
                $this->addFlightPlan($plane, $location);
            } else {
                // Route move
                $alliance = $map->nodes[$location]->alliance ?? "Normal";
                $this->incStat(1, "moves$alliance");
                $this->incStat(1, "moves$alliance", $playerId);
                if ($weather) {
                    $hasWeather[$weather] = true;
                    $this->incStat(1, "moves$weather");
                    $this->incStat(1, "moves$weather", $playerId);
                }
            }
        }
        for ($seat = 1; $seat <= $plane->seat; $seat++) {
            $this->incStat($distance, "seatEmpty$seat", $playerId);
            if ($seat <= $plane->pax) {
                $this->incStat($distance, "seatFull$seat", $playerId);
            }
        }

        // VIP Storm
        // Remove the VIP condition after flying through a storm
        if (array_key_exists('SLOW', $hasWeather)) {
            $stormIds = array_map('intval', $this->getObjectListFromDB("SELECT `pax_id` FROM `pax` WHERE `status` = 'SEAT' AND `player_id` = {$plane->id} AND `vip` = 'STORM'", true));
            if (!empty($stormIds)) {
                $this->DbQuery("UPDATE `pax` SET `vip` = NULL WHERE `pax_id` IN (" . join(',', $stormIds) . ")");
                $pax = $this->getPaxByIds($stormIds);
                $this->notifyAllPlayers('pax', '', [
                    'pax' => array_values($pax),
                ]);
            }
        }

        // VIP Wind
        // Remove the VIP condition after flying through a tailwind
        if (array_key_exists('FAST', $hasWeather)) {
            $windIds = array_map('intval', $this->getObjectListFromDB("SELECT `pax_id` FROM `pax` WHERE `status` = 'SEAT' AND `player_id` = {$plane->id} AND `vip` = 'WIND'", true));
            if (!empty($windIds)) {
                $this->DbQuery("UPDATE `pax` SET `vip` = NULL WHERE `pax_id` IN (" . join(',', $windIds) . ")");
                $pax = $this->getPaxByIds($windIds);
                $this->notifyAllPlayers('pax', '', [
                    'pax' => array_values($pax),
                ]);
            }
        }

        // Notify UI
        $msg = strlen($plane->location) == 3 ? N_REF_MSG['movePort'] : N_REF_MSG['move'];
        $this->notifyAllPlayers('move', $msg, [
            'fuel' => $distance,
            'location' => $plane->location,
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $this->awakenSnoozers($playerId);
        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    public function useTempSpeed(NPlane &$plane, int $value = -1): void
    {
        $plane->tempSpeed = $value;
        $this->DbQuery("UPDATE `plane` SET `temp_speed` = $value WHERE `player_id` = {$plane->id}");
        $this->notifyAllPlayers('message', N_REF_MSG['tempUsed'], [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Speed'),
            'tempIcon' => 'speed',
        ]);
    }

    public function board(int $paxId, ?int $paxPlayerId): void
    {
        $this->checkAction('board');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        if (!$this->validatePax($paxId, $paxPlayerId)) {
            // Race condition
            $this->notifyPlayer($playerId, 'noop', '', []);
            return;
        }
        $plane = $this->getPlaneById($playerId);
        $this->_board($plane, $paxId);
        $this->awakenSnoozers($playerId);
        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    private function _board(NPlane $plane, int $paxId): void
    {
        $x = $this->getPaxById($paxId, true);
        $planeIds = [$plane->id];

        $msg = N_REF_MSG['board'];
        $args = [
            'route' => $x->origin . "-" . $x->destination
        ];
        if ($x->status == 'SEAT') {
            if ($x->playerId == $plane->id) {
                throw new BgaVisibleSystemException("board: $x player ID is already {$plane->id} [???]");
            }
            // Transfer from another plane
            // Must be together at an airport
            $other = $this->getPlaneById($x->playerId);
            if ($other->location != $plane->location || strlen($plane->location) != 3) {
                $this->userException('boardTransfer', $other->name);
            }
            // Implicit deplane
            $msg = N_REF_MSG['boardTransfer'];
            $args['player_name2'] = $other->name;
            $this->_deplane($other, $paxId, true);
            $planeIds[] = $other->id;
            $x = $this->getPaxById($paxId, true);
        }

        $vipInfo = $x->getVipInfo();
        if ($this->getOption(N_OPTION_VIP)) {
            // VIP Celebrity
            // If this pax is a celebrity, the plane must be empty
            if ($vipInfo && $vipInfo['key'] == 'CELEBRITY' && intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `pax` WHERE `player_id` = {$plane->id} AND `status` = 'SEAT'")) > 0) {
                $this->vipException($vipInfo);
            }
            // Or, if a celebrity is on board, no boarding
            if (intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `pax` WHERE `player_id` = {$plane->id} AND `status` = 'SEAT' AND `vip` = 'CELEBRITY'")) > 0) {
                $this->vipException('CELEBRITY');
            }

            // VIP First
            // If anyone is first, this pax must be among them
            $firstList = $this->getObjectListFromDB("SELECT `pax_id` FROM `pax` WHERE `location` = '{$x->location}' AND `status` = 'PORT' AND `vip` = 'FIRST'", true);
            if (!empty($firstList) && !in_array($paxId, $firstList)) {
                $this->vipException('FIRST');
            }

            // VIP Double
            // Board the fugitive (must be BEFORE the real pax)
            if ($vipInfo && $vipInfo['key'] == 'DOUBLE' && $x->id > 0) {
                $this->_board($plane, $x->id * -1);
                $plane = $this->getPlaneById($plane->id);
            }

            // VIP Last
            // If this pax is last, the airport must be empty
            if ($vipInfo && $vipInfo['key'] == 'LAST' && intval($this->getUniqueValueFromDB("SELECT COUNT(1) FROM `pax` WHERE `location` = '{$x->location}' AND (`status` = 'PORT' OR (`status` = 'SECRET' AND `vip` = 'MYSTERY')) AND COALESCE(`vip`, 'X') != 'LAST'")) > 0) {
                $this->vipException($vipInfo);
            }

            // VIP Late
            // Requires 1 speed
            if ($vipInfo && $vipInfo['key'] == 'LATE') {
                $plane->speedRemain -= 1;
                if ($plane->speedRemain == -1 && $plane->tempSpeed == 1) {
                    $this->useTempSpeed($plane);
                } else if ($plane->speedRemain < 0) {
                    $this->vipException($vipInfo);
                }
                $this->DbQuery("UPDATE `plane` SET `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");
            }

            // VIP Loyal
            // Requires specific alliance
            if ($vipInfo && $vipInfo['key'] == 'LOYAL') {
                $alliance = $vipInfo['args']['1'];
                if (!in_array($alliance, $plane->alliances)) {
                    $this->vipException($vipInfo);
                }
            }
        }

        if ($x->status == 'PORT' || ($x->status == 'SECRET' && $vipInfo && $vipInfo['key'] == 'MYSTERY')) {
            // Pickup from airport
            if (strlen($x->location) != 3) {
                throw new BgaVisibleSystemException("board: $x location is not at an airport [???]");
            }
            if ($x->location != $plane->location) {
                $this->userException('boardPort', $x->location);
            }
        } else {
            throw new BgaVisibleSystemException("board: $x status is invalid [???]");
        }

        $remain = $plane->getSeatRemain();
        if ($remain == 0) {
            $this->userException('noSeat');
        } else if ($remain == 1 && $plane->tempSeat == 1) {
            $this->useTempSeat($plane);
        }

        // Note: Unlike physical game, we preserve anger until deplane
        $x->playerId = $plane->id;
        $x->status = 'SEAT';
        $this->DbQuery("UPDATE `pax` SET `player_id` = {$x->playerId}, `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");

        // Notify UI (except for VIP Double)
        if ($x->id > 0) {
            $planes = $this->getPlanesByIds($planeIds);
            $this->notifyAllPlayers('pax', $msg, [
                'location' => $x->location,
                'pax' => [$x],
                'player_id' => $plane->id,
                'player_name' => $plane->name,
            ] + $args);
            $this->notifyAllPlayers('planes', '', [
                'planes' => array_values($planes)
            ]);
        }
    }

    public function useTempSeat(NPlane &$plane, int $value = -1): void
    {
        $msg = $value == 1 ? N_REF_MSG['tempUndo'] : N_REF_MSG['tempUsed'];
        $plane->seatX += $value == 1 ? -1 : 1;
        $plane->tempSeat = $value;
        $this->DbQuery("UPDATE `plane` SET `seat_x` = {$plane->seatX}, `temp_seat` = {$plane->tempSeat} WHERE `player_id` = {$plane->id}");
        $this->notifyAllPlayers('message', $msg, [
            'i18n' => ['temp'],
            'preserve' => ['tempIcon'],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'temp' => clienttranslate('Temporary Seat'),
            'tempIcon' => 'seat',
        ]);
    }

    public function deplane(int $paxId, ?int $paxPlayerId): void
    {
        $this->checkAction('deplane');
        if ($this->enforceTimer()) {
            return;
        }
        $playerId = $this->getCurrentPlayerId();
        if (!$this->validatePax($paxId, $paxPlayerId)) {
            // Race condition
            $this->notifyPlayer($playerId, 'noop', '', []);
            return;
        }
        $plane = $this->getPlaneById($playerId);
        $this->_deplane($plane, $paxId);
        $this->awakenSnoozers($playerId);
        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    private function _deplane(NPlane $plane, int $paxId, bool $transfer = false): void
    {
        $x = $this->getPaxById($paxId, true);
        if ($x->status != 'SEAT') {
            throw new BgaVisibleSystemException("deplane: $x status is invalid [???]");
        }
        if (strlen($plane->location) != 3) {
            $this->userException('deplanePort');
        }

        // Erase anger if deplaned at a new location
        if ($x->location != $plane->location) {
            $x->resetAnger($this->getOption(N_OPTION_ANGER, 0));
        }
        $x->location = $plane->location;

        $args = [
            'location' => $x->location,
            'pax' => [$x],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'route' => $x->origin . "-" . $x->destination,
        ];

        // Is this the final destination?
        $deliver = $x->location == $x->destination;
        $vipInfo = $x->getVipInfo();
        if ($vipInfo) {
            // VIP Double
            // Deplane the fugitive (must be BEFORE the real pax)
            if ($vipInfo['key'] == 'DOUBLE' && $x->id > 0) {
                $this->_deplane($plane, $x->id * -1);
            }

            // VIP Direct
            // (with allowance for misclicks if not yet moved)
            if ($vipInfo['key'] == 'DIRECT' && !$deliver && $x->moves > 0) {
                $this->vipException($vipInfo);
            }

            // VIP Reunion
            if ($vipInfo['key'] == 'REUNION') {
                $reunion = $this->getPaxById(intval($this->getUniqueValueFromDB("SELECT `pax_id` FROM `pax` WHERE `vip` = 'REUNION' AND `destination` = '{$x->destination}' AND `pax_id` != {$x->id}")));
                if ($deliver && $reunion->status == 'PORT' && $reunion->location == $x->location) {
                    // Deliver companion
                    $reunion->playerId = $plane->id;
                    $reunion->status = 'CASH';
                    $args['pax'][] = $reunion;
                    $this->DbQuery("UPDATE `pax` SET `player_id` = {$reunion->getPlayerIdSql()}, `status` = '{$reunion->status}' WHERE `pax_id` = {$reunion->id}");
                    $this->incStat(1, 'pax');
                    $this->incStat(1, 'pax', $plane->id);
                    $this->incStat($reunion->cash, 'cash', $plane->id);
                    $this->notifyAllPlayers('message', N_REF_MSG['deplaneDeliver'], [
                        'location' => $reunion->location,
                        'player_id' => $plane->id,
                        'player_name' => $plane->name,
                        'route' => $reunion->origin . "-" . $reunion->destination,
                        'cash' => $reunion->cash,
                        'moves' => $reunion->moves,
                    ]);
                } else {
                    // Wait for companion
                    $deliver = false;
                }
            }

            // VIP Storm/Wind
            // Never deliver (condition is removed when met)
            if ($vipInfo['key'] == 'STORM' || $vipInfo['key'] == 'WIND') {
                $deliver = false;
            }
        }

        // Cannot transfer if delivery is possible
        if ($transfer && $deliver) {
            $this->userException('boardDeliver', $plane->name);
        }

        $countToWin = false;
        if ($deliver) {
            $x->status = 'CASH';
            $args['cash'] = $x->cash;
            $args['moves'] = $x->moves;
            $this->incStat(1, 'pax');
            $this->incStat(1, 'pax', $plane->id);
            $this->incStat($x->cash, 'cash', $plane->id);

            // VIP Return
            // Create the round trip
            if ($vipInfo && $vipInfo['key'] == 'RETURN' && $x->cash == 0) {
                $optionAnger = $this->getOption(N_OPTION_ANGER, 0);
                $cash = N_REF_FARE[$x->destination][$x->origin] * 2;
                $this->DbQuery("INSERT INTO `pax` (`anger`, `cash`, `destination`, `location`, `optimal`, `origin`, `status`, `vip`) VALUES ($optionAnger, $cash, '{$x->origin}', '{$x->location}', {$x->optimal}, '{$x->destination}', 'PORT', '{$x->vip}')");
                $newPax = $this->getPaxById($this->DbGetLastId());
                $args['pax'][] = $newPax;
            } else {
                $countToWin = true;
            }
        } else {
            $x->playerId = null;
            $x->status = 'PORT';

            // Undo Temporary Seat
            if ($plane->tempSeat == -1) {
                $this->useTempSeat($plane, 1);
            }
        }
        $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `location` = '{$x->location}', `player_id` = {$x->getPlayerIdSql()}, `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");
        if ($countToWin) {
            $args['countToWin'] = $this->countToWin();
        }
        // Notify UI about pax (except for transfers and VIP Double)
        if (!$transfer && $x->id > 0) {
            $msg = $deliver ? N_REF_MSG['deplaneDeliver'] : N_REF_MSG['deplane'];
            $this->notifyAllPlayers('pax', $msg, $args);
        }

        // Consume seat_x
        if ($plane->seatX > 0) {
            $plane->seatX--;
            $this->DbQuery("UPDATE `plane` SET `seat_x` = {$plane->seatX} WHERE `player_id` = {$plane->id}");
        }

        // Notify UI about plane (except VIP Double)
        if ($x->id > 0) {
            $planes = $this->getPlanesByIds([$plane->id]);
            $this->notifyAllPlayers('planes', '', [
                'planes' => array_values($planes)
            ]);
        }
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Helpers
    ////////////

    private function getFirstValue(array $array)
    {
        if (empty($array)) {
            trigger_error("getFirstValue(): Array is empty", E_USER_WARNING);
            return null;
        }
        return $array[array_key_first($array)];
    }

    private function getRandomKey(array $array)
    {
        $size = count($array);
        if ($size == 0) {
            trigger_error("getRandomKey(): Array is empty", E_USER_WARNING);
            return null;
        }
        $rand = random_int(0, $size - 1);
        $slice = array_slice($array, $rand, 1, true);
        foreach ($slice as $key => $value) {
            return $key;
        }
    }

    private function getRandomValue(array $array)
    {
        $size = count($array);
        if ($size == 0) {
            trigger_error("getRandomValue(): Array is empty", E_USER_WARNING);
            return null;
        }
        $rand = random_int(0, $size - 1);
        $slice = array_slice($array, $rand, 1, true);
        foreach ($slice as $key => $value) {
            return $value;
        }
    }

    private function getRandomSlice(array $array, int $count)
    {
        $size = count($array);
        if ($size == 0) {
            trigger_error("getRandomSlice(): Array is empty", E_USER_WARNING);
            return null;
        }
        if ($count < 1 || $count > $size) {
            trigger_error("getRandomSlice(): Invalid count $count for array with size $size", E_USER_WARNING);
            return null;
        }
        $slice = [];
        $randUnique = [];
        while (count($randUnique) < $count) {
            $rand = random_int(0, $size - 1);
            if (array_key_exists($rand, $randUnique)) {
                continue;
            }
            $randUnique[$rand] = true;
            $slice += array_slice($array, $rand, 1, true);
        }
        return $slice;
    }

    private function getOption(int $id, ?int $default = null): ?int
    {
        $value = @$this->gamestate->table_globals[$id];
        return $value == null ? $default : intval($value);
    }

    private function awakenSnoozers(int $playerId, bool $deadlock = false): void
    {
        $snoozers = $this->getCollectionFromDB("SELECT `player_id`, `player_name` FROM `player` WHERE `snooze` = 1 AND `player_id` != $playerId ORDER BY `player_name`", true);
        if (!empty($snoozers)) {
            if ($deadlock) {
                $args = [];
                $playerArgs = ['player_name5', 'player_name4', 'player_name3', 'player_name2', 'player_name'];
                foreach ($snoozers as $snoozerId => $snoozerName) {
                    $args[array_pop($playerArgs)] = $snoozerName;
                }
                $this->notifyAllPlayers('snoozeDeadlock', N_REF_MSG['snoozeDeadlock'], [
                    'players' => [
                        'log' => '${' . join('}, ${', array_keys($args)) . '}',
                        'args' => $args,
                    ]
                ]);
            }

            $this->DbQuery("UPDATE `player` SET `snooze` = 0 WHERE `player_id` IN (" . join(', ', array_keys($snoozers)) . ")");
            $this->gamestate->setPlayersMultiactive(array_keys($snoozers), '');
        }
    }

    private function enforceTimer(): bool
    {
        // We need to check BGA clock in case table switched to turn-based
        $endTime = $this->globals->get('endTime', 0);
        if (
            $endTime > 0
            && time() >= $endTime
            && $this->getOption(N_OPTION_TIMER)
            && in_array($this->getOption(N_BGA_CLOCK), N_REF_BGA_CLOCK_REALTIME)
            && $this->gamestate->state()['name'] == 'fly'
        ) {
            $this->notifyAllPlayers('flyTimer', N_REF_MSG['flyTimer'], []);
            $this->gamestate->setAllPlayersNonMultiactive('maintenance');
            return true;
        }
        return false;
    }

    private function getHourInfo(bool $beforeAddPax = false): array
    {
        $hour = $this->globals->get('hour');
        $hourInfo = [
            'hour' => $hour,
            'hourDesc' => N_REF_HOUR[$hour]['desc'],
        ];

        if ($hour == 'MORNING' || $hour == 'NOON' || $hour == 'NIGHT') {
            $playerCount = $this->getPlayersNumber();
            $draw = $playerCount;
            if ($hour == 'MORNING') {
                $draw--;
            } else if ($hour == 'NIGHT') {
                $draw++;
            }
            $hourPax = N_REF_HOUR_PAX[$playerCount][$hour];
            $remainPax = $this->countPaxByStatus($hour);
            $total = $hourPax / $draw;
            $round = ($hourPax - $remainPax) / $draw;
            if ($beforeAddPax) {
                $round++;
            }
            $hourInfo['draw'] = $draw;
            $hourInfo['round'] = $round;
            $hourInfo['total'] = $total;

            if ($this->getOption(N_OPTION_VIP)) {
                $vips = $this->globals->get('vips');
                $vipRemain = count($vips[$hour]);
                $hourInfo['vipRemain'] = $vipRemain;
                $hourInfo['vipNeed'] = $vipRemain > 0 && $vipRemain >= ($total - $round + 1);
                $hourInfo['vipNew'] = $this->globals->get('vipNew', false);
            }
        }
        return $hourInfo;
    }

    private function getVipOverall(array $vips): string
    {
        $morning = count($vips['MORNING']);
        $noon = count($vips['NOON']);
        $night = count($vips['NIGHT']);
        return "$morning/$noon/$night";
    }

    private function getFinaleTitleMessage(): array
    {
        return [
            'log' => N_REF_MSG['hourFinale'],
            'i18n' => ['hourDesc'],
            'countToWin' => $this->globals->get('countToWin', 0),
            'hourDesc' => N_REF_HOUR['FINALE']['desc'],
        ];
    }

    private function advanceHour(array $hourInfo): array
    {
        $advance = $hourInfo['hour'] == 'PREFLIGHT' || $hourInfo['round'] > $hourInfo['total'];
        if ($advance) {
            $nextHour = N_REF_HOUR[$hourInfo['hour']]['next'];
            $prevHour = N_REF_HOUR[$nextHour]['prev'];
            $this->globals->set('hour', $nextHour);
            $hourInfo = $this->getHourInfo(true);
        }
        $vip = array_key_exists('vipRemain', $hourInfo);
        $finale = $hourInfo['hour'] == 'FINALE';

        // Anger VIPs
        if ($advance && $vip && $prevHour) {
            $this->angerVips($prevHour);
        }

        // Timing
        $newTiming = [
            'time' => time(),
            'hourDesc' => $hourInfo['hourDesc'],
        ];
        if (!$finale) {
            $newTiming['round'] = $hourInfo['round'];
            $newTiming['total'] = $hourInfo['total'];
        }
        $hourTiming = $this->globals->get('hourTiming') ?? [];
        $hourTiming[] = $newTiming;
        $this->globals->set('hourTiming', $hourTiming);
        $hourInfo['hourTiming'] = $newTiming;

        // Notify hour
        $hourInfo['i18n'] = ['hourDesc'];
        if ($finale) {
            $remain = $this->countPaxByStatus(['PORT', 'SEAT']);
            if ($remain == 0) {
                // Skip the final round if no passengers remain
                throw new NGameOverException();
            }
            $hourInfo['countToWin'] = $this->globals->get('countToWin', 0);
            $this->notifyAllPlayers('hour', N_REF_MSG['hourFinale'], $hourInfo);
        } else {
            if ($advance) {
                // Notify weather
                $this->advanceWeather($hourInfo);
            }

            // Notify hour
            $this->notifyAllPlayers('hour', N_REF_MSG['hour'], $hourInfo);

            // Notify VIP count
            if ($advance && $vip) {
                $this->notifyAllPlayers('message', N_REF_MSG['hourVip'], [
                    'i18n' => ['hourDesc'],
                    'count' => $hourInfo['vipRemain'],
                    'hourDesc' => $hourInfo['hourDesc'],
                ]);
            }
        }
        return $hourInfo;
    }

    private function advanceWeather(array $hourInfo): void
    {
        $planeIds = $this->getObjectListFromDB("SELECT `player_id` FROM `plane` WHERE LENGTH(`location`) = 8", true);
        if (!empty($planeIds)) {
            // Move planes out of storms
            $this->DbQuery("UPDATE `plane` SET `location` = LEFT(`location`, 7)");
            $planes = $this->getPlanesByIds($planeIds);
            foreach ($planes as $plane) {
                $this->notifyAllPlayers('move', '', [
                    'plane' => $plane,
                    'player_id' => $plane->id,
                ]);
            }
        }

        // Notify weather
        $weather = $this->globals->get('weather')[$hourInfo['hour']];
        $desc = [];
        foreach ($weather as $location => $token) {
            $desc[$token][] = substr($location, 0, 3) . "-" . substr($location, 3, 3);
        }
        $this->notifyAllPlayers('message', N_REF_MSG['weatherSLOW'], [
            'preserve' => ['wrapper', 'weatherIcon'],
            'i18n' => ['hourDesc'],
            'hourDesc' => $hourInfo['hourDesc'],
            'location' => join(', ', $desc['SLOW']),
            'weatherIcon' => 'SLOW',
            'wrapper' => 'weatherFlex',
        ]);
        $this->notifyAllPlayers('weather', N_REF_MSG['weatherFAST'], [
            'preserve' => ['wrapper', 'weatherIcon'],
            'i18n' => ['hourDesc'],
            'hourDesc' => $hourInfo['hourDesc'],
            'location' => join(', ', $desc['FAST']),
            'weather' => $weather,
            'weatherIcon' => 'FAST',
            'wrapper' => 'weatherFlex',
        ]);
    }

    private function createUndo(): void
    {
        $this->DbQuery("INSERT INTO `pax_undo` SELECT * FROM `pax` WHERE `status` = 'CASH'");
        $this->DbQuery("INSERT INTO `plane_undo` SELECT * FROM `plane`");
        $this->DbQuery("INSERT INTO `stats_undo` SELECT * FROM `stats` WHERE `stats_type` >= 10 AND `stats_player_id` IS NOT NULL");
    }

    private function applyUndo(int $playerId): void
    {
        $oldPlane = $this->getPlaneById($playerId);
        $this->DbQuery("DELETE FROM `ledger` WHERE `player_id` = $playerId");
        $this->DbQuery("REPLACE INTO `pax` SELECT * FROM `pax_undo` WHERE `player_id` = $playerId");
        $this->DbQuery("REPLACE INTO `plane` SELECT * FROM `plane_undo` WHERE `player_id` = $playerId");
        $this->DbQuery("DELETE FROM `stats` WHERE `stats_type` >= 10 AND `stats_player_id` = $playerId");
        $this->DbQuery("INSERT INTO `stats` SELECT * FROM `stats_undo` WHERE `stats_player_id` = $playerId");
        $newPlane = $this->getPlaneById($playerId);

        $buys = [];
        if ($oldPlane->tempSeat && !$newPlane->tempSeat) {
            $state = 'prepareBuy';
            $buys[] = [
                'type' => 'TEMP_SEAT',
                'ownerId' => null,
            ];
        }
        if ($oldPlane->tempSpeed && !$newPlane->tempSpeed) {
            $state = 'prepareBuy';
            $buys[] = [
                'type' => 'TEMP_SPEED',
                'ownerId' => null,
            ];
        }
        if (empty($newPlane->alliances)) {
            $state = 'buildAlliance';
            $buys[] = [
                'type' => 'ALLIANCE',
                'alliance' => $oldPlane->alliances,
                'ownerId' => null,
            ];
        }
        if (!empty($buys)) {
            $this->notifyAllPlayers('buys', '', [
                'buys' => $buys,
                'state' => $state,
            ]);
        }
    }

    private function eraseUndo(): void
    {
        $this->DbQuery("DELETE FROM `ledger`");
        $this->DbQuery("DELETE FROM `pax_undo`");
        $this->DbQuery("DELETE FROM `plane_undo`");
        $this->DbQuery("DELETE FROM `stats_undo`");
    }

    private function getPlayerIds(): array
    {
        return $this->getObjectListFromDB("SELECT `player_id` FROM `player`", true);
    }

    private function giveExtraTimeAll(?int $seconds = null)
    {
        if ($this->isAsync()) {
            return;
        }
        foreach ($this->getPlayerIds() as $playerId) {
            try {
                $this->giveExtraTime($playerId, $seconds);
            } catch (Exception $ignore) {
            }
        }
    }

    private function getMap(bool $withWeather = true): NMap
    {
        $playerCount = $this->getPlayersNumber();
        $currentWeather = [];
        if ($withWeather) {
            extract($this->globals->getAll('hour', 'weather'));
            if (array_key_exists($hour, $weather)) {
                $currentWeather = $weather[$hour];
            }
        }
        return new NMap($playerCount, $this->getOption(N_OPTION_MAP), $currentWeather);
    }

    private function getPlaneById(int $playerId): NPlane
    {
        return $this->getPlanesByIds([$playerId])[$playerId];
    }

    private function getPlanesByIds($ids = []): array
    {
        $sql = "SELECT p.*, (SELECT COUNT(1) FROM `pax` x WHERE x.status = 'SEAT' AND x.player_id = p.player_id) AS pax, w.wallet, b.player_name FROM `plane` p JOIN `player` b ON (b.player_id = p.player_id) LEFT OUTER JOIN (SELECT `player_id`, GROUP_CONCAT(CONCAT(`pax_id`, '=', `cash`) ORDER BY `cash`, `pax_id`) AS `wallet` FROM `pax` WHERE `status` = 'CASH' AND `cash` > 0 GROUP BY `player_id`) w ON (w.player_id = p.player_id)";
        if (!empty($ids)) {
            $sql .= " WHERE p.player_id IN (" . join(',', $ids) . ")";
        }
        return array_map(function ($dbrow) {
            return new NPlane($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    private function getOwnerName(string $sqlWhere): ?string
    {
        return $this->getUniqueValueFromDB("SELECT b.`player_name` FROM `plane` p JOIN `player` b ON (b.player_id = p.player_id) WHERE $sqlWhere LIMIT 1");
    }

    private function getOwnerId(string $sqlWhere): ?string
    {
        return $this->getUniqueValueFromDB("SELECT `player_id` FROM `plane` WHERE $sqlWhere LIMIT 1");
    }

    private function getPaxById(int $paxId, bool $lock = false): NPax
    {
        return $this->getPaxByIds([$paxId], $lock)[$paxId];
    }

    private function getPaxByIds(array $ids = [], bool $lock = false): array
    {
        $sql = "SELECT * FROM `pax`";
        if (!empty($ids)) {
            $sql .= " WHERE `pax_id` IN (" . join(',', $ids) . ")";
        }
        if ($lock) {
            $sql .= " FOR UPDATE";
        }
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    private function getPaxByStatus($status, ?int $limit = null, ?int $playerId = null, bool $stReveal = false): array
    {
        if (is_array($status)) {
            $status = join("', '", $status);
        }
        $sql = "SELECT * FROM `pax` WHERE `status` IN ('$status')";
        if ($playerId != null) {
            $sql .= " AND `player_id` = $playerId";
        }
        if ($stReveal) {
            $sql .= " AND `vip` IS NULL";
        }
        $sql .= " ORDER BY `pax_id`";
        if ($limit != null) {
            $sql .= " LIMIT $limit";
        }
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    private function countPaxByStatus($status = null): int
    {
        if (is_array($status)) {
            $status = join("', '", $status);
        }
        $sql = "SELECT COUNT(1) FROM `pax`";
        if (!empty($status)) {
            $sql .= " WHERE `status` IN ('$status')";
        }
        return intval($this->getUniqueValueFromDB($sql));
    }

    private function validatePax(int $paxId, ?int $paxPlayerId): bool
    {
        $sql = "SELECT COUNT(1) FROM `pax` WHERE `pax_id` = $paxId AND `player_id`" . ($paxPlayerId == null ? " IS NULL" : " = $paxPlayerId");
        return $this->getUniqueValueFromDB($sql) > 0;
    }

    private function countComplaint(): int
    {
        return $this->countPaxByStatus('COMPLAINT') + $this->getStat('complaintFinale') + $this->getStat('complaintVip');
    }

    private function countToWin(): int
    {
        $leftover = (2 - $this->countComplaint()) * 2 + 1;
        $remain = $this->countPaxByStatus(['MORNING', 'NOON', 'NIGHT', 'SECRET', 'PORT', 'SEAT']);
        $countToWin = max($remain - $leftover, 0);
        $this->globals->set('countToWin', $countToWin);
        return $countToWin;
    }

    private function exceptionMsg(string $msgExKey, ...$args): string
    {
        $msg = $this->_(N_REF_MSG_EX[$msgExKey]);
        if (!empty($args)) {
            $msg = sprintf($msg, ...$args);
        }
        return $msg;
    }

    private function userException(string $msgExKey, ...$args): void
    {
        $msg = $this->exceptionMsg($msgExKey, ...$args);
        throw new BgaUserException($msg);
    }

    private function vipException($vipInfo): void
    {
        if (!is_array($vipInfo)) { // string input
            $vipInfo = [
                'name' => N_REF_VIP[$vipInfo]['name'],
                'desc' => N_REF_VIP[$vipInfo]['desc'],
            ];
        }
        $msg = $this->exceptionMsg('vip', $this->_($vipInfo['name']), $this->_($vipInfo['desc']));
        if (array_key_exists('args', $vipInfo) && $vipInfo['args']) {
            foreach ($vipInfo['args'] as $argKey => $argValue) {
                $msg = str_replace('${' . $argKey . '}', $argValue, $msg);
            }
        }
        throw new BgaUserException($msg);
    }

    private function getLedger(int $playerId): array
    {
        $sql = "SELECT `type`, `arg`, `cost` FROM `ledger` WHERE `player_id` = $playerId ORDER BY `type`, `cost`";
        return $this->getObjectListFromDB($sql);
    }

    private function addLedger(int $playerId, string $type, ?string $arg, int $cost): void
    {
        if ($arg) {
            $this->DbQuery("INSERT INTO `ledger` (`player_id`, `type`, `arg`, `cost`) VALUES ($playerId, '$type', '$arg', $cost)");
        } else {
            $this->DbQuery("INSERT INTO `ledger` (`player_id`, `type`, `cost`) VALUES ($playerId, '$type', $cost)");
        }
    }

    private function addFlightPlan(NPlane $plane, string $location)
    {
        // Check table exists
        if (!$this->getUniqueValueFromDB("SHOW TABLES LIKE 'plan'")) {
            return;
        }

        $move = $this->getStat('moves', $plane->id);

        // End current flight plan
        $plan = $this->getObjectFromDB("SELECT * FROM `plan` WHERE `alliance` = '{$plane->alliances[0]}' AND `destination` IS NULL");
        if ($plan != null) {
            if ($plan['origin'] == $location) {
                // return to same airport, continue current
                return;
            }
            $optimal = intval($this->getUniqueValueFromDB("SELECT MIN(`optimal`) FROM `pax` WHERE `destination` = '$location' AND `origin` = '{$plan['origin']}'"));
            $this->DbQuery("UPDATE `plan` SET `destination` = '$location', `destination_move` = $move, `optimal` = $optimal WHERE `plan_id` = {$plan['plan_id']}");
        }

        // Start new flight plan
        $hr = round(7 + (16 * $this->getGameProgression() / 100));
        $lower = intval($this->getUniqueValueFromDB("SELECT MAX(`min`) FROM `plan` WHERE `hr` = $hr"));
        $min = random_int($lower, min($lower + 20, 59));
        if (random_int(0, 2)) {
            $min = floor($min / 10) * 10;
        }
        $rand = random_int(0, 9);
        $this->DbQuery("INSERT INTO `plan` (`alliance`, `hr`, `min`, `origin`, `origin_move`, `rand`) VALUES ('{$plane->alliances[0]}', $hr, $min, '$location', $move, $rand)");
    }

    private function getFlightPlans()
    {
        // Check table exists
        if (!$this->getUniqueValueFromDB("SHOW TABLES LIKE 'plan'")) {
            return [];
        }

        return array_map(function ($plan) {
            $id = intval($plan['plan_id']) * 10 + intval($plan['rand']);
            $time = sprintf('%02d:%02d', $plan['hr'], $plan['min']);
            $moves = intval($plan['destination_move']) - intval($plan['origin_move']) - intval($plan['optimal']);
            return [$plan['destination'], $plan['alliance'], $id, $time, $moves];
        }, $this->getObjectListFromDB("SELECT * FROM `plan` WHERE `destination` IS NOT NULL"));
    }

    private function createPax(): void
    {
        $planes = $this->getPlanesByIds();
        $map = $this->getMap(false);
        $optimal = $map->getOptimalMoves();

        // Create possible passengers
        $pax = [];
        foreach (N_REF_FARE as $origin => $destinations) {
            if (in_array($origin, $map->airports)) {
                foreach ($destinations as $destination => $cash) {
                    if (in_array($destination, $map->airports)) {
                        $pax[] = [$origin, $destination, $cash, $optimal[$origin][$destination]];
                    }
                }
            }
        }
        shuffle($pax);

        // Create starting passenger in each airport
        $optionAnger = $this->getOption(N_OPTION_ANGER, 0);
        foreach ($planes as $plane) {
            foreach ($pax as $k => $x) {
                [$origin, $destination, $cash, $opt] = $x;
                if ($origin == $plane->alliances[0]) {
                    $sql = "INSERT INTO `pax` (`anger`, `cash`, `destination`, `location`, `optimal`, `origin`, `status`) VALUES ($optionAnger, $cash, '$destination', '$origin', $opt, '$origin', 'PORT')";
                    $this->DbQuery($sql);
                    unset($pax[$k]);
                    break;
                }
            }
            // Create starting flight plan
            $this->addFlightPlan($plane, $plane->alliances[0]);
        }

        // Create queued passengers in each hour
        $playerCount = count($planes);
        $paxCounts = N_REF_HOUR_PAX[$playerCount];
        foreach ($paxCounts as $status => $count) {
            $hourPax = array_splice($pax, $count * -1);
            foreach ($hourPax as $x) {
                [$origin, $destination, $cash, $opt] = $x;
                $sql = "INSERT INTO `pax` (`anger`, `cash`, `destination`, `optimal`, `origin`, `status`) VALUES ($optionAnger, $cash, '$destination', $opt, '$origin', '$status')";
                $this->DbQuery($sql);
            }
        }
    }

    private function addPax(array $hourInfo)
    {
        $pax = $this->getPaxByStatus($hourInfo['hour'], $hourInfo['draw']);
        if (empty($pax)) {
            throw new BgaVisibleSystemException("addPax: No passengers to add [???]");
        }

        foreach ($pax as $x) {
            $x->status = 'SECRET';
            $x->location = $x->origin;
            $this->DbQuery("UPDATE `pax` SET `location` = '{$x->location}', `status` = 'SECRET' WHERE `pax_id` = {$x->id}");
        }

        $this->notifyAllPlayers('pax', N_REF_MSG['addPax'], [
            'count' => count($pax),
            'location' => $this->getPaxLocations($pax),
            'pax' => array_values($this->filterPax($pax)),
        ]);
    }

    private function filterPax(array $pax): array
    {
        foreach ($pax as $x) {
            if ($x->status == 'SECRET') {
                // Don't display cash or destination
                $x->cash = 0;
                $x->destination = null;
            }
        }
        // VIP Double is never included (managed by client)
        return array_filter($pax, function ($x) {
            return $x->id > 0;
        });
    }

    private function getPaxLocations(array $pax): string
    {
        $uniq = [];
        foreach ($pax as $x) {
            if ($x->location) {
                $uniq[$x->location] = true;
            }
        }
        $locations = array_keys($uniq);
        sort($locations);
        return join(', ', $locations);
    }

    private function angerPax(): void
    {
        $pax = $this->getPaxByStatus(['SECRET', 'PORT']);
        if (!empty($pax)) {
            // VIP Baby
            $babyLocations = $this->getObjectListFromDB("SELECT DISTINCT `location` FROM `pax` WHERE `status` = 'PORT' AND `vip` = 'BABY'", true);
            $angerPax = [];
            $complaintPax = [];
            foreach ($pax as $x) {
                if ($x->vip == 'CREW') {
                    // VIP Crew
                    // Never angry
                    continue;
                }
                $increase = $x->vip != 'BABY' && in_array($x->location, $babyLocations) ? 2 : 1;
                $x->anger += $increase;
                if ($x->anger < 4) {
                    // Increase anger
                    $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger} WHERE `pax_id` = {$x->id}");
                    $angerPax[] = $x;
                } else {
                    if ($x->id > 0) {
                        // File complaint
                        $x->status = 'COMPLAINT';
                        $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `status` = 'COMPLAINT' WHERE `pax_id` = {$x->id}");
                        $complaintPax[] = $x;
                    } else {
                        // VIP Double
                        // Delete the fugitive
                        $this->DbQuery("DELETE FROM `pax` WHERE `pax_id` = {$x->id}");
                    }
                }
            }
            $this->notifyAllPlayers('pax', '', [
                'pax' => array_values($pax),
            ]);
            if (!empty($angerPax)) {
                $this->notifyAllPlayers('message', N_REF_MSG['anger'], [
                    'count' => count($angerPax),
                ]);
            }
            if (!empty($complaintPax)) {
                $count = count($complaintPax);
                $total = $this->countComplaint();
                $this->notifyAllPlayers('complaint', N_REF_MSG['complaintPort'], [
                    'complaint' => $count,
                    'countToWin' => $this->countToWin(),
                    'location' => $this->getPaxLocations($complaintPax),
                    'total' => $total,
                ]);
                if ($total >= 3) {
                    throw new NGameOverException();
                }
            }
        }
    }

    private function angerVips(string $hour): void
    {
        // File complaint for every unserved VIP
        $vips = $this->globals->get('vips');
        $count = count($vips[$hour]);
        if ($count > 0) {
            // Erase missed VIPs
            $vips[$hour] = [];
            $this->globals->set('vips', $vips);
            $this->notifyAllPlayers('vip', '', [
                'overall' => $this->getVipOverall($vips)
            ]);

            // Notify complaints
            $this->incStat($count, 'complaintVip');
            $total = $this->countComplaint();
            $this->notifyAllPlayers('complaint', N_REF_MSG['complaintVip'], [
                'i18n' => ['hourDesc'],
                'complaint' => $count,
                'countToWin' => $this->countToWin(),
                'hourDesc' => N_REF_HOUR[$hour]['desc'],
                'total' => $total,
            ]);
            if ($total >= 3) {
                throw new NGameOverException();
            }
        }
    }

    private function endGame(): void
    {
        // Calculate final statistics
        $planes = $this->getPlanesByIds();
        $playerCount = count($planes);
        $this->globals->set('progression', N_REF_PROGRESSION[$playerCount]);
        $totalAlliances = 0;
        $totalSeat = 0;
        $totalSpeed = 0;
        $tempSeat = 0;
        $tempSpeed = 0;
        foreach ($planes as $plane) {
            $totalAlliances += count($plane->alliances);
            $totalSeat += $plane->seat;
            $totalSpeed += $plane->speed;
            $tempSeat += intval($this->getStat('tempSeat', $plane->id));
            $tempSpeed += intval($this->getStat('tempSpeed', $plane->id));
            for ($seat = 1; $seat <= $plane->seat; $seat++) {
                $seatEmpty = intval($this->getStat("seatEmpty$seat", $plane->id));
                if ($seatEmpty > 0) {
                    $seatFull = intval($this->getStat("seatFull$seat", $plane->id));
                    // BGA bug #109: https://studio.boardgamearena.com/bug?id=109
                    // setStat is broken, use initStat as workaround
                    $this->initStat('player', "seat$seat", round($seatFull / $seatEmpty * 100, 2), $plane->id);
                }
            }
        }
        $this->setStat($totalAlliances / $playerCount, 'alliances');
        $this->setStat($totalSeat / $playerCount, 'seat');
        $this->setStat($totalSpeed / $playerCount, 'speed');
        $this->setStat($tempSeat, 'tempSeat');
        $this->setStat($tempSpeed, 'tempSpeed');

        $complaintPort = $this->countPaxByStatus('COMPLAINT');
        $journeyAvg = intval($this->getUniqueValueFromDB("SELECT AVG(`moves`) FROM `pax` WHERE `status` IN ('CASH', 'PAID')"));
        $journeyMax = intval($this->getUniqueValueFromDB("SELECT MAX(`moves`) FROM `pax` WHERE `status` IN ('CASH', 'PAID')"));
        $this->setStat($complaintPort, 'complaintPort');
        $this->setStat($journeyAvg, 'journeyAvg');
        $this->setStat($journeyMax, 'journeyMax');

        // Calculate final score
        $complaint = $this->countComplaint();
        if ($complaint >= 3) {
            $this->DbQuery("UPDATE `player` SET `player_score` = 0");
            $this->notifyAllPlayers('message', N_REF_MSG['endLose'], [
                'complaint' => $complaint,
            ]);
        } else {
            // 2p = 14, 3p = 16, 4p = 20, 5p = 24
            $score = ceil(pow($playerCount, 1.6)) + 10;
            if ($this->getOption(N_OPTION_TIMER) != 1) {
                // Half score with double/unlimited timer
                $score = ceil($score / 2);
            }
            $this->DbQuery("UPDATE `player` SET `player_score` = $score");
            $this->notifyAllPlayers('message', N_REF_MSG['endWin'], []);
        }

        // Notify flight plans
        $this->notifyAllPlayers('plans', '', ['plans' => $this->getFlightPlans()]);

        // Really end the game
        $this->gamestate->nextState('end');
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utilities
    ////////////

    private function generatePermutations(array $array): Generator
    {
        // https://en.wikipedia.org/wiki/Permutation#Generation_in_lexicographic_order
        // Sort the array and this is the first permutation
        sort($array);
        $y = 1;
        yield $array;

        $count = count($array);
        do {
            // Find the largest index k where a[k] < a[k + 1]
            // End when no such index exists
            $found = false;
            for ($k = $count - 2; $k >= 0; $k--) {
                $kvalue = $array[$k];
                $knext = $array[$k + 1];
                if ($kvalue < $knext) {
                    // Find the largest index l greater than k where a[k] < a[l]
                    for ($l = $count - 1; $l > $k; $l--) {
                        $lvalue = $array[$l];
                        if ($kvalue < $lvalue) {
                            // Swap a[k] and a[l]
                            [$array[$k], $array[$l]] = [$array[$l], $array[$k]];

                            // Reverse the array from a[k + 1] through the end
                            $reverse = array_reverse(array_slice($array, $k + 1));
                            array_splice($array, $k + 1, $count, $reverse);
                            $y++;
                            yield $array;

                            // Restart with the new array to find the next permutation
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
        } while ($found);
        $this->debug("generatePermutations completed: $y iterations for count=$count // ");
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    public function zombieTurn($state, $playerId)
    {
        $stateName = $state['name'];
        $this->debug("zombieTurn state name $stateName // ");
        if ($stateName == 'build' || $stateName == 'prepare') {
            $this->applyUndo($playerId);
        }
        $plane = $this->getPlaneById($playerId);

        // Surrender passengers
        $pax = $this->getPaxByStatus('SEAT', null, $playerId);
        if (!empty($pax)) {
            foreach ($pax as $x) {
                $x->resetAnger($this->getOption(N_OPTION_ANGER, 0));
                $x->playerId = null;
                $x->status = 'PORT';
                $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `player_id` = NULL, `status` = '{$x->status}' WHERE `pax_id` = {$x->id}");
                if ($x->id > 0) {
                    $this->notifyAllPlayers('message', N_REF_MSG['deplane'], [
                        'location' => $x->location,
                        'player_id' => $plane->id,
                        'player_name' => $plane->name,
                        'route' => $x->origin . "-" . $x->destination,
                    ]);
                }
            }
            $this->notifyAllPlayers('pax', '', [
                'pax' => array_values($this->filterPax($pax)),
            ]);
        }

        // Surrender temporary purchases
        if ($plane->tempSeat) {
            $this->useTempSeat($plane, 0);
        }
        if ($plane->tempSpeed) {
            $this->useTempSpeed($plane, 0);
        }
        $this->notifyAllPlayers('planes', '', [
            'planes' => [$plane],
        ]);

        // End turn
        $next = $stateName == 'prepare'  ? 'reveal' : 'maintenance';
        $this->gamestate->setPlayerNonMultiactive($playerId, $next);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    public function upgradeTableDb($fromVersion)
    {
        $changes = [
            [2407130558, "CREATE TABLE IF NOT EXISTS `bga_globals` (`name` varchar(50) NOT NULL, `value` json DEFAULT NULL, PRIMARY KEY (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8"],
        ];
        foreach ($changes as [$version, $sql]) {
            if ($fromVersion <= $version) {
                $this->warn("upgradeTableDb: fromVersion=$fromVersion, change=[ $version, $sql ]");
                $this->applyDbUpgradeToAllDB($sql);
            }
        }

        if ($fromVersion <= 2407071822) {
            $this->warn("upgradeTableDb: fromVersion=$fromVersion, calculate countToWin");
            $this->countToWin();
        }

        if ($fromVersion <= 2407130558) {
            $this->warn("upgradeTableDb: fromVersion=$fromVersion, populate globals");

            $vips = [
                'MORNING' => [],
                'NOON' => [],
                'NIGHT' => [],
            ];
            $morning = $this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = 'vipMORNING'");
            if (!empty($morning)) {
                $vips['MORNING'] = explode(',', $morning);
            }
            $noon = $this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = 'vipNOON'");
            if (!empty($noon)) {
                $vips['NOON'] = explode(',', $noon);
            }
            $night = $this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = 'vipNIGHT'");
            if (!empty($night)) {
                $vips['NIGHT'] = explode(',', $night);
            }

            $weather = [];
            foreach ($this->getObjectListFromDB("SELECT `hour`, `location`, `token` FROM `weather`") as $row) {
                $weather[$row['hour']][$row['location']] = $row['token'];
            }

            $this->globals->set('countToWin', intval($this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = 'countToWin'")));
            $this->globals->set('hour', $this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = 'hour'"));
            $this->globals->set('progression', intval($this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = 'progression'")));
            $this->globals->set('vipNew', boolval($this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = 'vipNew'")));
            $this->globals->set('vips', $vips);
            $this->globals->set('vipWelcome', $this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = 'vipWelcome'"));
            $this->globals->set('weather', $weather);
        }

        // Give extra time
        $this->giveExtraTimeAll(9999);
        $this->globals->delete('endTime');

        $this->warn("upgradeTableDb complete: fromVersion=$fromVersion");
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// Production bug report handler
    //////////

    public function loadBugReportSQL(int $reportId, array $studioPlayers): void
    {
        $prodPlayers = $this->getObjectListFromDb("SELECT `player_id` FROM `player`", true);
        $prodCount = count($prodPlayers);
        $studioCount = count($studioPlayers);
        if ($prodCount != $studioCount) {
            throw new BgaVisibleSystemException("Incorrect player count (bug report has $prodCount players, studio table has $studioCount players)");
        }
        $sql = [
            "UPDATE `global` SET `global_value` = 2 WHERE `global_id` = 1 AND `global_value` = 99"
        ];
        foreach ($prodPlayers as $index => $prodId) {
            $studioId = $studioPlayers[$index];
            $sql[] = "UPDATE `global` SET `global_value` = $studioId WHERE `global_value` = $prodId";
            $sql[] = "UPDATE `player` SET `player_id` = $studioId WHERE `player_id` = $prodId";
            $sql[] = "UPDATE `pax` SET `player_id` = $studioId WHERE `player_id` = $prodId";
            $sql[] = "UPDATE `pax_undo` SET `player_id` = $studioId WHERE `player_id` = $prodId";
            $sql[] = "UPDATE `plane` SET `player_id` = $studioId WHERE `player_id` = $prodId";
            $sql[] = "UPDATE `plane_undo` SET `player_id` = $studioId WHERE `player_id` = $prodId";
            $sql[] = "UPDATE `stats` SET `stats_player_id` = $studioId WHERE `stats_player_id` = $prodId";
            $sql[] = "UPDATE `stats_undo` SET `stats_player_id` = $studioId WHERE `stats_player_id` = $prodId";
        }
        foreach ($sql as $q) {
            $this->DbQuery($q);
        }
        $this->reloadPlayersBasicInfos();
    }
}
