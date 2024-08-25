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

require_once 'modules/constants.inc.php';

$stats_type = [
    'table' => [
        'complaintPort' => [
            'id' => 10,
            'name' => totranslate('Complaints: Passengers waiting'),
            'type' => 'int'
        ],
        'complaintFinale' => [
            'id' => 11,
            'name' => totranslate('Complaints: Passengers not delivered'),
            'type' => 'int'
        ],
        'complaintVip' => [
            'id' => 12,
            'name' => totranslate('Complaints: VIPs not accepted'),
            'type' => 'int'
        ],

        'moves' => [
            'id' => 15,
            'name' => totranslate('Moves'),
            'type' => 'int',
        ],
        'movesFAST' => [
            'id' => 16,
            'name' => totranslate('Moves through tailwinds'),
            'type' => 'int'
        ],
        'movesSLOW' => [
            'id' => 17,
            'name' => totranslate('Moves through storms'),
            'type' => 'int'
        ],
        'movesNormal' => [
            'id' => 40,
            'name' => totranslate('Moves on normal routes'),
            'type' => 'int'
        ],
        'movesATL' => [
            'id' => 41,
            'name' => totranslate('Moves on ATL routes'),
            'type' => 'int'
        ],
        'movesDFW' => [
            'id' => 42,
            'name' => totranslate('Moves on DFW routes'),
            'type' => 'int'
        ],
        'movesLAX' => [
            'id' => 43,
            'name' => totranslate('Moves on LAX routes'),
            'type' => 'int'
        ],
        'movesORD' => [
            'id' => 44,
            'name' => totranslate('Moves on ORD routes'),
            'type' => 'int'
        ],
        'movesSEA' => [
            'id' => 45,
            'name' => totranslate('Moves on SEA routes'),
            'type' => 'int'
        ],

        'pax' => [
            'id' => 35,
            'name' => totranslate('Passengers delivered'),
            'type' => 'int'
        ],
        'journeyAvg' => [
            'id' => 38,
            'name' => totranslate('Average moves/passenger'),
            'type' => 'int'
        ],
        'journeyMax' => [
            'id' => 39,
            'name' => totranslate('Maximum moves/passenger'),
            'type' => 'int'
        ],

        'alliances' => [
            'id' => 52,
            'name' => totranslate('Average alliances/player'),
            'type' => 'float'
        ],
        'seat' => [
            'id' => 53,
            'name' => totranslate('Average seats/player'),
            'type' => 'float'
        ],
        'speed' => [
            'id' => 54,
            'name' => totranslate('Average speed/player'),
            'type' => 'float'
        ],
        'tempSeat' => [
            'id' => 55,
            'name' => totranslate('Temporary Seats purchased'),
            'type' => 'int'
        ],
        'tempSeatUnused' => [
            'id' => 57,
            'name' => totranslate('Temporary Seats unused'),
            'type' => 'int'
        ],
        'tempSpeed' => [
            'id' => 56,
            'name' => totranslate('Temporary Speed purchased'),
            'type' => 'int'
        ],
        'tempSpeedUnused' => [
            'id' => 58,
            'name' => totranslate('Temporary Speed unused'),
            'type' => 'int'
        ],

        'vipMORNING' => [
            'id' => 70,
            'name' => totranslate('VIPs in Morning'),
            'type' => 'int'
        ],
        'vipNOON' => [
            'id' => 71,
            'name' => totranslate('VIPs in Afternoon'),
            'type' => 'int'
        ],
        'vipNIGHT' => [
            'id' => 72,
            'name' => totranslate('VIPs in Evening'),
            'type' => 'int'
        ],
    ],

    'player' => [
        'moves' => [
            'id' => 15,
            'name' => totranslate('Moves'),
            'type' => 'int',
        ],
        'movesFAST' => [
            'id' => 16,
            'name' => totranslate('Moves through tailwinds'),
            'type' => 'int'
        ],
        'movesSLOW' => [
            'id' => 17,
            'name' => totranslate('Moves through storms'),
            'type' => 'int'
        ],
        'movesNormal' => [
            'id' => 40,
            'name' => totranslate('Moves on normal routes'),
            'type' => 'int'
        ],
        'movesATL' => [
            'id' => 41,
            'name' => totranslate('Moves on ATL routes'),
            'type' => 'int'
        ],
        'movesDFW' => [
            'id' => 42,
            'name' => totranslate('Moves on DFW routes'),
            'type' => 'int'
        ],
        'movesLAX' => [
            'id' => 43,
            'name' => totranslate('Moves on LAX routes'),
            'type' => 'int'
        ],
        'movesORD' => [
            'id' => 44,
            'name' => totranslate('Moves on ORD routes'),
            'type' => 'int'
        ],
        'movesSEA' => [
            'id' => 45,
            'name' => totranslate('Moves on SEA routes'),
            'type' => 'int'
        ],

        'ATL' => [
            'id' => 21,
            'name' => totranslate('ATL visits'),
            'type' => 'int'
        ],
        'DEN' => [
            'id' => 22,
            'name' => totranslate('DEN visits'),
            'type' => 'int'
        ],
        'DFW' => [
            'id' => 23,
            'name' => totranslate('DFW visits'),
            'type' => 'int'
        ],
        'JFK' => [
            'id' => 24,
            'name' => totranslate('JFK visits'),
            'type' => 'int'
        ],
        'LAX' => [
            'id' => 25,
            'name' => totranslate('LAX visits'),
            'type' => 'int'
        ],
        'MIA' => [
            'id' => 26,
            'name' => totranslate('MIA visits'),
            'type' => 'int'
        ],
        'ORD' => [
            'id' => 27,
            'name' => totranslate('ORD visits'),
            'type' => 'int'
        ],
        'SEA' => [
            'id' => 28,
            'name' => totranslate('SEA visits'),
            'type' => 'int'
        ],
        'SFO' => [
            'id' => 29,
            'name' => totranslate('SFO visits'),
            'type' => 'int'
        ],

        'pax' => [
            'id' => 35,
            'name' => totranslate('Passengers delivered'),
            'type' => 'int'
        ],

        'cash' => [
            'id' => 50,
            'name' => totranslate('Cash earned'),
            'type' => 'int'
        ],
        'overpay' => [
            'id' => 51,
            'name' => totranslate('Cash overpaid'),
            'type' => 'int'
        ],
        'alliances' => [
            'id' => 52,
            'name' => totranslate('Alliances'),
            'type' => 'int'
        ],
        'seat' => [
            'id' => 53,
            'name' => totranslate('Seats'),
            'type' => 'int'
        ],
        'speed' => [
            'id' => 54,
            'name' => totranslate('Speed'),
            'type' => 'int'
        ],
        'tempSeat' => [
            'id' => 55,
            'name' => totranslate('Temporary Seats purchased'),
            'type' => 'int'
        ],
        'tempSeatUnused' => [
            'id' => 57,
            'name' => totranslate('Temporary Seats unused'),
            'type' => 'int'
        ],
        'tempSpeed' => [
            'id' => 56,
            'name' => totranslate('Temporary Speed purchased'),
            'type' => 'int'
        ],
        'tempSpeedUnused' => [
            'id' => 58,
            'name' => totranslate('Temporary Speed unused'),
            'type' => 'int'
        ],

        'seat1' => [
            'id' => 61,
            'name' => totranslate('Seat 1 utilization %'),
            'type' => 'float'
        ],
        'seat2' => [
            'id' => 62,
            'name' => totranslate('Seat 2 utilization %'),
            'type' => 'float'
        ],
        'seat3' => [
            'id' => 63,
            'name' => totranslate('Seat 3 utilization %'),
            'type' => 'float'
        ],
        'seat4' => [
            'id' => 64,
            'name' => totranslate('Seat 4 utilization %'),
            'type' => 'float'
        ],
        'seat5' => [
            'id' => 65,
            'name' => totranslate('Seat 5 utilization %'),
            'type' => 'float'
        ],

        'seatEmpty1' => [
            'id' => 9061,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],
        'seatFull1' => [
            'id' => 9161,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],
        'seatEmpty2' => [
            'id' => 9062,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],
        'seatFull2' => [
            'id' => 9162,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],
        'seatEmpty3' => [
            'id' => 9063,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],
        'seatFull3' => [
            'id' => 9163,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],
        'seatEmpty4' => [
            'id' => 9064,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],
        'seatFull4' => [
            'id' => 9164,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],
        'seatEmpty5' => [
            'id' => 9065,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],
        'seatFull5' => [
            'id' => 9165,
            'name' => '',
            'type' => 'int',
            'display' => 'limited',
        ],

        'payCustom' => [
            'id' => 9001,
            'name' => totranslate('Cash overpaid'),
            'type' => 'int',
            'display' => 'limited',
        ],
    ]
];
