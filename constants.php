<?php
// Carriage return.
define('CR',  "\n");
define('CR2', "\n");

// Icons.
define('TEAM_B',        iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F499)));
define('TEAM_R',        iconv('UCS-4LE', 'UTF-8', pack('V', 0x2764)));
define('TEAM_Y',        iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F49B)));
define('TEAM_CANCEL',   iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F494)));
define('TEAM_DONE',     iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F4AA)));
define('TEAM_UNKNOWN',  iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F680)));
define('EMOJI_REFRESH', iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F504)));
define('EMOJI_GROUP',   iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F465)));
define('EMOJI_WARN',    iconv('UCS-4LE', 'UTF-8', pack('V', 0x26A0)));

// Weather Icons.
define('WEATHER_SUNNY',            iconv('UCS-4LE', 'UTF-8', pack('V', 0x2600)));
define('WEATHER_CLEAR',            iconv('UCS-4LE', 'UTF-8', pack('V', 0x2728)));
define('WEATHER_RAIN',             iconv('UCS-4LE', 'UTF-8', pack('V', 0x2614)));
define('WEATHER_CLOUDY',           iconv('UCS-4LE', 'UTF-8', pack('V', 0x2601)));
define('WEATHER_PARTLY_CLOUDY',    iconv('UCS-4LE', 'UTF-8', pack('V', 0x26C5)));
define('WEATHER_WINDY',            iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F32A)));
define('WEATHER_SNOW',             iconv('UCS-4LE', 'UTF-8', pack('V', 0x26C4)));
define('WEATHER_FOG',              iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F32B)));

// Teams.
$teams = array(
    'mystic'    => TEAM_B,
    'valor'     => TEAM_R,
    'instinct'  => TEAM_Y,
    'unknown'   => TEAM_UNKNOWN,
    'cancel'    => TEAM_CANCEL
);

// Raid eggs.
$eggs = array(
    '9995',  // Level 5
    '9994',  // Level 4
    '9993',  // Level 3
    '9992',  // Level 2
    '9991'   // Level 1
);
