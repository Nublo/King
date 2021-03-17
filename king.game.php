<?php

require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );

class King extends Table {
	function __construct() {
        parent::__construct();
        self::initGameStateLabels(
            array(
                "currentRound" => 10,
                "roundTrump" => 11,
                "trick" => 12,
                "bidType" => 13
            )
        );

        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
	}
	
    protected function getGameName() { return "king"; }

    protected function setupNewGame($players, $options = array()) {
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode($values, ',');
        self::DbQuery($sql);
        self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();
        
        $this->setupGlobalValues();
        $this->setupCards();
        $this->setupPossibleBids($players);

        $this->activeNextPlayer();
    }

    function setupGlobalValues() {
        self::setGameStateValue("currentRound", 0);
        self::setGameStateValue("roundTrump", -1);
        self::setGameStateValue("trick", -1);
        self::setGameStateValue("bidType", -1);
    }

    function setupCards() {
        $cards = array();
        foreach ($this->colors as $color_id => $color) {
            // ["spades", "hearts", "clubs", "diamonds"]
            for ($value = 7; $value <= 14; $value ++) {
                $cards[] = array('type' => $color_id, 'type_arg' => $value,'nbr' => 1);
            }
        }
        $this->cards->createCards($cards, 'deck');

        $this->cards->shuffle('deck');
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(10, 'deck', $player_id);
        }
    }

    // K Q J L H N + + +
    function setupPossibleBids($players) {
        $sql = "INSERT INTO bid (player_id, bid_type, is_allowed, is_plus) VALUES ";
        $values = array();
        $is_allowed = true;
        foreach($players as $player_id => $player) {
            for ($bid_type = 0; $bid_type <= 8; $bid_type++) {
                $is_plus = false;
                if ($bid_type > 5) {
                    $is_plus = true;
                }
                $values[] = "('".$player_id."','".$bid_type."','".$is_allowed."','".$is_plus."')";
            }
        }
        $sql .= implode($values, ',');
        self::DbQuery($sql);
    }

    protected function getAllDatas() {
        $result = array();
        $current_player_id = self::getCurrentPlayerId();
    
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        $result['hand'] = $this->cards->getCardsInLocation('hand', $current_player_id);
        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');
  
        return $result;
    }

    function getGameProgression() {
        return (int) ($this->getGameStateValue("currentRound") * 3.7);
    }

    function getActiveBids() {
        $result = array();
        $sql = "SELECT player_id, bid_type FROM bid WHERE is_allowed = 1";
        $dbres = self::DbQuery($sql);
        while ($row = mysql_fetch_assoc($dbres)) {
            $player_id = $row['player_id'];
            if (!isset($result[$player_id])) {
                $result[$player_id] = array();
            }
            array_push($result[$player_id], $this->bidToReadable($row['bid_type']));
        }
        return $result;
    }

    function bidToReadable($bid_type) {
        switch ($bid_type) {
            case 0: return "K";
            case 1: return "Q";
            case 2: return "J";
            case 3: return "L";
            case 4: return "H";
            case 5: return "N";
            case 6: return "+";
            case 7: return "+";
            case 8: return "+";
        }
    }

    function bidToLongReadable($bid_type, $color) {
        if (!isset($bid_type)) {
            return "Plus. Trick is " . $this->colorToReadble($color);
        }
        switch ($bid_type) {
            case 0: return "Don't take King of Hearts";
            case 1: return "Don't take Queens";
            case 2: return "Don't take Jacks";
            case 3: return "Don't take 2 Last";
            case 4: return "Don't take Hearts";
            case 5: return "Don't take Nothing";
        }
    }

    // ["spades", "hearts", "clubs", "diamonds"]
    function colorToReadble($color) {
        switch ($color) {
            case 0: return "Spade";
            case 1: return "Heart";
            case 2: return "Club";
            case 3: return "Diamond";
        }
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    function selectBid($bid_type, $color) {
        self::checkAction("selectBid");
        $player_id = self::getActivePlayerId();

        if (isset($bid_type)) {
            $sql = "SELECT is_allowed FROM bid WHERE player_id = '$player_id' AND bid_type = '$bid_type'";
            $row = mysql_fetch_assoc(self::DbQuery($sql));
            if (!$row['is_allowed']) {
                throw new BgaUserException(bidToReadable($bid_type) . " was alredy played");
            }

            $sql = "UPDATE bid SET is_allowed = 0 WHERE player_id = '$player_id' AND bid_type = '$bid_type'";
            self::DbQuery($sql);
        } else {
            $sql = "SELECT bid_type FROM bid WHERE player_id = '$player_id' AND is_plus = 1 AND is_allowed = 1 LIMIT 1";
            $result = self::DbQuery($sql);
            if (mysqli_num_rows($result) == 0) {
                throw new BgaUserException("All pluses where already used");
            }
            $bidToUpdate = mysql_fetch_assoc($result)['bid_type'];

            $sql = "UPDATE bid SET is_allowed = 0 WHERE player_id = '$player_id' AND bid_type = '$bidToUpdate'";
            self::DbQuery($sql);
        }

        // TODO probably we should notify users about the full bid stat update
        self::notifyAllPlayers(
            'selectedBid',
            clienttranslate('${player_name} selected to play ${bid_value}'),
            array(
                'player_name' => self::getActivePlayerName(),
                'bid_value' => $this->bidToLongReadable($bid_type, $color),
                'bid_type' => $bid_type,
                'color' => $color
            )
        );

        $this->gamestate->nextState(""); // OPEN buyin for everyone, not yet implemented
    }

    function playCard($card_id) {
        self::checkAction("playCard");
        $player_id = self::getActivePlayerId();
        $this->cards->moveCard($card_id, 'cardsontable', $player_id);
        // XXX check rules here
        $currentCard = $this->cards->getCard($card_id);

        $currentTrickColor = self::getGameStateValue('trick');
        if ($currentTrickColor == 0) {
            self::setGameStateValue('trick', $currentCard['type']);
        }

        self::notifyAllPlayers(
            'playCard', 
            clienttranslate('${player_name} plays ${value_displayed} ${color_displayed}'), 
            array(
                'i18n' => array('color_displayed', 'value_displayed'),
                'card_id' => $card_id,
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'value' => $currentCard['type_arg'],
                'value_displayed' => $this->values_label[$currentCard['type_arg']],
                'color' => $currentCard['type'],
                'color_displayed' => $this->colors[$currentCard['type']]['name']
            )
        );
        $this->gamestate->nextState('playCard');
    }
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    function argChooseBid() {
        $player_id = self::getActivePlayerId();
        $sql = "SELECT bid_type FROM bid WHERE player_id = '$player_id' AND is_allowed = 1 ORDER by bid_type";
        $dbres = self::DbQuery($sql);
        $result = array();

        while ($row = mysql_fetch_assoc($dbres)) {
            $result[] = $row['bid_type'];
        }

        return $result;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    function stNewBid() {
        self::setGameStateValue("bidType", -1);
    }

    function stNewHand() {
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');
        $players = self::loadPlayersBasicInfos();
        foreach ( $players as $player_id => $player ) {
            $cards = $this->cards->pickCards(10, 'deck', $player_id);
            self::notifyPlayer($player_id, 'newHand', '', array('cards' => $cards));
        }

        $nextRound = $this->getGameStateValue("currentRound") + 1;
        self::setGameStateValue("currentRound", $nextRound);
        self::setGameStateValue("roundTrump", -1);
        self::setGameStateValue("trick", 0);

        $this->gamestate->nextState("");
    }

    function stNextPlayer() {
        if ($this->cards->countCardInLocation('cardsontable') == 3) {
            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
            $best_value = 0;
            $best_value_player_id = null;
            $currentTrickColor = self::getGameStateValue('trick');
            foreach ($cards_on_table as $card) {
                if ($card['type'] == $currentTrickColor) {
                    if ($best_value_player_id === null || $card ['type_arg'] > $best_value) {
                        $best_value_player_id = $card['location_arg'];
                        $best_value = $card ['type_arg'];
                    }
                }
            }
            
            $this->gamestate->changeActivePlayer($best_value_player_id);
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $best_value_player_id);

            $players = self::loadPlayersBasicInfos();
            self::notifyAllPlayers(
                'trickWin', 
                clienttranslate('${player_name} wins the trick'), 
                array(
                    'player_id' => $best_value_player_id,
                    'player_name' => $players[$best_value_player_id]['player_name']
                )
            );            
            self::notifyAllPlayers(
                'giveAllCardsToPlayer',
                '',
                array('player_id' => $best_value_player_id)
            );
        
            if ($this->cards->countCardInLocation('hand') == 0) {
                $this->gamestate->nextState("endHand");
            } else {
                $this->gamestate->nextState("nextTrick");
            }
        } else {
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stNewTrick() {
        self::setGameStateInitialValue("trick", 0);
        $this->gamestate->nextState();
    }

    function stEndHand() {
        $players = self::loadPlayersBasicInfos();

        $player_to_points = array();
        foreach ($players as $player_id => $player) {
            $player_to_points[$player_id] = 0;
        }
        $cards = $this->cards->getCardsInLocation("cardswon");
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            if ($card['type'] == 2) {
                $player_to_points[$player_id] ++;
            }
        }
        // Apply scores to player
        foreach ($player_to_points as $player_id => $points) {
            if ($points != 0) {
                $sql = "UPDATE player SET player_score=player_score-$points  WHERE player_id='$player_id'";
                self::DbQuery($sql);
                $heart_number = $player_to_points[$player_id];
                self::notifyAllPlayers(
                    "points", 
                    clienttranslate('${player_name} gets ${nbr} hearts and looses ${nbr} points'), 
                    array(
                        'player_id' => $player_id,
                        'player_name' => $players[$player_id]['player_name'],
                        'nbr' => $heart_number
                    )
                );
            } else {
                self::notifyAllPlayers(
                    "points", 
                    clienttranslate('${player_name} did not get any hearts'), 
                    array(
                        'player_id' => $player_id,
                        'player_name' => $players[$player_id]['player_name']
                    )
                );
            }
        }
        $newScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true);
        self::notifyAllPlayers(
            "newScores",
            '',
            array('newScores' => $newScores)
        );

        ///// Test if this is the end of the game
        foreach ( $newScores as $player_id => $score ) {
            if ($score <= -100) {
                // Trigger the end of the game !
                $this->gamestate->nextState("endGame");
                return;
            }
        }

        $this->gamestate->nextState("nextHand");
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    function zombieTurn($state, $active_player) {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
    function upgradeTableDb($from_version) {}
}