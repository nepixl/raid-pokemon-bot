<?php
/**
 * Bot access check.
 * @param $update
 * @param $access_type
 */
function bot_access_check($update, $access_type = BOT_ACCESS, $return_result = false)
{
    // Restricted or public access
    if(!empty($access_type)) {
	$all_chats = '';
	// Always add maintainer and admins.
	$all_chats .= !empty(MAINTAINER_ID) ? MAINTAINER_ID . ',' : '';
	$all_chats .= !empty(BOT_ADMINS) ? BOT_ADMINS . ',' : '';
	$all_chats .= ($access_type == BOT_ADMINS) ? '' : $access_type;

	// Make sure all_chats does not end with ,
	$all_chats = rtrim($all_chats,',');

	// Get telegram ID to check access from $update - either message, callback_query or inline_query
	$update_type = '';
	$update_type = !empty($update['message']['from']['id']) ? 'message' : $update_type; 
	$update_type = (empty($update_type) && !empty($update['callback_query']['from']['id'])) ? 'callback_query' : $update_type; 
	$update_type = (empty($update_type) && !empty($update['inline_query']['from']['id'])) ? 'inline_query' : $update_type; 
	$update_id = $update[$update_type]['from']['id'];

	// Check each admin chat defined in $access_type 
	debug_log('Telegram message type: ' . $update_type);
	debug_log('Checking access for ID: ' . $update_id);
	debug_log('Checking these chats now: ' . $all_chats);
	$chats = explode(',', $all_chats);
   	foreach($chats as $chat) {
	    // Get chat object 
            debug_log("Getting chat object for '" . $chat . "'");
	    $chat_obj = get_chat($chat);

	    // Check chat object for proper response.
	    if ($chat_obj['ok'] == true) {
		debug_log('Proper chat object received, continuing with access check.');
		$allow_access = false;
		// ID matching $chat and private chat type?
		//if ($chat_obj['result']['id'] == ($update['message']['from']['id'] || $update['callback_query']['from']['id']) && $chat_obj['result']['type'] == "private") {
		if ($chat_obj['result']['id'] == $update_id && $chat_obj['result']['type'] == "private") {
		    debug_log('Positive result on access check!');
		    $allow_access = true;
		    break;
		} else {
		    // Result was ok, but access not granted. Continue with next chat if type is private.
		    if ($chat_obj['result']['type'] == "private") {
		        debug_log('Negative result on access check! Continuing with next chat...');
		    	continue;
		    }
		}
	    } else {
		debug_log('Chat ' . $chat . ' does not exist! Continuing with next chat...');
		continue;
	    }

	    // Clear chat_obj since it did not match 
	    $chat_obj = '';

	    // Get administrators from chat
            debug_log("Getting administrators from chat '" . $chat . "'");
    	    $chat_obj = get_admins($chat);

    	    // Make sure we get a proper response
    	    if ($chat_obj['ok'] == true) { 
	        foreach($chat_obj['result'] as $admin) {
	                // If user is found as administrator allow access to the bot
			// if ($admin['user']['id'] == $update['message']['from']['id'] || $admin['user']['id'] == $update['inline_query']['from']['id']) {
	                if ($admin['user']['id'] == $update_id) {
		            debug_log('Positive result on access check!');
		            $allow_access = true;
		            break 2;
		        }
                }
	    }
	}

        // Prepare logging of id, username and/or first_name
	$msg = '';
	$msg .= !empty($update[$update_type]['from']['id']) ? "Id: " . $update[$update_type]['from']['id']  . CR : '';
	$msg .= !empty($update[$update_type]['from']['username']) ? "Username: " . $update[$update_type]['from']['username'] . CR : '';
	$msg .= !empty($update[$update_type]['from']['first_name']) ? "First Name: " . $update[$update_type]['from']['first_name'] . CR : '';

        // Allow or deny access to the bot and log result
        if ($allow_access && !$return_result) {
            debug_log("Allowing access to the bot for user:" . CR . $msg);
        } else if ($allow_access && $return_result) {
            debug_log("Allowing access to the bot for user:" . CR . $msg);
	    return $allow_access;
        } else if (!$allow_access && $return_result) {
            debug_log("Denying access to the bot for user:" . CR . $msg);
	    return $allow_access;
        } else {
            debug_log("Denying access to the bot for user:" . CR . $msg);
            $response_msg = '<b>' . getTranslation('bot_access_denied') . '</b>';
            // Edit message or send new message based on value of $update_type
            if ($update_type == 'callback_query') {
                $keys = [];
                // Edit message.
                edit_message($update, $response_msg, $keys);
                // Answer the callback.
                answerCallbackQuery($update[$update_type]['id'], getTranslation('bot_access_denied'));
            } else {
	        sendMessage($update[$update_type]['from']['id'], $response_msg);
            }
            exit;
        }
    } else {
        $msg = '';
        $msg .= !empty($update['message']['from']['id']) ? "Id: " . $update['message']['from']['id'] . CR : '';
        $msg .= !empty($update['message']['from']['username']) ? "Username: " . $update['message']['from']['username'] . CR : '';
        $msg .= !empty($update['message']['from']['first_name']) ? "First Name: " . $update['message']['from']['first_name'] . CR : '';
        debug_log("Bot access is not restricted! Allowing access for user: " . CR . $msg);
        return true;
    }
}

/**
 * Raid access check.
 * @param $update
 * @param $data
 * @return bool
 */
function raid_access_check($update, $data, $return_result = false)
{
    // Default: Deny access to raids
    $raid_access = false;

    // Build query.
    $rs = my_query(
        "
        SELECT    *
        FROM      raids
          WHERE   id = {$data['id']}
        "
    );

    $raid = $rs->fetch_assoc();

    if ($update['callback_query']['from']['id'] != $raid['user_id']) {
        // Build query.
        $rs = my_query(
            "
            SELECT    COUNT(*)
            FROM      users
              WHERE   user_id = {$update['callback_query']['from']['id']}
               AND    moderator = 1
            "
        );

        $row = $rs->fetch_row();

        if (empty($row['0'])) {
	    $admin_access = bot_access_check($update, BOT_ADMINS, true);
	    if ($admin_access) {
	        // Allow raid access
		$raid_access = true;
	    }
        } else {
	    // Allow raid access
	    $raid_access = true;
        }
    } else {
        // Allow raid access
        $raid_access = true;
    }

    // Allow or deny access to the raid and log result
    if ($raid_access && !$return_result) {
        debug_log("Allowing access to the raid");
    } else if ($raid_access && $return_result) {
        debug_log("Allowing access to the raid");
        return $raid_access;
    } else if (!$raid_access && $return_result) {
        debug_log("Denying access to the raid");
        return $raid_access;
    } else {
        $keys = [];
        if (isset($update['callback_query']['inline_message_id'])) {
            editMessageText($update['callback_query']['inline_message_id'], '<b>' . getTranslation('raid_access_denied') . '</b>', $keys);
        } else {
            editMessageText($update['callback_query']['message']['message_id'], '<b>' . getTranslation('raid_access_denied') . '</b>', $keys, $update['callback_query']['message']['chat']['id'], $keys);
        }
        answerCallbackQuery($update['callback_query']['id'], getTranslation('raid_access_denied'));
        exit;
    }
}

/**
 * Raid duplication check.
 * @param $gym
 * @param $end
 * @return string
 */
function raid_duplication_check($gym,$end)
{
    // Build query.
    $rs = my_query(
        "
        SELECT    *,
                          UNIX_TIMESTAMP(end_time)                        AS ts_end,
                          UNIX_TIMESTAMP(start_time)                      AS ts_start,
                          UNIX_TIMESTAMP(NOW())                           AS ts_now,
                          UNIX_TIMESTAMP(end_time)-UNIX_TIMESTAMP(NOW())  AS t_left
            FROM      raids
            WHERE   gym_name = '{$gym}'
	    ORDER BY id DESC
	    LIMIT 1
        "
    );

    // Get row.
    $raid = $rs->fetch_assoc();

    // Set duplicate ID to 0
    $duplicate_id = 0;

    // If gym is in database and new end_time matches existing end_time the updated duplicate ID to raid ID from database
    if ($raid) {
	// Timezone - maybe there's a more elegant solution as date_default_timezone_set?!
        $tz = TIMEZONE;
        date_default_timezone_set($tz);
	
	// Now
	$now = time();

	// Compare time - check minutes before and after database value
	$beforeAfter = 15;
	$extendBefore = 180;

	// Seems raid is being created at the moment
        if ($raid['ts_end'] === NULL) {
	    // Compare via start_time.
	    $compare = "start";
	    $time4compare = $now;

	    // Set compare values.
	    $ts_compare_before = $raid['ts_start'] - ($beforeAfter*60);
	    $ts_compare_after = $raid['ts_start'] + ($beforeAfter*60);
	} else {
	    // Compare via end_time.
	    $compare = "end";
	    $time4compare = $now + $end*60;

	    // Set compare values.
	    // Extend compare time for raid times if $time4compare is equal to $now which means $end must be 0
	    $ts_compare_before = ($time4compare == $now) ? ($raid['ts_end'] - ($extendBefore*60)) : ($raid['ts_end'] - ($beforeAfter*60));
	    $ts_compare_after = $raid['ts_end'] + ($beforeAfter*60);
	}

        // Debug log unix times
        debug_log('Unix timestamp of ' . $compare . 'time new raid: ' . $time4compare);
        debug_log('Unix timestamp of ' . $compare . 'time -' . (($time4compare == $now) ? $extendBefore : $beforeAfter) . ' minutes of existing raid: ' . $ts_compare_before);
        debug_log('Unix timestamp of ' . $compare . 'time +' . $beforeAfter . ' minutes of existing raid: ' . $ts_compare_after);

        // Debug log
        debug_log('Searched database for raids at ' . $raid['gym_name']);
        debug_log('Database raid ID of last raid at '. $raid['gym_name'] . ': ' . $raid['id']);
        debug_log('New raid at ' . $raid['gym_name'] . ' will ' . $compare . ': ' . unix2tz($time4compare,$tz));
        debug_log('Existing raid at ' . $raid['gym_name'] . ' will ' . $compare . ' between ' . unix2tz($ts_compare_before,$tz) . ' and ' . unix2tz($ts_compare_after,$tz));

        // Check if end_time of new raid is between plus minus the specified minutes of existing raid
        if($time4compare >= $ts_compare_before && $time4compare <= $ts_compare_after){
	    // Update existing raid.
	    // Negative raid ID if compare method is start and not end time
	    $duplicate_id = ($compare == "start") ? (0-$raid['id']) : $raid['id'];
	    debug_log('New raid matches ' . $compare . 'time of existing raid!');
	    debug_log('Updating raid ID: ' . $duplicate_id);
    	} else {
	    // Create new raid.
	    debug_log('New raid ' . $compare . 'time does not match the ' . $compare . 'time of existing raid.');
	    debug_log('Creating new raid at gym: ' . $raid['gym_name']);
        }
    } else {
	debug_log("Gym '" . $gym . "' not found in database!");
	debug_log("Creating new raid at gym: " . $gym);
    }

    // Return ID, -ID or 0
    return $duplicate_id;
}

/**
 * Insert gym.
 * @param $gym_name
 * @param $latitude
 * @param $longitude
 * @param $address
 */
function insert_gym($name, $lat, $lon, $address)
{
    global $db;

    // Build query to check if gym is already in database or not
    $rs = my_query(
        "
        SELECT    COUNT(*)
        FROM      gyms
          WHERE   gym_name = '{$name}'
         "
        );

    $row = $rs->fetch_row();

    // Gym already in database or new
    if (empty($row['0'])) {
        // Build query for gyms table to add gym to database
        debug_log('Gym not found in database gym list! Adding gym "' . $name . '" to the database gym list.');
        $rs = my_query(
            "
            INSERT INTO   gyms
            SET           lat = '{$lat}',
                              lon = '{$lon}',
                              gym_name = '{$db->real_escape_string($name)}',
                              address = '{$db->real_escape_string($address)}'
            "
        );
    } else {
        // Update gyms table to reflect gym changes.
        debug_log('Gym found in database gym list! Updating gym "' . $name . '" now.');
        $rs = my_query(
            "
            UPDATE        gyms
            SET           lat = '{$lat}',
                              lon = '{$lon}',
                              address = '{$db->real_escape_string($address)}'
               WHERE      gym_name = '{$name}'
            "
        );
    }
}

/**
 * Get raid level of a pokemon.
 * @param $pokedex_id
 * @return string
 */
function get_raid_level($pokedex_id)
{
    // Make sure $pokedex_id is numeric
    if(is_numeric($pokedex_id)) {
        // Get raid level from database
        $rs = my_query(
                "
                SELECT    raid_level
                FROM      pokemon
                WHERE     pokedex_id = $pokedex_id
                "
            );

        $raid_level = '0';
        while ($level = $rs->fetch_assoc()) {
            $raid_level = $level['raid_level'];
        }
    } else {
        $raid_level = '0';
    }

    return $raid_level;
}

/**
 * Get raid data.
 * @param $raid_id
 * @return array
 */
function get_raid($raid_id)
{
    // Get the raid data by id.
    $rs = my_query(
        "
        SELECT     raids.*, users.name,
                   UNIX_TIMESTAMP(start_time)                      AS ts_start,
                   UNIX_TIMESTAMP(end_time)                        AS ts_end,
                   UNIX_TIMESTAMP(NOW())                           AS ts_now,
                   UNIX_TIMESTAMP(end_time)-UNIX_TIMESTAMP(NOW())  AS t_left
        FROM       raids
        LEFT JOIN  users
        ON         raids.user_id = users.user_id
        WHERE      raids.id = {$raid_id}
        "
    );

    // Get the row.
    $raid = $rs->fetch_assoc();

    return $raid;
}

/**
 * Get last 20 active raids.
 * @param $timezone
 * @return array
 */
function get_active_raids($tz)
{
    // Get the raid data by id.
    $rs = my_query(
        "
        SELECT     *,
                   UNIX_TIMESTAMP(start_time)                      AS ts_start,
                   UNIX_TIMESTAMP(end_time)                        AS ts_end,
                   UNIX_TIMESTAMP(NOW())                           AS ts_now,
                   UNIX_TIMESTAMP(end_time)-UNIX_TIMESTAMP(NOW())  AS t_left
        FROM       raids
        WHERE      end_time>NOW()
        AND        timezone='{$tz}'
        ORDER BY   end_time ASC LIMIT 20
        "
    );

    // Get the raids.
    $raids = $rs->fetch_assoc();

    debug_log($raids);

    return $raids;
}

/**
 * Get local name of pokemon.
 * @param $pokedex_id
 * @return string
 */
