<?php
// Write to log.
debug_log('vote()');

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

// User has voted before.
if (!empty($answer)) {
    // Get extra people.
    $extraPeople = intval($data['arg'] - 1);

    // Update attendance.
    my_query(
        "
        UPDATE    attendance
        SET       extra_people = {$extraPeople}
          WHERE   raid_id = {$data['id']}
            AND   user_id = {$update['callback_query']['from']['id']}
        "
    );

    // Send vote response.
    send_response_vote($update, $data);
}

exit();
