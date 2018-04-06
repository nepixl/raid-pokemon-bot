<?php
// Write to log.
debug_log('POKEDEX');

// Check access - user must be admin!
bot_access_check($update, BOT_ADMINS);

// Init empty keys array.
$keys = array();

// Create keys array.
$keys = [
    [
        [
            'text'          => getTranslation('update_pokemon'),
            'callback_data' => '0:pokedex:0'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_raid_pokemon'),
            'callback_data' => '0:pokedex_list_raids:0'
        ]
    ]
];

// Set message.
$msg = '<b>' . getTranslation('pokedex_start') . ':</b>';

// Send message.
send_message($update['message']['chat']['id'], $msg, $keys, ['reply_markup' => ['selective' => true, 'one_time_keyboard' => true]]);

exit;