function get_local_pokemon_name($pokedex_id)
{
    // Init pokemon name and define fake pokedex ids used for raid eggs
    $pokemon_name = '';
    $eggs = $GLOBALS['eggs'];

    // Get eggs from normal translation.
    if(in_array($pokedex_id, $eggs)) {
        $pokemon_name = getTranslation('egg_' . substr($pokedex_id, -1));
    } else { 
        $pokemon_name = getTranslation('pokemon_id_' . $pokedex_id);
    }

    // Fallback: Get original pokemon name from database
    if(empty($pokemon_name)) {
        $rs = my_query(
                "
                SELECT    pokemon_name
                FROM      pokemon
                WHERE     pokedex_id = $pokedex_id
                "
            );

        while ($pokemon = $rs->fetch_assoc()) {
            $pokemon_name = $pokemon['pokemon_name'];
        }
    }

    return $pokemon_name;
}

/**
 * Get quest.
 * @param $quest_id
 * @return array
 */
function get_quest($quest_id)
{
    // Get the quest data by id.
    $rs = my_query(
        "
        SELECT     quests.*,
                   quests_questlist.quest_type, quests_questlist.quest_quantity, quests_questlist.quest_action,
                   quests_rewardlist.reward_type, quests_rewardlist.reward_quantity, 
                   pokestops.pokestop_name, pokestops.lat, pokestops.lon, pokestops.address
        FROM       quests
        LEFT JOIN  pokestops
        ON         quests.pokestop_id = pokestops.id
        LEFT JOIN  quests_questlist
        ON         quests.quest_id = quests_questlist.id
        LEFT JOIN  quests_rewardlist
        ON         quests.reward_id = quests_rewardlist.id
        WHERE      quests.id = {$quest_id}
        "
    );

    // Get the row.
    $quest = $rs->fetch_assoc();

    debug_log($quest);

    return $quest;
}

/**
 * Get quest and reward as formatted string.
 * @param $quest array
 * @return array
 */
function get_formatted_quest($quest)
{
    /** Example:
     * Pokestop: Reward-Stop Number 1
     * Quest-Street 5, 13579 Poke-City
     * Quest: Hatch 1 Egg
     * Reward: 10 Berries
    */

    // Pokestop name and address.
    $pokestop_name = SP . '<b>' . (!empty($quest['pokestop_name']) ? ($quest['pokestop_name']) : (getTranslation('unnamed_pokestop'))) . '</b>' . CR;

    // Add google maps link.
    if (!empty($quest['address'])) {
        $pokestop_address = '<a href="https://maps.google.com/?daddr=' . $quest['lat'] . ',' . $quest['lon'] . '">' . $quest['address'] . '</a>';
    } else {
        $pokestop_address = '<a href="http://maps.google.com/maps?q=' . $quest['lat'] . ',' . $quest['lon'] . '">http://maps.google.com/maps?q=' . $quest['lat'] . ',' . $quest['lon'] . '</a>';
    }

    // Quest action: Singular or plural?
    $quest_action = explode(":", getTranslation('quest_action_' . $quest['quest_action']));
    $quest_action_singular = $quest_action[0];
    $quest_action_plural = $quest_action[1];
    $qty_action = $quest['quest_quantity'] . SP . (($quest['quest_quantity'] > 1) ? ($quest_action_plural) : ($quest_action_singular));

    // Reward type: Singular or plural?
    $reward_type = explode(":", getTranslation('reward_type_' . $quest['reward_type']));
    $reward_type_singular = $reward_type[0];
    $reward_type_plural = $reward_type[1];
    $qty_reward = $quest['reward_quantity'] . SP . (($quest['reward_quantity'] > 1) ? ($reward_type_plural) : ($reward_type_singular));

    // Build quest text.
    $text = '';
    $text .= getTranslation('pokestop') . ':' . $pokestop_name . $pokestop_address . CR;
    $text .= getTranslation('quest') . ': <b>' . getTranslation('quest_type_' . $quest['quest_type']) . SP . $qty_action . '</b>' . CR;
    $text .= getTranslation('reward') . ': <b>' . $qty_reward . '</b>' . CR;

    return $text;
}

/**
 * Get today's quests as formatted string.
 * @return string
 */
function get_todays_formatted_quests()
{
    // Get the quest data by id.
    $rs = my_query(
        "
        SELECT     *
        FROM       quests
        WHERE      quest_date = CURDATE()
        "
    );

    // Init empty message and counter.
    $msg = '';
    $count = 0;

    // Get the quests.
    while ($todays_quests = $rs->fetch_assoc()) {
        $quest = get_quest($todays_quests['id']);
        $msg .= get_formatted_quest($quest);
        $msg .= CR;
        $count = $count + 1;
    }

    // No quests today?
    if($count == 0) {
        $msg = getTranslation('no_quests_today');
    }

    return $msg;
}

/**
 * Delete quest.
 * @param $quest_id
 */
function delete_quest($quest_id)
{
    global $db;

    // Delete telegram messages for quest.
    $rs = my_query(
        "
        SELECT        *
            FROM      cleanup_quests
            WHERE     quest_id = '{$quest_id}'
              AND     chat_id <> 0
        "
    );

    // Counter
    $counter = 0;

    // Delete every telegram message
    while ($row = $rs->fetch_assoc()) {
        // Delete telegram message.
        debug_log('Deleting telegram message ' . $row['message_id'] . ' from chat ' . $row['chat_id'] . ' for quest ' . $row['quest_id']);
        delete_message($row['chat_id'], $row['message_id']);
        $counter = $counter + 1;
    }

    // Nothing to delete on telegram.
    if ($counter == 0) {
        debug_log('Quest with ID ' . $quest_id . ' was not found in the cleanup table! Skipping deletion of telegram messages!');
    }

    // Delete quest from cleanup table.
    debug_log('Deleting quest ' . $quest_id . ' from the cleanup table:');
    $rs_cleanup = my_query(
        "
        DELETE FROM   cleanup_quests
        WHERE   quest_id = '{$quest_id}' 
           OR   cleaned = '{$quest_id}'
        "
    );

    // Delete quest from quest table.
    debug_log('Deleting quest ' . $quest_id . ' from the quest table:');
    $rs_quests = my_query(
        "
        DELETE FROM   quests 
        WHERE   id = '{$quest_id}'
        "
    );
}

/**
 * Get pokestop.
 * @param $pokestop_id
 * @return array
 */
function get_pokestop($pokestop_id)
{
    // Get pokestop from database
    $rs = my_query(
            "
            SELECT    *
            FROM      pokestops
            WHERE     id = {$pokestop_id}
            "
        );

    $stop = $rs->fetch_assoc();

    return $stop;
}

/**
 * Get pokestops starting with the searchterm.
 * @param $searchterm
 * @return bool|array
 */
function get_pokestop_list_keys($searchterm)
{
    // Make sure the search term is not empty
    if(!empty($searchterm)) {
        // Get pokestop from database
        $rs = my_query(
                "
                SELECT    id, pokestop_name
                FROM      pokestops
                WHERE     pokestop_name LIKE '%$searchterm%'
                "
            );

        // Init empty keys array.
        $keys = array();

        // Add key for each found pokestop
        while ($stops = $rs->fetch_assoc()) {
            // Add keys.
            $keys[] = array(
                'text'          => $stops['pokestop_name'],
                'callback_data' => $stops['id'] . ':quest_create:0'
            );
        }

        // Get the inline key array.
        $keys = inline_key_array($keys, 2);
    } else {
        // Return false.
        $keys = false;
    }

    return $keys;
}

/**
 * Get gym.
 * @param $id
 * @return array
 */
function get_gym($id)
{
    // Get gym from database
    $rs = my_query(
            "
            SELECT    *
            FROM      gyms
	    WHERE     id = {$id}
            "
        );

    $gym = $rs->fetch_assoc();

    return $gym;
}

/**
 * Get pokemon info as formatted string.
 * @param $pokedex_id
 * @return array
 */
function get_pokemon_info($pokedex_id)
{
    /** Example:
     * Raid boss: Mewtwo (#ID)
     * Weather: Icons
     * CP: CP values (Boosted CP values)
    */
    $info = '';
    $info .= getTranslation('raid_boss') . ': <b>' . get_local_pokemon_name($pokedex_id) . ' (#' . $pokedex_id . ')</b>' . CR . CR;
    $poke_raid_level = get_raid_level($pokedex_id);
    $poke_cp = get_formatted_pokemon_cp($pokedex_id);
    $poke_weather = get_pokemon_weather($pokedex_id);
    $info .= getTranslation('pokedex_raid_level') . ': ' . getTranslation($poke_raid_level . 'stars') . CR;
    $info .= (empty($poke_cp)) ? (getTranslation('pokedex_cp') . CR) : $poke_cp . CR;
    $info .= getTranslation('pokedex_weather') . ': ' . get_weather_icons($poke_weather) . CR . CR;

    return $info;
}

/**
 * Get pokemon cp values.
 * @param $pokedex_id
 * @return array
 */
function get_pokemon_cp($pokedex_id)
{
    // Get gyms from database
    $rs = my_query(
            "
            SELECT    min_cp, max_cp, min_weather_cp, max_weather_cp
            FROM      pokemon
            WHERE     pokedex_id = {$pokedex_id}
            "
        );

    $cp = $rs->fetch_assoc();

    return $cp;
}

/**
 * Get formatted pokemon cp values.
 * @param $pokedex_id
 * @return string
 */
function get_formatted_pokemon_cp($pokedex_id)
{
    // Get gyms from database
    $rs = my_query(
            "
            SELECT    min_cp, max_cp, min_weather_cp, max_weather_cp
            FROM      pokemon
            WHERE     pokedex_id = {$pokedex_id}
            "
        );

    $cp20 = '';
    $cp25 = '';

    while($row = $rs->fetch_assoc()) {
        // CP
        $cp20 .= ($row['min_cp'] > 0) ? $row['min_cp'] : '';
        $cp20 .= (!empty($cp20) && $cp20 > 0) ? ('/' . $row['max_cp']) : ($row['max_cp']);

        // Weather boosted CP
        $cp25 .= ($row['min_weather_cp'] > 0) ? $row['min_weather_cp'] : '';
        $cp25 .= (!empty($cp25) && $cp25 > 0) ? ('/' . $row['max_weather_cp']) : ($row['max_weather_cp']);
    }

    // Combine CP and weather boosted CP
    $cp = (!empty($cp20)) ? (getTranslation('pokedex_cp') . ' <b>' . $cp20 . '</b>') : '';
    $cp .= (!empty($cp25)) ? (' (' . $cp25 . ')') : '';

    return $cp;
}

/**
 * Get pokemon weather.
 * @param $pokedex_id
 * @return string
 */
function get_pokemon_weather($pokedex_id)
{
    // Get pokemon weather from database
    $rs = my_query(
            "
            SELECT    weather
            FROM      pokemon
            WHERE     pokedex_id = {$pokedex_id}
            "
        );

    // Fetch the row.
    $ww = $rs->fetch_assoc();

    return $ww['weather'];
}

/**
 * Get weather icons.
 * @param $weather_value
 * @return string
 */
function get_weather_icons($weather_value)
{
    if($weather_value > 0) {
        // Get length of arg and split arg
        $weather_value_length = strlen((string)$weather_value);
        $weather_value_string = str_split((string)$weather_value);

        // Init weather icons string.
        $weather_icons = '';

        // Add icons to string.
        for ($i = 0; $i < $weather_value_length; $i = $i + 1) {
            // Get weather icon from constants
            $weather_icons .= $GLOBALS['weather'][$weather_value_string[$i]];
            $weather_icons .= ' ';
        }

        // Trim space after last icon
        $weather_icons = rtrim($weather_icons);
    } else {
        $weather_icons = '';
    }

    return $weather_icons;
}

/**
 * Get user.
 * @param $user_id
 * @return message
 */
function get_user($user_id)
{
    // Get user details.
    $rs = my_query(
        "
        SELECT    * 
                FROM      users
                  WHERE   user_id = {$user_id}
        "
    );

    // Fetch the row.
    $row = $rs->fetch_assoc();

    // Build message string.
    $msg = '';

    // Add name.
    $msg .= 'Name: <a href="tg://user?id=' . $row['user_id'] . '">' . htmlspecialchars($row['name']) . '</a>' . CR;

    // Unknown team.
    if ($row['team'] === NULL) {
        $msg .= 'Team: ' . $GLOBALS['teams']['unknown'] . CR;

    // Known team.
    } else {
        $msg .= 'Team: ' . $GLOBALS['teams'][$row['team']] . CR;
    }

    // Add level.
    if ($row['level'] != 0) {
        $msg .= 'Level: ' . $row['level'] . CR;
    }

    return $msg;
}

/**
 * Get timezone from user or config as fallback.
 * @param $update
 * @return timezone
 */
function get_timezone($update)
{
    // Get telegram ID to check access from $update - either message, callback_query or inline_query
    $update_type = '';
    $update_type = !empty($update['message']['from']['id']) ? 'message' : $update_type;
    $update_type = (empty($update_type) && !empty($update['callback_query']['from']['id'])) ? 'callback_query' : $update_type;
    $update_type = (empty($update_type) && !empty($update['inline_query']['from']['id'])) ? 'inline_query' : $update_type;
    $update_id = $update[$update_type]['from']['id'];

    // Log message type and ID
    debug_log('Telegram message type: ' . $update_type);
    debug_log('Getting timezone for ID: ' . $update_id);

    // Build query.
    $rs = my_query(
        "
        SELECT    timezone
        FROM      raids
          WHERE   id = (
                      SELECT    raid_id
                      FROM      attendance
                        WHERE   user_id = {$update_id}
                      ORDER BY  id DESC LIMIT 1
                  )
        "
    );

    // Get row.
    $row = $rs->fetch_assoc();

    // No data found.
    if (!$row) {
        $tz = TIMEZONE;
        debug_log('No timezone found for ID: ' . $update_id, '!');
        debug_log('Returning default timezone: ' . $tz, '!');
    } else {
        $tz = $row['timezone'];
        debug_log('Found timezone for ID: ' . $update_id);
        debug_log('Returning timezone: ' . $tz);
    }

    return $tz;
}

/**
 * Moderator keys.
 * @param $limit
 * @param $action
 * @return array
 */
