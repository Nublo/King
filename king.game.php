<?php

require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );

class King extends Table {
	function __construct() {
        parent::__construct();
        self::initGameStateLabels(
            array(
                "currentRound" => 10, // number, in total 27 rounds in the game
                "firstCardPlayed" => 11,
                "bidType" => 12, // 0-5 games, 6-8 +
                "bidColor" => 13, // plus bid trump - [spade, hearts, clubs, diamonds, no_trump]
                "bid_player" => 14,
                "isHeartsPlayed" => 15,
                "lastTwoFirstId" => 16,
                "lastTwoSecondId" => 17,
                "remainingMinusPoints" => 18,
                "game_variant" => 100,
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
        $this->setupStatistics();
        $this->setupCards();
        $this->setupPossibleBids($players);

        $this->activeNextPlayer();
    }

    function setupGlobalValues() {
        self::setGameStateValue("currentRound", 0);
        self::setGameStateValue("firstCardPlayed", 0);
        self::setGameStateValue("bidType", -1);
        self::setGameStateValue("bidColor", -1);
        self::setGameStateValue("isHeartsPlayed", 0);
        self::setGameStateValue("lastTwoFirstId", -1);
        self::setGameStateValue("lastTwoSecondId", -1);
        self::setGameStateValue("remainingMinusPoints", 0);
    }

    function setupStatistics() {
        self::initStat("player", "self_plus", 0);
        self::initStat("player", "others_plus", 0);
        self::initStat("player", "self_minus", 0);
        self::initStat("player", "others_minus", 0);
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
        $result['bidType'] = self::getGameStateValue('bidType');
        $result['bidColor'] = self::getGameStateValue('bidColor');
        $result['isHeartsPlayed'] = self::getGameStateValue('isHeartsPlayed');
        $result['firstCardPlayed'] = self::getGameStateValue('firstCardPlayed');
        $result['activeBids'] = self::getActiveBids();
        $result['isPlusesSkipped'] = $this->getGameStateValue('game_variant') == 2;

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
            array_push($result[$player_id], $this->bids_label[$row['bid_type']]);
        }
        return $result;
    }

    function bidToLongReadable($bid_type, $bid_color) {
        if (!isset($bid_type)) {
            if (isset($bid_color)) {
                if ($bid_color != 5) {
                    return "Trump is " . $this->colors[$bid_color]['emoji'];
                } else {
                    return "No trump";
                }
            } else {
                return "Plus";
            }
        }
        return $this->bids_long_label[$bid_type];
    }

    function cardToString($card) {
        return $this->values_label[$card['type_arg']] . $this->colors[$card['type']]['emoji'];
    }

    function isPlus($ignore_no_trump) {
        $rightBorder = 6;
        if ($ignore_no_trump) {
            $rightBorder = 5;
        }
        return self::getGameStateValue("bidColor") > 0 && self::getGameStateValue("bidColor") < $rightBorder;
    }

    function isCardInPlayerHand($card_id, $user_hand) {
        foreach ($user_hand as $card) {
            if ($card_id == $card['id']) {
                return true;
            }
        }
        return false;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    function selectBid($bid_type) {
        self::debug("debugBid2: type=" . $bid_type . ";");
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
            self::setGameStateValue("bidType", $bid_type);

            self::notifyAllPlayers(
                'selectedBid',
                clienttranslate('${player_name} selected to play ${bid_value}'),
                array(
                    'player_name' => self::getActivePlayerName(),
                    'bid_value' => $this->bidToLongReadable($bid_type, null),
                    'bid_type' => $bid_type,
                    'active_bids' => $this->getActiveBids()
                )
            );
        } else {
            self::notifyAllPlayers(
                'selectedBid',
                clienttranslate('${player_name} selected to play ${bid_value}'),
                array(
                    'player_name' => self::getActivePlayerName(),
                    'bid_value' => $this->bidToLongReadable(null, null),
                    'bid_type' => $bid_type,
                    'active_bids' => $this->getActiveBids()
                )
            );
        }

        $buyIn = $this->cards->getCardsInLocation('deck');
        $first_card = array_shift($buyIn);
        $second_card = array_shift($buyIn);
        self::notifyAllPlayers(
            'openBuyin',
            clienttranslate('Buyin: ${first_card} ${second_card}'),
            array(
                'player_id' => $player_id,
                'first_card' => $this->cardToString($first_card),
                'second_card' => $this->cardToString($second_card)
            )
        );

        self::notifyPlayer(
            $player_id,
            'giveBuyinToPlayer',
            '',
            array(
                'first_card' => $first_card,
                'second_card' => $second_card
            )
        );
        $this->cards->pickCards(2, 'deck', $player_id);

        if (isset($bid_type)) {
            $this->gamestate->nextState("discard");
        } else {
            $this->gamestate->nextState("choosePlusColor");
        }
    }

    function selectPlusColor($bid_color) {
        $player_id = self::getActivePlayerId();
        $sql = "SELECT bid_type FROM bid WHERE player_id = '$player_id' AND is_plus = 1 AND is_allowed = 1 LIMIT 1";
        $result = self::DbQuery($sql);
        if (mysqli_num_rows($result) == 0) {
            throw new BgaUserException("All pluses where already used");
        }
        $bidToUpdate = mysql_fetch_assoc($result)['bid_type'];

        $sql = "UPDATE bid SET is_allowed = 0 WHERE player_id = '$player_id' AND bid_type = '$bidToUpdate'";
        self::DbQuery($sql);
        self::setGameStateValue("bidColor", $bid_color);

        if ($this->getGameStateValue('game_variant') == 2) {
            $this->gamestate->nextState("endHand");
            self::notifyAllPlayers(
                'skipPlus',
                clienttranslate('${player_name} selected to skip plus'),
                array(
                    'player_name' => self::getActivePlayerName()
                )
            );
        } else {
            self::notifyAllPlayers(
                'selectedBid',
                clienttranslate('${player_name} selected to play ${bid_value}'),
                array(
                    'player_name' => self::getActivePlayerName(),
                    'bid_value' => $this->bidToLongReadable(null, $bid_color),
                    'bid_color' => $bid_color
                )
            );
            $this->gamestate->nextState("discard");
        }
    }

    function discard($card_id1, $card_id2) {
        $player_id = self::getActivePlayerId();
        $this->cards->moveCard($card_id1, 'discard', $player_id);
        $this->cards->moveCard($card_id2, 'discard', $player_id);
        self::notifyPlayer(
            $player_id,
            'discard',
            '',
            array(
                'first_card_id' => $card_id1,
                'second_card_id' => $card_id2
            )
        );

        $this->gamestate->nextState("");
    }

    function takeEverything() {
        if ($this->isPlus(false)) {
            throw new BgaUserException("You can't take everything when playing plus");
        }

        $players = self::loadPlayersBasicInfos();
        $active_player_id = self::getActivePlayerId();

        if (self::getGameStateValue("bidType") == 3) {
            if ($this->cards->countCardInLocation('hand', $active_player_id) >= 2) {
                self::setGameStateValue("lastTwoFirstId", $active_player_id);
                self::setGameStateValue("lastTwoSecondId", $active_player_id);
            } else if ($this->cards->countCardInLocation('hand', $active_player_id) == 1) {
                self::setGameStateValue("lastTwoSecondId", $active_player_id);
            }
        }

        foreach ($players as $player_id => $player) {
            $this->cards->moveAllCardsInLocation('hand', 'cardswon', $player_id, $active_player_id);
        }
        $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $active_player_id);

        $points = self::getGameStateValue("remainingMinusPoints");
        $this->updatePlayerPoints($active_player_id, $points);

        $this->gamestate->nextState("endHand");
    }

