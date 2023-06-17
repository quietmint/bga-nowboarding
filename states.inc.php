<?php

$machinestates = [
    // BGA framework state, do not modify
    N_STATE_BEGIN => [
        'name' => 'gameSetup',
        'action' => 'stGameSetup',
        'description' => '',
        'transitions' => [
            '' => N_STATE_BUILD
        ],
        'type' => 'manager',
    ],

    N_STATE_BUILD => [
        'name' => 'build',
        'action' => 'stMultiactive',
        'args' => 'argBuild',
        'description' => clienttranslate('Waiting for others to build their airplane'),
        'descriptionmyturn' => clienttranslate('Build your airplane'),
        'possibleactions' => [
            'buy',
        ],
        'transitions' => [
            'shuffle' => N_STATE_SHUFFLE,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_SHUFFLE => [
        'name' => 'shuffle',
        'action' => 'stShuffle',
        'description' => '',
        'transitions' => [
            'preflight' => N_STATE_PREFLIGHT,
        ],
        'type' => 'game',
    ],

    N_STATE_PREFLIGHT => [
        'name' => 'preflight',
        'action' => 'stMultiactive',
        'args' => 'argPreflight',
        'description' => clienttranslate('Waiting for others to begin the round'),
        'descriptionmyturn' => clienttranslate('Discuss plans and purchase upgrades before the round begins'),
        'possibleactions' => [
            'begin',
            'buy',
        ],
        'transitions' => [
            'flight' => N_STATE_FLIGHT,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_FLIGHT => [
        'name' => 'flight',
        'action' => 'stMultiactive',
        'args' => 'argFlight',
        'description' => clienttranslate('Waiting for others to finish the round'),
        'descriptionmyturn' => clienttranslate('Go! Go! Go!'),
        'possibleactions' => [
            'dropPassenger',
            'end',
            'move',
            'pickPassenger',
        ],
        'transitions' => [
            'maintenance' => N_STATE_MAINTENANCE,
        ],
        'type' => 'multipleactiveplayer',
    ],

    N_STATE_MAINTENANCE => [
        'name' => 'maintenance',
        'action' => 'stMaintenance',
        'description' => '',
        'transitions' => [
            'end' => N_STATE_END,
            'preflight' => N_STATE_PREFLIGHT,
        ],
        'type' => 'game',
    ],

    // BGA framework state, do not modify
    N_STATE_END => [
        'name' => 'gameEnd',
        'description' => clienttranslate('End of game'),
        'type' => 'manager',
        'action' => 'stGameEnd',
        'args' => 'argGameEnd'
    ]
];
