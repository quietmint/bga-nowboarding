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

  public function begin()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('begin');
    $this->game->begin();
    self::ajaxResponse();
  }

  public function buy()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('buy');
    $type = self::getArg('type', AT_alphanum, true);
    $color = self::getArg('color', AT_alphanum, false);
    $this->game->buy($type, $color);
    self::ajaxResponse();
  }

  public function end()
  {
    self::setAjaxMode();
    self::checkVersion();
    $this->game->checkAction('end');
    $this->game->end();
    self::ajaxResponse();
  }
}
