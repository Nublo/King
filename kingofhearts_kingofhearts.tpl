{OVERALL_GAME_HEADER}
<button id="takeeverything"></button>
<div id="playertables">

    <!-- BEGIN player -->
    <div class="playertable whiteblock playertable_{DIR}">
        <div class="playertablename" style="color:#{PLAYER_COLOR}">
            {PLAYER_NAME}
        </div>
        <div class="playertablecard" id="playertablecard_{PLAYER_ID}">
        </div>
    </div>
    <!-- END player -->

</div>

<div id="handinfo">
</div>

<div id="myhand_wrap" class="whiteblock">
    <h3>{MY_HAND}</h3>
    <div id="myhand">
    </div>
</div>

<div id="playerbids">
    <!-- BEGIN bid -->
    <div id="playerbid" class="whiteblock">
        <h3>{PLAYER_NAME} : {PLAYER_BIDS}</h3>
    </div>
    <!-- END bid -->
</div>

<script type="text/javascript">

var jstpl_cardontable = '<div class="cardontable" id="cardontable_${player_id}" style="background-position:-${x}px -${y}px">\
                        </div>';

</script>

{OVERALL_GAME_FOOTER}
