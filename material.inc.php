<?php

$this->colors = array(
    1 => array(
    	'name' => clienttranslate('spade'),
        'nametr' => self::_('spade'),
        'emoji' => '♠️'
    ),
    2 => array(
    	'name' => clienttranslate('heart'),
    	'nametr' => self::_('heart'),
        'emoji' => '❤️'
    ),
    3 => array(
    	'name' => clienttranslate('club'),
        'nametr' => self::_('club'),
        'emoji' => '☘️'
    ),
    4 => array(
    	'name' => clienttranslate('diamond'),
        'nametr' => self::_('diamond'),
        'emoji' => '🔹'
    )
);

$this->values_label = array(
    2 =>'2',
    3 => '3',
    4 => '4',
    5 => '5',
    6 => '6',
    7 => '7',
    8 => '8',
    9 => '9',
    10 => '10',
    11 => clienttranslate('J'),
    12 => clienttranslate('Q'),
    13 => clienttranslate('K'),
    14 => clienttranslate('A')
);

$this->bids_label = array(
    0 => 'K',
    1 => 'Q',
    2 => 'J',
    3 => 'L',
    4 => 'H',
    5 => 'N',
    6 => '+',
    7 => '+',
    8 => '+'
);

$this->bids_long_label = array(
    0 => "Don't take King of Hearts",
    1 => "Don't take Queens",
    2 => "Don't take Jacks",
    3 => "Don't take 2 Last",
    4 => "Don't take Hearts",
    5 => "Take Nothing"
);