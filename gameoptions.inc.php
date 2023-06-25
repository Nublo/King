<?php

$game_options = array(
    100 => array(
        'name' => totranslate('Pluses variation'),    
        'values' => array(
            1 => array(
                'name' => totranslate('Normal')
            ),
            2 => array(
                'name' => totranslate('Pluses are skipped by player')
            )
        ),
        'default' => 1
    ),
    101 => array(
        'name' => totranslate('Logs variation'),
        'values' => array(
            1 => array(
                'name' => totranslate('Compact')
            ),
            2 => array(
                'name' => totranslate('Full')
            )
        ),
        "default" => 1
    )
);