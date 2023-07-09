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
require_once 'modules/NMap.class.php';
require_once 'modules/NMove.class.php';
require_once 'modules/NNode.class.php';
require_once 'modules/NNodeHop.class.php';
require_once 'modules/NNodePort.class.php';
require_once 'modules/NPax.class.php';
require_once 'modules/NPlane.class.php';

class NowBoarding extends Table
{
    public $hourDesc;
    public $exMsg;
    public $msg;

    function __construct()
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

        // Erase beginner flags (affects giveExtraTime)
        $this->DbQuery("UPDATE `player` SET `player_beginner` = NULL");
        $this->reloadPlayersBasicInfos();

        // Table statistics
        $this->initStat('table', 'complaint', 0);
        $this->initStat('table', 'moves', 0);
        $this->initStat('table', 'movesFAST', 0);
        $this->initStat('table', 'movesSLOW', 0);
        $this->initStat('table', 'pax', 0);
        $this->initStat('table', 'stopsAvg', 0);
        $this->initStat('table', 'stops0', 0);
        $this->initStat('table', 'stops1', 0);
        $this->initStat('table', 'stops2', 0);
        $this->initStat('table', 'stops3', 0);
        $this->initStat('table', 'stops4', 0);
        $this->initStat('table', 'stops5', 0);
        $this->initStat('table', 'stops6', 0);
        $this->initStat('table', 'stops7', 0);
        $this->initStat('table', 'alliances', 0);
        $this->initStat('table', 'seat', 0);
        $this->initStat('table', 'speed', 0);
        $this->initStat('table', 'tempSeat', 0);
        $this->initStat('table', 'tempSpeed', 0);

        // Player statistics
        $this->initStat('player', 'moves', 0);
        $this->initStat('player', 'movesFAST', 0);
        $this->initStat('player', 'movesSLOW', 0);
        $this->initStat('player', 'ATL', 0);
        $this->initStat('player', 'DEN', 0);
        $this->initStat('player', 'DFW', 0);
        $this->initStat('player', 'JFK', 0);
        $this->initStat('player', 'MIA', 0);
        $this->initStat('player', 'ORD', 0);
        if ($playerCount >= 4) {
            $this->initStat('player', 'SEA', 0);
        }
        $this->initStat('player', 'SFO', 0);
        $this->initStat('player', 'pax', 0);
        $this->initStat('player', 'cash', 0);
        $this->initStat('player', 'overpay', 0);
        $this->initStat('player', 'alliances', 0);
        $this->initStat('player', 'seat', 1);
        $this->initStat('player', 'speed', 3);
        $this->initStat('player', 'tempSeat', 0);
        $this->initStat('player', 'tempSpeed', 0);

