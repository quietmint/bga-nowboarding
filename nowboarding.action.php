<?php

class action_nowboarding extends APP_GameAction
{
  // Constructor: please do not modify
  public function __default()
  {
    if (self::isArg('notifwindow')) {
      $this->view = "common_notifwindow";
      $this->viewArgs['table'] = self::getArg("table", AT_posint, true);
    } else {
      $this->view = "nowboarding_nowboarding";
      self::trace("Complete reinitialization of board game");
    }
  }

  private function checkVersion()
  {
    $clientVersion = (int) self::getArg('version', AT_int, false);
    $this->game->checkVersion($clientVersion);
  }

  // Production bug report handler
  public function loadBugSQL()
  {
    self::setAjaxMode();
    $reportId = (int) self::getArg('report_id', AT_int, true);
    $this->game->loadBugSQL($reportId);
    self::ajaxResponse();
  }

  function undo()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->undo();
    self::ajaxResponse();
  }

  public function buy()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('buy');
    $type = self::getArg('type', AT_alphanum, true);
    $alliance = self::getArg('alliance', AT_alphanum, false);
    $this->game->buy($type, $alliance);
    self::ajaxResponse();
  }

  public function buyAgain()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->buyAgain();
    self::ajaxResponse();
  }

  public function pay()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('pay');
    $paid = explode(',', self::getArg('paid', AT_numberlist, true));
    $this->game->pay($paid);
    self::ajaxResponse();
  }

  public function prepareDone()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('prepareDone');
    $this->game->prepareDone();
    self::ajaxResponse();
  }

  public function flyDone()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('flyDone');
    $this->game->flyDone();
    self::ajaxResponse();
  }

  public function flyAgain()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->flyAgain();
    self::ajaxResponse();
  }

  public function move()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('move');
    $location = self::getArg('location', AT_alphanum, true);
    $this->game->move($location);
    self::ajaxResponse();
  }

  public function board()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('board');
    $paxId = self::getArg('paxId', AT_int, true);
    $this->game->board($paxId);
    self::ajaxResponse();
  }

  public function deplane()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('deplane');
    $paxId = self::getArg('paxId', AT_int, true);
    $confirm = self::getArg('confirm', AT_bool, false) || false;
    $this->game->deplane($paxId, $confirm);
    self::ajaxResponse();
  }
}
