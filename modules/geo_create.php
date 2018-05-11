<?php
// Write to log.
debug_log('quest_geo()');

// For debug.
//debug_log($update);
//debug_log($data);

// Latitude and longitude
$lat = '';
$lon = '';

// Get latitude / longitude values from Telegram Mobile Client
if (isset($update['message']['location'])) {
    $lat = $update['message']['location']['latitude'];
    $lon = $update['message']['location']['longitude'];
}

// Set the message.
$msg = getTranslation('create_raid_or_quest');

// Create keys array.
$keys = [
    [
        [
            'text'          => getTranslation('create_a_raid'),
            'callback_data' => '0:raid_create:' . $lat . ',' . $lon,
        ],
        [
            'text'          => getTranslation('quest'),
            'callback_data' => '0:raid_create:' . $coords,
        ]
    ]
];

// Send message.
send_message($update['message']['chat']['id'], $msg, $keys, ['disable_web_page_preview' => 'true']);

exit();
