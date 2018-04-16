<?php
// Write to log.
debug_log('vote_team()');

// For debug.
//debug_log($update);
//debug_log($data);

// Check if the user has voted for this raid before.
$rs = my_query(
    "
    SELECT    user_id
    FROM      attendance
      WHERE   raid_id = {$data['id']}
        AND   user_id = {$update['callback_query']['from']['id']}
    "
);

// Get the answer.
$answer = $rs->fetch_assoc();

// Write to log.
debug_log($answer);

// Make sure user has voted before.
if (!empty($answer)) {
    // Update attendance table.
    my_query(
        "
        UPDATE attendance
        SET    team = CASE
                 WHEN team = 'mystic' THEN 'valor'
                 WHEN team = 'valor' THEN 'instinct'
                 ELSE 'mystic'
               END
          WHERE   raid_id = {$data['id']}
            AND   user_id = {$update['callback_query']['from']['id']}
        "
    );
}

// Always update users table.
my_query(
    "
    UPDATE    users
    SET    team = CASE
             WHEN team = 'mystic' THEN 'valor'
             WHEN team = 'valor' THEN 'instinct'
             ELSE 'mystic'
           END
      WHERE   user_id = {$update['callback_query']['from']['id']}
    "
);

// Send vote response.
send_response_vote($update, $data);

exit();
