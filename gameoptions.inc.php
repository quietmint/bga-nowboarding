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
        'values' => [
            1 => [
                'name' => totranslate('Standard time'),
                'description' => totranslate("The flight phase lasts the standard amount of time (30 - 45 seconds depending on player count) and is strictly enforced. Players cannot take actions after time is up."),
                'tmdisplay' => totranslate('Standard time'),
            ],
            2 => [
                'name' => totranslate('Double time'),
                'description' => totranslate("The flight phase lasts twice as long (60 - 90 seconds depending on player count) and is strictly enforced. Players cannot take actions after time is up."),
                'tmdisplay' => totranslate('Double time'),
                'firstgameonly' => true,
            ],
            0 => [
                'name' => totranslate('Relaxed time'),
                'description' => totranslate("House rule: The flight phase time is loosely enforced by players at their discretion (like other BGA games). Players can still take actions after time is up."),
                'tmdisplay' => totranslate('Relaxed time'),
            ],
        ],
        'displaycondition' => [
            [
                'type' => 'otheroption',
                'id' => N_BGA_SPEED,
                'value' => N_REF_BGA_SPEED_REALTIME,
            ],
        ],
    ],

    N_OPTION_VIP => [
        'name' => totranslate('VIP Variant'),
        'default' => 0,
        'values' => [
            0 => [
                'name' => totranslate('Disabled'),
            ],
            1 => [
                'name' => totranslate('Enabled'),
                'description' => totranslate('Increase difficulty by adding passengers with challenging conditions'),
                'tmdisplay' => totranslate('VIP Variant'),
                'nobeginner' => true,
            ],
        ],
    ],

    N_OPTION_HANDOFF => [
        'name' => totranslate('Money Handoff Variant'),
        'default' => 0,
        'values' => [
            0 => [
                'name' => totranslate('Disabled'),
            ],
            1 => [
                'name' => totranslate('Enabled'),
                'description' => totranslate('Reduce difficulty by allowing players to exchange cash'),
                'tmdisplay' => totranslate('Money Handoff Variant'),
            ],
        ],
    ],
];