function edit_moderator_keys($limit, $action)
{
    // Number of entries to display at once.
    $entries = 10;

    // Number of entries to skip with skip-back and skip-next buttons
    $skip = 50;

    // Module for back and next keys
    $module = "mods";

    // Init empty keys array.
    $keys = array();

    // Get moderators from database
    if ($action == "list" || $action == "delete") {
        $rs = my_query(
                "
                SELECT    *
                FROM      users
                WHERE     moderator = 1 
	        ORDER BY  name
	        LIMIT     $limit, $entries
                "
            );

	// Number of entries
        $cnt = my_query(
                "
                SELECT    COUNT(*)
                FROM      users
                WHERE     moderator = 1 
                "
            );
    } else if ($action == "add") {
        $rs = my_query(
                "
                SELECT    *
                FROM      users
                WHERE     (moderator = 0 OR moderator IS NULL)
                ORDER BY  name
                LIMIT     $limit, $entries
                "
            );

	// Number of entries
        $cnt = my_query(
                "
                SELECT    COUNT(*)
                FROM      users
                WHERE     (moderator = 0 OR moderator IS NULL)
                "
            );
    }

    // Number of database entries found.
    $sum = $cnt->fetch_row();
    $count = $sum['0'];

    // List users / moderators
    while ($mod = $rs->fetch_assoc()) {
        $keys[] = array(
            'text'          => $mod['name'],
            'callback_data' => '0:mods_' . $action . ':' . $mod['user_id']
        );
    }

    // Empty backs and next keys
    $keys_back = array();
    $keys_next = array();

    // Add back key.
    if ($limit > 0) {
        $new_limit = $limit - $entries;
        $empty_back_key = array();
        $back = universal_key($empty_back_key, $new_limit, $module, $action, getTranslation('back') . " (-" . $entries . ")");
        $keys_back[] = $back[0][0];
    }

    // Add skip back key.
    if ($limit - $skip > 0) {
        $new_limit = $limit - $skip - $entries;
        $empty_back_key = array();
        $back = universal_key($empty_back_key, $new_limit, $module, $action, getTranslation('back') . " (-" . $skip . ")");
        $keys_back[] = $back[0][0];
    }

    // Add next key.
    if (($limit + $entries) < $count) {
        $new_limit = $limit + $entries;
        $empty_next_key = array();
        $next = universal_key($empty_next_key, $new_limit, $module, $action, getTranslation('next') . " (+" . $entries . ")");
        $keys_next[] = $next[0][0];
    }

    // Add skip next key.
    if (($limit + $skip + $entries) < $count) {
        $new_limit = $limit + $skip + $entries;
        $empty_next_key = array();
        $next = universal_key($empty_next_key, $new_limit, $module, $action, getTranslation('next') . " (+" . $skip . ")");
        $keys_next[] = $next[0][0];
    }

    // Exit key
    $empty_exit_key = array();
    $key_exit = universal_key($empty_exit_key, "0", "exit", "0", getTranslation('abort'));

    // Get the inline key array.
    $keys = inline_key_array($keys, 1);
    $keys_back = inline_key_array($keys_back, 2);
    $keys_next = inline_key_array($keys_next, 2);
    $keys = array_merge($keys_back, $keys);
    $keys = array_merge($keys, $keys_next);
    $keys = array_merge($keys, $key_exit);

    return $keys;
}

/**
 * Inline key array.
 * @param $buttons
 * @param $columns
 * @return array
 */
function inline_key_array($buttons, $columns)
{
    $result = array();
    $col = 0;
    $row = 0;

    foreach ($buttons as $v) {
        $result[$row][$col] = $v;
        $col++;

        if ($col >= $columns) {
            $row++;
            $col = 0;
        }
    }
    return $result;
}

/**
 * Raid edit start keys.
 * @param $id
 * @return array
 */
function raid_edit_start_keys($id)
{
    // Get all raid levels from database
    $rs = my_query(
            "
            SELECT    raid_level
            FROM      pokemon
            WHERE     raid_level != '0'
            GROUP BY  raid_level
            ORDER BY  FIELD(raid_level, '5', '4', '3', '2', '1', 'X')
            "
        );

    // Init empty keys array.
    $keys = array();

    // Add key for each raid level
    while ($level = $rs->fetch_assoc()) {
        $keys[] = array(
            'text'          => getTranslation($level['raid_level'] . 'stars'),
            'callback_data' => $id . ':edit:' . $level['raid_level']
        );
    }
    
    // Get the inline key array.
    $keys = inline_key_array($keys, 3);

    return $keys;
}

/**
 * Raid gym first letter selection
 * @return array
 */
function raid_edit_gyms_first_letter_keys() {
    // Get gyms from database
    $rs = my_query(
            "
            SELECT UPPER(LEFT(gym_name, 1)) AS first_letter
            FROM      gyms
            GROUP BY LEFT(gym_name, 1)
            "
        );

    // Init empty keys array.
    $keys = array();

    while ($gym = $rs->fetch_assoc()) {
	// Add first letter to keys array
        $keys[] = array(
            'text'          => $gym['first_letter'],
            'callback_data' => '0:raid_by_gym:' . $gym['first_letter']
        );
    }

    // Get the inline key array.
    $keys = inline_key_array($keys, 4);

    return $keys;
}

/**
 * Raid edit gym keys.
 * @param $first
 * @return array
 */
function raid_edit_gym_keys($first)
{
    // Get gyms from database
    $rs = my_query(
            "
            SELECT    id, gym_name
            FROM      gyms
	    WHERE     UPPER(LEFT(gym_name, 1)) = UPPER('{$first}')
	    ORDER BY  gym_name
            "
        );

    // Init empty keys array.
    $keys = array();

    while ($gym = $rs->fetch_assoc()) {
	$keys[] = array(
            'text'          => $gym['gym_name'],
            'callback_data' => '0:raid_create:ID,' . $gym['id']
        );
    }
    
    // Get the inline key array.
    $keys = inline_key_array($keys, 1);

    return $keys;
}

/**
 * Pokedex edit pokemon keys.
 * @param $limit
 * @param $action
 * @return array
 */
function edit_pokedex_keys($limit, $action, $all_pokemon = true)
{
    // Number of entries to display at once.
    $entries = 10;

    // Number of entries to skip with skip-back and skip-next buttons
    $skip = 50;

    // Module for back and next keys
    $module = "pokedex";

    // Init empty keys array.
    $keys = array();

    // Get only pokemon with CP and weather values from database
    if($all_pokemon == false) {
        $rs = my_query(
            "
            SELECT    pokedex_id
            FROM      pokemon
            WHERE     min_cp > 0
              AND     max_cp > 0
              AND     min_weather_cp > 0
              AND     max_weather_cp > 0
              AND     weather > 0
            ORDER BY  pokedex_id
            LIMIT     $limit, $entries
            "
        );

        // Number of entries
        $cnt = my_query(
            "
            SELECT    COUNT(*)
            FROM      pokemon
            WHERE     min_cp > 0
              AND     max_cp > 0
              AND     min_weather_cp > 0
              AND     max_weather_cp > 0
              AND     weather > 0
            "
        );
    // Get all pokemon from database
    } else {
        $rs = my_query(
            "
            SELECT    pokedex_id
            FROM      pokemon
            ORDER BY  pokedex_id
            LIMIT     $limit, $entries
            "
        );

        // Number of entries
        $cnt = my_query(
            "
            SELECT    COUNT(*)
            FROM      pokemon
            "
        );
    }
    // Number of database entries found.
    $sum = $cnt->fetch_row();
    $count = $sum['0'];

    // List users / moderators
    while ($mon = $rs->fetch_assoc()) {
        $pokemon_name = get_local_pokemon_name($mon['pokedex_id']);
        $keys[] = array(
            'text'          => $mon['pokedex_id'] . ' ' . $pokemon_name,
            'callback_data' => $mon['pokedex_id'] . ':pokedex_edit_pokemon:0'
        );
    }

    // Empty backs and next keys
    $keys_back = array();
    $keys_next = array();

    // Add back key.
    if ($limit > 0) {
        $new_limit = $limit - $entries;
        $empty_back_key = array();
        $back = universal_key($empty_back_key, $new_limit, $module, $action, getTranslation('back') . " (-" . $entries . ")");
        $keys_back[] = $back[0][0];
    }

    // Add skip back key.
    if ($limit - $skip > 0) {
        $new_limit = $limit - $skip - $entries;
        $empty_back_key = array();
        $back = universal_key($empty_back_key, $new_limit, $module, $action, getTranslation('back') . " (-" . $skip . ")");
        $keys_back[] = $back[0][0];
    }

    // Add next key.
    if (($limit + $entries) < $count) {
        $new_limit = $limit + $entries;
        $empty_next_key = array();
        $next = universal_key($empty_next_key, $new_limit, $module, $action, getTranslation('next') . " (+" . $entries . ")");
        $keys_next[] = $next[0][0];
    }

    // Add skip next key.
    if (($limit + $skip + $entries) < $count) {
        $new_limit = $limit + $skip + $entries;
        $empty_next_key = array();
        $next = universal_key($empty_next_key, $new_limit, $module, $action, getTranslation('next') . " (+" . $skip . ")");
        $keys_next[] = $next[0][0];
    }

    // Exit key
    $empty_exit_key = array();
    $key_exit = universal_key($empty_exit_key, "0", "exit", "0", getTranslation('abort'));

    // Get the inline key array.
    $keys = inline_key_array($keys, 1);
    $keys_back = inline_key_array($keys_back, 2);
    $keys_next = inline_key_array($keys_next, 2);
    $keys = array_merge($keys_back, $keys);
    $keys = array_merge($keys, $keys_next);
    $keys = array_merge($keys, $key_exit);

    return $keys;
}

/**
 * Quest type keys.
 * @param $pokestop_id
 * @return array
 */
function quest_type_keys($pokestop_id)
{
    // Get all quest types from database
    $rs = my_query(
            "
            SELECT    quest_type
            FROM      quests_questlist
            GROUP BY  quest_type
            "
        );

    // Init empty keys array.
    $keys = array();

    // Add key for each quest quantity and action
    while ($quest = $rs->fetch_assoc()) {
        $text = getTranslation('quest_type_'. $quest['quest_type']) . '...';
        // Add keys.
        $keys[] = array(
            'text'          => $text,
            'callback_data' => $pokestop_id . ':quest_edit_type:' . $quest['quest_type']
        );
    }

    // Get the inline key array.
    $keys = inline_key_array($keys, 2);

    return $keys;
}

/**
 * Quest quantity and action keys.
 * @param $pokestop_id
 * @param $quest_type
 * @return array
 */
function quest_qty_action_keys($pokestop_id, $quest_type)
{
    // Get all quest types from database
    $rs = my_query(
            "
            SELECT    *
            FROM      quests_questlist
            WHERE     quest_type = '$quest_type'
            "
        );

    // Init empty keys array.
    $keys = array();

    // Add key for each quest quantity and action
    while ($quest = $rs->fetch_assoc()) {
        // Quest action: Singular or plural?
        $quest_action = explode(":", getTranslation('quest_action_' . $quest['quest_action']));
        $quest_action_singular = $quest_action[0];
        $quest_action_plural = $quest_action[1];
        $qty_action = $quest['quest_quantity'] . SP . (($quest['quest_quantity'] > 1) ? ($quest_action_plural) : ($quest_action_singular));

        // Add keys.
        $keys[] = array(
            'text'          => $qty_action,
            'callback_data' => $pokestop_id . ':quest_edit_reward:' . $quest['id']
        );
    }

    // Get the inline key array.
    $keys = inline_key_array($keys, 2);

    return $keys;
}

/**
 * Reward type keys.
 * @param $pokestop_id
 * @param $quest_id
 * @return array
 */
function reward_type_keys($pokestop_id, $quest_id)
{
    // Get all reward types from database
    $rs = my_query(
            "
            SELECT    reward_type
            FROM      quests_rewardlist
            GROUP BY  reward_type
            "
        );

    // Init empty keys array.
    $keys = array();

    // Add key for each quest quantity and action
    while ($reward = $rs->fetch_assoc()) {
        $rw_type = explode(":", getTranslation('reward_type_' . $reward['reward_type']));
        $text = $rw_type[0];
        // Add keys.
        $keys[] = array(
            'text'          => $text,
            'callback_data' => $pokestop_id . ',' . $quest_id . ':quest_edit_qty_reward:' . $reward['reward_type']
        );
    }

    // Get the inline key array.
    $keys = inline_key_array($keys, 2);

    return $keys;
}

/**
 * Reward quantity and type keys.
 * @param $pokestop_id
 * @param $quest_id
 * @param $reward_type
 * @return array
 */
function reward_qty_type_keys($pokestop_id, $quest_id, $reward_type)
{
    // Get all reward types from database
    $rs = my_query(
            "
            SELECT    *
            FROM      quests_rewardlist
            WHERE     reward_type = '$reward_type'
            "
        );

    // Init empty keys array.
    $keys = array();

    // Add key for each reward quantity and type
    while ($reward = $rs->fetch_assoc()) {
        // Reward qty: Singular or plural?
        $rw_type = explode(":", getTranslation('reward_type_' . $reward['reward_type']));
        $rw_type_singular = $rw_type[0];
        $rw_type_plural = $rw_type[1];
        $qty_rw = $reward['reward_quantity'] . SP . (($reward['reward_quantity'] > 1) ? ($rw_type_plural) : ($rw_type_singular));

        // Add keys.
        $keys[] = array(
            'text'          => $qty_rw,
            'callback_data' => $pokestop_id . ',' . $quest_id . ':quest_save:' . $reward['id']
        );
    }

    // Get the inline key array.
    $keys = inline_key_array($keys, 2);

    return $keys;
}

/**
 * Pokemon keys.
 * @param $raid_id
 * @param $raid_level
 * @return array
 */
function pokemon_keys($raid_id, $raid_level, $action)
{
    // Init empty keys array.
    $keys = array();

    // Get pokemon from database
    $rs = my_query(
            "
            SELECT    pokedex_id
            FROM      pokemon
            WHERE     raid_level = '$raid_level'
            "
        );

    // Add key for each raid level
    while ($pokemon = $rs->fetch_assoc()) {
        $keys[] = array(
            'text'          => get_local_pokemon_name($pokemon['pokedex_id']),
            'callback_data' => $raid_id . ':' . $action . ':' . $pokemon['pokedex_id']
        );
    }

    // Get the inline key array.
    $keys = inline_key_array($keys, 3);

    return $keys;
}

/**
 * Weather keys.
 * @param $pokedex_id
 * @param $action
 * @param $arg
 * @return array
 */
