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
            'name' => totranslate('Move bonus from tailwinds'),
            'type' => 'int'
        ],
        'movesSLOW' => [
            'id' => 17,
            'name' => totranslate('Move penalty from storms'),
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
        'efficiencyAvg' => [
            'id' => 36,
            'name' => totranslate('Average efficiency %'),
            'type' => 'float'
        ],
        'efficiencyMin' => [
            'id' => 37,
            'name' => totranslate('Minimum efficiency %'),
            'type' => 'float'
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
            'name' => totranslate('Move bonus from tailwinds'),
            'type' => 'int'
        ],
        'movesSLOW' => [
            'id' => 17,
            'name' => totranslate('Move penalty from storms'),
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
    ]
];
