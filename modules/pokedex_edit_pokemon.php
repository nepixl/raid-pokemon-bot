<?php
// Write to log.
debug_log('pokedex_edit_pokemon()');
debug_log($update);
debug_log($data);

// Set the id.
$pokedex_id = $data['id'];

// Init empty keys array.
$keys = array();

// Set the message.
$msg = getTranslation('pokedex_select_action');

// Create keys array.
$keys = [
    [
        [
            'text'          => getTranslation('pokedex_raid_level'),
            'callback_data' => $pokedex_id . ':pokedex_set_raid_level:setlevel'
        ]
    ]
];

// Edit message.
edit_message($update, $msg, $keys, false);

// Build callback message string.
$callback_response = getTranslation('select_pokemon');

// Answer callback.
answerCallbackQuery($update['callback_query']['id'], $callback_response);
