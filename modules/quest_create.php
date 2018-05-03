<?php
// Write to log.
debug_log('quest_create()');

// For debug.
//debug_log($update);
//debug_log($data);

// Pokestop id.
$pokestop_id = $data['id'];

// Check if quest already exists for this pokestop.
$rs = my_query(
    "
    SELECT    id, pokestop_id
    FROM      quests
      WHERE   quest_date = CURDATE()
        AND   pokestop_id = {$pokestop_id}
    "
);

// Get the answer.
$answer = $rs->fetch_assoc();

// Write to log.
debug_log($answer);

// Quest already in database or new
if (!$answer) {
    // Build message string.
    $msg = '';
    $msg .= getTranslation('quest_select_type') . CR;

    // Create the keys.
    $keys = quest_type_keys($pokestop_id);
} else {
    // Quest already in the database for this pokestop.
    $msg = EMOJI_WARN . '<b> ' . getTranslation('quest_already_submitted') . ' </b>' . EMOJI_WARN . CR . CR;
    $quest = get_quest($answer['id']);
    $msg .= get_formatted_quest($quest);

    // Empty keys.
    $keys = [];
}

// Edit message.
edit_message($update, $msg, $keys, ['disable_web_page_preview' => 'true']);

// Build callback message string.
$callback_response = 'OK';

// Answer callback.
answerCallbackQuery($update['callback_query']['id'], $callback_response);

exit();
