<?php

class action_king extends APP_GameAction { 
  
  public function __default() {
    if (self::isArg('notifwindow')) {
      $this->view = "common_notifwindow";
      $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
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
    $bid_type = self::getArg("bidId", AT_posint);
    $color = self::getArg("plusColor", AT_posint);
    $this->game->selectBid($bid_type, $color);
    self::ajaxResponse();
  }

}