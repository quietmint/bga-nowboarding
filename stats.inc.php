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
        'complaint' => [
            'id' => 10,
            'name' => totranslate('Complaints'),
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

        'stops0' => [
            'id' => 40,
            'name' => totranslate('Non-stop passengers'),
            'type' => 'int'
        ],
        'stops1' => [
            'id' => 41,
            'name' => totranslate('1-stop passengers'),
            'type' => 'int'
        ],
        'stops2' => [
            'id' => 42,
            'name' => totranslate('2-stop passengers'),
            'type' => 'int'
        ],
        'stops3' => [
            'id' => 43,
            'name' => totranslate('3-stop passengers'),
            'type' => 'int'
        ],
        'stops4' => [
            'id' => 44,
            'name' => totranslate('4-stop passengers'),
            'type' => 'int'
        ],
        'stops5' => [
            'id' => 45,
            'name' => totranslate('5-stop passengers'),
            'type' => 'int'
        ],
        'stops6' => [
            'id' => 46,
            'name' => totranslate('6-stop passengers'),
            'type' => 'int'
        ],
        'stops7' => [
            'id' => 47,
            'name' => totranslate('7-stop and above passengers'),
            'type' => 'int'
        ],
        'stopsAvg' => [
            'id' => 39,
            'name' => totranslate('Average stops/passenger'),
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
        'tempSpeed' => [
            'id' => 56,
            'name' => totranslate('Temporary Speed purchased'),
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
        'tempSpeed' => [
            'id' => 56,
            'name' => totranslate('Temporary Speed purchased'),
            'type' => 'int'
        ],
    ]
];
