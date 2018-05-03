<?php
// Write to log.
debug_log('quest_edit_reward()');

// For debug.
//debug_log($update);
//debug_log($data);

// Pokestop id.
$pokestop_id = $data['id'];

// Quest id and type.
$quest_id_type = explode(",", $data['arg']);
$quest_id = $quest_id_type[0];
$quest_type = $quest_id_type[1];

// Build message string.
$msg = '';
$msg .= getTranslation('reward_select_type') . CR;

// Create the keys.
$keys = reward_type_keys($pokestop_id, $quest_id, $quest_type);

// Edit message.
edit_message($update, $msg, $keys, false);

// Build callback message string.
$callback_response = 'OK';

// Answer callback.
answerCallbackQuery($update['callback_query']['id'], $callback_response);

exit();
