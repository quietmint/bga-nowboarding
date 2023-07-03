<?php

$this->hourDesc = [
    'PREFLIGHT' => clienttranslate('Preflight'),
    'MORNING' => clienttranslate('Morning'),
    'NOON' => clienttranslate('Afternoon'),
    'NIGHT' => clienttranslate('Evening'),
    'FINALE' => clienttranslate('Final Round'),
];

$this->msg = [
    'addPax' => clienttranslate('${count} passengers arrive at airports'),
    'alliance' => clienttranslate('${player_name} joins alliance ${alliance}'),
    'anger' => clienttranslate('${count} passengers in airports get angry'),
    'buildReset' => clienttranslate('${player_name} restarts their turn'),
    'complaint' => clienttranslate('${complaint} complaints are filed by angry passengers at ${location}'),
    'complaintFinale' => clienttranslate('${complaint} complaints are filed by ${count} undelivered passengers'),
    'deplane' => clienttranslate('${player_name} deplanes a ${route} passenger at ${location}'),
    'deplaneDeliver' => clienttranslate('${player_name} delivers a ${route} passenger and earns ${cash}'),
    'endLose' => clienttranslate('Rough landing! Your airline goes out of business after reciving ${complaint} complaints!'),
    'endWin' => clienttranslate('Congratulations! Your airline is a soaring success!'),
    'enplane' => clienttranslate('${player_name} enplanes a ${route} passenger at ${location}'),
    'finale' => clienttranslate('Caution: The moving walkway is ending. This is the final round!'),
    'move' => clienttranslate('${player_name} flys to ${location}'),
    'prepare' => clienttranslate('Prepare for the next round'),
    'seat' => clienttranslate('${player_name} upgrades seats to ${seat}'),
    'speed' => clienttranslate('${player_name} upgrades speed to ${speed}'),
    'tempSeat' => clienttranslate('${player_name} purchases the temporary seat'),
    'tempSeatReturn' => clienttranslate('${player_name} uses and returns the temporary seat'),
    'tempSpeed' => clienttranslate('${player_name} purchases the temporary speed'),
    'tempSpeedReturn' => clienttranslate('${player_name} uses and returns the temporary speed'),
    'weather' => clienttranslate('${hourDesc} weather: Storms slow travel between ${slow} while tailwinds speed travel between ${fast}'),
];

$this->exMsg = [
    'allianceDuplicate' => $this->_("You already joined alliance %s"),
    'allianceOwner' => $this->_("%s already selected alliance %s"),
    'deplanePort' => $this->_("You must be at an airport to deplane passengers"),
    'enplanePort' => $this->_("You must be at %s to enplane this passenger"),
    'enplaneTransfer' => $this->_("You and %s must be at the same airport to transfer passengers"),
    'noCash' => $this->_("You have insufficient funds (cost: %s, cash: %s)"),
    'noSeat' => $this->_("You have no empty seats"),
    'tempSeatOwner' => $this->_("%s already owns the temporary seat"),
    'tempSpeedOwner' => $this->_("%s already owns the temporary speed"),
    'version' => $this->_("A new version of this game is now available. Please reload the page (F5)."),
];