        // Set the hour
        $this->setVar('hour', 'PREFLIGHT');
        $this->createUndo();
    }

    function checkVersion(int $clientVersion): void
    {
        $gameVersion = $this->gamestate->table_globals[N_OPTION_VERSION];
        if ($clientVersion != $gameVersion) {
            throw new BgaVisibleSystemException($this->exMsg['version']);
        }
    }

    protected function getAllDatas(): array
    {
        $players = $this->getCollectionFromDb("SELECT player_id id, player_score score FROM player");
        return [
            'complaint' => $this->countPaxByStatus('COMPLAINT'),
            'hour' => $this->getHourInfo(),
            'map' => $this->getMap(),
            'pax' => $this->filterPax($this->getPaxByStatus(['SECRET', 'PORT', 'SEAT'])),
            'planes' => $this->getPlanesByIds(),
            'players' => $players,
            'version' => intval($this->gamestate->table_globals[N_OPTION_VERSION]),
        ];
    }

    function getGameProgression(): int
    {
        $paxTotal = $this->countPaxByStatus();
        if ($paxTotal == 0) {
            return 0;
        }
        $paxRemain = $this->countPaxByStatus(['MORNING', 'NOON', 'NIGHT']);
        return ($paxTotal - $paxRemain) / $paxTotal * 100;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// States
    ////////////

    /*
     * SETUP
     * Each player builds their plane
     */
    function stInitPrivate()
    {
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
    }

    /*
     * SETUP #1
     * Each player chooses a starting airport and alliance
     */
    function argBuildAlliance(int $playerId): array
    {
        $buys = [];
        $claimed = [];
        $playerCount = $this->getPlayersNumber();
        if ($playerCount <= 3) {
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
    function argBuildAlliance2(int $playerId): array
    {
        $buys = [];
        $claimed = $this->getObjectListFromDB("SELECT `alliances` FROM `plane` WHERE `player_id` = $playerId", true);
        // Exclude Seattle for 2-3 players
        $claimed[] = 'SEA';
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
    function argBuildUpgrade(int $playerId): array
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

    /*
     * MAINTENANCE
     * Create passengers (first turn only)
     * Increase anger and file complaints
     * Add new passengers
     */

    function stMaintenance(): void
    {
        $hourInfo = $this->getHourInfo(true);
        if ($hourInfo['hour'] == 'PREFLIGHT') {
            // Create pax on the first turn
            $this->eraseUndo();
            $this->createPax();
            $pax = $this->getPaxByStatus('PORT');
            $this->notifyAllPlayers('pax', $this->msg['addPax'], [
                'count' => count($pax),
                'pax' => array_values($pax),
                'location' => $this->getPaxLocations($pax),
            ]);
        } else {
            // Add anger/complaints on subsequent turns
            $didEndGame = $this->angerPax();
            if ($didEndGame) {
                return;
            }
        }

        if ($hourInfo['hour'] == 'FINALE') {
            // Add complaints on final round
            $this->endGame();
        } else {
            // Advance the hour
            $hourInfo = $this->advanceHour($hourInfo);
            if ($hourInfo['hour'] != 'FINALE') {
                if ($hourInfo['round'] == 1) {
                    $this->addWeather();
                }
                $this->addPax($hourInfo);
            }
            $this->gamestate->nextState('prepare');
        }
    }

    /*
     * PREPARE
     * Reset planes
     * Add passengers
     * Players purchase upgrades
     */
    function stPrepare()
    {
        $this->giveExtraTimeAll();
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->initializePrivateStateForAllActivePlayers();
        $this->DbQuery("UPDATE `plane` SET `speed_remain` = `speed`");
        $this->createUndo();
        $planes = $this->getPlanesByIds();
        $this->notifyAllPlayers('planes', '', [
            'planes' => array_values($planes)
        ]);
    }

    function argPrepareBuy(int $playerId): array
    {
        $plane = $this->getPlaneById($playerId);
        $cash = $plane->getCashRemain();
        $wallet = $this->getPaxWallet($plane->id);
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
        if (!$plane->tempSeat) {
            $ownerId = $this->getOwnerId("`temp_seat` = 1");
            $buys[] = [
                'type' => 'TEMP_SEAT',
                'cost' => $cost,
                'enabled' => $cash >= $cost,
                'ownerId' => $ownerId,
            ];
        }

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
        if (!$plane->tempSpeed) {
            $ownerId = $this->getOwnerId("`temp_speed` = 1");
            $buys[] = [
                'type' => 'TEMP_SPEED',
                'cost' => $cost,
                'enabled' => $cash >= $cost,
                'ownerId' => $ownerId,
            ];
        }

        // Alliances
        $cost = 7;
        $claimed = $plane->alliances;
        $playerCount = $this->getPlayersNumber();
        if ($playerCount <= 3) {
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

        $args = [
            'buys' => $buys,
            'cash' => $cash,
            'wallet' => array_values($wallet),
        ];
        if ($plane->debt > 0) {
            $ledger = $this->getLedger($playerId);
            $pay = $this->_argPreparePay($plane, $wallet);
            $args['ledger'] = $ledger;
            $args['overpay'] = $pay['overpay'];
        }

        return $args;
    }

    function argPreparePay(int $playerId): array
    {
        $plane = $this->getPlaneById($playerId);
        $wallet = $this->getPaxWallet($plane->id);
        return $this->_argPreparePay($plane, $wallet);
    }

    function _argPreparePay(NPlane $plane, array $wallet): array
    {
        $walletCount = count($wallet);
        $suggestion = null;
        $overpay = null;
        foreach ($this->generatePermutations($wallet) as $p) {
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
            'wallet' => array_values($wallet),
        ];
    }

    /*
     * FLIGHT
     * Reveal passengers
     * Start the clock
     * Players transport passengers
     */

    function stReveal(): void
    {
        // Remove undo info
        $this->eraseUndo();

        // Reveal passengers
        $pax = $this->getPaxByStatus('SECRET');
        foreach ($pax as $x) {
            $x->status = 'PORT';
            $this->DbQuery("UPDATE `pax` SET `status` = 'PORT' WHERE `pax_id` = {$x->id}");
        }
        $this->notifyAllPlayers('pax', '', [
            'pax' => array_values($pax),
        ]);

        // Reset thinking time
        $this->giveExtraTimeAll(null, true);

        // Play the sound and begin flying
        $this->notifyAllPlayers('sound', '', [
            'sound' => 'chime',
            'suppress' => ['yourturn'],
        ]);
        $this->gamestate->nextState('fly');
    }

    function argFlyPrivate(int $playerId): array
    {
        $map = $this->getMap();
        $plane = $this->getPlaneById($playerId);
        return [
            'moves' => $map->getPossibleMoves($plane),
            'paxDrop' => [],
            'paxPickup' => [],
            'speedRemain' => max(0, $plane->speedRemain),
        ];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Actions (ajax)
    ////////////

    function undo(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->applyUndo($playerId);

        $plane = $this->getPlaneById($playerId);
        $this->notifyAllPlayers('planes', $this->msg['undo'], [
            'planes' => [$plane],
            'player_id' => $playerId,
            'player_name' => $this->getCurrentPlayerName(),
        ]);
        $this->gamestate->setPlayersMultiactive([$playerId], '');
        $this->gamestate->initializePrivateState($playerId);
    }

    function buy(string $type, ?string $alliance): void
    {
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
            throw new BgaUserException(sprintf($this->exMsg['allianceOwner'], $owner, $alliance));
        }

        $color = N_REF_ALLIANCE_COLOR[$alliance];
        $plane->alliances = [$alliance];
        $plane->location = $alliance;
        $this->DbQuery("UPDATE `plane` SET `alliances` = '{$plane->getAlliancesSql()}', `location` = '{$plane->location}' WHERE `player_id` = {$plane->id}");
        $this->DbQuery("UPDATE `player` SET `player_color` = '$color' WHERE `player_id` = {$plane->id}");
        $this->reloadPlayersBasicInfos();

        $this->notifyAllPlayers('buildPrimary', $this->msg['alliance'], [
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

        // Statistics
        $this->setStat(1, 'alliances', $plane->id);

        $playerCount = $this->getPlayersNumber();
        $this->gamestate->nextPrivateState($plane->id,  $playerCount == 2 ? 'buildAlliance2' : 'buildUpgrade');
    }

    private function buyAlliance(NPlane $plane, string $alliance): void
    {
        $isBuild = $this->gamestate->state()['name'] == 'build';
        if (in_array($alliance, $plane->alliances)) {
            throw new BgaUserException(sprintf($this->exMsg['allianceDuplicate'], $alliance));
        }
        $cost = $isBuild ? 0 : 7;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->alliances[] = $alliance;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `alliances` = '{$plane->getAlliancesSql()}' WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'ALLIANCE', $alliance, $cost);

        $this->notifyAllPlayers('planes', $this->msg['alliance'], [
            'alliance' => $alliance,
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        // Statistics
        $this->setStat(count($plane->alliances), 'alliances', $plane->id);

        $this->gamestate->nextPrivateState($plane->id, $isBuild ? 'buildUpgrade' : 'prepareBuy');
    }

    private function buySeat(NPlane $plane): void
    {
        if ($plane->seat >= 5) {
            throw new BgaVisibleSystemException("buySeat: $plane already at maximum seats 5 [???]");
        }
        $isBuild = $this->gamestate->state()['name'] == 'build';
        $cost = $isBuild ? 0 : N_REF_SEAT_COST[$plane->seat + 1];
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->seat++;
        $plane->seatRemain++;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `seat` = {$plane->seat} WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'SEAT', $plane->seat, $cost);

        $this->notifyAllPlayers('planes', $this->msg['seat'], [
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'seat' => $plane->seat,
        ]);

        // Statistics
        $this->setStat($plane->seat, 'seat', $plane->id);

        if ($isBuild) {
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'maintenance');
        } else {
            $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
        }
    }

    private function buyTempSeat(NPlane $plane): void
    {
        $owner = $this->getOwnerName("`temp_seat` = 1");
        if ($owner != null) {
            throw new BgaUserException(sprintf($this->exMsg['tempOwner'], $owner, $this->_('Temporary Seat')));
        }
        $cost = 2;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->tempSeat = true;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `temp_seat` = 1 WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'TEMP_SEAT', null, $cost);

        $this->notifyAllPlayers('planes', $this->msg['temp'], [
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

        // Statistics
        $this->incStat(1, 'tempSeat', $plane->id);

        $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
    }

    private function buySpeed(NPlane $plane): void
    {
        if ($plane->speed >= 9) {
            throw new BgaVisibleSystemException("buySpeed: $plane already at maximum speed 9 [???]");
        }
        $isBuild = $this->gamestate->state()['name'] == 'build';
        $cost = $isBuild ? 0 : N_REF_SPEED_COST[$plane->speed + 1];
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->speed++;
        $plane->speedRemain = $plane->speed;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `speed` = {$plane->speed}, `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'SPEED', $plane->speed, $cost);

        $this->notifyAllPlayers('planes', $this->msg['speed'], [
            'planes' => [$plane],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
            'speed' => $plane->speed,
        ]);

        // Statistics
        $this->setStat($plane->speed, 'speed', $plane->id);

        if ($isBuild) {
            $this->gamestate->setPlayerNonMultiactive($plane->id, 'maintenance');
        } else {
            $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
        }
    }

    private function buyTempSpeed(NPlane $plane): void
    {
        $owner = $this->getOwnerName("`temp_speed` = 1");
        if ($owner != null) {
            throw new BgaUserException(sprintf($this->exMsg['tempOwner'], $owner, $this->_('Temporary Speed')));
        }
        $cost = 1;
        $cash = $plane->getCashRemain();
        if ($cash < $cost) {
            throw new BgaUserException(sprintf($this->exMsg['noCash'], "\${$cost}", "\${$cash}"));
        }

        $plane->debt += $cost;
        $plane->tempSpeed = true;
        $this->DbQuery("UPDATE `plane` SET `debt` = {$plane->debt}, `temp_speed` = 1 WHERE `player_id` = {$plane->id}");
        $this->addLedger($plane->id, 'TEMP_SPEED', null, $cost);

        $this->notifyAllPlayers('planes', $this->msg['temp'], [
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

        // Statistics
        $this->incStat(1, 'tempSpeed', $plane->id);

        $this->gamestate->nextPrivateState($plane->id, 'prepareBuy');
    }

    function buyAgain(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->nextPrivateState($playerId, 'prepareBuy');
    }

    function pay($paid): void
    {
        $playerId = $this->getCurrentPlayerId();
        $wallet = $this->getPaxWallet($playerId);
        $total = 0;
        $validIds = [];
        foreach ($paid as $cash) {
            if (!$cash) {
                continue;
            }
            $paxId = array_search($cash, $wallet);
            if ($paxId === false) {
                throw new BgaVisibleSystemException("pay: Wallet $playerId has no \$$cash bill with validIds=" . join(',', $validIds) . " [???]");
            }
            unset($wallet[$paxId]);
            $total += $cash;
            $validIds[] = $paxId;
        }

        $plane = $this->getPlaneById($playerId);
        if ($total < $plane->debt) {
            throw new BgaUserException(sprintf($this->exMsg['noPay'], "\${$plane->debt}"));
        }

        // Payment
        $this->DbQuery("UPDATE `pax` SET `status` = 'PAID' WHERE `pax_id` IN (" . join(',', $validIds) . ")");
        $this->DbQuery("UPDATE `plane` SET `debt` = 0 WHERE `player_id` = $playerId");

        // Update plane gauges UI (e.g., overpayment)
        $plane = $this->getPlaneById($playerId);
        $this->notifyAllPlayers('planes', '', [
            'planes' => [$plane],
        ]);

        // Statistics
        $overpay = $plane->debt - $total;
        if ($overpay > 0) {
            $this->incStat($overpay, 'overpay', $plane->id);
        }

        $this->gamestate->setPlayerNonMultiactive($playerId, 'reveal');
    }

    function prepareDone(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        if ($plane->debt > 0) {
            $this->gamestate->nextPrivateState($plane->id, 'preparePay');
        } else {
            $this->gamestate->setPlayerNonMultiactive($playerId, 'reveal');
        }
    }

    function flyDone(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($playerId, 'maintenance');
    }

    function flyAgain(): void
    {
        $playerId = $this->getCurrentPlayerId();
        $this->gamestate->setPlayersMultiactive([$playerId], '');
    }

    function move(string $location): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        $map = $this->getMap();
        $possible = $map->getPossibleMoves($plane);
        if (!array_key_exists($location, $possible)) {
            throw new BgaVisibleSystemException("move: $plane cannot reach $location [???]");
        }

        $move = $possible[$location];
        $plane->origin = $move->getOrigin();
        $plane->location = $location;
        $plane->speedRemain -= $move->fuel;
        if ($plane->speedRemain == -1 && $plane->tempSpeed) {
            $plane->tempSpeed = false;
            $this->DbQuery("UPDATE `plane` SET `temp_speed` = NULL WHERE `player_id` = {$plane->id}");
            $this->notifyAllPlayers('message', $this->msg['tempUsed'], [
                'i18n' => ['temp'],
                'preserve' => ['tempIcon'],
                'player_id' => $plane->id,
                'player_name' => $plane->name,
                'temp' => clienttranslate('Temporary Speed'),
                'tempIcon' => 'speed',
            ]);
        } else if ($plane->speedRemain < 0) {
            throw new BgaVisibleSystemException("move: $plane not enough fuel to reach $location with speedRemain={$plane->speedRemain}, tempSpeed={$plane->getTempSpeedSql()} [???]");
        }
        $this->DbQuery("UPDATE `plane` SET `location` = '{$plane->location}', `origin` = '{$plane->origin}', `speed_remain` = {$plane->speedRemain} WHERE `player_id` = {$plane->id}");

        // Statistics
        $this->incStat($move->fuel, 'moves');
        $this->incStat($move->fuel, 'moves', $playerId);
        array_shift($move->path);
        foreach ($move->path as $location) {
            if (strlen($location) == 3) {
                $this->incStat(1, $location, $playerId);
            }
            if (array_key_exists($location, $map->weather)) {
                $type = $map->weather[$location];
                $this->incStat(1, "moves$type");
                $this->incStat(1, "moves$type", $playerId);
            }
        }

        $msg = strlen($location) == 3 ? $this->msg['movePort'] : $this->msg['move'];
        $this->notifyAllPlayers('move', $msg, [
            'fuel' => $move->fuel,
            'location' => $plane->location,
            'plane' => $plane,
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    function board(int $paxId): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        $x = $this->getPaxById($paxId, true);
        $planeIds = [$playerId];

        if ($x->status == 'SEAT') {
            if ($x->playerId == $playerId) {
                throw new BgaVisibleSystemException("board: $x player ID is already $playerId [???]");
            }
            // Transfer from another plane
            // Must be together at an airport
            $other = $this->getPlaneById($x->playerId);
            if ($other->location != $plane->location || strlen($plane->location) != 3) {
                throw new BgaUserException(sprintf($this->exMsg['boardTransfer'], $other->name));
            }
            // Implicit deplane
            $planeIds[] = $other->id;
            $this->notifyAllPlayers('message', $this->msg['deplane'], [
                'location' => $other->location,
                'player_id' => $other->id,
                'player_name' => $other->name,
            ]);
            $x->stops++;
            if ($other->location != $plane->location) {
                // Erase anger if deplaned at a new location
                $x->anger = 0;
            }
        } else if ($x->status == 'PORT') {
            // Pickup from airport
            if (strlen($x->location) != 3) {
                throw new BgaVisibleSystemException("board: $x location is not at an airport [???]");
            }
            if ($x->location != $plane->location) {
                throw new BgaUserException(sprintf($this->exMsg['boardPort'], $x->location));
            }
        } else {
            throw new BgaVisibleSystemException("board: $x status is invalid [???]");
        }

        if ($plane->seatRemain <= 0) {
            if (!$plane->tempSeat) {
                throw new BgaUserException($this->exMsg['noSeat']);
            }
            $plane->tempSeat = false;
            $this->DbQuery("UPDATE `plane` SET `temp_seat` = NULL WHERE `player_id` = {$plane->id}");
            $this->notifyAllPlayers('planes', $this->msg['tempUsed'], [
                'i18n' => ['temp'],
                'preserve' => ['tempIcon'],
                'planes' => [$plane],
                'player_id' => $plane->id,
                'player_name' => $plane->name,
                'temp' => clienttranslate('Temporary Seat'),
                'tempIcon' => 'seat',
            ]);
        }

        // Note: We preserve anger until deplane
        $x->playerId = $plane->id;
        $x->status = 'SEAT';
        $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `player_id` = {$x->playerId}, `status` = '{$x->status}', `stops` = {$x->stops} WHERE `pax_id` = {$x->id}");

        // Update UI empty seats
        $planes = $this->getPlanesByIds($planeIds);
        $this->notifyAllPlayers('planes', '', [
            'planes' => array_values($planes)
        ]);

        $this->notifyAllPlayers('pax', $this->msg['board'], [
            'location' => $x->location,
            'pax' => [$x],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ]);

        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    function deplane(int $paxId, bool $confirm = false): void
    {
        $playerId = $this->getCurrentPlayerId();
        $plane = $this->getPlaneById($playerId);
        $x = $this->getPaxById($paxId, true);
        if ($x->status != 'SEAT') {
            throw new BgaVisibleSystemException("deplane: $x status is invalid [???]");
        }
        if (strlen($plane->location) != 3) {
            throw new BgaUserException($this->exMsg['deplanePort']);
        }

        if ($x->location == $plane->location) {
            // Preserve anger if deplaned at prior location
            if ($x->anger > 0 && !$confirm) {
                throw new BgaUserException("!!!deplaneConfirm");
            }
        } else {
            // Erase anger if deplaned at a new location
            $x->anger = 0;
        }

        $x->location = $plane->location;
        $args = [
            'location' => $x->destination,
            'pax' => [$x],
            'player_id' => $plane->id,
            'player_name' => $plane->name,
        ];
        if ($x->destination == $plane->location) {
            $x->status = 'CASH';
            $msg = $this->msg['deplaneDeliver'];
            $args['cash'] = $x->cash;
            $this->incStat(1, 'pax');
            $this->incStat(1, 'pax', $playerId);
            $this->incStat($x->cash, 'cash', $playerId);
            $stops = min($x->stops, 7);
            $this->incStat(1, "stops$stops");
        } else {
            $x->playerId = null;
            $x->status = 'PORT';
            $x->stops++;
            $msg = $this->msg['deplane'];
        }
        $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `location` = '{$x->location}', `player_id` = {$x->getPlayerIdSql()}, `status` = '{$x->status}', `stops` = {$x->stops} WHERE `pax_id` = {$x->id}");

        // Update UI empty seats
        self::debug("Refresh plane for $playerId // ");
        $planes = $this->getPlanesByIds([$playerId]);
        $this->notifyAllPlayers('planes', '', [
            'planes' => array_values($planes)
        ]);

        $this->notifyAllPlayers('pax', $msg, $args);

        $this->gamestate->nextPrivateState($plane->id, 'flyPrivate');
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Helpers
    ////////////

    function getVar(string $key): ?string
    {
        return $this->getUniqueValueFromDB("SELECT `value` FROM `var` WHERE `key` = '$key'");
    }

    function setVar(string $key, string $value): void
    {
        $this->DbQuery("INSERT INTO `var` (`key`, `value`) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE `value` = '$value'");
    }

    function getHourInfo(bool $beforeAddPax = false): array
    {
        $playerCount = $this->getPlayersNumber();
        $hour = $this->getVar('hour');
        $hourInfo = [
            'hour' => $hour,
            'hourDesc' => $this->hourDesc[$hour],
        ];
        if ($hour == 'MORNING' || $hour == 'NOON' || $hour == 'NIGHT') {
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
        }
        return $hourInfo;
    }

    function advanceHour(array $hourInfo): array
    {
        $advance = $hourInfo['hour'] == 'PREFLIGHT' || $hourInfo['round'] > $hourInfo['total'];
        if ($advance) {
            $nextHour = N_REF_HOUR_NEXT[$hourInfo['hour']];
            $this->setVar('hour', $nextHour);
            $hourInfo = $this->getHourInfo(true);
        }

        // Notify hour
        $hourInfo['i18n'] = ['hourDesc'];
        if ($hourInfo['hour'] == 'FINALE') {
            $hourInfo['count'] = $this->countPaxByStatus(['PORT', 'SEAT']);
            $this->notifyAllPlayers('hour', $this->msg['hourFinale'], $hourInfo);
        } else {
            $this->notifyAllPlayers('hour', $this->msg['hour'], $hourInfo);
        }
        return $hourInfo;
    }

    function createUndo(): void
    {
        $this->DbQuery("INSERT INTO `pax_undo` SELECT * FROM `pax` WHERE `status` = 'CASH'");
        $this->DbQuery("INSERT INTO `plane_undo` SELECT * FROM `plane`");
        $this->DbQuery("INSERT INTO `stats_undo` SELECT * FROM `stats` WHERE `stats_type` >= 10 AND `stats_player_id` IS NOT NULL");
    }

    function applyUndo(int $playerId): void
    {
        $oldPlane = $this->getPlaneById($playerId);
        $this->DbQuery("DELETE FROM `ledger` WHERE `player_id` = $playerId");
        $this->DbQuery("DELETE FROM `pax` WHERE `player_id` = $playerId");
        $this->DbQuery("INSERT INTO `pax` SELECT * FROM `pax_undo` WHERE `player_id` = $playerId");
        $this->DbQuery("DELETE FROM `plane` WHERE `player_id` = $playerId");
        $this->DbQuery("INSERT INTO `plane` SELECT * FROM `plane_undo` WHERE `player_id` = $playerId");
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

    function eraseUndo(): void
    {
        $this->DbQuery("DELETE FROM `ledger`");
        $this->DbQuery("DELETE FROM `pax_undo`");
        $this->DbQuery("DELETE FROM `plane_undo`");
        $this->DbQuery("DELETE FROM `stats_undo`");
    }

    function getPlayerIds(): array
    {
        return $this->getObjectListFromDB("SELECT `player_id` FROM `player`", true);
    }

    function giveExtraTimeAll(?int $seconds = null, ?bool $reset = false)
    {
        if ($reset && !$this->isAsync()) {
            $this->DbQuery("UPDATE `player` SET `player_remaining_reflexion_time` = 0");
        }
        foreach ($this->getPlayerIds() as $playerId) {
            $this->giveExtraTime($playerId, $seconds);
        }
    }

    function getMap(): NMap
    {
        $playerCount = $this->getPlayersNumber();
        $weather = $this->getWeather();
        return new NMap($playerCount, $weather);
    }

    function getWeather(): array
    {
        return $this->getCollectionFromDb("SELECT `location`, `token` FROM `weather`", true);
    }

    function getPlaneById(int $playerId): NPlane
    {
        return $this->getPlanesByIds([$playerId])[$playerId];
    }

    function getPlanesByIds($ids = []): array
    {
        $sql = <<<SQL
SELECT
  p.*,
  p.seat - (
    SELECT
      COUNT(1)
    FROM
      `pax` x
    WHERE
      x.status = 'SEAT'
      AND x.player_id = p.player_id
  ) AS seat_remain,
  (
    SELECT
      SUM(cash)
    FROM
      `pax` x
    WHERE
      x.status = 'CASH'
      AND x.player_id = p.player_id
  ) AS cash,
  b.player_name
FROM
  `plane` p
  JOIN `player` b ON (b.player_id = p.player_id)
SQL;
        if (!empty($ids)) {
            $sql .= " WHERE p.player_id IN (" . join(',', $ids) . ")";
        }
        return array_map(function ($dbrow) {
            return new NPlane($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    function getOwnerName(string $sqlWhere): ?string
    {
        return $this->getUniqueValueFromDB(<<<SQL
SELECT
  b.`player_name`
FROM
  `plane` p
  JOIN `player` b ON (b.player_id = p.player_id)
WHERE $sqlWhere
LIMIT 1
SQL);
    }

    function getOwnerId(string $sqlWhere): ?string
    {
        return $this->getUniqueValueFromDB(<<<SQL
SELECT
  `player_id`
FROM
  `plane`
WHERE $sqlWhere
LIMIT 1
SQL);
    }

    function getPaxById(int $paxId, bool $lock = false): NPax
    {
        return $this->getPaxByIds([$paxId], $lock)[$paxId];
    }

    function getPaxByIds(array $ids = [], bool $lock = false): array
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

    function getPaxByStatus($status, ?int $limit = null, ?int $playerId = null): array
    {
        if (is_array($status)) {
            $status = join("', '", $status);
        }
        $sql = "SELECT * FROM `pax` WHERE `status` IN ('$status')";
        if ($playerId != null) {
            $sql .= " AND `player_id` = $playerId";
        }
        $sql .= " ORDER BY `pax_id`";
        if ($limit != null) {
            $sql .= " LIMIT $limit";
        }
        return array_map(function ($dbrow) {
            return new NPax($dbrow);
        }, $this->getCollectionFromDb($sql));
    }

    function countPaxByStatus($status = null): int
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

    function getPaxWallet(int $playerId): array
    {
        $sql = "SELECT `pax_id`, `cash` FROM `pax` WHERE `status` = 'CASH' AND `player_id` = $playerId ORDER BY `cash` DESC, `pax_id`";
        return array_map('intval', $this->getCollectionFromDB($sql, true));
    }

    function getLedger(int $playerId): array
    {
        $sql = "SELECT `type`, `arg`, `cost` FROM `ledger` WHERE `player_id` = $playerId ORDER BY `type`, `cost`";
        return $this->getObjectListFromDB($sql);
    }

    function addLedger(int $playerId, string $type, ?string $arg, int $cost): void
    {
        if ($arg) {
            $this->DbQuery("INSERT INTO `ledger` (`player_id`, `type`, `arg`, `cost`) VALUES ($playerId, '$type', '$arg', $cost)");
        } else {
            $this->DbQuery("INSERT INTO `ledger` (`player_id`, `type`, `cost`) VALUES ($playerId, '$type', $cost)");
        }
    }

    function createPax(): void
    {
        $planes = $this->getPlanesByIds();
        $playerCount = count($planes);
        $pax = [
            ['ATL', 'DEN', 2],
            ['ATL', 'DFW', 2],
            ['ATL', 'LAX', 4],
            ['ATL', 'MIA', 1],
            ['ATL', 'ORD', 2],
            ['ATL', 'SFO', 4],
            ['DEN', 'ATL', 2],
            ['DEN', 'DFW', 2],
            ['DEN', 'LAX', 2],
            ['DEN', 'MIA', 3],
            ['DEN', 'ORD', 2],
            ['DEN', 'SFO', 2],
            ['DFW', 'ATL', 2],
            ['DFW', 'DEN', 2],
            ['DFW', 'LAX', 2],
            ['DFW', 'MIA', 2],
            ['DFW', 'ORD', 3],
            ['DFW', 'SFO', 3],
            ['LAX', 'ATL', 4],
            ['LAX', 'DEN', 2],
            ['LAX', 'DFW', 2],
            ['LAX', 'MIA', 3],
            ['LAX', 'ORD', 3],
            ['LAX', 'SFO', 1],
            ['MIA', 'ATL', 1],
            ['MIA', 'DEN', 3],
            ['MIA', 'DFW', 2],
            ['MIA', 'LAX', 3],
            ['MIA', 'ORD', 3],
            ['MIA', 'SFO', 4],
            ['ORD', 'ATL', 2],
            ['ORD', 'DEN', 2],
            ['ORD', 'DFW', 3],
            ['ORD', 'LAX', 3],
            ['ORD', 'MIA', 3],
            ['ORD', 'SFO', 3],
            ['SFO', 'ATL', 4],
            ['SFO', 'DEN', 2],
            ['SFO', 'DFW', 3],
            ['SFO', 'LAX', 1],
            ['SFO', 'MIA', 4],
            ['SFO', 'ORD', 3],
        ];
        if ($playerCount >= 3) {
            // Include JFK with 3+ players
            array_push(
                $pax,
                ['ATL', 'JFK', 2],
                ['DEN', 'JFK', 3],
                ['DFW', 'JFK', 3],
                ['JFK', 'ATL', 2],
                ['JFK', 'DEN', 2],
                ['JFK', 'DFW', 3],
                ['JFK', 'LAX', 5],
                ['JFK', 'MIA', 3],
                ['JFK', 'ORD', 2],
                ['JFK', 'SFO', 4],
                ['LAX', 'JFK', 5],
                ['MIA', 'JFK', 3],
                ['ORD', 'JFK', 2],
                ['SFO', 'JFK', 4]
            );
        }
        if ($playerCount >= 4) {
            // Include SEA with 4+ players
            array_push(
                $pax,
                ['ATL', 'SEA', 4],
                ['DEN', 'SEA', 2],
                ['DFW', 'SEA', 3],
                ['JFK', 'SEA', 3],
                ['LAX', 'SEA', 3],
                ['MIA', 'SEA', 5],
                ['ORD', 'SEA', 2],
                ['SEA', 'ATL', 4],
                ['SEA', 'DEN', 2],
                ['SEA', 'DFW', 3],
                ['SEA', 'JFK', 3],
                ['SEA', 'LAX', 3],
                ['SEA', 'MIA', 5],
                ['SEA', 'ORD', 2],
                ['SEA', 'SFO', 2],
                ['SFO', 'SEA', 2]
            );
        }
        shuffle($pax);

        // Create starting passenger in each airport
        foreach ($planes as $plane) {
            foreach ($pax as $k => $x) {
                [$destination, $origin, $cash] = $x;
                if ($origin == $plane->alliances[0]) {
                    $sql = "INSERT INTO pax (`status`,`cash`, `destination`, `location`, `origin`) VALUES ('PORT', $cash, '$destination', '$origin', '$origin')";
                    $this->DbQuery($sql);
                    unset($pax[$k]);
                    $startingPax[] = $x;
                    break;
                }
            }
        }

        // Create queued passengers in each hour
        $paxCounts = N_REF_HOUR_PAX[$playerCount];
        foreach ($paxCounts as $status => $count) {
            $hourPax = array_splice($pax, $count * -1);
            foreach ($hourPax as $x) {
                [$destination, $origin, $cash] = $x;
                $sql = "INSERT INTO pax (`status`, `cash`, `destination`, `origin`) VALUES ('$status', $cash, '$destination', '$origin')";
                $this->DbQuery($sql);
            }
        }
    }

    function addPax(array $hourInfo)
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

        $this->notifyAllPlayers('pax', $this->msg['addPax'], [
            'count' => count($pax),
            'location' => $this->getPaxLocations($pax),
            'pax' => array_values($this->filterPax($pax)),
        ]);
    }

    function filterPax(array $pax): array
    {
        foreach ($pax as $x) {
            if ($x->status == 'SECRET') {
                $x->destination = null;
            }
        }
        return $pax;
    }

    function getPaxLocations(array $pax): string
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

    function angerPax(): bool
    {
        $pax = $this->getPaxByStatus('PORT');
        if (!empty($pax)) {
            $anger = [];
            $complaint = [];
            foreach ($pax as $x) {
                $x->anger++;
                if ($x->anger < 4) {
                    // Increase anger
                    $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger} WHERE `pax_id` = {$x->id}");
                    $anger[] = $x;
                } else {
                    // File complaint
                    $x->status = 'COMPLAINT';
                    $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `status` = 'COMPLAINT' WHERE `pax_id` = {$x->id}");
                    $complaint[] = $x;
                }
            }
            if (!empty($anger)) {
                $this->notifyAllPlayers('message', $this->msg['anger'], [
                    'count' => count($anger),
                ]);
            }
            if (!empty($complaint)) {
                $count = count($complaint);
                $total = $this->countPaxByStatus('COMPLAINT');
                $this->notifyAllPlayers('complaint', $this->msg['complaint'], [
                    'complaint' => $count,
                    'location' => $this->getPaxLocations($complaint),
                    'total' => $total,
                ]);
                $this->setStat($total, 'complaint');
                if ($total >= 3) {
                    $this->notifyAllPlayers('pax', $this->msg['endLose'], [
                        'complaint' => $total,
                        'pax' => array_values($pax),
                    ]);
                    $this->endStats();
                    return true;
                }
            }
            $this->notifyAllPlayers('pax', '', [
                'pax' => array_values($pax),
            ]);
        }
        return false;
    }

    function addWeather(): void
    {
        // Delete old weather
        $map = $this->getMap();
        $map->weather = [];
        $this->DbQuery("DELETE FROM `weather`");

        // Determine how much weather to add
        $playerCount = $this->getPlayersNumber();
        $tokens = ['FAST', 'SLOW', 'FAST', 'SLOW', 'FAST', 'SLOW'];
        if ($playerCount == 2) {
            array_splice($tokens, 2);
        } else if ($playerCount == 3) {
            array_splice($tokens, 4);
        }

        // Select a (different) random route for each token
        $desc = [];
        $routeIds = array_rand($map->routes, count($tokens));
        foreach ($routeIds as $routeId) {
            // Select a random node on the route
            $route = $map->routes[$routeId];
            $node = $route[array_rand($route)];
            $token = array_pop($tokens);
            $this->DbQuery("INSERT INTO weather (`location`, `token`) VALUES ('{$node->id}', '$token')");
            $map->weather[$node->id] = $token;
            $desc[$token][] = substr_replace($routeId, '-', 3, 0);
        }

        // Notify
        $this->notifyAllPlayers('weather', $this->msg['weather'], [
            'routeFast' => join(', ', $desc['FAST']),
            'routeSlow' => join(', ', $desc['SLOW']),
            'weather' => $map->weather,
        ]);
    }

    function endGame(): void
    {
        // End final round
        // File a complaint for every 2 pax
        $count = $this->countPaxByStatus(['PORT', 'SEAT']);
        $complaint = floor($count / 2);
        $total = $this->countPaxByStatus('COMPLAINT') + $complaint;
        if ($complaint > 0) {
            $this->notifyAllPlayers('complaint', $this->msg['complaintFinale'], [
                'complaint' => $complaint,
                'count' => $count,
                'total' => $total,
            ]);
            $this->setStat($total, 'complaint');
        }

        // Determine win/lose
        if ($total >= 3) {
            $this->notifyAllPlayers('message', $this->msg['endLose'], [
                'complaint' => $total,
            ]);
        } else {
            $this->DbQuery("UPDATE `player` SET `player_score` = 1");
            $this->notifyAllPlayers('message', $this->msg['endWin'], []);
        }
        $this->endStats();
    }

    function endStats(): void
    {
        // Calculate final statistics
        $planes = $this->getPlanesByIds();
        $playerCount = count($planes);
        $alliances = 0;
        $seat = 0;
        $speed = 0;
        $tempSeat = 0;
        $tempSpeed = 0;
        foreach ($planes as $plane) {
            $alliances += count($plane->alliances);
            $seat += $plane->seat;
            $speed += $plane->speed;
            $tempSeat += intval($this->getStat('tempSeat', $plane->id));
            $tempSpeed += intval($this->getStat('tempSpeed', $plane->id));
        }
        $this->setStat($alliances / $playerCount, 'alliances');
        $this->setStat($seat / $playerCount, 'seat');
        $this->setStat($speed / $playerCount, 'speed');
        $this->setStat($tempSeat, 'tempSeat');
        $this->setStat($tempSpeed, 'tempSpeed');

        $stopsAvg = $this->getUniqueValueFromDB("SELECT AVG(`stops`) FROM `pax` WHERE `status` IN ('CASH', 'PAID')");
        $this->setStat($stopsAvg, 'stopsAvg');

        // End the game
        $this->gamestate->nextState('end');
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utilities
    ////////////

    public function generatePermutations(array $array): Generator
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
        self::debug("generatePermutations completed: $y iterations for count=$count // ");
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    function zombieTurn($state, $playerId)
    {
        $stateName = $state['name'];
        self::debug("zombieTurn state name $stateName // ");
        if ($stateName == 'build' || $stateName == 'prepare') {
            $this->applyUndo($playerId);
        }
        $plane = $this->getPlaneById($playerId);

        // Surrender temporary purchases
        if ($plane->tempSeat) {
            $this->DbQuery("UPDATE `plane` SET `temp_seat` = NULL WHERE `player_id` = {$plane->id}");
            $this->notifyAllPlayers('message', $this->msg['tempUsed'], [
                'i18n' => ['temp'],
                'preserve' => ['tempIcon'],
                'player_id' => $plane->id,
                'player_name' => $plane->name,
                'temp' => clienttranslate('Temporary Seat'),
                'tempIcon' => 'seat',
            ]);
        }
        if ($plane->tempSpeed) {
            $this->DbQuery("UPDATE `plane` SET `temp_speed` = NULL WHERE `player_id` = {$plane->id}");
            $this->notifyAllPlayers('message', $this->msg['tempUsed'], [
                'i18n' => ['temp'],
                'preserve' => ['tempIcon'],
                'player_id' => $plane->id,
                'player_name' => $plane->name,
                'temp' => clienttranslate('Temporary Speed'),
                'tempIcon' => 'speed',
            ]);
        }
        if ($plane->tempSeat || $plane->tempSpeed) {
            $plane->tempSeat = false;
            $plane->tempSpeed = false;
            $this->notifyAllPlayers('planes', '', [
                'planes' => [$plane],
            ]);
        }

        // Surrender passengers
        $pax = $this->getPaxByStatus('SEAT', null, $playerId);
        if (!empty($pax)) {
            foreach ($pax as &$x) {
                $x->anger = 0;
                $x->playerId = null;
                $x->status = 'PORT';
                $x->stops++;
                $this->DbQuery("UPDATE `pax` SET `anger` = {$x->anger}, `player_id` = NULL, `status` = '{$x->status}', `stops` = {$x->stops} WHERE `pax_id` = {$x->id}");
                $this->notifyAllPlayers('message', $this->msg['deplane'], [
                    'location' => $x->location,
                    'player_id' => $plane->id,
                    'player_name' => $plane->name,
                ]);
            }
            $this->notifyAllPlayers('pax', '', [
                'pax' => array_values($pax),
            ]);
        }

        // End turn
        $next = $stateName == 'prepare'  ? 'reveal' : 'maintenance';
        $this->gamestate->setPlayerNonMultiactive($playerId, $next);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    function upgradeTableDb($fromVersion)
    {
    }
}
