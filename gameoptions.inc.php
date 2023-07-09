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

$timerDesc = totranslate("Official rule: Time is strictly enforced. The flight phase will end and you cannot take actions after time is up.");

$game_options = [
    N_OPTION_TIMER => [
        'name' => totranslate('Flight Phase Timer'),
        'default' => 30,
        'values' => [
            15 => [
                'name' => totranslate('15-second timer'),
                'description' => $timerDesc,
                'tmdisplay' => totranslate('15-second timer'),
            ],
            30 => [
                'name' => totranslate('30-second timer'),
                'description' => $timerDesc,
                'tmdisplay' => totranslate('30-second timer'),
            ],
            45 => [
                'name' => totranslate('45-second timer'),
                'description' => $timerDesc,
                'tmdisplay' => totranslate('45-second timer'),
            ],
            60 => [
                'name' => totranslate('60-second timer'),
                'description' => $timerDesc,
                'tmdisplay' => totranslate('60-second timer'),
                'firstgameonly' => true,
            ],
            0 => [
                'name' => totranslate('Relaxed timer'),
                'description' => totranslate("House rule: Like other BGA games, time is loosely enforced by players at their discretion. The flight phase will continue and you can take actions even after time is up."),
                'tmdisplay' => totranslate('Relaxed timer'),
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
