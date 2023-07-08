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

require_once APP_BASE_PATH . 'view/common/game.view.php';

class view_nowboarding_nowboarding extends game_view
{
    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return 'nowboarding';
    }

    function build_page($viewArgs)
    {
    }
}
