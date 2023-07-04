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

  function reset()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->reset();
    self::ajaxResponse();
  }

  public function flightBegin()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('begin');
    $this->game->flightBegin();
    self::ajaxResponse();
  }

  public function flightEnd()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('end');
    $this->game->flightEnd();
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
