<?php
// Write to log.
debug_log('pokedex()');
debug_log($update);
debug_log($data);

// Get the limit.
$limit = $data['id'];

// Get the action.
$action = $data['arg'];

if ($update['message']['chat']['type'] == 'private' || $update['callback_query']['message']['chat']['type'] == 'private') {
    // Set message.
    $msg = getTranslation('pokedex_list_of_all') . CR . getTranslation('pokedex_edit_pokemon');

    // Get pokemon.
    $keys = edit_pokedex_keys($limit, $action);

    // Empty keys?
    if (!$keys) {
	$msg = getTranslation('pokedex_not_found');
    }

    // Edit message.
    edit_message($update, $msg, $keys, false);

    // Build callback message string.
    $callback_response = 'OK';

    // Answer callback.
    answerCallbackQuery($update['callback_query']['id'], $callback_response);
} 