function weather_keys($pokedex_id, $action, $arg)
{
    // Get the type, level and cp
    $data = explode("-", $arg);
    $weather_add = $data[0] . '-';
    $weather_value = $data[1];

    // Save and reset values
    $save_arg = 'save-' . $weather_value;
    $reset_arg = $weather_add . '0';
    
    // Init empty keys array.
    $keys = array();

    // Max amount of weathers a pokemon raid boss can have is 3 which means 999
    // Keys will be shown up to 99 and when user is adding one more weather we exceed 99, so we remove the keys then
    // This means we do not exceed the max amout of 3 weathers a pokemon can have :)
    // And no, 99 is not a typo if you read my comment above :P
    if($weather_value <= 99) {
        // Get last number from weather array
        end($GLOBALS['weather']);
        $last = key($GLOBALS['weather']);

        // Add buttons for each weather.
        for ($i = 1; $i <= $last; $i = $i + 1) {
            // Get length of arg and split arg
            $weather_value_length = strlen((string)$weather_value);
            $weather_value_string = str_split((string)$weather_value);

            // Continue if weather got already selected
            if($weather_value_length == 1 && $weather_value == $i) continue;
            if($weather_value_length == 2 && $weather_value_string[0] == $i) continue;
            if($weather_value_length == 2 && $weather_value_string[1] == $i) continue;

            // Set new weather.
            $new_weather = $weather_add . ($weather_value == 0 ? '' : $weather_value) . $i;

            // Set keys.
            $keys[] = array(
                'text'          => $GLOBALS['weather'][$i],
                'callback_data' => $pokedex_id . ':' . $action . ':' . $new_weather
            ); 
        }
    }

    // Get the inline key array.
    $keys = inline_key_array($keys, 3);

    // Save and Reset key
    $keys[] = array(
        array(
            'text'          => EMOJI_DISK,
            'callback_data' => $pokedex_id . ':' . $action . ':' . $save_arg
        ),
        array(
            'text'          => getTranslation('reset'),
            'callback_data' => $pokedex_id . ':' . $action . ':' . $reset_arg
        )
    );

    return $keys;
}

/**
 * CP keys.
 * @param $pokedex_id
 * @param $action
 * @param $arg
 * @return array
 */
function cp_keys($pokedex_id, $action, $arg)
{
    // Get the type, level and cp
    $data = explode("-", $arg);
    $cp_type_level = $data[0] . '-' . $data[1];
    $cp_add = $data[0] . '-' . $data[1] . '-' . $data[2] . '-';
    $old_cp = $data[3];

    // Save and reset values
    $save_arg = $cp_type_level . '-save-' . $old_cp;
    $reset_arg = $cp_add . '0';
    
    // Init empty keys array.
    $keys = array();

    // Max CP is 9999 and no the value 999 is not a typo!
    // Keys will be shown up to 999 and when user is adding one more number we exceed 999, so we remove the keys then
    // This means we do not exceed a Max CP of 9999 :)
    if($old_cp <= 999) {

        // Add keys 0 to 9
        /**
         * 7 8 9
         * 4 5 6
         * 1 2 3
         * 0
        */

        // 7 8 9
        for ($i = 7; $i <= 9; $i = $i + 1) {
            // Set new cp
            $new_cp = $cp_add . ($old_cp == 0 ? '' : $old_cp) . $i;

            // Set keys.
            $keys[] = array(
                'text'          => $i,
                'callback_data' => $pokedex_id . ':' . $action . ':' . $new_cp
            );
        }

        // 4 5 6
        for ($i = 4; $i <= 6; $i = $i + 1) {
            // Set new cp
            $new_cp = $cp_add . ($old_cp == 0 ? '' : $old_cp) . $i;

            // Set keys.
            $keys[] = array(
                'text'          => $i,
                'callback_data' => $pokedex_id . ':' . $action . ':' . $new_cp
            );
        }

        // 1 2 3
        for ($i = 1; $i <= 3; $i = $i + 1) {
            // Set new cp
            $new_cp = $cp_add . ($old_cp == 0 ? '' : $old_cp) . $i;

            // Set keys.
            $keys[] = array(
                'text'          => $i,
                'callback_data' => $pokedex_id . ':' . $action . ':' . $new_cp
            );
        }

        // 0
        if($old_cp != 0) {
            // Set new cp
            $new_cp = $cp_add . $old_cp . '0';
        } else {
            $new_cp = $reset_arg;
        }
        
        // Set keys.
        $keys[] = array(
            'text'          => '0',
            'callback_data' => $pokedex_id . ':' . $action . ':' . $new_cp
        );
    }

    // Save
    $keys[] = array(
        'text'          => EMOJI_DISK,
        'callback_data' => $pokedex_id . ':' . $action . ':' . $save_arg
    );

    // Reset
    $keys[] = array(
        'text'          => getTranslation('reset'),
        'callback_data' => $pokedex_id . ':' . $action . ':' . $reset_arg
    );

    // Get the inline key array.
    $keys = inline_key_array($keys, 3);

    return $keys;
}

/**
 * Universal key.
 * @param $keys
 * @param $id
 * @param $action
 * @param $arg
 * @param $text
 * @return array
 */
function universal_key($keys, $id, $action, $arg, $text = '0')
{
    $keys[] = [
            array(
                'text'          => $text,
                'callback_data' => $id . ':' . $action . ':' . $arg
            )
        ];

    // Write to log.
    //debug_log($keys);

    return $keys;
}

/**
 * Share raid keys.
 * @param $raid_id
 * @param $user_id
 * @return array
 */
function share_raid_keys($raid_id, $user_id)
{
    // Moderator or not?
    debug_log("Checking if user is moderator: " . $user_id);
    $rs = my_query(
        "
        SELECT    moderator
        FROM      users
          WHERE   user_id = {$user_id}
        "
    );

    // Fetch user data.
    $user = $rs->fetch_assoc();

    // Check moderator status.
    $mod = $user['moderator'];
    debug_log('User is ' . (($mod == 1) ? '' : 'not ') . 'a moderator: ' . $user_id);

    // Add share button if not restricted.
    if ((SHARE_MODERATORS == true && $mod == 1) || SHARE_USERS == true) {
        debug_log('Adding general share key to inline keys');
        // Set the keys.
        $keys[] = [
            [
                'text'                => getTranslation('share'),
                'switch_inline_query' => strval($raid_id)
            ]
        ];
    }

    // Add buttons for predefined sharing chats.
    if (!empty(SHARE_CHATS)) {
        // Add keys for each chat.
        $chats = explode(',', SHARE_CHATS);
        foreach($chats as $chat) {
            // Get chat object 
            debug_log("Getting chat object for '" . $chat . "'");
            $chat_obj = get_chat($chat);

            // Check chat object for proper response.
            if ($chat_obj['ok'] == true) {
                debug_log('Proper chat object received, continuing to add key for this chat: ' . $chat_obj['result']['title']);
                $keys[] = [
                    [
                        'text'          => getTranslation('share_with') . ' ' . $chat_obj['result']['title'],
                        'callback_data' => $raid_id . ':raid_share:' . $chat
                    ]
                ];
            }
        }
    }

    return $keys;
}

/**
 * Share keys.
 * @param $quest_id
 * @param $user_id
 * @return array
 */
function share_quest_keys($quest_id, $user_id) 
{
    // Moderator or not?
    debug_log("Checking if user is moderator: " . $user_id);
    $rs = my_query(
        "
        SELECT    moderator
        FROM      users
          WHERE   user_id = {$user_id}
        "
    );

    // Fetch user data.
    $user = $rs->fetch_assoc();

    // Check moderator status.
    $mod = $user['moderator'];
    debug_log('User is ' . (($mod == 1) ? '' : 'not ') . 'a moderator: ' . $user_id);

    // Add share button if not restricted.
    if ((SHARE_MODERATORS == true && $mod == 1) || SHARE_USERS == true) {
        debug_log('Adding general share key to inline keys');
        // Set the keys.
        $keys[] = [
            [
                'text'                => getTranslation('share'),
                'switch_inline_query' => strval($quest_id)
            ]
        ];
    }
        
    // Add buttons for predefined sharing chats.
    if (!empty(SHARE_CHATS)) {
        // Add keys for each chat.
        $chats = explode(',', SHARE_CHATS);
        foreach($chats as $chat) {
            // Get chat object 
            debug_log("Getting chat object for '" . $chat . "'");
            $chat_obj = get_chat($chat);
            
            // Check chat object for proper response.
            if ($chat_obj['ok'] == true) {
                debug_log('Proper chat object received, continuing to add key for this chat: ' . $chat_obj['result']['title']);
                $keys[] = [
                    [
                        'text'          => getTranslation('share_with') . ' ' . $chat_obj['result']['title'],
                        'callback_data' => $quest_id . ':quest_share:' . $chat
                    ]
                ];
            }
        }
    }

    return $keys;
}

/**
 * Insert raid cleanup info to database.
 * @param $chat_id
 * @param $message_id
 * @param $raid_id
 */
function insert_raid_cleanup($chat_id, $message_id, $raid_id)
{
    // Log ID's of raid, chat and message
    debug_log('Raid_ID: ' . $raid_id);
    debug_log('Chat_ID: ' . $chat_id);
    debug_log('Message_ID: ' . $message_id);

    if ((is_numeric($chat_id)) && (is_numeric($message_id)) && (is_numeric($raid_id)) && ($raid_id > 0)) {
        global $db;

        // Get raid times.
        $rs = my_query(
            "
            SELECT    *, 
                                  UNIX_TIMESTAMP(start_time)                      AS ts_start,
                                  UNIX_TIMESTAMP(end_time)                        AS ts_end,
                                  UNIX_TIMESTAMP(NOW())                           AS ts_now,
                                  UNIX_TIMESTAMP(end_time)-UNIX_TIMESTAMP(NOW())  AS t_left
                    FROM      raids
                      WHERE   id = {$raid_id}
            "
        );
    
        // Fetch raid data.
        $raid = $rs->fetch_assoc();
	
	// Init found.
	$found = false;

        // Insert cleanup info to database
        if ($raid) {
	    // Check if cleanup info is already in database or not
	    // Needed since raids can be shared to multiple channels / supergroups!
	    $rs = my_query(
                "
		SELECT    *
            	    FROM      cleanup_raids
                    WHERE     raid_id = '{$raid_id}'
                "
            );

	    // Chat_id and message_id equal to info from database
	    while ($cleanup = $rs->fetch_assoc()) {
		// Leave while loop if cleanup info is already in database
		if(($cleanup['chat_id'] == $chat_id) && ($cleanup['message_id'] == $message_id)) {
            	    debug_log('Cleanup preparation info is already in database!');
		    $found = true;
		    break;
		} 
	    }
	}

	// Insert into database when raid found but no cleanup info found
        if ($raid && !$found) {
            // Build query for cleanup table to add cleanup info to database
            debug_log('Adding cleanup info to database:');
            $rs = my_query(
                "
                INSERT INTO   cleanup_raids
                SET           raid_id = '{$raid_id}',
                                  chat_id = '{$chat_id}',
                                  message_id = '{$message_id}'
                "
            );
	} 
    } else {
        debug_log('Invalid input for cleanup preparation!');
    }
}

/**
 * Run raids cleanup.
 * @param $telegram
 * @param $database
 */
