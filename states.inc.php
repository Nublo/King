<?php

$machinestates = array(

    // The initial state. Please do not modify.
    1 => array(
        "name" => "gameSetup",
        "description" => clienttranslate("Game setup"),
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array("" => 20)
    ),

    /// New hand
    20 => array(
        "name" => "newHand",
        "description" => "",
        "type" => "game",
        "action" => "stNewHand",
        "updateGameProgression" => true,   
        "transitions" => array("" => 21)
    ),
    21 => array(       
        "name" => "newBid",
        "description" => clienttranslate('${actplayer} must choose bid'),
        "descriptionmyturn" => clienttranslate('${you} must choose bid'),
        "type" => "activeplayer",
        "action" => "stNewBid",
        "possibleactions" => array("selectBid"),
        "transitions" => array("" => 30)
    ),
    
    /// Trick
    30 => array(
        "name" => "newTrick",
        "description" => "",
        "type" => "game",
        "action" => "stNewTrick",
        "transitions" => array("" => 31)
    ),
    31 => array(
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must play a card'),
        "descriptionmyturn" => clienttranslate('${you} must play a card'),
        "type" => "activeplayer",
        "possibleactions" => array("playCard"),
        "transitions" => array("playCard" => 32)
    ), 
    32 => array(
        "name" => "nextPlayer",
        "description" => "",
        "type" => "game",
        "action" => "stNextPlayer",
        "transitions" => array("nextPlayer" => 31, "nextTrick" => 30, "endHand" => 40)
    ), 
    
    /// End of the hand
    40 => array(
        "name" => "endHand",
        "description" => "",
        "type" => "game",
        "action" => "stEndHand",
        "transitions" => array("nextHand" => 20, "endGame" => 99)
    ),
   
    // Final state.
    // Please do not modify (and do not overload action/args methods).
    99 => array(
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    )

);