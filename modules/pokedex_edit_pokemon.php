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
$msg = get_local_pokemon_name($pokedex_id) . ' (#' . $pokedex_id . ')' . CR . CR;
$msg .= '<b>' . getTranslation('pokedex_select_action') . '</b>';

// Create keys array.
$keys = [
    [
        [
            'text'          => getTranslation('pokedex_raid_level'),
            'callback_data' => $pokedex_id . ':pokedex_set_raid_level:setlevel'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_min_cp'),
            'callback_data' => $pokedex_id . ':pokedex_set_cp:min-20'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_max_cp'),
            'callback_data' => $pokedex_id . ':pokedex_set_cp:max-20'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_min_boosted_cp'),
            'callback_data' => $pokedex_id . ':pokedex_set_cp:min-25'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_max_boosted_cp'),
            'callback_data' => $pokedex_id . ':pokedex_set_cp:max-25'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_weather'),
            'callback_data' => $pokedex_id . ':pokedex_set_weather:setweather'
        ]
    ],
    [
        [
            'text'          => getTranslation('abort'),
            'callback_data' => '0:exit:0'
        ]
    ]
];

// Edit message.
edit_message($update, $msg, $keys, false);

// Build callback message string.
$callback_response = getTranslation('select_pokemon');

// Answer callback.
answerCallbackQuery($update['callback_query']['id'], $callback_response);
