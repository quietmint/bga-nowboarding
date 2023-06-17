<?php

// States
const N_STATE_BEGIN = 1;
const N_STATE_BUILD = 2;
const N_STATE_SHUFFLE = 3;
const N_STATE_PREFLIGHT = 4;
const N_STATE_FLIGHT = 5;
const N_STATE_MAINTENANCE = 6;
const N_STATE_END = 99;

// Options
const N_OPTION_VERSION = 300;

// Reference lookups
const N_COLOR_REF = [
    'RED' => ['hex' => 'ff0000', 'startNode' => 'ORD'],
    'ORANGE' => ['hex' => 'f07f16', 'startNode' => 'LAX'],
    'GREEN' => ['hex' => '008000', 'startNode' => 'ATL'],
    'BLUE' => ['hex' => '0000ff', 'startNode' => 'SEA'],
    'PURPLE' => ['hex' => '982fff', 'startNode' => 'DFW'],
];

const N_SEAT_REF = [
    2 => 5,
    3 => 9,
    4 => 13,
    5 => 17,
];

const N_SPEED_REF = [
    4 => 5,
    5 => 7,
    6 => 9,
    7 => 11,
    8 => 13,
    9 => 15
];
