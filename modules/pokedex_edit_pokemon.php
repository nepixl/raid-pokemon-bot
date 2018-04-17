<?php
// Write to log.
debug_log('pokedex_edit_pokemon()');

// For debug.
//debug_log($update);
//debug_log($data);

// Set the id.
$pokedex_id = $data['id'];

// Init empty keys array.
$keys = array();

// Set the message.
/* Example:
 * Raid boss: Mewtwo (#ID)
 * Weather: Icons
 * CP: CP values (Boosted CP values)
*/
$msg = get_pokemon_info($pokedex_id);
//$msg = getTranslation('raid_boss') . ': <b>' . get_local_pokemon_name($pokedex_id) . ' (#' . $pokedex_id . ')</b>' . CR . CR;
//$msg .= getTranslation('pokedex_raid_level') . ': ' . get_raid_level($pokedex_id) . CR;
//$msg .= get_formatted_pokemon_cp($pokedex_id) . CR;
//$poke_weather = get_pokemon_weather($pokedex_id);
//$msg .= getTranslation('pokedex_weather') . ': ' . get_weather_icons($poke_weather) . CR . CR;
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
            'callback_data' => $pokedex_id . ':pokedex_set_cp:min-20-add-0'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_max_cp'),
            'callback_data' => $pokedex_id . ':pokedex_set_cp:max-20-add-0'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_min_weather_cp'),
            'callback_data' => $pokedex_id . ':pokedex_set_cp:min-25-add-0'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_max_weather_cp'),
            'callback_data' => $pokedex_id . ':pokedex_set_cp:max-25-add-0'
        ]
    ],
    [
        [
            'text'          => getTranslation('pokedex_weather'),
            'callback_data' => $pokedex_id . ':pokedex_set_weather:add-0'
        ]
    ],
    [
        [
            'text'          => getTranslation('back'),
            'callback_data' => '0:pokedex:0'
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