    function playCard($card_id) {
        self::checkAction("playCard");
        $player_id = self::getActivePlayerId();

        if (!$this->isCardInPlayerHand($card_id, $this->cards->getCardsInLocation('hand', $player_id))) {
            throw new BgaUserException("Card was already played. Refresh page");
        }

        $this->cards->moveCard($card_id, 'cardsontable', $player_id);
        // XXX check rules here
        $currentHand = $this->cards->getCardsInLocation('hand', $player_id);
        $currentCard = $this->cards->getCard($card_id);
        $firstCardPlayed = self::getGameStateValue("firstCardPlayed");

        if ($firstCardPlayed == 0) {
            $firstCardPlayed = $currentCard['type'];
            self::setGameStateValue("firstCardPlayed", $currentCard['type']);
        }

        if ($currentCard['type'] == 2) {
            self::setGameStateValue("isHeartsPlayed", 1);
        }

        self::notifyAllPlayers(
            'playCard',
            clienttranslate('${player_name} plays ${value_displayed}'),
            array(
                'i18n' => array('color_displayed', 'value_displayed'),
                'card_id' => $card_id,
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'value' => $currentCard['type_arg'],
                'value_displayed' => $this->cardToString($currentCard),
                'color' => $currentCard['type'],
                'first_card_played' => $firstCardPlayed
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

    function stNewHand() {
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(10, 'deck', $player_id);
            self::notifyPlayer($player_id, 'newHand', '', array('cards' => $cards));
        }

        self::setGameStateValue("currentRound", $this->getGameStateValue("currentRound") + 1);
        self::setGameStateValue("firstCardPlayed", 0);
        self::setGameStateValue("bidType", -1);
        self::setGameStateValue("bidColor", -1);
        self::setGameStateValue("lastTwoFirstId", -1);
        self::setGameStateValue("lastTwoSecondId", -1);
        self::setGameStateValue("bid_player", self::getActivePlayerId());
        self::setGameStateValue("isHeartsPlayed", 0);
        self::setGameStateValue("remainingMinusPoints", -40);

        $this->gamestate->nextState("");
    }

    function stNewBid() {}

    function stNewPlusColor() {}

    function stDiscard() {}

    function stNewTrick() {
        self::setGameStateInitialValue("firstCardPlayed", 0);
        $this->gamestate->nextState();
    }

    function stNextPlayer() {
        if ($this->cards->countCardInLocation('cardsontable') == 3) {
            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
            $best_value = 0;
            $best_value_player_id = null;
            $baseColor = self::getGameStateValue("firstCardPlayed");
            self::debug("stNextPlayer_debug:" . self::getGameStateValue("bidColor") . ";");
            $currentHandTrump = self::getGameStateValue("bidColor");
            $hasTrumpInHand = false;
            foreach ($cards_on_table as $card) {
                if ($card['type'] == $currentHandTrump && $this->isPlus(true)) {
                    $hasTrumpInHand = true;
                }
            }
            if ($hasTrumpInHand) {
                $baseColor = $currentHandTrump;
            }
            foreach ($cards_on_table as $card) {
                if ($card['type'] == $baseColor) {
                    self::debug("first_debug:" . $card['type_arg'] . " - " . $card['type'] . ";");
                    if ($best_value_player_id === null || $card['type_arg'] > $best_value) {
                        $best_value_player_id = $card['location_arg'];
                        $best_value = $card['type_arg'];
                        $best_color = $card['type'];
                        continue;
                    }
                }
            }

            $this->notifyPointsUpdate($best_value_player_id);

            $this->gamestate->changeActivePlayer($best_value_player_id);
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $best_value_player_id);

            $remaining_cards = $this->cards->countCardInLocation('hand', $best_value_player_id);
            if ($remaining_cards == 1) {
                self::setGameStateValue("lastTwoFirstId", $best_value_player_id);
            } else if ($remaining_cards == 0) {
                self::setGameStateValue("lastTwoSecondId", $best_value_player_id);
            }

            $players = self::loadPlayersBasicInfos();
            self::notifyAllPlayers(
                'trickWin',
                clienttranslate('${player_name} wins the hand'),
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

            if ($this->cards->countCardInLocation('hand') == 0 || $this->fastEndCheck()) {
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

    function fastEndCheck() {
        $cards = $this->cards->getCardsInLocation("cardswon");
        $bid_type = self::getGameStateValue("bidType");

        if ($bid_type == 0) {
            foreach ($cards as $card) {
                $player_id = $card['location_arg'];
                if ($card['type'] == 2 && $card['type_arg'] == 13) {
                    return true;
                }
            }
        } else if ($bid_type == 1) {
            $countQueens = 0;
            foreach ($cards as $card) {
                if ($card['type_arg'] == 12) {
                    $countQueens++;
                }
            }
            if ($countQueens == 4) {
                return true;
            }
        } else if ($bid_type == 2) {
            $countJacks = 0;
            foreach ($cards as $card) {
                if ($card['type_arg'] == 11) {
                    $countJacks++;
                }
            }
            if ($countJacks == 4) {
                return true;
            }
        } else if ($bid_type == 4) {
            $countHearts = 0;
            foreach ($cards as $card) {
                $player_id = $card['location_arg'];
                if ($card['type'] == 2) {
                    $countHearts++;
                }
            }
            if ($countHearts == 8) {
                return true;
            }
        }

        return false;
    }

    function stEndHand() {
        if ($this->getGameStateValue('game_variant') != 2 || !$this->isPlus(false)) {
            $this->notifyEndHandPoints();
        }

        if ($this->getGameStateValue("currentRound") == 27) {
            $this->gamestate->nextState("endGame");
            return;
        }

        $this->gamestate->changeActivePlayer(self::getGameStateValue("bid_player"));
        $this->activeNextPlayer();

        $this->gamestate->nextState("nextHand");
    }

    function notifyPointsUpdate($player_id) {
        $players = self::loadPlayersBasicInfos();
        $cards = $this->cards->getCardsInLocation("cardsontable");
        $bid_type = self::getGameStateValue("bidType");

        $points = 0;
        if ($this->isPlus(false)) {
            $points = 8;
        } else if ($bid_type == 0) {
            foreach ($cards as $card) {
                if ($card['type'] == 2 && $card['type_arg'] == 13) {
                    $points = -40;
                    $this->updateRemainingMinusPoints(40);
                }
            }
        } else if ($bid_type == 1) {
            foreach ($cards as $card) {
                if ($card['type_arg'] == 12) {
                    $points -= 10;
                    $this->updateRemainingMinusPoints(10);
                }
            }
        } else if ($bid_type == 2) {
            foreach ($cards as $card) {
                if ($card['type_arg'] == 11) {
                    $points -= 10;
                    $this->updateRemainingMinusPoints(10);
                }
            }
        } else if ($bid_type == 3) {
            $remaining_cards = $this->cards->countCardInLocation('hand', $player_id);
            switch ($remaining_cards) {
                case 0:
                case 1:
                    $points = -20;
                    $this->updateRemainingMinusPoints(20);
                    break;
            }
        } else if ($bid_type == 4) {
            foreach ($cards as $card) {
                if ($card['type'] == 2) {
                    $points -= 5;
                    $this->updateRemainingMinusPoints(5);
                }
            }
        } else if ($bid_type == 5) {
            $points = -4;
            $this->updateRemainingMinusPoints(4);
        }

        $this->updatePlayerPoints($player_id, $points);
    }

    function updatePlayerPoints($player_id, $points) {
        if ($points == 0) {
            return;
        }
        $players = self::loadPlayersBasicInfos();
        $sql = "UPDATE player SET player_score=player_score+$points  WHERE player_id='$player_id'";
        self::DbQuery($sql);
        self::notifyAllPlayers(
            "points",
            clienttranslate('${player_name} gets ${nbr} points'),
            array(
                'player_id' => $player_id,
                'player_name' => $players[$player_id]['player_name'],
                'nbr' => $points
            )
        );
        $newScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true);
        self::notifyAllPlayers(
            "newScores",
            '',
            array('newScores' => $newScores)
        );
    }

    function notifyEndHandPoints() {
        $players = self::loadPlayersBasicInfos();

        $player_to_points = array();
        foreach ($players as $player_id => $player) {
            $player_to_points[$player_id] = 0;
        }
        $cards = $this->cards->getCardsInLocation("cardswon");

        $bid_type = self::getGameStateValue("bidType");
        if ($this->isPlus(false)) {
            $this->countPointsForPlus($player_to_points, $cards);
        } else if ($bid_type == 0) {
            $this->countPointsForKing($player_to_points, $cards);
        } else if ($bid_type == 1) {
            $this->countPointsForQueens($player_to_points, $cards);
        } else if ($bid_type == 2) {
            $this->countPointsForJacks($player_to_points, $cards);
        } else if ($bid_type == 3) {
            $this->countPointsForLast($player_to_points, $cards);
        } else if ($bid_type == 4) {
            $this->countPointsForHearts($player_to_points, $cards);
        } else if ($bid_type == 5) {
            $this->countPointsForNothing($player_to_points, $cards);
        }

        self::notifyAllPlayers(
            "points",
            clienttranslate('Hand results'),
            array()
        );
        foreach ($player_to_points as $player_id => $points) {
            $points = $player_to_points[$player_id];
            self::notifyAllPlayers(
                "points",
                clienttranslate('${player_name} scored ${nbr} points'),
                array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'nbr' => $points
                )
            );

            $isSelfBid = self::getGameStateValue("bid_player") == $player_id;
            if ($this->isPlus(false)) {
                if ($isSelfBid) {
                    self::incStat($points, "self_plus", $player_id);
                } else {
                    self::incStat($points, "others_plus", $player_id);
                }
            } else {
                if ($isSelfBid) {
                    self::incStat($points, "self_minus", $player_id);
                } else {
                    self::incStat($points, "others_minus", $player_id);
                }
            }
        }
    }

    function countPointsForPlus(&$player_to_points, $cards) {
        $result = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $result[$player_id] = 0;
        }
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            $result[$player_id]++;
        }
        foreach ($players as $player_id => $player) {
            $new_points = ($result[$player_id] / 3) * 8;
            $player_to_points[$player_id] += $new_points;
        }
    }

    function countPointsForKing(&$player_to_points, $cards) {
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            if ($card['type'] == 2 && $card['type_arg'] == 13) {
                $player_to_points[$player_id] -= 40;
            }
        }
    }

