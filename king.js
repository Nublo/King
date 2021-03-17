define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock"
],
function (dojo, declare) {
    return declare("bgagame.king", ebg.core.gamegui, {
        constructor: function() {
            this.cardwidth = 72;
            this.cardheight = 96;
        },
        
        setup: function(gamedatas) {
            for (var player_id in gamedatas.players) {
                var player = gamedatas.players[player_id];
            }
            
            this.playerHand = new ebg.stock();
            this.playerHand.create(this, $('myhand'), this.cardwidth, this.cardheight);
            this.playerHand.image_items_per_row = 13;

            for (var color = 1; color <= 4; color++) {
                for (var value = 7; value <= 14; value++) {
                    var card_type_id = this.getCardUniqueId(color, value);
                    this.playerHand.addItemType(card_type_id, card_type_id, g_gamethemeurl + 'img/cards.jpg', card_type_id);
                }
            }

            this.setupCardsInHand(this.gamedatas);
            this.setupNotifications();

            dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');
        },

        setupCardsInHand: function(gamedatas) {
            for (var i in gamedatas.hand) {
                var card = gamedatas.hand[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            for (i in gamedatas.cardsontable) {
                var card = gamedatas.cardsontable[i];
                var color = card.type;
                var value = card.type_arg;
                var player_id = card.location_arg;
                this.playCardOnTable(player_id, color, value, card.id);
            }
        },
       
        onEnteringState: function(stateName, args) {
            console.log('Entering state: ' + stateName);
            
            switch(stateName) { 
                case 'newBid':
                break;
            }
        },

        onLeavingState: function(stateName) {
            console.log('Leaving state: ' + stateName);
            
            switch(stateName) {
                case 'dummmy':
                break;
            }            
        }, 

        onUpdateActionButtons: function(stateName, args) {
            console.log('onUpdateActionButtons: ' + stateName + args);
                      
            if (this.isCurrentPlayerActive()) {            
                switch(stateName) {
                    case 'newBid':
                    if (args.includes("0")) {
                        this.addActionButton('bid_king', _('K'), 'onKingSelected');
                    }
                    if (args.includes("1")) {
                        this.addActionButton('bid_queens', _('Q'), 'onQueensSelected');
                    }
                    if (args.includes("2")) {
                        this.addActionButton('bid_jacks', _('J'), 'onJacksSelected');
                    }
                    if (args.includes("3")) {
                        this.addActionButton('bid_last', _('L'), 'onLast2Selected');
                    }
                    if (args.includes("4")) {
                        this.addActionButton('bid_hearts', _('H'), 'onHeartsSelected');
                    }
                    if (args.includes("5")) {
                        this.addActionButton('bid_nothing', _('N'), 'onNothingSelected');
                    }
                    if (args.includes("6") || args.includes("7") || args.includes("8")) {
                        this.addActionButton('bid_plus_spades', _('+S'), 'onPlusSpadesSelected');
                        this.addActionButton('bid_plus_hearts', _('+H'), 'onPlusHeartsSelected');
                        this.addActionButton('bid_plus_clubs', _('+C'), 'onPlusClubsSelected');
                        this.addActionButton('bid_plus_diamonds', _('+D'), 'onPlusDiamondsSelected');
                    }
                    break;
                }
            }
        },

        // ["spades", "hearts", "clubs", "diamonds"]
        getCardUniqueId : function(color, value) {
            return (color - 1) * 13 + (value - 2);
        },

        playCardOnTable : function(player_id, color, value, card_id) {
            dojo.place(this.format_block('jstpl_cardontable', {
                x : this.cardwidth * (value - 2),
                y : this.cardheight * (color - 1),
                player_id : player_id
            }), 'playertablecard_' + player_id);

            if (player_id != this.player_id) {
                this.placeOnObject('cardontable_' + player_id, 'overall_player_board_' + player_id);
            } else {
                if ($('myhand_item_' + card_id)) {
                    this.placeOnObject('cardontable_' + player_id, 'myhand_item_' + card_id);
                    this.playerHand.removeFromStockById(card_id);
                }
            }

            this.slideToObject('cardontable_' + player_id, 'playertablecard_' + player_id).play();
        },


        ///////////////////////////////////////////////////
        //// Player's action

        onPlayerHandSelectionChanged : function() {
            var items = this.playerHand.getSelectedItems();

            if (items.length > 0) {
                var action = 'playCard';
                if (this.checkAction(action, true)) {
                    var card_id = items[0].id;                  
                    this.ajaxcall(
                        "/" + this.game_name + "/" + this.game_name + "/" + action + ".html", 
                        {id : card_id, lock : true},
                        this, 
                        function(result) {

                        }, 
                        function(is_error) {

                        });
                    this.playerHand.unselectAll();
                } else if (this.checkAction('giveCards')) {
                    // Can give cards => let the player select some cards
                } else {
                    this.playerHand.unselectAll();
                }
            }
        },

        // Bid selection
        onKingSelected : function() { this.selectBid(0); },

        onQueensSelected : function() { this.selectBid(1); },

        onJacksSelected : function() { this.selectBid(2); },

        onLast2Selected : function() { this.selectBid(3); },

        onHeartsSelected : function() { this.selectBid(4); },

        onNothingSelected : function() { this.selectBid(5); },

        onPlusSpadesSelected : function() { this.selectPlus(0); },

        onPlusHeartsSelected : function() { this.selectPlus(1); },

        onPlusClubsSelected : function() { this.selectPlus(2); },

        onPlusDiamondsSelected : function() { this.selectPlus(3); },

        selectBid : function(bidType) {
            var action = 'selectBid';
            this.ajaxcall(
                "/" + this.game_name + "/" + this.game_name + "/" + action + ".html", 
                {bidId : bidType, lock : true},
                this, 
                function(result) {}, 
                function(is_error) {}
            );
        },

        selectPlus : function(color) {
            var action = 'selectBid';
            this.ajaxcall(
                "/" + this.game_name + "/" + this.game_name + "/" + action + ".html", 
                {plusColor : color, lock : true},
                this, 
                function(result) {}, 
                function(is_error) {}
            );
        },
        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        setupNotifications: function() {
            dojo.subscribe('newHand', this, "notif_newHand");
            dojo.subscribe('playCard', this, "notif_playCard");
            dojo.subscribe('trickWin', this, "notif_trickWin");
            this.notifqueue.setSynchronous('trickWin', 1000);
            dojo.subscribe('giveAllCardsToPlayer', this, "notif_giveAllCardsToPlayer");
            dojo.subscribe('newScores', this, "notif_newScores");
        },

        notif_newHand : function(notif) {
            this.playerHand.removeAll();

            for (var i in notif.args.cards) {
                var card = notif.args.cards[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
        },

        notif_playCard : function(notif) {
            this.playCardOnTable(
                notif.args.player_id, 
                notif.args.color,
                notif.args.value,
                notif.args.card_id
            );
        },

        notif_trickWin : function(notif) {},

        notif_giveAllCardsToPlayer : function(notif) {
            var winner_id = notif.args.player_id;
            for (var player_id in this.gamedatas.players) {
                var anim = this.slideToObject('cardontable_' + player_id, 'overall_player_board_' + winner_id);
                dojo.connect(
                    anim,
                    'onEnd',
                    function(node) {
                        dojo.destroy(node);
                    }
                );
                anim.play();
            }
        },

        notif_newScores : function(notif) {
           for (var player_id in notif.args.newScores) {
               this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);
           }
       },
        
   });
});