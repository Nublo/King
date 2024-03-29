<?php

class action_kingofhearts extends APP_GameAction { 
  
  public function __default() {
    if (self::isArg('notifwindow')) {
      $this->view = "common_notifwindow";
      $this->viewArgs['table'] = self::getArg("table", AT_posint, true);
    } else {
      $this->view = "king_king";
      self::trace("Complete reinitialization of board game");
    }
  }

  public function playCard() {
    self::setAjaxMode();
    $card_id = self::getArg("id", AT_posint, true);
    $this->game->playCard($card_id);
    self::ajaxResponse();
  }

  public function selectBid() {
    self::setAjaxMode();
    $bid_type = self::getArg("bidId", AT_int);
    $this->game->selectBid($bid_type);
    self::ajaxResponse();
  }

  public function choosePlusColor() {
    self::setAjaxMode();
    $bid_color = self::getArg("bidColor", AT_int);
    $this->game->choosePlusColor($bid_color);
    self::ajaxResponse();
  }

  public function discard() {
    self::setAjaxMode();
    $card_id1 = self::getArg("id1", AT_posint, true);
    $card_id2 = self::getArg("id2", AT_posint, true);
    $this->game->discard($card_id1, $card_id2);
    self::ajaxResponse();
  }

  public function takeEverything() {
    self::setAjaxMode();
    $this->game->takeEverything();
    self::ajaxResponse();
  }

}