function run_raids_cleanup ($telegram = 2, $database = 2) {
    // Check configuration, cleanup of telegram needs to happen before database cleanup!
    if (CLEANUP_TIME_TG > CLEANUP_TIME_DB) {
	cleanup_log('Configuration issue! Cleanup time for telegram messages needs to be lower or equal to database cleanup time!');
	cleanup_log('Stopping cleanup process now!');
	exit;
    }

    /* Check input
     * 0 = Do nothing
     * 1 = Cleanup
     * 2 = Read from config
    */

    // Get cleanup values from config per default.
    if ($telegram == 2) {
	$telegram = (CLEANUP_TELEGRAM == true) ? 1 : 0;
    }

    if ($database == 2) {
	$database = (CLEANUP_DATABASE == true) ? 1 : 0;
    }

    // Start cleanup when at least one parameter is set to trigger cleanup
    if ($telegram == 1 || $database == 1) {
        // Query for telegram cleanup without database cleanup
        if ($telegram == 1 && $database == 0) {
            // Get cleanup info.
            $rs = my_query(
                "
                SELECT    * 
                FROM      cleanup_raids
                  WHERE   chat_id <> 0
                  ORDER BY id DESC
                  LIMIT 0, 250     
                ", true
            );
        // Query for database cleanup without telegram cleanup
        } else if ($telegram == 0 && $database == 1) {
            // Get cleanup info.
            $rs = my_query(
                "
                SELECT    * 
                FROM      cleanup_raids
                  WHERE   chat_id = 0
                  LIMIT 0, 250
                ", true
            );
        // Query for telegram and database cleanup
        } else {
            // Get cleanup info.
            $rs = my_query(
                "
                SELECT    * 
                FROM      cleanup_raids
                  LIMIT 0, 250
                ", true
            );
        }

        // Init empty cleanup jobs array.
        $cleanup_jobs = array();

	// Fill array with cleanup jobs.
        while ($rowJob = $rs->fetch_assoc()) {
            $cleanup_jobs[] = $rowJob;
        }

        // Write to log.
        cleanup_log($cleanup_jobs);

        // Init previous raid id.
        $prev_raid_id = "FIRST_RUN";

        foreach ($cleanup_jobs as $row) {
	    // Set current raid id.
	    $current_raid_id = ($row['raid_id'] == 0) ? $row['cleaned'] : $row['raid_id'];

            // Write to log.
            cleanup_log("Cleanup ID: " . $row['id']);
            cleanup_log("Chat ID: " . $row['chat_id']);
            cleanup_log("Message ID: " . $row['message_id']);
            cleanup_log("Raid ID: " . $row['raid_id']);

            // Make sure raid exists
            $rs = my_query(
                "
                SELECT  UNIX_TIMESTAMP(end_time)      AS ts_end
                FROM    raids
                  WHERE id = {$current_raid_id}
                ", true
            );
            $rr = $rs->fetch_row();

            // No raid found - set cleanup to 0 and continue with next raid
            if (empty($rr['0'])) {
                cleanup_log('No raid found with ID: ' . $current_raid_id, '!');
                cleanup_log('Updating cleanup information.');
                my_query(
                "
                    UPDATE    cleanup_raids
                    SET       chat_id = 0, 
                              message_id = 0 
                    WHERE   id = {$row['id']}
                ", true
                );

                // Continue with next raid
                continue;
            }

	    // Get raid data only when raid_id changed compared to previous run
	    if ($prev_raid_id != $current_raid_id) {
                // Get the raid data by id.
                $rs = my_query(
                    "
                    SELECT  *,
                            UNIX_TIMESTAMP(end_time)                        AS ts_end,
                            UNIX_TIMESTAMP(start_time)                      AS ts_start,
                            UNIX_TIMESTAMP(NOW())                           AS ts_now,
                            UNIX_TIMESTAMP(end_time)-UNIX_TIMESTAMP(NOW())  AS t_left
                    FROM    raids
                      WHERE id = {$current_raid_id}
                    ", true
                );

                // Fetch raid data.
                $raid = $rs->fetch_assoc();

	        // Set times. 
	        $end = $raid['ts_end'];
	        $tz = $raid['timezone'];
    	        $now = $raid['ts_now'];
	        $cleanup_time_tg = 60*CLEANUP_TIME_TG;
	        $cleanup_time_db = 60*CLEANUP_TIME_DB;

		// Write times to log.
		cleanup_log("Current time: " . unix2tz($now,$tz,"Y-m-d H:i:s"));
		cleanup_log("Raid end time: " . unix2tz($end,$tz,"Y-m-d H:i:s"));
		cleanup_log("Telegram cleanup time: " . unix2tz(($end + $cleanup_time_tg),$tz,"Y-m-d H:i:s"));
		cleanup_log("Database cleanup time: " . unix2tz(($end + $cleanup_time_db),$tz,"Y-m-d H:i:s"));

		// Write unix timestamps to log.
		cleanup_log(CR . "Unix timestamps:");
		cleanup_log("Current time: " . $now);
		cleanup_log("Raid end time: " . $end);
		cleanup_log("Telegram cleanup time: " . ($end + $cleanup_time_tg));
		cleanup_log("Database cleanup time: " . ($end + $cleanup_time_db));
	    }

	    // Time for telegram cleanup?
	    if (($end + $cleanup_time_tg) < $now) {
                // Delete raid poll telegram message if not already deleted
	        if ($telegram == 1 && $row['chat_id'] != 0 && $row['message_id'] != 0) {
		    // Delete telegram message.
                    cleanup_log('Deleting telegram message ' . $row['message_id'] . ' from chat ' . $row['chat_id'] . ' for raid ' . $row['raid_id']);
                    delete_message($row['chat_id'], $row['message_id']);
		    // Set database values of chat_id and message_id to 0 so we know telegram message was deleted already.
                    cleanup_log('Updating telegram cleanup information.');
		    my_query(
    		    "
    		        UPDATE    cleanup_raids
    		        SET       chat_id = 0, 
    		                  message_id = 0 
      		        WHERE   id = {$row['id']}
		    ", true
		    );
	        } else {
		    if ($telegram == 1) {
			cleanup_log('Telegram message is already deleted!');
		    } else {
			cleanup_log('Telegram cleanup was not triggered! Skipping...');
		    }
		}
	    } else {
		cleanup_log('Skipping cleanup of telegram for this raid! Cleanup time has not yet come...');
	    }

	    // Time for database cleanup?
	    if (($end + $cleanup_time_db) < $now) {
                // Delete raid from attendance table.
	        // Make sure to delete only once - raid may be in multiple channels/supergroups, but only 1 time in database
	        if (($database == 1) && $row['raid_id'] != 0 && ($prev_raid_id != $current_raid_id)) {
		    // Delete raid from attendance table.
                    cleanup_log('Deleting attendances for raid ' . $current_raid_id);
                    my_query(
                    "
                        DELETE FROM    attendance
                        WHERE   id = {$row['raid_id']}
                    ", true
                    );

		    // Set database value of raid_id to 0 so we know attendance info was deleted already
		    // Use raid_id in where clause since the same raid_id can in cleanup more than once
                    cleanup_log('Updating database cleanup information.');
                    my_query(
                    "
                        UPDATE    cleanup_raids
                        SET       raid_id = 0, 
				  cleaned = {$row['raid_id']}
                        WHERE   raid_id = {$row['raid_id']}
                    ", true
                    );
	        } else {
		    if ($database == 1) {
		        cleanup_log('Attendances are already deleted!');
		    } else {
			cleanup_log('Attendance cleanup was not triggered! Skipping...');
		    }
		}

		// Delete raid from cleanup table and raid table once every value is set to 0 and cleaned got updated from 0 to the raid_id
		// In addition trigger deletion only when previous and current raid_id are different to avoid unnecessary sql queries
		if ($row['raid_id'] == 0 && $row['chat_id'] == 0 && $row['message_id'] == 0 && $row['cleaned'] != 0 && ($prev_raid_id != $current_raid_id)) {
		    // Delete raid from raids table.
		    cleanup_log('Deleting raid ' . $row['cleaned'] . ' from database.');
                    my_query(
                    "
                        DELETE FROM    raids
                        WHERE   id = {$row['cleaned']}
                    ", true
                    );
		    
		    // Get all cleanup jobs which will be deleted now.
                    cleanup_log('Removing cleanup info from database:');
		    $rs_cl = my_query(
                    "
                        SELECT *
			FROM    cleanup_raids
                        WHERE   cleaned = {$row['cleaned']}
                    ", true
		    );

		    // Log each cleanup ID which will be deleted.
		    while($rs_cleanups = $rs_cl->fetch_assoc()) {
 			cleanup_log('Cleanup ID: ' . $rs_cleanups['id'] . ', Former Raid ID: ' . $rs_cleanups['cleaned']);
		    }

		    // Finally delete from cleanup table.
                    my_query(
                    "
                        DELETE FROM    cleanup_raids
                        WHERE   cleaned = {$row['cleaned']}
                    ", true
                    );
		} else {
		    if ($prev_raid_id != $current_raid_id) {
			cleanup_log('Time for complete removal of raid from database has not yet come.');
		    } else {
			cleanup_log('Complete removal of raid from database was already done!');
		    }
		}
	    } else {
		cleanup_log('Skipping cleanup of database for this raid! Cleanup time has not yet come...');
	    }
	
	    // Store current raid id as previous id for next loop
            $prev_raid_id = $current_raid_id;
        }

    // Write to log.
    cleanup_log('Finished with cleanup process!');
    }
}

/**
 * Keys vote.
 * @param $raid
 * @return array
 */
function keys_vote($raid)
{
    // Init keys time array.
    $keys_time = [];

    $end_time = $raid['ts_end'];
    $now = $raid['ts_now'];
    $start_time = $raid['ts_start'];

    $keys = [
        [
            [
                'text'          => getTranslation('alone'),
                'callback_data' => $raid['id'] . ':vote_extra:0'
            ],
            [
                'text'          => '+ ' . TEAM_B,
                'callback_data' => $raid['id'] . ':vote_extra:mystic'
            ],
            [
                'text'          => '+ ' . TEAM_R,
                'callback_data' => $raid['id'] . ':vote_extra:valor'
            ],
            [
                'text'          => '+ ' . TEAM_Y,
                'callback_data' => $raid['id'] . ':vote_extra:instinct'
            ]
        ],
        [
            [
                'text'          => 'Team',
                'callback_data' => $raid['id'] . ':vote_team:0'
            ],
            [
                'text'          => 'Lvl +',
                'callback_data' => $raid['id'] . ':vote_level:up'
            ],
            [
                'text'          => 'Lvl -',
                'callback_data' => $raid['id'] . ':vote_level:down'
            ]
        ]
    ];

    // Raid ended already.
    if ($end_time < $now) {
        $keys = [
            [
                [
                    'text'          => getTranslation('raid_done'),
                    'callback_data' => $raid['id'] . ':vote_refresh:0'
                ]
            ]
        ];
    // Raid is still running.
    } else {
	$timePerSlot = 60*RAID_SLOTS;
	$timeBeforeEnd = 60*RAID_LAST_START;
        $col = 1;

        // Attend raid at any time
        if(RAID_ANYTIME == true)
        {
            $keys_time[] = array(
                'text'          => getTranslation('anytime'),
                'callback_data' => $raid['id'] . ':vote_time:0'
            );
        }

        // Old stuff, left for possible future use or in case of bugs:
        //for ($i = ceil($now / $timePerSlot) * $timePerSlot; $i <= ($end_time - $timeBeforeEnd); $i = $i + $timePerSlot) {
        for ($i = ceil($start_time / $timePerSlot) * $timePerSlot; $i <= ($end_time - $timeBeforeEnd); $i = $i + $timePerSlot) {
/*
            if ($col++ >= 4) {
                $keys[] = $keys_time;
                $keys_time = [];
                $col = 1;
            }
*/
	    // Plus 60 seconds, so vote button for e.g. 10:00 will disappear after 10:00:59 / at 10:01:00 and not right after 09:59:59 / at 10:00:00
	    if (($i + 60) > $now) {
		// Display vote buttons for now + 1 additional minute
                $keys_time[] = array(
                    'text'          => unix2tz($i, $raid['timezone']),
                    'callback_data' => $raid['id'] . ':vote_time:' . $i
                );
	    }

	    // This is our last run of the for loop since $i + timePerSlot are ahead of $end_time - $timeBeforeEnd
	    // Offer a last raid, which is x minutes before the raid ends, x = $timeBeforeEnd
            if (($i + $timePerSlot) > ($end_time - $timeBeforeEnd)) {
		// Set the time for the last possible raid and add vote key if there is enough time left
                $timeLastRaid = $end_time - $timeBeforeEnd;
		if($timeLastRaid > $i + $timeBeforeEnd && ($timeLastRaid >= $now)){
		    // Round last raid time to 5 minutes to avoid crooked voting times
		    $near5 = 5*60;
		    $timeLastRaid = round($timeLastRaid / $near5) * $near5;
                    $keys_time[] = array(
                        'text'          => unix2tz($timeLastRaid, $raid['timezone']),
                        'callback_data' => $raid['id'] . ':vote_time:' . $timeLastRaid
                    );
		}
            }
        }

        // Add time keys.
        $keys_time = inline_key_array($keys_time, 4);
        $keys = array_merge($keys, $keys_time);
        //$keys[] = $keys_time;

        // Init keys pokemon array.
        $keys_poke = [];

        // Get current pokemon
        $raid_pokemon = $raid['pokemon'];

        // Get raid level
        $raid_level = '0';
        $raid_level = get_raid_level($raid_pokemon);

        // Get participants
        $rs = my_query(
            "
            SELECT    count(attend_time)                  AS count,
                      sum(pokemon = '0')                  AS count_any_pokemon,
                      sum(pokemon = '{$raid_pokemon}')    AS count_raid_pokemon
            FROM      attendance
              WHERE   raid_id = {$raid['id']}
              AND     attend_time IS NOT NULL
              AND     raid_done != 1
              AND     cancel != 1
             "
        );

        $row = $rs->fetch_assoc();

        // Count participants and participants by pokemon
        $count_pp = $row['count'];
        $count_any_pokemon = $row['count_any_pokemon'];
        $count_raid_pokemon = $row['count_raid_pokemon'];

        // Write to log.
        debug_log('Participants for raid with ID ' . $raid['id'] . ': ' . $count_pp);
        debug_log('Participants who voted for any pokemon: ' . $count_any_pokemon);
        debug_log('Participants who voted for ' . $raid_pokemon . ': ' . $count_raid_pokemon);

        // Hide keys for specific cases
        $show_keys = true;
        // Make sure raid boss is not an egg
        if(!in_array($raid_pokemon, $GLOBALS['eggs'])) {
            // Make sure we either have no participants
            // OR all participants voted for "any" raid boss
            // OR all participants voted for the hatched raid boss 
            // OR all participants voted for "any" or the hatched raid boss
            if($count_pp == 0 || $count_pp == $count_any_pokemon || $count_pp == $count_raid_pokemon || $count_pp == ($count_any_pokemon + $count_raid_pokemon)) {
                $show_keys = false;
            }
        }

        // Add pokemon keys if we found the raid boss
        if ($raid_level != '0' && $show_keys) {
            // Get pokemon from database
            $rs = my_query(
                "
                SELECT    pokedex_id
                FROM      pokemon
                WHERE     raid_level = '$raid_level'
                "
            );

            // Init counter. 
            $count = 0;

            // Get eggs.
            $eggs = $GLOBALS['eggs'];

            // Add key for each raid level
            while ($pokemon = $rs->fetch_assoc()) {
                if(in_array($pokemon['pokedex_id'], $eggs)) continue;
                $keys_poke[] = array(
                    'text'          => get_local_pokemon_name($pokemon['pokedex_id']),
                    'callback_data' => $raid['id'] . ':vote_pokemon:' . $pokemon['pokedex_id']
                );

                // Counter
                $count = $count + 1;
            }

            // Add pokemon keys if we have two or more pokemon
            if($count >= 2) {
                // Add button if raid boss does not matter
                $keys_poke[] = array(
                    'text'          => getTranslation('any_pokemon'),
                    'callback_data' => $raid['id'] . ':vote_pokemon:0'
                );

                // Finally add pokemon to keys
                $keys_poke = inline_key_array($keys_poke, 3);
                $keys = array_merge($keys, $keys_poke);
            }
        }

        // Show icon, icon + text or just text.
        // Icon.
        if(RAID_VOTE_ICONS == true && RAID_VOTE_TEXT == false) {
            $text_here = EMOJI_HERE;
            $text_late = EMOJI_LATE;
            $text_done = TEAM_DONE;
            $text_cancel = TEAM_CANCEL;
        // Icon + text.
        } else if(RAID_VOTE_ICONS == true && RAID_VOTE_TEXT == true) {
            $text_here = EMOJI_HERE . getTranslation('here');
            $text_late = EMOJI_LATE . getTranslation('late');
            $text_done = TEAM_DONE . getTranslation('done');
            $text_cancel = TEAM_CANCEL . getTranslation('cancellation');
        // Text.
        } else {
            $text_here = getTranslation('here');
            $text_late = getTranslation('late');
            $text_done = getTranslation('done');
            $text_cancel = getTranslation('cancellation');
        }
        
        // Add status keys.
        $keys[] = [
            [
                'text'          => EMOJI_REFRESH,
                'callback_data' => $raid['id'] . ':vote_refresh:0'
            ],
            [
                'text'          => $text_here,
                'callback_data' => $raid['id'] . ':vote_status:arrived'
            ],
            [
                'text'          => $text_late,
                'callback_data' => $raid['id'] . ':vote_status:late'
            ],
            [
                'text'          => $text_done,
                'callback_data' => $raid['id'] . ':vote_status:raid_done'
            ],
            [
                'text'          => $text_cancel,
                'callback_data' => $raid['id'] . ':vote_status:cancel'
            ],
        ];
    }

    return $keys;
}

/**
 * Update user.
 * @param $update
 * @return bool|mysqli_result
 */
function update_user($update)
{
    global $db;

    $name = '';
    $nick = '';
    $sep = '';

    if (isset($update['message']['from'])) {
        $msg = $update['message']['from'];
    }

    if (isset($update['callback_query']['from'])) {
        $msg = $update['callback_query']['from'];
    }

    if (isset($update['inline_query']['from'])) {
        $msg = $update['inline_query']['from'];
    }

    if (!empty($msg['id'])) {
        $id = $msg['id'];

    } else {
        debug_log('No id', '!');
        debug_log($update, '!');
        return false;
    }

    if ($msg['first_name']) {
        $name = $msg['first_name'];
        $sep = ' ';
    }

    if (isset($msg['last_name'])) {
        $name .= $sep . $msg['last_name'];
    }

    if (isset($msg['username'])) {
        $nick = $msg['username'];
    }

    // Create or update the user.
    $request = my_query(
        "
        INSERT INTO users
        SET         user_id = {$id},
                    nick    = '{$db->real_escape_string($nick)}',
                    name    = '{$db->real_escape_string($name)}'
        ON DUPLICATE KEY
        UPDATE      nick    = '{$db->real_escape_string($nick)}',
                    name    = '{$db->real_escape_string($name)}'
        "
    );

    return $request;
}

