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

$game_options = [
    N_OPTION_TIMER => [
        'name' => totranslate('Flight Phase Timer'),
        'default' => 1,
        'notdisplayedmessage' => totranslate('Off'),
        'values' => [
            0 => [
                'name' => totranslate('Off'),
                'description' => totranslate("House rule: The flight phase has no specific time limit"),
                'tmdisplay' => totranslate('Flight Phase Timer Off'),
            ],
            1 => [
                'name' => totranslate('On'),
                'description' => totranslate("The flight phase lasts 30 - 45 seconds (depending on player count) and time is STRICTLY ENFORCED"),
            ],
            2 => [
                'name' => totranslate('Doubled'),
                'description' => totranslate("The flight phase lasts 60 - 90 seconds (depending on player count) and time is STRICTLY ENFORCED"),
                'tmdisplay' => totranslate('Flight Phase Timer Doubled'),
                'firstgameonly' => true,
            ],

        ],
        'displaycondition' => [
            [
                'type' => 'otheroption',
                'id' => N_BGA_CLOCK,
                'value' => N_REF_BGA_CLOCK_REALTIME,
            ],
        ],
    ],

    N_OPTION_VIP => [
        'name' => totranslate('VIP Variant'),
        'default' => 0,
        'values' => [
            0 => [
                'name' => totranslate('Off'),
            ],
            1 => [
                'name' => totranslate('On'),
                'description' => totranslate('VIPs add additional complications like "must board first" or "must fly alone"'),
                'tmdisplay' => totranslate('VIP Variant'),
                'nobeginner' => true,
            ],
        ],
    ],
];
