<?php
// Write to log.
debug_log('quest_edit_qty_reward()');

// For debug.
//debug_log($update);
//debug_log($data);

// Pokestop and quest id.
$stop_quest = explode(",", $data['id']);
$pokestop_id = $stop_quest[0];
$quest_id = $stop_quest[1];

// Quest and Reward type.
$qr_types = explode(",", $data['arg']);
$quest_type = $qr_types[0];
$reward_type = $qr_types[1];

// Build message string.
$msg = '';
$msg .= getTranslation('reward_select_qty_reward') . CR;

// Create the keys.
$keys = reward_qty_type_keys($pokestop_id, $quest_id, $quest_type, $reward_type);

// Edit message.
edit_message($update, $msg, $keys, false);

// Build callback message string.
$callback_response = 'OK';

// Answer callback.
answerCallbackQuery($update['callback_query']['id'], $callback_response);

exit();