/**
 * Send response vote.
 * @param $update
 * @param $data
 * @param bool $new
 */
function send_response_vote($update, $data, $new = false)
{
    // Get the raid data by id.
    $raid = get_raid($data['id']);

    $msg = show_raid_poll($raid);
    $keys = keys_vote($raid);

    // Write to log.
    debug_log($keys);

    if ($new) {
        $loc = send_location($update['callback_query']['message']['chat']['id'], $raid['lat'], $raid['lon']);

        // Write to log.
        debug_log('location:');
        debug_log($loc);

        // Send the message.
        send_message($update['callback_query']['message']['chat']['id'], $msg . "\n", $keys, ['reply_to_message_id' => $loc['result']['message_id']]);
        // Answer the callback.
        answerCallbackQuery($update['callback_query']['id'], $msg);
    } else {
        // Edit the message.
        edit_message($update, $msg, $keys, ['disable_web_page_preview' => 'true']);
        // Change message string.
        $msg = getTranslation('vote_updated');
        // Answer the callback.
        answerCallbackQuery($update['callback_query']['id'], $msg);
    }

    exit();
}

/**
 * Send please vote for a time first.
 * @param $update
 */
function send_vote_time_first($update)
{
    // Set the message.
    $msg = getTranslation('vote_time_first');

    // Answer the callback.
    answerCallbackQuery($update['callback_query']['id'], $msg);

    exit();
}

/**
 * Insert overview.
 * @param $chat_id
 * @param $message_id
 */
function insert_overview($chat_id, $message_id)
{
    global $db;

    // Build query to check if overview details are already in database or not
    $rs = my_query(
        "
        SELECT    COUNT(*)
        FROM      overview
          WHERE   chat_id = '{$chat_id}'
         "
        );

    $row = $rs->fetch_row();

    // Overview already in database or new
    if (empty($row['0'])) {
        // Build query for overview table to add overview info to database
        debug_log('Adding new overview information to database overview list!');
        $rs = my_query(
            "
            INSERT INTO   overview
            SET           chat_id = '{$chat_id}',
                          message_id = '{$message_id}'
            "
        );
    } else {
        // Nothing to do - overview information is already in database.
        debug_log('Overview information is already in database! Nothing to do...');
    }
}

/**
 * Delete overview.
 * @param $chat_id
 * @param $message_id
 */
function delete_overview($chat_id, $message_id)
{
    global $db;

    // Delete telegram message.
    debug_log('Deleting overview telegram message ' . $message_id . ' from chat ' . $chat_id);
    delete_message($chat_id, $message_id);

    // Delete overview from database.
    debug_log('Deleting overview information from database for Chat_ID: ' . $chat_id);
    $rs = my_query(
        "
        DELETE FROM   overview 
        WHERE   chat_id = '{$chat_id}'
        "
    );
}

/**
 * Get overview data to Share or refresh.
 * @param $update
 * @param $chats_active
 * @param $raids_active
 * @param $action - refresh or share
 * @param $chat_id
 */
function get_overview($update, $chats_active, $raids_active, $action = 'refresh', $chat_id = 0)
{
    // Add pseudo array for last run to active chats array
    $last_run = array();
    $last_run['chat_id'] = 'LAST_RUN';
    $chats_active[] = $last_run;

    // Init previous chat_id and raid_id
    $previous = 'FIRST_RUN';
    $previous_raid = 'FIRST_RAID';
    
    $tz = TIMEZONE;

    // Any active raids currently?
    if (empty($raids_active)) {
        // Init keys.
        $keys = array();
        $keys = [];

        // Refresh active overview messages with 'no_active_raids_currently' or send 'no_active_raids_found' message to user.
        $rs = my_query(
            "
            SELECT    *
            FROM      overview
            "
        );

        // Refresh active overview messages.
        while ($row_overview = $rs->fetch_assoc()) {
            $chat_id = $row_overview['chat_id'];
            $message_id = $row_overview['message_id'];

            // Get info about chat for title.
            debug_log('Getting chat object for chat_id: ' . $row_overview['chat_id']);
            $chat_obj = get_chat($row_overview['chat_id']);
            $chat_title = '';

            // Set title.
            if ($chat_obj['ok'] == 'true') {
                $chat_title = $chat_obj['result']['title'];
                debug_log('Title of the chat: ' . $chat_obj['result']['title']);
            }

            // Set the message.
            $msg = '<b>' . getTranslation('raid_overview_for_chat') . ' ' . $chat_title . ' '. getTranslation('from') .' '. unix2tz(time(), $tz, 'H:i') . '</b>' .  CR . CR;
            $msg .= getTranslation('no_active_raids');

            //Add custom message from the config.   
            if (PIN_MESSAGE != '') {
                $msg .= PIN_MESSAGE . CR;
            }

            // Edit the message, but disable the web preview!
            debug_log('Updating overview:' . CR . 'Chat_ID: ' . $chat_id . CR . 'Message_ID: ' . $message_id);
            editMessageText($message_id, $msg, $keys, $chat_id);
        }

        // Triggered from user or cronjob?
        if (!empty($update['callback_query']['id'])) {
            // Send no active raids message to the user.
            $msg = getTranslation('no_active_raids');

            // Edit the message, but disable the web preview!
            edit_message($update, $msg, $keys);

            // Answer the callback.
            answerCallbackQuery($update['callback_query']['id'], $msg);
        }
    
        // Exit here.
        exit;
    }

    // Share or refresh each chat.
    foreach ($chats_active as $row) {
        $current = $row['chat_id'];

        // Are any raids shared?
        if ($previous == "FIRST_RUN" && $current == "LAST_RUN") {
            // Send no active raids message to the user.
            $msg = getTranslation('no_active_raids_shared');

            // Edit the message, but disable the web preview!
            edit_message($update, $msg, $keys);

            // Answer the callback.
            answerCallbackQuery($update['callback_query']['id'], $msg);
        }

        // Send message if not first run and previous not current
        if ($previous !== 'FIRST_RUN' && $previous !== $current) {
            // Add keys.
	    $keys = array();
        
            //Add custom message from the config.	
            if (PIN_MESSAGE != '') {
                $msg .= PIN_MESSAGE . CR;
            }

            // Share or refresh?
            if ($action == 'share') {
                if ($chat_id == 0) {
                    // Make sure it's not already shared
                    $rs = my_query(
                        "
                        SELECT    COUNT(*)
                        FROM      overview
                        WHERE      chat_id = '{$previous}'
                        "
                    );

                    $row = $rs->fetch_row();

                    if (empty($row['0'])) {
                        // Not shared yet - Share button
                        $keys[] = [
                            [
                                'text'          => getTranslation('share_with') . ' ' . $chat_obj['result']['title'],
                                'callback_data' => '0:overview_share:' . $previous
                            ]
                        ];
                    } else {
                        // Already shared - refresh button
                        $keys[] = [
                            [
                                'text'          => EMOJI_REFRESH,
                                'callback_data' => '0:overview_refresh:' . $previous
                            ]
                        ];
                    }

                    // Send the message, but disable the web preview!
                    send_message($update['callback_query']['message']['chat']['id'], $msg, $keys, ['disable_web_page_preview' => 'true']);

                    // Set the callback message and keys
                    $callback_keys = array();
                    $callback_keys = [];
                    $callback_msg = '<b>' . getTranslation('list_all_overviews') . ':</b>';

                    // Edit the message.
                    edit_message($update, $callback_msg, $callback_keys);

                    // Answer the callback.
                    answerCallbackQuery($update['callback_query']['id'], 'OK');
                } else {
                    // Shared overview
                    $keys = [];

                    // Set callback message string.
                    $msg_callback = getTranslation('successfully_shared');

                    // Edit the message, but disable the web preview!
                    edit_message($update, $msg_callback, $keys, ['disable_web_page_preview' => 'true']);

                    // Answer the callback.
                    answerCallbackQuery($update['callback_query']['id'], $msg_callback);

                    // Send the message, but disable the web preview!
                    send_message($chat_id, $msg, $keys, ['disable_web_page_preview' => 'true']);
                }
	    } else {
                // Refresh overview messages.
                $keys = [];

                // Get active overviews 
                $rs = my_query(
                    "
                    SELECT    message_id
                    FROM      overview
                    WHERE      chat_id = '{$previous}'
                    "
                );

                // Edit text for all messages, but disable the web preview!
                while ($row_msg_id = $rs->fetch_assoc()) {
                    // Set message_id.
                    $message_id = $row_msg_id['message_id'];
                    debug_log('Updating overview:' . CR . 'Chat_ID: ' . $previous . CR . 'Message_ID: ' . $message_id);
                    editMessageText($message_id, $msg, $keys, $previous, ['disable_web_page_preview' => 'true']);
                }

                // Triggered from user or cronjob?
                if (!empty($update['callback_query']['id'])) {
                    // Answer the callback.
                    answerCallbackQuery($update['callback_query']['id'], 'OK');
                }
            }
        }

        // End if last run
        if ($current == 'LAST_RUN') {
            break;
        }

        // Continue with next if previous and current raid id are equal
        if ($previous_raid == $row['raid_id']) {
            continue;
        }

        // Create message for each raid_id
        if($previous !== $current) {
            // Get info about chat for username.
            debug_log('Getting chat object for chat_id: ' . $row['chat_id']);
            $chat_obj = get_chat($row['chat_id']);
            $chat_username = '';

            // Set username if available.
            if ($chat_obj['ok'] == 'true' && isset($chat_obj['result']['username'])) {
                $chat_username = $chat_obj['result']['username'];
                debug_log('Username of the chat: ' . $chat_obj['result']['username']);
            }

            $msg = '<b>' . getTranslation('raid_overview_for_chat') . ' ' . $chat_obj['result']['title'] . ' ' . getTranslation('from') . ' '. unix2tz(time(), $tz, 'H:i') . '</b>' .  CR . CR;
        }

        // Set variables for easier message building.
        $raid_id = $row['raid_id'];
        $pokemon = $raids_active[$raid_id]['pokemon'];
        $pokemon = get_local_pokemon_name($pokemon);
        $gym = $raids_active[$raid_id]['gym_name'];
        $now = $raids_active[$raid_id]['ts_now'];
        $tz = $raids_active[$raid_id]['timezone'];
        $start_time = $raids_active[$raid_id]['ts_start'];
        $end_time = $raids_active[$raid_id]['ts_end'];
        $time_left = floor($raids_active[$raid_id]['t_left'] / 60);

        // Build message and add each gym in this format - link gym_name to raid poll chat_id + message_id if possible
        /* Example:
         * Raid Overview from 18:18h
         *
         * Train Station Gym
         * Raikou - still 0:24h
         *
         * Bus Station Gym
         * Level 5 Egg opens up 18:41h
        */
        // Gym name.
        $msg .= !empty($chat_username) ? '<a href="https://t.me/' . $chat_username . '/' . $row['message_id'] . '">' . htmlspecialchars($gym) . '</a>' : $gym;
        $msg .= CR;

        // Raid has not started yet - adjust time left message
        if ($now < $start_time) {
            // Now
            $week_now = date('W', $now);
            $year_now = date('Y', $now);

            // Start
            $week_start = date('W', $start_time);
            $weekday_start = date('N', $start_time);
            $day_start = date('j', $start_time);
            $month_start = date('m', $start_time);
            $year_start = date('Y', $start_time);
            $raid_day = getTranslation('weekday_' . $weekday_start);
            $raid_month = getTranslation('month_' . $month_start);

            // Days until the raid starts
            $date_now = new DateTime(date('Y-m-d', $now));
            $date_raid = new DateTime(date('Y-m-d', $start_time));
            $days_to_raid = $date_raid->diff($date_now)->format("%a");

            // Is the raid in the same week?
            if($week_now == $week_start && $date_now == $date_raid) {
                // Output: Raid egg opens up 17:00
                $msg .= $pokemon . ' — <b>' . getTranslation('raid_egg_opens') . ' ' . unix2tz($start_time, $tz) . '</b>' . CR;
            } else {
                if($days_to_raid > 7) {
                    // Output: Raid egg opens on Friday, 13 April (2018)
                    $msg .= $pokemon . ' — <b>' . getTranslation('raid_egg_opens_day') . ' ' .  $raid_day . ', ' . $day_start . ' ' . $raid_month . (($year_start > $year_now) ? $year_start : '');
                } else {
                    // Output: Raid egg opens on Friday
                    $msg .= $pokemon . ' — <b>' . getTranslation('raid_egg_opens_day') . ' ' .  $raid_day;
                }
                // Adds 'at 17:00' to the output.
                $msg .= ' ' . getTranslation('raid_egg_opens_at') . ' ' . unix2tz($start_time, $tz) . '</b>' . CR;
            }
        // Raid has started already
        } else {
            // Add time left message.
            $msg .= $pokemon . ' — <b>' . getTranslation('still') . ' ' . floor($time_left / 60) . ':' . str_pad($time_left % 60, 2, '0', STR_PAD_LEFT) . 'h</b>' . CR;
        }

        // Count attendances
        $rs_att = my_query(
            "
            SELECT          count(attend_time)          AS count,
                            sum(team = 'mystic')        AS count_mystic,
                            sum(team = 'valor')         AS count_valor,
                            sum(team = 'instinct')      AS count_instinct,
                            sum(team IS NULL)           AS count_no_team,
                            sum(extra_mystic)           AS extra_mystic,
                            sum(extra_valor)            AS extra_valor,
                            sum(extra_instinct)         AS extra_instinct
            FROM            attendance
              WHERE         raid_id = {$raid_id}
                AND         attend_time IS NOT NULL
                AND         raid_done != 1
                AND         cancel != 1
            "
        );

        $att = $rs_att->fetch_assoc();

        // Add to message.
        if ($att['count'] > 0) {
            $msg .= EMOJI_GROUP . '<b> ' . ($att['count'] + $att['extra_mystic'] + $att['extra_valor'] + $att['extra_instinct']) . '</b> — ';
            $msg .= ((($att['count_mystic'] + $att['extra_mystic']) > 0) ? TEAM_B . ($att['count_mystic'] + $att['extra_mystic']) . '  ' : '');
            $msg .= ((($att['count_valor'] + $att['extra_valor']) > 0) ? TEAM_R . ($att['count_valor'] + $att['extra_valor']) . '  ' : '');
            $msg .= ((($att['count_instinct'] + $att['extra_instinct']) > 0) ? TEAM_Y . ($att['count_instinct'] + $att['extra_instinct']) . '  ' : '');
            $msg .= (($att['count_no_team'] > 0) ? TEAM_UNKNOWN . $att['count_no_team'] : '');
            $msg .= CR;
        }

        // Add CR to message now since we don't know if attendances got added or not
        $msg .= CR;

        // Prepare next iteration
        $previous = $current;
        $previous_raid = $row['raid_id'];
    }
}
/**
 * Convert unix timestamp to time string by timezone settings.
 * @param $unix
 * @param $tz
 * @param string $format
 * @return bool|string
 */
