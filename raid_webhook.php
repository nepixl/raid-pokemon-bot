<?php

// MAD Webhook by (exilhanseat)[https://github.com/exilhanseat/]
//
// 				Setup
//
// Place raid_webhook.php in your rootdirectory of the Telegramraidbot
//
// Edit row: 221 and set your ChatID
//
// Set your MAD Webhookendpoint to: [raid]www.yourraidbot.de/raid_webhook.php?apikey=telegramapikey
//
// Extend your Telegramraidbottable: 'ALTER TABLE gyms ADD COLUMN external_id VARCHAR(35)'
//
// Copy all external_id's and name from MAD to your Raidbot:
// RM(MAD): 		SELECT CONCAT('''UPDATE GYMS SET external_id = ''',gym_id,''' WHERE gym_name = ''', name, ''';') from gymdetails;
// Monocle(MAD): 	SELECT CONCAT('''UPDATE GYMS SET external_id = ''',external_id,''' WHERE gym_name = ''', name, '';') from forts;
// Insert the output of the upper statement into your Raidbot SQL-TABLE
//
//
require_once (__DIR__ . '/config.php');
require_once (__DIR__ . '/core/class/constants.php');
require_once (__DIR__ . '/core/class/debug.php');
require_once (__DIR__ . '/core/class/functions.php');
require_once (__DIR__ . '/core/class/geo_api.php');
require_once (__DIR__ . '/logic.php');
// Check API Key and get input from telegram
include_once (CORECLASS_PATH . '/apikey.php');

// Get language
include_once (CORECLASS_PATH . '/language.php');

$dbh = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD, array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
));
$dbh->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);

// Establish mysql connection.
$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
$db->set_charset('utf8mb4');
// Write to log.
debug_log('RAID_WEBHOOK()');

$dinput = file_get_contents('php://input');

$raids = json_decode($dinput, true);

$tz = TIMEZONE;
foreach ($raids as $data) {
    // Raid boss name
    $boss = $data['message']['pokemon_id'];

    if ($boss == '0') {
        $boss = '999' . $data['message']['level'];
    }
    try {

        // get pokemon_form
        $query = '
        SELECT pokemon_form
        FROM pokemon
        WHERE
            pokedex_id LIKE :pokedex_id AND
            raid_level != \'0\'
        LIMIT 1
    ';
        $statement = $dbh->prepare($query);
        $statement->bindValue(':pokedex_id', $boss, PDO::PARAM_STR);
        $statement->execute();
        while ($row = $statement->fetch()) {
            $boss = $boss . '-' . $row['pokemon_form'];
        }
    } catch (PDOException $exception) {

        error_log($exception->getMessage());
        $dbh = null;
        exit();
    }

    // Endtime from input
    $endtime = $data['message']['end'];
    $starttime = $data['message']['start'];
    if ($starttime == $endtime) {
        $starttime = time();
    }

    // Team
    $team = $data['message']['team_id'];
    if (! empty($team)) {
        // Switch by team id.
        switch ($team) {
            case (1):
                $team = 'mystic';
                break;
            case (2):
                $team = 'valor';
                break;
            case (3):
                $team = 'instinct';
                break;
            default:
                $team = '';
        }
        // Team id is missing.
    }

    // Escape comma in Raidname
    $gym_external_id = $data['message']['gym_id'];

    $gym_id = 0;
    $address = '';
    try {

        // Update gym name in raid table.
        $query = '
        SELECT id, address
        FROM gyms
        WHERE
            external_id = :external_id
        LIMIT 1
    ';
        $statement = $dbh->prepare($query);
        $statement->bindValue(':external_id', $gym_external_id, PDO::PARAM_STR);
        $statement->execute();
        while ($row = $statement->fetch()) {

            $gym_id = $row['id'];
            $address = $row['address'];
        }
    } catch (PDOException $exception) {

        error_log($exception->getMessage());
        $dbh = null;
        exit();
    }

    /* Remove all unknown gyms */
    if ($gym_id <= 0) {
        exit();
    }

    $start = date("Y-m-d H:i:s", $starttime);
    $end = date("Y-m-d H:i:s", $endtime);

    // Insert new raid or update existing raid/ex-raid?
    $raid_id = raid_duplication_check($gym_id, $start, $end);

    if ($raid_id > 0) {

        // Get current pokemon from database for raid.
        $rs_ex_raid = my_query("
        SELECT    pokemon
            FROM      raids
              WHERE   id = {$raid_id}
        ");

        // Get row.
        $row_ex_raid = $rs_ex_raid->fetch_assoc();
        $poke_name = $row_ex_raid['pokemon'];
        debug_log('Comparing the current pokemon to pokemons from ex-raid list now...');
        debug_log('Current Pokemon in database for this raid: ' . $poke_name);

        // Make sure it's not an Ex-Raid before updating the pokemon.
        $raid_level = get_raid_level($poke_name);
        if ($raid_level == 'X') {
            // Ex-Raid! Update only team in raids table.
            debug_log('Current pokemon is an ex-raid pokemon: ' . $poke_name);
            debug_log('Pokemon "' . $poke_name . '" will NOT be updated to "' . $boss . '"!');
            my_query("
            UPDATE    raids
            SET	      gym_team = '{$db->real_escape_string($team)}'
              WHERE   id = {$raid_id}
            ");
        } else {
            // Update pokemon and team in raids table.
            debug_log('Current pokemon is NOT an ex-raid pokemon: ' . $poke_name);
            debug_log('Pokemon "' . $poke_name . '" will be updated to "' . $boss . '"!');
            my_query("
            UPDATE    raids
            SET       pokemon = '{$db->real_escape_string($boss)}',
		      gym_team = '{$db->real_escape_string($team)}'
              WHERE   id = {$raid_id}
            ");
        }

        // Debug log
        debug_log('Updated raid ID: ' . $raid_id);

        // Get raid data.
        $raid = get_raid($raid_id);

    } else {
        // Build the query.
        $rs = my_query("
        INSERT INTO   raids
        SET           pokemon = '{$db->real_escape_string($boss)}',
		              user_id = '4711',
		              first_seen = DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:00'),
		              start_time = FROM_UNIXTIME({$starttime}),
		              end_time = FROM_UNIXTIME({$endtime}),
		              gym_team = '{$db->real_escape_string($team)}',
		              gym_id = '{$gym_id}',
		              timezone = '{$tz}'
        ");
        // }

        // Get last insert id from db.
        $id = my_insert_id();

        // Write to log.
        debug_log('ID=' . $id);

        // Get raid data.
        $raid = get_raid($id);

        // Set text.
        $text = show_raid_poll($raid);

        // get chat_id
		$chat_id = -08154711CHATID;
         
        // Set keys.
        $keys = keys_vote($raid);

        // Send the message.
        send_message($chat_id, $text, $keys, [
            'reply_to_message_id' => $chat_id,
            'reply_markup' => [
                'selective' => true,
                'one_time_keyboard' => true
            ],
            'disable_web_page_preview' => 'true'
        ]);

        notify_abos($raid);
    }
}
?>
