<?php

$this->hourDesc = [
    'PREFLIGHT' => clienttranslate('Preflight'),
    'MORNING' => clienttranslate('Morning'),
    'NOON' => clienttranslate('Afternoon'),
    'NIGHT' => clienttranslate('Evening'),
    'FINALE' => clienttranslate('Final Round'),
];

$this->msg = [
    'addPax' => clienttranslate('${count} passengers arrive at ${location}'),
    'alliance' => clienttranslate('${player_name} joins alliance ${alliance}'),
    'anger' => clienttranslate('${count} passengers in airports get angry'),
    'buildReset' => clienttranslate('${player_name} restarts their turn'),
    'complaint' => clienttranslate('${complaint} complaints are filed by angry passengers at ${location}'),
    'complaintFinale' => clienttranslate('${complaint} complaints are filed by ${count} undelivered passengers'),
    'deplane' => clienttranslate('${player_name} deplanes a ${route} passenger at ${location}'),
    'deplaneDeliver' => clienttranslate('${player_name} delivers a ${route} passenger and earns ${cash}'),
    'endLose' => clienttranslate('Rough landing! Your airline goes out of business after receiving ${complaint} complaints!'),
    'endWin' => clienttranslate('Congratulations! Your airline is a soaring success!'),
    'board' => clienttranslate('${player_name} boards a ${route} passenger at ${location}'),
    'finale' => clienttranslate('Caution: The moving walkway is ending. This is the final round!'),
    'move' => clienttranslate('${player_name} flys to ${location}'),
    'prepare' => clienttranslate('Prepare for the next round'),
    'seat' => clienttranslate('${player_name} upgrades seats to ${seat}'),
    'speed' => clienttranslate('${player_name} upgrades speed to ${speed}'),
    'temp' => clienttranslate('${player_name} purchases ${temp}'),
    'tempUsed' => clienttranslate('${player_name} uses ${temp}'),
    'weather' => clienttranslate('${hourDesc} weather: Storms slow travel between ${slow} while tailwinds speed travel between ${fast}'),
];

$this->exMsg = [
    'allianceDuplicate' => $this->_("You already joined alliance %s"),
    'allianceOwner' => $this->_("%s already selected alliance %s"),
    'deplanePort' => $this->_("You must be at an airport to deplane passengers"),
    'boardPort' => $this->_("You must be at %s to board this passenger"),
    'boardTransfer' => $this->_("You and %s must be at the same airport to transfer passengers"),
    'noCash' => $this->_("You have insufficient funds (cost: %s, cash: %s)"),
    'noSeat' => $this->_("You have no empty seats"),
    'tempOwner' => $this->_("%s already owns %s"),
    'version' => $this->_("A new version of this game is now available. Please reload the page (F5)."),
];


$this->vips = [
    'BABY' => [
        'name' => clienttranslate('Crying Baby'),
        'desc' => clienttranslate('Other pasengers at same airport gain 2 anger per turn'),
        'hours' => ['MORNING', 'AFTERNOON'],
    ],
    'CELEBRITY' => [
        'name' => clienttranslate('Celebrity'),
        'desc' => clienttranslate('Must fly alone'),
        'hours' => ['EVENING'],
    ],
    'DIRECT' => [
        'name' => clienttranslate('Direct Flight'),
        'desc' => clienttranslate('Only deplanes at destination'),
        'hours' => ['EVENING'],
    ],
    'DOUBLE' => [
        'name' => clienttranslate('Captured Fugitive'),
        'desc' => clienttranslate('Requires 2 seats'),
        'hours' => ['AFTERNOON'],
    ],
    'FIRST' => [
        'name' => clienttranslate('First In Line'),
        'desc' => clienttranslate('Must board before other passengers'),
        'hours' => ['AFTERNOON'],
    ],
    'GRUMPY' => [
        'name' => clienttranslate('Grumpy'),
        'desc' => clienttranslate('Starts at 1 anger'),
        'hours' => ['EVENING'],
    ],
    'IMPATIENT' => [
        'name' => clienttranslate('Impatient'),
        'desc' => clienttranslate('Anger never reset'),
        'hours' => ['MORNING'],
    ],
    'NERVOUS' => [
        'name' => clienttranslate('Nervous'),
        'desc' => clienttranslate('Cannot fly through weather'),
        'hours' => ['MORNING'],
    ],
];
