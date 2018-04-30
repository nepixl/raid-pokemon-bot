<?php
// Write to log.
debug_log('quest_edit_qty_action()');

// For debug.
//debug_log($update);
//debug_log($data);

// Pokestop id.
$pokestop_id = $data['id'];

// Quest type.
$quest_type = $data['arg'];

// Build message string.
$msg = '';
$msg .= getTranslation('quest_select_qty_action') . CR;

// Create the keys.
$keys = quest_qty_action_keys($pokestop_id, $quest_type);

// Edit message.
edit_message($update, $msg, $keys, false);

// Build callback message string.
$callback_response = 'OK';

// Answer callback.
answerCallbackQuery($update['callback_query']['id'], $callback_response);

exit();
