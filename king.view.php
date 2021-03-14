<?php
  require_once( APP_BASE_PATH."view/common/game.view.php" );
  
  class view_king_king extends game_view
  {
    function getGameName() {
        return "king";
    }
  	function build_page( $viewArgs )
  	{		
        $players = $this->game->loadPlayersBasicInfos();
        $template = "king_king";
        $directions = array( 'S', 'W', 'E' );
        
        $this->page->begin_block($template, "player");
        foreach ( $players as $player_id => $info ) {
            $dir = array_shift($directions);
            $this->page->insert_block("player", array ("PLAYER_ID" => $player_id,
                    "PLAYER_NAME" => $players [$player_id] ['player_name'],
                    "PLAYER_COLOR" => $players [$player_id] ['player_color'],
                    "DIR" => $dir ));
        }
        $this->tpl['MY_HAND'] = self::_("My hand");

        /*********** Do not change anything below this line  ************/
  	}
  }
  