function unix2tz($unix, $tz, $format = 'H:i')
{
    // Unix timestamp is required.
    if (!empty($unix)) {
        // Create dateTime object.
        $dt = new DateTime('@' . $unix);

        // Set the timezone.
        $dt->setTimeZone(new DateTimeZone($tz));

        // Return formatted time.
        return $dt->format($format);

    } else {
        return false;
    }
}

/**
 * Delete raid.
 * @param $raid_id
 */
function delete_raid($raid_id)
{
    global $db;

    // Delete telegram messages for raid.
    $rs = my_query(
        "
        SELECT    *
            FROM      cleanup_raids
            WHERE     raid_id = '{$raid_id}'
              AND     chat_id <> 0
        "
    );

    // Counter
    $counter = 0;

    // Delete every telegram message
    while ($row = $rs->fetch_assoc()) {
        // Delete telegram message.
        debug_log('Deleting telegram message ' . $row['message_id'] . ' from chat ' . $row['chat_id'] . ' for raid ' . $row['raid_id']);
        delete_message($row['chat_id'], $row['message_id']);
        $counter = $counter + 1;
    }

    // Nothing to delete on telegram.
    if ($counter == 0) {
        debug_log('Raid with ID ' . $raid_id . ' was not found in the cleanup table! Skipping deletion of telegram messages!');
    }

    // Delete raid from cleanup table.
    debug_log('Deleting raid ' . $raid_id . ' from the cleanup table:');
    $rs_cleanup = my_query(
        "
        DELETE FROM   cleanup_raids
        WHERE   raid_id = '{$raid_id}' 
           OR   cleaned = '{$raid_id}'
        "
    );

    // Delete raid from attendance table.
    debug_log('Deleting raid ' . $raid_id . ' from the attendance table:');
    $rs_attendance = my_query(
        "
        DELETE FROM   attendance 
        WHERE  raid_id = '{$raid_id}'
        "
    );

    // Delete raid from raid table.
    debug_log('Deleting raid ' . $raid_id . ' from the raid table:');
    $rs_raid = my_query(
        "
        DELETE FROM   raids 
        WHERE   id = '{$raid_id}'
        "
    );
}

/**
 * Show raid poll.
 * @param $raid
 * @return string
 */
function show_raid_poll($raid)
{
    // Init empty message string.
    $msg = '';

    // Display gym details.
    if ($raid['gym_name'] || $raid['gym_team']) {
        // Add gym name to message.
        if ($raid['gym_name']) {
            $msg .= getTranslation('gym') . ': <b>' . $raid['gym_name'] . '</b>';
        }

        // Add team to message.
        if ($raid['gym_team']) {
            $msg .= ' ' . $GLOBALS['teams'][$raid['gym_team']];
        }

        $msg .= CR;
    }

    // Add google maps link to message.
    if (!empty($raid['address'])) {
        $msg .= '<a href="https://maps.google.com/?daddr=' . $raid['lat'] . ',' . $raid['lon'] . '">' . $raid['address'] . '</a>' . CR;
    } else {
	$msg .= '<a href="http://maps.google.com/maps?q=' . $raid['lat'] . ',' . $raid['lon'] . '">http://maps.google.com/maps?q=' . $raid['lat'] . ',' . $raid['lon'] . '</a>' . CR;
    }

    // Display raid boss name.
    $msg .= getTranslation('raid_boss') . ': <b>' . get_local_pokemon_name($raid['pokemon']) . '</b>';

    // Display raid boss weather.
    $pokemon_weather = get_pokemon_weather($raid['pokemon']);
    $msg .= ($pokemon_weather != 0) ? (' ' . get_weather_icons($pokemon_weather)) : '';
    $msg .= CR;

    // Display raid boss CP values.
    $pokemon_cp = get_formatted_pokemon_cp($raid['pokemon']);
    $msg .= (!empty($pokemon_cp)) ? ($pokemon_cp . CR) : ''; 

    // Display time left.
    $time_left = floor($raid['t_left'] / 60);
    if ( strpos(str_pad($time_left % 60, 2, '0', STR_PAD_LEFT) , '-' ) !== false ) {
        $tl_msg = '<b>' . getTranslation('raid_done') . '</b>' . CR;
    } else {
	// Replace $time_left with $tl_msg too
        $tl_msg = ' — <b>' . getTranslation('still') . ' ' . floor($time_left / 60) . ':' . str_pad($time_left % 60, 2, '0', STR_PAD_LEFT) . 'h</b>' . CR;
    }

    // Raid has not started yet - adjust time left message
    if ($raid['ts_now'] < $raid['ts_start']) {
        // Now
	$week_now = date('W', $raid['ts_now']);
	$year_now = date('Y', $raid['ts_now']);

        // Start
	$week_start = date('W', $raid['ts_start']);
	$weekday_start = date('N', $raid['ts_start']);
	$day_start = date('j', $raid['ts_start']);
	$month_start = date('m', $raid['ts_start']);
	$year_start = date('Y', $raid['ts_start']);
        $raid_day = getTranslation('weekday_' . $weekday_start);
        $raid_month = getTranslation('month_' . $month_start);

        // Days until the raid starts
        $date_now = new DateTime(date('Y-m-d', $raid['ts_now']));
        $date_raid = new DateTime(date('Y-m-d', $raid['ts_start']));
        $days_to_raid = $date_raid->diff($date_now)->format("%a");

        // Is the raid in the same week?
        if($week_now == $week_start && $date_now == $date_raid) {
            // Output: Raid egg opens up 17:00
            $msg .= '<b>' . getTranslation('raid_egg_opens') . ' ' . unix2tz($raid['ts_start'], $raid['timezone']) . '</b>' . CR;
        } else {
            if($days_to_raid > 7) {
                // Output: Raid egg opens on Friday, 13 April (2018)
                $msg .= '<b>' . getTranslation('raid_egg_opens_day') . ' ' .  $raid_day . ', ' . $day_start . ' ' . $raid_month . (($year_start > $year_now) ? $year_start : '');
            } else {
                // Output: Raid egg opens on Friday
                $msg .= '<b>' . getTranslation('raid_egg_opens_day') . ' ' .  $raid_day;
            }
            // Adds 'at 17:00' to the output.
            $msg .= ' ' . getTranslation('raid_egg_opens_at') . ' ' . unix2tz($raid['ts_start'], $raid['timezone']) . '</b>' . CR;
        }

    // Raid has started and active or already ended
    } else {

        // Add raid is done message.
        if ($time_left < 0) {
            $msg .= $tl_msg;

        // Add time left message.
        } else {
            $msg .= getTranslation('raid_until') . ' ' . unix2tz($raid['ts_end'], $raid['timezone']);
	    $msg .= $tl_msg;
        }
    }

    // Add Ex-Raid Message if Pokemon is in Ex-Raid-List.
    $raid_level = get_raid_level($raid['pokemon']);
    if($raid_level == 'X') {
        $msg .= CR . EMOJI_WARN . ' <b>' . getTranslation('exraid_pass') . '</b> ' . EMOJI_WARN . CR;
    }

    // Get counts and sums for the raid
    // 1 - Grouped by attend_time
    $rs_cnt = my_query(
        "
        SELECT DISTINCT UNIX_TIMESTAMP(attend_time) AS ts_att,
                        count(attend_time)          AS count,
                        sum(team = 'mystic')        AS count_mystic,
                        sum(team = 'valor')         AS count_valor,
                        sum(team = 'instinct')      AS count_instinct,
                        sum(team IS NULL)           AS count_no_team,
                        sum(extra_mystic)           AS extra_mystic,
                        sum(extra_valor)            AS extra_valor,
                        sum(extra_instinct)         AS extra_instinct,
                        sum(IF(late = '1', (late = '1') + extra_mystic + extra_valor + extra_instinct, 0)) AS count_late,
                        sum(pokemon = '0')                   AS count_any_pokemon,
                        sum(pokemon = '{$raid['pokemon']}')  AS count_raid_pokemon,
                        attend_time
        FROM            attendance
          WHERE         raid_id = {$raid['id']}
            AND         attend_time IS NOT NULL
            AND         raid_done != 1
            AND         cancel != 1
          GROUP BY      attend_time
          ORDER BY      attend_time, pokemon
        "
    );

    // Init empty count array and count sum.
    $cnt = array();
    $cnt_all = 0;
    $cnt_latewait = 0;

    while ($cnt_row = $rs_cnt->fetch_assoc()) {
        $cnt[$cnt_row['ts_att']] = $cnt_row;
        $cnt_all = $cnt_all + $cnt_row['count'];
        $cnt_latewait = $cnt_latewait + $cnt_row['count_late'];
    }

    // Write to log.
    debug_log($cnt);

    // Add no attendance found message.
    if ($cnt_all > 0) {
        // Get counts and sums for the raid
        // 2 - Grouped by attend_time and pokemon
        $rs_cnt_pokemon = my_query(
            "
            SELECT DISTINCT UNIX_TIMESTAMP(attend_time) AS ts_att,
                            count(attend_time)          AS count,
                            sum(team = 'mystic')        AS count_mystic,
                            sum(team = 'valor')         AS count_valor,
                            sum(team = 'instinct')      AS count_instinct,
                            sum(team IS NULL)           AS count_no_team,
                            sum(extra_mystic)           AS extra_mystic,
                            sum(extra_valor)            AS extra_valor,
                            sum(extra_instinct)         AS extra_instinct,
                            sum(IF(late = '1', (late = '1') + extra_mystic + extra_valor + extra_instinct, 0)) AS count_late,
                            sum(pokemon = '0')                   AS count_any_pokemon,
                            sum(pokemon = '{$raid['pokemon']}')  AS count_raid_pokemon,
                            attend_time,
                            pokemon
            FROM            attendance
              WHERE         raid_id = {$raid['id']}
                AND         attend_time IS NOT NULL
                AND         raid_done != 1
                AND         cancel != 1
              GROUP BY      attend_time, pokemon
              ORDER BY      attend_time, pokemon
            "
        );

        // Init empty count array and count sum.
        $cnt_pokemon = array();

        while ($cnt_rowpoke = $rs_cnt_pokemon->fetch_assoc()) {
            $cnt_pokemon[$cnt_rowpoke['ts_att'] . '_' . $cnt_rowpoke['pokemon']] = $cnt_rowpoke;
        }

        // Write to log.
        debug_log($cnt_pokemon);

        // Get attendance for this raid.
        $rs_att = my_query(
            "
            SELECT      attendance.*,
                        users.name,
                        users.level,
                        UNIX_TIMESTAMP(attend_time) AS ts_att
            FROM        attendance
            LEFT JOIN   users
            ON          attendance.user_id = users.user_id
              WHERE     raid_id = {$raid['id']}
                AND     raid_done != 1
                AND     cancel != 1
              ORDER BY  attend_time,
                        pokemon,
                        team,
                        arrived
            "
        );

        // Init previous attend time and pokemon
        $previous_att_time = 'FIRST_RUN';
        $previous_pokemon = 'FIRST_RUN';

        // For each attendance.
        while ($row = $rs_att->fetch_assoc()) {
            // Set current attend time and pokemon
            $current_att_time = $row['ts_att'];
            $current_pokemon = $row['pokemon'];

            // Add hint for late attendances.
            if(RAID_LATE_MSG && $previous_att_time == 'FIRST_RUN' && $cnt_latewait > 0) {
                $late_wait_msg = str_replace('RAID_LATE_TIME', RAID_LATE_TIME, getTranslation('late_participants_wait'));
                $msg .= CR . EMOJI_LATE . '<i>' . getTranslation('late_participants') . ' ' . $late_wait_msg . '</i>' . CR;
            }

            // Add section/header for time
            if($previous_att_time != $current_att_time) {
                // Add to message.
                $count_att_time_extrapeople = $cnt[$current_att_time]['extra_mystic'] + $cnt[$current_att_time]['extra_valor'] + $cnt[$current_att_time]['extra_instinct'];
                $msg .= CR . '<b>' . (($current_att_time == 0) ? (getTranslation('anytime')) : (unix2tz($current_att_time, $raid['timezone']))) . '</b>' . ' [' . ($cnt[$current_att_time]['count'] + $count_att_time_extrapeople) . ']';

                // Add attendance counts by team.
                if ($cnt[$current_att_time]['count'] > 0) {
                    // Attendance counts by team.
                    $count_mystic = $cnt[$current_att_time]['count_mystic'] + $cnt[$current_att_time]['extra_mystic'];
                    $count_valor = $cnt[$current_att_time]['count_valor'] + $cnt[$current_att_time]['extra_valor'];
                    $count_instinct = $cnt[$current_att_time]['count_instinct'] + $cnt[$current_att_time]['extra_instinct'];
                    $count_late = $cnt[$current_att_time]['count_late'];

                    // Add to message.
                    $msg .= ' — ';
                    $msg .= (($count_mystic > 0) ? TEAM_B . $count_mystic . '  ' : '');
                    $msg .= (($count_valor > 0) ? TEAM_R . $count_valor . '  ' : '');
                    $msg .= (($count_instinct > 0) ? TEAM_Y . $count_instinct . '  ' : '');
                    $msg .= (($cnt[$current_att_time]['count_no_team'] > 0) ? TEAM_UNKNOWN . $cnt[$current_att_time]['count_no_team'] . '  ' : '');
                    $msg .= (($count_late > 0) ? EMOJI_LATE . $count_late . '  ' : '');
                }
                $msg .= CR;
            }

            // Add section/header for pokemon
            if($previous_pokemon != $current_pokemon || $previous_att_time != $current_att_time) {
                // Get counts for pokemons
                $count_all = $cnt[$current_att_time]['count'];
                $count_any_pokemon = $cnt[$current_att_time]['count_any_pokemon'];
                $count_raid_pokemon = $cnt[$current_att_time]['count_raid_pokemon'];

                // Show attendances when multiple pokemon are selected, unless all attending users voted for the raid boss + any pokemon
                if($count_all != ($count_any_pokemon + $count_raid_pokemon)) {
                    // Add pokemon name.
                    $msg .= ($current_pokemon == 0) ? ('<b>' . getTranslation('any_pokemon') . '</b>') : ('<b>' . get_local_pokemon_name($current_pokemon) . '</b>');

                    // Attendance counts by team.
                    $current_att_time_poke = $cnt_pokemon[$current_att_time . '_' . $current_pokemon];
                    $count_att_time_poke_extrapeople = $current_att_time_poke['extra_mystic'] + $current_att_time_poke['extra_valor'] + $current_att_time_poke['extra_instinct'];
                    $poke_count_mystic = $current_att_time_poke['count_mystic'] + $current_att_time_poke['extra_mystic'];
                    $poke_count_valor = $current_att_time_poke['count_valor'] + $current_att_time_poke['extra_valor'];
                    $poke_count_instinct = $current_att_time_poke['count_instinct'] + $current_att_time_poke['extra_instinct'];
                    $poke_count_late = $current_att_time_poke['count_late'];

                    // Add to message.
                    $msg .= ' [' . ($current_att_time_poke['count'] + $count_att_time_poke_extrapeople) . '] — ';
                    $msg .= (($poke_count_mystic > 0) ? TEAM_B . $poke_count_mystic . '  ' : '');
                    $msg .= (($poke_count_valor > 0) ? TEAM_R . $poke_count_valor . '  ' : '');
                    $msg .= (($poke_count_instinct > 0) ? TEAM_Y . $poke_count_instinct . '  ' : '');
                    $msg .= (($current_att_time_poke['count_no_team'] > 0) ? TEAM_UNKNOWN . ($current_att_time_poke['count_no_team']) : '');
                    $msg .= (($poke_count_late > 0) ? EMOJI_LATE . $poke_count_late . '  ' : '');
                    $msg .= CR;
                }
            }

            // Add users: TEAM -- LEVEL -- NAME -- ARRIVED -- EXTRAPEOPLE
            $msg .= ($row['arrived']) ? (EMOJI_HERE . ' ') : (($row['late']) ? (EMOJI_LATE . ' ') : '└ ');
            $msg .= ($row['team'] === NULL) ? ($GLOBALS['teams']['unknown'] . ' ') : ($GLOBALS['teams'][$row['team']] . ' ');
            $msg .= ($row['level'] == 0) ? ('<b>00</b> ') : (($row['level'] < 10) ? ('<b>0' . $row['level'] . '</b> ') : ('<b>' . $row['level'] . '</b> '));
            $msg .= '<a href="tg://user?id=' . $row['user_id'] . '">' . htmlspecialchars($row['name']) . '</a> ';
            $msg .= ($row['extra_mystic']) ? ('+' . $row['extra_mystic'] . TEAM_B . ' ') : '';
            $msg .= ($row['extra_valor']) ? ('+' . $row['extra_valor'] . TEAM_R . ' ') : '';
            $msg .= ($row['extra_instinct']) ? ('+' . $row['extra_instinct'] . TEAM_Y . ' ') : '';
            $msg .= CR;

            // Prepare next result
            $previous_att_time = $current_att_time;
            $previous_pokemon = $current_pokemon; 
        }
    }

    // Get sums canceled/done for the raid
    $rs_cnt_cancel_done = my_query(
        "
        SELECT DISTINCT sum(raid_done = '1')   AS count_done,
                        sum(cancel = '1')      AS count_cancel,
                        sum(extra_mystic)           AS extra_mystic,
                        sum(extra_valor)            AS extra_valor,
                        sum(extra_instinct)         AS extra_instinct
        FROM            attendance
          WHERE         raid_id = {$raid['id']}
            AND         (raid_done = 1
                        OR cancel = 1)
          GROUP BY      raid_done
          ORDER BY      raid_done
        "
    );

    // Init empty count array and count sum.
    $cnt_cancel_done = array();

    while ($cnt_row_cancel_done = $rs_cnt_cancel_done->fetch_assoc()) {
        // Cancel count
        if($cnt_row_cancel_done['count_cancel'] > 0) {
            $cnt_cancel_done['count_cancel'] = $cnt_row_cancel_done['count_cancel'] + $cnt_row_cancel_done['extra_mystic'] + $cnt_row_cancel_done['extra_valor'] + $cnt_row_cancel_done['extra_instinct'];
        }

        // Done count
        if($cnt_row_cancel_done['count_done'] > 0) {
            $cnt_cancel_done['count_done'] = $cnt_row_cancel_done['count_done'] + $cnt_row_cancel_done['extra_mystic'] + $cnt_row_cancel_done['extra_valor'] + $cnt_row_cancel_done['extra_instinct'];
        }
    }
    
    // Set canceled count to avoid undefined index notices.
    if(!isset($cnt_cancel_done['count_cancel'])) {
        $cnt_cancel_done['count_cancel'] = 0;
    }

    // Set done count to avoid undefined index notices.
    if(!isset($cnt_cancel_done['count_done'])) {
        $cnt_cancel_done['count_done'] = 0;
    }

    // Write to log.
    debug_log($cnt_cancel_done);

    // Canceled or done?
    if((isset($cnt_cancel_done['count_cancel']) && $cnt_cancel_done['count_cancel'] > 0) || (isset($cnt_cancel_done['count_done']) && $cnt_cancel_done['count_done'] > 0)) {
        // Get done and canceled attendances
        $rs_att = my_query(
            "
            SELECT      attendance.*,
                        users.name,
                        users.level,
                        UNIX_TIMESTAMP(attend_time) AS ts_att
            FROM        attendance
            LEFT JOIN   users
            ON          attendance.user_id = users.user_id
              WHERE     raid_id = {$raid['id']}
                AND     (raid_done = 1
                        OR cancel = 1)
              ORDER BY  raid_done,
                        attend_time
            "
        );

        // Init cancel_done value.
        $cancel_done = 'CANCEL';

        // For each canceled / done.
        while ($row = $rs_att->fetch_assoc()) {
            // Add section/header for canceled
            if($row['cancel'] == 1 && $cancel_done == 'CANCEL') {
                $msg .= CR . TEAM_CANCEL . ' <b>' . getTranslation('cancel') . ': </b>' . '[' . $cnt_cancel_done['count_cancel'] . ']' . CR;
                $cancel_done = 'DONE';
            }

            // Add section/header for canceled
            if($row['raid_done'] == 1 && $cancel_done == 'CANCEL' || $row['raid_done'] == 1 && $cancel_done == 'DONE') {
                $msg .= CR . TEAM_DONE . ' <b>' . getTranslation('finished') . ': </b>' . '[' . $cnt_cancel_done['count_done'] . ']' . CR;
                $cancel_done = 'END';
            }

            // Add users: TEAM -- LEVEL -- NAME -- CANCELED/DONE -- EXTRAPEOPLE
            $msg .= ($row['team'] === NULL) ? ('└ ' . $GLOBALS['teams']['unknown'] . ' ') : ('└ ' . $GLOBALS['teams'][$row['team']] . ' ');
            $msg .= ($row['level'] == 0) ? ('<b>00</b> ') : (($row['level'] < 10) ? ('<b>0' . $row['level'] . '</b> ') : ('<b>' . $row['level'] . '</b> '));
            $msg .= '<a href="tg://user?id=' . $row['user_id'] . '">' . htmlspecialchars($row['name']) . '</a> ';
            $msg .= ($row['cancel'] == 1) ? ('[' . (($row['ts_att'] == 0) ? (getTranslation('anytime')) : (unix2tz($row['ts_att'], $raid['timezone']))) . '] ') : '';
            $msg .= ($row['raid_done'] == 1) ? ('[' . (($row['ts_att'] == 0) ? (getTranslation('anytime')) : (unix2tz($row['ts_att'], $raid['timezone']))) . '] ') : '';
            $msg .= ($row['extra_mystic']) ? ('+' . $row['extra_mystic'] . TEAM_B . ' ') : '';
            $msg .= ($row['extra_valor']) ? ('+' . $row['extra_valor'] . TEAM_R . ' ') : '';
            $msg .= ($row['extra_instinct']) ? ('+' . $row['extra_instinct'] . TEAM_Y . ' ') : '';
            $msg .= CR;
        }
    } 

    // Add no attendance found message.
    if ($cnt_all + $cnt_cancel_done['count_cancel'] + $cnt_cancel_done['count_done'] == 0) {
        $msg .= CR . getTranslation('no_participants_yet') . CR;
    }

    // Display creator.
    $msg .= ($raid['user_id'] && $raid['name']) ? (CR . getTranslation('created_by') . ': <a href="tg://user?id=' . $raid['user_id'] . '">' . htmlspecialchars($raid['name']) . '</a>') : '';

    // Add update time and raid id to message.
    $msg .= CR . '<i>' . getTranslation('updated') . ': ' . unix2tz(time(), $raid['timezone'], 'H:i:s') . '</i>';
    $msg .= '  ID = ' . $raid['id']; // DO NOT REMOVE! --> NEEDED FOR CLEANUP PREPARATION!

    // Return the message.
    return $msg;
}

