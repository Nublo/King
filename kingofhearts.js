define([
  "dojo",
  "dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  "ebg/stock",
], function (dojo, declare) {
  return declare("bgagame.kingofhearts", ebg.core.gamegui, {
    constructor: function () {
      this.cardwidth = 72;
      this.cardheight = 96;
      this.currentBidType = -1;
      this.currentBidColor = -1;
      this.isHeartsPlayed = 0;
      this.firstCardPlayed = 0;
    },

    setup: function (gamedatas) {
      console.log(gamedatas, "gamedatas");
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
      }

      this.currentBidType = gamedatas.bidType;
      this.currentBidColor = gamedatas.bidColor;
      this.isHeartsPlayed = +gamedatas.isHeartsPlayed;
      this.firstCardPlayed = +gamedatas.firstCardPlayed;

      this.playerHand = new ebg.stock();
      this.playerHand.create(
        this,
        $("myhand"),
        this.cardwidth,
        this.cardheight
      );
      this.playerHand.image_items_per_row = 13;

      for (var color = 1; color <= 4; color++) {
        for (var value = 7; value <= 14; value++) {
          var card_type_id = this.getCardUniqueId(color, value);
          this.playerHand.addItemType(
            card_type_id,
            card_type_id,
            g_gamethemeurl + "img/cards.jpg",
            card_type_id
          );
        }
      }

      this.setupCardsInHand(this.gamedatas);
      this.setupNotifications();
      this.updateHandInfo();

      dojo.connect(
        this.playerHand,
        "onChangeSelection",
        this,
        "onPlayerHandSelectionChanged"
      );
    },

    setupCardsInHand: function (gamedatas) {
      for (var i in gamedatas.hand) {
        var card = gamedatas.hand[i];
        this.addCardToHand(card);
      }

      for (i in gamedatas.cardsontable) {
        var card = gamedatas.cardsontable[i];
        var color = card.type;
        var value = card.type_arg;
        var player_id = card.location_arg;
        this.playCardOnTable(player_id, color, value, card.id);
      }
    },

    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);

      switch (stateName) {
      }
    },

    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
      }
    },

    onUpdateActionButtons: function (stateName, args) {
      console.log("onUpdateActionButtons: ", stateName, args);

      if (this.isCurrentPlayerActive()) {
        switch (stateName) {
          case "newBid":
            if (args.includes("0")) {
              this.addActionButton("bid_king", _("K"), "onKingSelected");
            }
            if (args.includes("1")) {
              this.addActionButton("bid_queens", _("Q"), "onQueensSelected");
            }
            if (args.includes("2")) {
              this.addActionButton("bid_jacks", _("J"), "onJacksSelected");
            }
            if (args.includes("3")) {
              this.addActionButton("bid_last", _("L"), "onLast2Selected");
            }
            if (args.includes("4")) {
              this.addActionButton("bid_hearts", _("H"), "onHeartsSelected");
            }
            if (args.includes("5")) {
              this.addActionButton("bid_nothing", _("N"), "onNothingSelected");
            }
            if (
              args.includes("6") ||
              args.includes("7") ||
              args.includes("8")
            ) {
              this.addActionButton(
                "select_plus",
                _("+"),
                "onPlusSelected"
              );
            }
            break;
          case "choosePlusColor":
            this.addActionButton(
                "bid_plus_spades",
                _("♠️"),
                "onPlusSpadesSelected"
              );
              this.addActionButton(
                "bid_plus_hearts",
                _("❤️"),
                "onPlusHeartsSelected"
              );
              this.addActionButton(
                "bid_plus_clubs",
                _("☘️"),
                "onPlusClubsSelected"
              );
              this.addActionButton(
                "bid_plus_diamonds",
                _("🔹"),
                "onPlusDiamondsSelected"
              );
              this.addActionButton(
                "no_trump",
                _("no trump"),
                "onNoTrumpSelected"
              )
            break;
          case "playerTurn":
            var bidType = +this.currentBidType
            if (bidType >= 0 && bidType < 6) {
              this.addActionButton(
                "take_all",
                _("take all"),
                "onTakeEverything"
              );
            }
            break;
        }
      }
    },

    updateHandInfo: function() {
      if (this.bidTypeToReadable() == "" && this.plusToReadable() == "") {
        $('handinfo').innerHTML = "";
        return;
      }

      var bidInfo = "Bid is: Don't take " + this.bidTypeToReadable();
      if (this.bidTypeToReadable() == "") {
        bidInfo = "Bid is plus: " + this.plusToReadable();
      }

      if (+this.currentBidType == 0 || +this.currentBidType == 4) {
        if (!this.isHeartsPlayed) {
          bidInfo = bidInfo + ". ❤️ rejected";
        } else {
          bidInfo = bidInfo + ". ❤️ accepted";
        }
      }

      $('handinfo').innerHTML = bidInfo;
    },

    bidTypeToReadable: function() {
      switch (+this.currentBidType) {
        case 0:
          return "King of hearts";
        case 1:
          return "Queens";
        case 2:
          return "Jacks";
        case 3:
          return "2 Last"
        case 4:
          return "Hearts";
        case 5:
          return "Nothing"
        default:
          return ""
      }
    },

    plusToReadable: function() {
      switch (+this.currentBidColor) {
        case 1:
          return "♠️";
        case 2:
          return "❤️";
        case 3:
          return "☘";
        case 4:
          return "🔹";
        case 5:
          return "no trump";
        default:
          return "";
      }
    },

    // ["spades", "hearts", "clubs", "diamonds"]
    getCardUniqueId: function (color, value) {
      return (color - 1) * 13 + (value - 2);
    },

    getCardFromType: function (id) {
      return { color: Math.floor(id / 13) + 1, value: (id % 13) + 2 };
    },

    addCardToHand: function (card) {
      var color = card.type;
      var value = card.type_arg;
      this.playerHand.addToStockWithId(
        this.getCardUniqueId(color, value),
        card.id
      );
    },

    playCardOnTable: function (player_id, color, value, card_id) {
      dojo.place(
        this.format_block("jstpl_cardontable", {
          x: this.cardwidth * (value - 2),
          y: this.cardheight * (color - 1),
          player_id: player_id,
        }),
        "playertablecard_" + player_id
      );

      if (player_id != this.player_id) {
        this.placeOnObject(
          "cardontable_" + player_id,
          "overall_player_board_" + player_id
        );
      } else {
        if ($("myhand_item_" + card_id)) {
          this.placeOnObject(
            "cardontable_" + player_id,
            "myhand_item_" + card_id
          );
          this.playerHand.removeFromStockById(card_id);
        }
      }

      this.slideToObject(
        "cardontable_" + player_id,
        "playertablecard_" + player_id
      ).play();
    },

    ///////////////////////////////////////////////////
    //// Player's action

    hasColorInHand: function (hand, color) {
      const getCardFromType = this.getCardFromType;

      return !!hand.find(function (item) {
        return getCardFromType(item.type).color == color;
      });
    },

    isActionValid: function (card) {
      console.log(
        card,
        "card",
        this.playerHand.items,
        "playersHand",
        this.currentBidType,
        "bid",
        this.currentBidColor,
        "bid_color",
        this.isHeartsPlayed,
        "isHeartsPlayed",
        this.firstCardPlayed,
        "firstCardPlayed"
      );

      if (+this.currentBidType == 0 || +this.currentBidType == 4) {
        if (
          !this.isHeartsPlayed &&
          card.color == "2" &&
          this.playerHand.items.find(function (item) {
            return item.type > 25 || item.type < 13;
          }) &&
          !this.firstCardPlayed 
        ) {
          return false;
        }
      }

      if (+this.currentBidColor > 0 && +this.currentBidColor < 5) {
        if (
          this.firstCardPlayed &&
          this.firstCardPlayed != card.color &&
          card.color != +this.currentBidColor &&
          this.hasColorInHand(this.playerHand.items, +this.currentBidColor)
        ) {
          return false;
        }
      }

      if (
        this.firstCardPlayed &&
        this.firstCardPlayed != card.color &&
        this.hasColorInHand(this.playerHand.items, this.firstCardPlayed)
      ) {
        return false;
      }

      return true;
    },

    onPlayerHandSelectionChanged: function () {
      var items = this.playerHand.getSelectedItems();

      if (items.length) {
        if (
          this.checkAction("playCard", true) &&
          this.isActionValid(this.getCardFromType(items[0].type))
        ) {
          var card_id = items[0].id;
          this.ajaxcall(
            "/" + this.game_name + "/" + this.game_name + "/playCard.html",
            { id: card_id, lock: true },
            this,
            function (result) {},
            function (is_error) {}
          );
          this.playerHand.unselectAll();
        } else if (this.checkAction("discard", true)) {
          if (items.length == 2) {
            this.ajaxcall(
              "/" + this.game_name + "/" + this.game_name + "/discard.html",
              { id1: items[0].id, id2: items[1].id, lock: true },
              this,
              function (result) {},
              function (is_error) {}
            );
            this.playerHand.unselectAll();
          }
        } else {
          this.playerHand.unselectAll();
        }
      }
    },

    onKingSelected: function () {
      this.selectBid(0);
    },

    onQueensSelected: function () {
      this.selectBid(1);
    },

    onJacksSelected: function () {
      this.selectBid(2);
    },

    onLast2Selected: function () {
      this.selectBid(3);
    },

    onHeartsSelected: function () {
      this.selectBid(4);
    },

    onNothingSelected: function () {
      this.selectBid(5);
    },

    onPlusSelected: function() {
      this.selectPlus();
    },

    onPlusSpadesSelected: function () {
      this.choosePlus(1);
    },

    onPlusHeartsSelected: function () {
      this.choosePlus(2);
    },

    onPlusClubsSelected: function () {
      this.choosePlus(3);
    },

    onPlusDiamondsSelected: function () {
      this.choosePlus(4);
    },

    onNoTrumpSelected: function () {
      this.choosePlus(5);
    },

    selectBid: function (bidType) {
      console.log("bidType - " + bidType + ";");
      this.ajaxcall(
        "/" + this.game_name + "/" + this.game_name + "/selectBid.html",
        { bidId: bidType, lock: true },
        this,
        function (result) {},
        function (is_error) {}
      );
    },

    selectPlus: function () {
      this.ajaxcall(
        "/" + this.game_name + "/" + this.game_name + "/selectBid.html",
        { lock: true },
        this,
        function (result) {},
        function (is_error) {}
      );
    },

    choosePlus: function(cardColor) {
      console.log("color - " + cardColor + ";");
      this.ajaxcall(
        "/" + this.game_name + "/" + this.game_name + "/choosePlusColor.html",
        { bidColor: cardColor, lock: true },
        this,
        function (result) {},
        function (is_error) {}
      );
    },

    onTakeEverything: function() {
      if (this.checkAction("playCard", true)) {
        this.ajaxcall(
          "/" + this.game_name + "/" + this.game_name + "/takeEverything.html",
          {lock: true},
          this,
          function (result) {},
          function (is_error) {}
        );
      }
    },

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    setupNotifications: function () {
      dojo.subscribe("newHand", this, "notif_newHand");
      dojo.subscribe("selectedBid", this, "notif_selectedBid");

      dojo.subscribe("openBuyin", this, "notif_openBuyin");
      // this.notifqueue.setSynchronous('openBuyin', 3000);
      dojo.subscribe("giveBuyinToPlayer", this, "notif_giveBuyinToPlayer");

      dojo.subscribe("discard", this, "notif_discard");

      dojo.subscribe("playCard", this, "notif_playCard");
      dojo.subscribe("trickWin", this, "notif_trickWin");
      this.notifqueue.setSynchronous("trickWin", 1000);
      dojo.subscribe(
        "giveAllCardsToPlayer",
        this,
        "notif_giveAllCardsToPlayer"
      );
      dojo.subscribe("newScores", this, "notif_newScores");
    },

    notif_newHand: function (notif) {
      this.currentBidType = -1;
      this.currentBidColor = -1;
      this.isHeartsPlayed = 0;
      this.firstCardPlayed = 0;

      this.updateHandInfo();
      this.playerHand.removeAll();

      for (var player_id in this.gamedatas.players) {
        try {
          dojo.destroy("cardontable_" + player_id);
        }
        catch(e) {} // card can be missing from table
      }

      for (var i in notif.args.cards) {
        var card = notif.args.cards[i];
        this.addCardToHand(card);
      }
    },

    notif_openBuyin: function (notif) {
      // TODO open cards and present them on table
    },

    notif_selectedBid: function (notif) {
      console.log("selectedBid - ", notif.args);
      this.currentBidColor = +notif.args.bid_color;

      if (notif.args.bid_type == null) {
        this.currentBidType = -1;
      } else {
        this.currentBidType = +notif.args.bid_type;
      }

      this.updateHandInfo();
    },

    notif_giveBuyinToPlayer: function (notif) {
      this.addCardToHand(notif.args.first_card);
      this.addCardToHand(notif.args.second_card);
    },

    notif_discard: function (notif) {
      this.playerHand.removeFromStockById(notif.args.first_card_id);
      this.playerHand.removeFromStockById(notif.args.second_card_id);
    },

    notif_playCard: function (notif) {
      console.log(notif.args.first_card_played, "card played");
      this.firstCardPlayed = +notif.args.first_card_played;
      if (notif.args.color == 2) {
        this.isHeartsPlayed = 1;
      }

      this.playCardOnTable(
        notif.args.player_id,
        notif.args.color,
        notif.args.value,
        notif.args.card_id
      );

      this.updateHandInfo();
    },

    notif_trickWin: function (notif) {
      this.firstCardPlayed = 0;
    },

    notif_giveAllCardsToPlayer: function (notif) {
      var winner_id = notif.args.player_id;
      for (var player_id in this.gamedatas.players) {
        var anim = this.slideToObject(
          "cardontable_" + player_id,
          "overall_player_board_" + winner_id
        );
        dojo.connect(anim, "onEnd", function (node) {
          dojo.destroy(node);
        });
        anim.play();
      }
    },

    notif_newScores: function (notif) {
      for (var player_id in notif.args.newScores) {
        this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);
      }
    },
  });
});
