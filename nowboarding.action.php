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

class action_nowboarding extends APP_GameAction
{
  // Constructor: please do not modify
  public function __default()
  {
    if ($this->isArg('notifwindow')) {
      $this->view = 'common_notifwindow';
      $this->viewArgs['table'] = $this->getArg('table', AT_posint, true);
    } else {
      $this->view = 'nowboarding_nowboarding';
      $this->trace('Complete reinitialization of board game');
    }
  }

  private function checkVersion()
  {
    $clientVersion = (int) $this->getArg('version', AT_int, false);
    $this->game->checkVersion($clientVersion);
  }

  // Production bug report handler
  public function loadBugSQL()
  {
    $this->setAjaxMode(false);
    $reportId = (int) $this->getArg('report_id', AT_int, true);
    $this->game->loadBugSQL($reportId);
    $this->ajaxResponse();
  }

  public function jsError()
  {
    $this->setAjaxMode(false);
    $this->game->jsError($_POST['userAgent'], $_POST['msg']);
    $this->ajaxResponse();
  }

  function undo()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $this->game->undo();
    $this->ajaxResponse();
  }

  function vip()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $accept = $this->getArg('accept', AT_bool, true);
    $this->game->vip($accept);
    $this->ajaxResponse();
  }

  public function buy()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $type = $this->getArg('type', AT_alphanum, true);
    $alliance = $this->getArg('alliance', AT_alphanum, false);
    $this->game->buy($type, $alliance);
    $this->ajaxResponse();
  }

  public function buyAgain()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $this->game->buyAgain();
    $this->ajaxResponse();
  }

  public function pay()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $paid = explode(',', $this->getArg('paid', AT_numberlist, true));
    $this->game->pay($paid);
    $this->ajaxResponse();
  }

  public function prepareDone()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $this->game->prepareDone();
    $this->ajaxResponse();
  }

  public function flyDone()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $snooze = boolval($this->getArg('snooze', AT_bool, false));
    $this->game->flyDone($snooze);
    $this->ajaxResponse();
  }

  public function flyAgain()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $this->game->flyAgain();
    $this->ajaxResponse();
  }

  public function flyTimer()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $this->game->flyTimer();
    $this->ajaxResponse();
  }

  public function move()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $from = $this->getArg('from', AT_alphanum, true);
    $to = $this->getArg('to', AT_alphanum, true);
    $this->game->move($from, $to);
    $this->ajaxResponse();
  }

  public function board()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $paxId = $this->getArg('paxId', AT_int, true);
    $paxPlayerId = $this->getArg('paxPlayerId', AT_int, false);
    $this->game->board($paxId, $paxPlayerId);
    $this->ajaxResponse();
  }

  public function deplane()
  {
    $this->setAjaxMode();
    $this->checkVersion();
    $paxId = $this->getArg('paxId', AT_int, true);
    $paxPlayerId = $this->getArg('paxPlayerId', AT_int, false);
    $this->game->deplane($paxId, $paxPlayerId);
    $this->ajaxResponse();
  }
}