/**
 * Show small raid poll.
 * @param $raid
 * @return string
 */
function show_raid_poll_small($raid)
{
    // Left for possible future redesign of small raid poll
    //$time_left = floor($raid['t_left'] / 60);
    //$time_left = 'noch ' . floor($time_left / 60) . ':' . str_pad($time_left % 60, 2, '0', STR_PAD_LEFT);

    // Build message string.
    $msg = '';
    // Pokemon
    if(!empty($raid['pokemon'])) {
        $msg .= '<b>' . get_local_pokemon_name($raid['pokemon']) . '</b> ' . CR;
    }
    // Start time and end time
    if(!empty($raid['ts_start']) && !empty($raid['ts_end'])) {
        // Now
        $week_now = date('W', $raid['ts_now']);
        $year_now = date('Y', $raid['ts_now']);

        // Start
        $week_start = date('W', $raid['ts_start']);
        $weekday_start = date('N', $raid['ts_start']);
        $day_start = date('j', $raid['ts_start']);
        $month_start = date('m', $raid['ts_start']);
        $year_start = date('Y', $raid['ts_start']);
        $raid_day = getTranslation('weekday_' . $weekday_start);
        $raid_month = getTranslation('month_' . $month_start);

        // Days until the raid starts
        $date_now = new DateTime(date('Y-m-d', $raid['ts_now']));
        $date_raid = new DateTime(date('Y-m-d', $raid['ts_start']));
        $days_to_raid = $date_raid->diff($date_now)->format("%a");

        // Is the raid in the same week?
        if($week_now == $week_start && $date_now == $date_raid) {
            // Output: Raid egg opens up 17:00
            $msg .= '<b>' . getTranslation('raid_egg_opens') . ' ' . unix2tz($raid['ts_start'], $raid['timezone']) . '</b>' . CR;
        } else {
            if($days_to_raid > 7) {
                // Output: Raid egg opens on Friday, 13 April (2018)
                $msg .= '<b>' . getTranslation('raid_egg_opens_day') . ' ' .  $raid_day . ', ' . $day_start . ' ' . $raid_month . (($year_start > $year_now) ? $year_start : '');
            } else {
                // Output: Raid egg opens on Friday
                $msg .= '<b>' . getTranslation('raid_egg_opens_day') . ' ' .  $raid_day;
            }
            // Adds 'at 17:00' to the output.
            $msg .= ' ' . getTranslation('raid_egg_opens_at') . ' ' . unix2tz($raid['ts_start'], $raid['timezone']) . '</b>' . CR;
        }
    }
    // Gym Name
    if(!empty($raid['gym_name'])) {
        $msg .= $raid['gym_name'] . CR;
    }

    // Address found.
    if (!empty($raid['address'])) {
        $msg .= '<i>' . $raid['address'] . '</i>' . CR2;
    }

    // Count attendances
    $rs = my_query(
        "
        SELECT          count(attend_time)          AS count,
                        sum(team = 'mystic')        AS count_mystic,
                        sum(team = 'valor')         AS count_valor,
                        sum(team = 'instinct')      AS count_instinct,
                        sum(team IS NULL)           AS count_no_team,
                        sum(extra_mystic)           AS extra_mystic,
                        sum(extra_valor)            AS extra_valor,
                        sum(extra_instinct)         AS extra_instinct
        FROM            attendance
          WHERE         raid_id = {$raid['id']}
            AND         attend_time IS NOT NULL
            AND         raid_done != 1
            AND         cancel != 1
        "
    );

    $row = $rs->fetch_assoc();

    // Add to message.
    if ($row['count'] > 0) {
        // Count by team.
        $count_mystic = $row['count_mystic'] + $row['extra_mystic'];
        $count_valor = $row['count_valor'] + $row['extra_valor'];
        $count_instinct = $row['count_instinct'] + $row['extra_instinct'];

        // Add to message.
        $msg .= EMOJI_GROUP . '<b> ' . ($row['count'] + $row['extra_mystic'] + $row['extra_valor'] + $row['extra_instinct']) . '</b> — ';
        $msg .= (($count_mystic > 0) ? TEAM_B . $count_mystic . '  ' : '');
        $msg .= (($count_valor > 0) ? TEAM_R . $count_valor . '  ' : '');
        $msg .= (($count_instinct > 0) ? TEAM_Y . $count_instinct . '  ' : '');
        $msg .= (($row['count_no_team'] > 0) ? TEAM_UNKNOWN . $row['count_no_team'] : '');
        $msg .= CR;
    } else {
        $msg .= getTranslation('no_participants') . CR;
    }

    return $msg;
}

/**
 * Raid list.
 * @param $update
 */
function raid_list($update)
{
    // Init empty rows array.
    $rows = array();

    // Inline list polls.
    if ($update['inline_query']['query']) {

        $iqq = intval($update['inline_query']['query']);

        // By ID.
        $request = my_query(
            "
            SELECT    *,
			          UNIX_TIMESTAMP(end_time)                        AS ts_end,
			          UNIX_TIMESTAMP(start_time)                      AS ts_start,
			          UNIX_TIMESTAMP(NOW())                           AS ts_now,
			          UNIX_TIMESTAMP(end_time)-UNIX_TIMESTAMP(NOW())  AS t_left
		    FROM      raids
		      WHERE   id = {$iqq}
            "
        );

    } else {
        // By user.
        $request = my_query(
            "
            SELECT      *,
			            UNIX_TIMESTAMP(end_time)                        AS ts_end,
			            UNIX_TIMESTAMP(start_time)                      AS ts_start,
			            UNIX_TIMESTAMP(NOW())                           AS ts_now,
			            UNIX_TIMESTAMP(end_time)-UNIX_TIMESTAMP(NOW())  AS t_left
		    FROM        raids
		      WHERE     user_id = {$update['inline_query']['from']['id']}
		      ORDER BY  id DESC LIMIT 3
            "
        );
    }

    while ($answer = $request->fetch_assoc()) {
        $rows[] = $answer;
    }

    debug_log($rows);
    answerInlineQuery($update['inline_query']['id'], $rows);
}