    function countPointsForQueens(&$player_to_points, $cards) {
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            if ($card['type_arg'] == 12) {
                $player_to_points[$player_id] -= 10;
            }
        }
    }

    function countPointsForJacks(&$player_to_points, $cards) {
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            if ($card['type_arg'] == 11) {
                $player_to_points[$player_id] -= 10;
            }
        }
    }

    function countPointsForLast(&$player_to_points, $cards) {
        if (self::getGameStateValue("lastTwoFirstId") != -1) {
            $player_to_points[self::getGameStateValue("lastTwoFirstId")] -= 20;    
        }
        if (self::getGameStateValue("lastTwoSecondId") != -1) {
            $player_to_points[self::getGameStateValue("lastTwoSecondId")] -= 20;
        }
    }

    function countPointsForHearts(&$player_to_points, $cards) {
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            if ($card['type'] == 2) {
                $player_to_points[$player_id] -= 5;
            }
        }
    }

    function countPointsForNothing(&$player_to_points, $cards) {
        $result = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $result[$player_id] = 0;
        }
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            $result[$player_id]++;
        }
        foreach ($players as $player_id => $player) {
            $new_points = ($result[$player_id] / 3) * 4;
            $player_to_points[$player_id] -= $new_points;
        }
    }

    function updateRemainingMinusPoints($pointsDiff) {
        $savedPoints = self::getGameStateValue("remainingMinusPoints");
        self::setGameStateValue("remainingMinusPoints", $savedPoints + $pointsDiff);
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
