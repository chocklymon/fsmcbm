<?php
/* Copyright (c) 2014 Curtis Oakley
 * http://chockly.org/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Controls interactions with the database and output to the user.
 * @author Curtis Oakley
 */
class Controller
{

    /**
     * The database class.
     * @var Database The database connection.
     */
    private $db;

    /**
     * The output handler
     * @var Output
     */
    private $output;

    public function getUserIdByUsername($username)
    {
        $username = $this->db->sanitize($username);
        $sql = <<<SQL
SELECT u.user_id
  FROM users u
  LEFT JOIN user_aliases ua ON (u.user_id = ua.user_id)
  WHERE ua.username = '{$username}'
SQL;

        $user_row = $this->db->querySingleRow($sql);
        return $user_row['user_id'];
    }

    public function getUserIdByUUID($uuid)
    {
        $uuid = $this->prepareUUID($uuid);
        $sql = <<<SQL
SELECT u.user_id
  FROM users u
  WHERE u.uuid = '{$uuid}'
SQL;
        $user_row = $this->db->querySingleRow($sql);
        return $user_row['user_id'];
    }

    /**
     * Construct a new controller.
     * @param Database $database The database instance to use.
     * @param Output $output The output instance to use.
     */
    public function __construct(Database $database, Output $output)
    {
        $this->db = $database;
        $this->output = $output;
    }

    /**
     * Adds a new incident to the database.
     * @param int $moderator_id The id of the logged in moderator.
     * @param FilteredInput $input The input to get the incident data from.
     * @throws InvalidArgumentException
     */
    public function addIncident($moderator_id, FilteredInput $input)
    {
        $user_id       = $this->db->sanitize($input->user_id, true);
        $today         = $this->getNow();
        $incident_date = $this->db->getDate($input->getTimestamp('incident_date'), false);
        $incident_type = $this->db->sanitize($input->incident_type);
        $notes         = $this->db->sanitize($input->notes);
        $action_taken  = $this->db->sanitize($input->action_taken);
        $world         = $this->db->sanitize($input->world);
        $coord_x       = $this->db->sanitize($input->coord_x, true);
        $coord_y       = $this->db->sanitize($input->coord_y, true);
        $coord_z       = $this->db->sanitize($input->coord_z, true);

        // Verify that we have a user id
        if ($user_id === null || $user_id <= 0) {
            throw new InvalidArgumentException("Please provide a user for this incident.");
        }

        $query = "INSERT INTO `incident` (`user_id`, `moderator_id`, `created_date`, `modified_date`, `incident_date`, `incident_type`, `notes`, `action_taken`, `world`, `coord_x`, `coord_y`, `coord_z`)
            VALUES ('$user_id', '$moderator_id', '$today', '$today', '$incident_date', '$incident_type', '$notes', '$action_taken', '$world', '$coord_x', '$coord_y', '$coord_z')";

        $incident_id = $this->db->insert($query);

        // Return the id
        $this->output->append($incident_id, 'incident_id');
        $this->output->reply();
    }


    /**
     * Adds a new user to the database.
     * @param type $user_id The id of the logged in moderator.
     * @param FilteredInput $input The input to get the user data from.
     * @throws InvalidArgumentException
     */
    public function addUser($user_id, FilteredInput $input)
    {
        // Get the UUID and make sure it isn't empty
        if ($input->existsAndNotEmpty('uuid')) {
            $uuid = $this->prepareUUID($input->uuid);
        } else {
            throw new InvalidArgumentException("UUID required");
        }

        // See if this user is a duplicate
        $res = $this->db->query("SELECT `user_id` FROM `users` WHERE `uuid` = '$uuid'");
        if ($res->num_rows == 1) {
            // UUID already in the database
            throw new InvalidArgumentException("User already exits");
        }
        $res->free();

        // Use the user info from the post to build the insert statement
        $insert = 'INSERT INTO `users` (`uuid`,`modified_date`';
        $values = "VALUES ('{$uuid}','{$this->getNow()}'";

        if ($input->exists('rank')) {
            $insert .= ',`rank`';
            $values .= ',' . $this->db->sanitize($input->rank, true);
        }// TODO have a way to specify a default rank
        if ($input->exists('relations')) {
            $insert .= ',`relations`';
            $values .= ",'{$this->db->sanitize($input->relations)}'";
        }
        if ($input->exists('notes')) {
            $insert .= ',`notes`';
            $values .= ",'{$this->db->sanitize($input->notes)}'";
        }
        if ($input->exists('banned')) {
            $insert .= ',`banned`';
            $banned = $input->getBoolean('banned');
            $values .= ",{$banned}";
        } else {
            // Banned isn't set, assume that new users are not banned
            $banned = false;
        }
        if ($input->exists('permanent')) {
            $insert .= ',`permanent`';
            $permanent = $input->getBoolean('permanent');
            $values .= ",{$permanent}";
        } else {
            $permanent = false;
        }

        // Insert the user
        $sql = $insert . ') ' . $values . ')';
        Log::debug($sql);
        $player_id = $this->db->insert($sql);

        // See if we need to add to the ban history
        if ($banned) {
            $this->updateBanHistory($player_id, $user_id, $banned, $permanent);
        }

        // Add a known username
        if ($input->existsAndNotEmpty('username')) {
            $this->addUserAlias($player_id, $input->username, true);
        }

        // Return the new users ID
        $this->output->append($player_id, 'user_id');
        $this->output->reply();
    }


    /**
     * Finds possible user names to autocomplete a term.
     * @param FilteredInput $input The input to use to get the term data.
     * @throws InvalidArgumentException
     */
    public function autoComplete(FilteredInput $input)
    {
        // Make sure that the term is at least two characters long
        $term = $input->term;
        if (empty($term) || mb_strlen($term) < 2) {
            throw new InvalidArgumentException("AutoComplete term must be longer than one character");
        }


        $uuid_term = $this->prepareUUID($term, false);
        $term = $this->db->sanitize($term);

        // Search by usernames
        $sql = <<<SQL
SELECT `users`.`user_id`, `users`.`uuid`, `user_aliases`.`username`
 FROM `users`
 LEFT JOIN `user_aliases` ON (`users`.`user_id` = `user_aliases`.`user_id`)
 WHERE `user_aliases`.`username` LIKE '{$term}%'
SQL;

        $res = $this->db->query($sql, 'Invalid auto-complete term');
        while ($row = $res->fetch_assoc()) {
            $this->output->append(
                array(
                    'username' => $row['username'],
                    'user_id' => $row['user_id'],
                    'uuid' => $row['uuid']
                )
            );
        }
        $res->free();

        // Search by UUID
        if (strlen($uuid_term) >= 2) {
            $sql = <<<SQL
    SELECT `users`.`user_id`, `users`.`uuid`, `user_aliases`.`username`
     FROM `users`
     LEFT JOIN `user_aliases` ON (`users`.`user_id` = `user_aliases`.`user_id`)
     WHERE `users`.`uuid` LIKE '{$uuid_term}%'
SQL;

            $res = $this->db->query($sql, 'Invalid auto-complete term');
            while ($row = $res->fetch_assoc()) {
                $this->output->append(
                    array(
                        'username' => $row['username'],
                        'user_id' => $row['user_id'],
                        'uuid' => $row['uuid']
                    )
                );
            }
            $res->free();
        }

        $this->output->reply();
    }
    

    /**
     * Delete an incident.
     * @param FilteredInput $input The input to use to get the incident ID
     */
    public function deleteIncident(FilteredInput $input)
    {
        $incident_id = $this->db->sanitize($input->incident_id, true);

        if ($incident_id == 0) {
            $this->output->error("Invalid Incident ID");
        } else {
            $sql = "DELETE FROM `incident` WHERE `incident_id` = $incident_id";// TODO have a deleted flag?
            $this->db->query($sql);
            $this->output->success();
        }
    }


    /**
     * Retrieves a list of all banned users.
     */
    public function getBans()
    {
        $query = <<<SQL
SELECT u.user_id, u.uuid, ua.username, i.incident_date, i.incident_type, i.action_taken
 FROM users AS u
 LEFT JOIN (
    SELECT *
    FROM incident AS q
    ORDER BY q.incident_date DESC
 ) AS i ON u.user_id = i.user_id
 LEFT JOIN
    user_aliases AS ua ON (ua.user_id = u.user_id)
 WHERE u.banned = TRUE
   AND ua.active = TRUE
 GROUP BY u.user_id
 ORDER BY i.incident_date DESC
SQL;

        $this->db->queryRowsIntoOutput($query, $this->output);
        $this->output->reply();
    }

    /**
     * Gets the current time as a string ready for insertion into a MySQL datetime
     * field (Y-m-d H:i:s).
     * @return string The current time as a string.
     */
    public function getNow()
    {
        return $this->db->getDate();
    }

    /**
     * Gets all the users on the watchlist.
     * The watchlist is defined as a user that isn't banned, but has an incident
     * attached to them.
     */
    public function getWatchlist()
    {
        $query = <<<SQL
SELECT u.user_id, u.uuid, ua.username, i.incident_date, i.incident_type, i.action_taken
FROM incident AS i
LEFT OUTER JOIN
 incident AS i2 ON (i2.user_id = i.user_id AND i.incident_date < i2.incident_date)
LEFT JOIN
 users AS u ON (i.user_id = u.user_id)
LEFT JOIN
 user_aliases AS ua ON (ua.user_id = u.user_id)
WHERE i2.user_id IS NULL
  AND u.banned = FALSE
  AND ua.active = TRUE
ORDER BY i.incident_date DESC
SQL;

        $this->db->queryRowsIntoOutput($query, $this->output);
        $this->output->reply();
    }

    /**
     * Retrieves the information for a user.
     * This includes all the users incidents (if any) and their user information.
     * @param FilteredInput $input The input to use for getting the user's id.
     * @throws InvalidArgumentException
     */
    public function retrieveUserData(FilteredInput $input)
    {
        $user_id = 0;
        if($input->existsAndNotEmpty('uuid')) {
            $user_id = $this->getUserIdByUUID($input->uuid);
        } elseif ($input->existsAndNotEmpty('user_id')) {
            $user_id = $this->db->sanitize($input->user_id, true);
        } elseif ($input->existsAndNotEmpty('username')) {
            $user_id = $this->getUserIdByUsername($input->username);
        }

        if($user_id <= 0) {
            // Invalid lookup
            throw new InvalidArgumentException("Invalid user ID");
        }


        // Get the user
        $user_info = $this->db->querySingleRow(
            "SELECT * FROM users WHERE user_id = '{$user_id}'",
            'User not found.'
        );

        // Get the known usernames
        $aliases = $this->db->queryRows("SELECT username, active FROM user_aliases WHERE user_id = '{$user_id}'");
        foreach($aliases as $alias) {
            $user_info['usernames'][] = array('username' => $alias['username'], 'active' => (bool) $alias['active']);
        }

        // Convert dates to the correct format
        $user_info['modified_date'] = $this->formatDateForResponse($user_info['modified_date']);

        // Convert values to booleans that should be booleans
        $user_info['banned'] = (bool) $user_info['banned'];
        $user_info['permanent'] = (bool) $user_info['permanent'];

        $this->output->append($user_info, 'user');


        // Get the incidents
        $sql = <<<SQL
SELECT i.*, ua.username AS moderator
FROM `incident` AS i
LEFT JOIN
  `user_aliases` AS ua ON (i.moderator_id = ua.user_id)
WHERE i.user_id = '{$user_id}'
  AND ua.active = TRUE
ORDER BY i.incident_date
SQL;

        $incidents = $this->db->queryRows($sql);
        foreach ($incidents as &$incident) {
            // Change all the incident dates to the correct date times string format
            $incident['created_date'] = $this->formatDateForResponse($incident['created_date']);
            $incident['modified_date'] = $this->formatDateForResponse($incident['modified_date']);
            $incident['incident_date'] = $this->formatDateForResponse($incident['incident_date']);
        }
        $this->output->append($incidents, 'incident');


        // Get the ban history
        $sql = <<<SQL
SELECT ua.username AS moderator, bh.date, bh.banned, bh.permanent
FROM `ban_history` AS bh
LEFT JOIN
  `user_aliases` AS ua ON (bh.moderator_id = ua.user_id)
WHERE bh.`user_id` = '{$user_id}'
  AND ua.active = TRUE
ORDER BY bh.`date`
SQL;

        $ban_history = $this->db->queryRows($sql);
        foreach ($ban_history as &$history) {
            // Format the data to the expected data types and string formats
            $history['date'] = $this->formatDateForResponse($history['date']);
            $history['banned'] = (bool) $history['banned'];
            $history['permanent'] = (bool) $history['permanent'];
        }
        $this->output->append($ban_history, 'history');

        $this->output->reply();
    }


    /**
     * Searches the text fields in the database for the provided search keyword.
     * @param FilteredInput $input The input to use to get the search keyword.
     * @throws InvalidArgumentException
     */
    public function search(FilteredInput $input)
    {
        // TODO also search by UUID? (Maybe only if it appears to be a UUID?, or always?)
        $search = $input->search;
        if (empty($search) || mb_strlen($search) < 2) {
            // Searches must contain at least two characters
            throw new InvalidArgumentException("Search string must be longer than one.");
        }

        $search = $this->db->sanitize($search);


        // Get users matching the search
        $query = <<<SQL
SELECT u.user_id, u.uuid, ua.username, u.banned, r.name AS rank, u.relations, u.notes
FROM `users` AS u
LEFT JOIN
  `rank` AS r ON (u.rank = r.rank_id)
LEFT JOIN
  `user_aliases` AS ua ON (u.user_id = ua.user_id)
WHERE
      ua.username LIKE '%$search%'
   OR u.relations LIKE '%$search%'
   OR u.notes LIKE '%$search%'
SQL;
        $this->db->queryRowsIntoOutput($query, $this->output, 'users');

        
        // Get incidents matching the search
        $query = <<<SQL
SELECT  u.user_id, u.uuid, ua.username, i.incident_date, i.incident_type, i.action_taken
FROM `incident` AS i
LEFT JOIN
  `users` AS u ON (i.user_id = u.user_id)
LEFT JOIN
  `user_aliases` AS ua ON (u.user_id = ua.user_id)
WHERE
      i.notes LIKE '%$search%'
   OR i.incident_type LIKE '%$search%'
   OR i.action_taken LIKE '%$search%'
SQL;
        $this->db->queryRowsIntoOutput($query, $this->output, 'incidents');
        
        $this->output->reply();
    }


    /**
     * Updates an exisiting user with new data.
     * @param int $user_id The id of the moderator performing the update.
     * @param FilteredInput $input The input to get the user information from.
     * @throws InvalidArgumentException
     */
    public function updateUser($user_id, FilteredInput $input)
    {
        // TODO make an add username function and endpoint

        // Sanitize the inputs
        $player_id = $this->db->sanitize($input->user_id, true);

        // Verify that we have a valid user id
        if($player_id <= 0) {
            throw new InvalidArgumentException("Invalid user ID");
        }

        $rank = $this->db->sanitize($input->rank, true);
        $banned = $input->getBoolean('banned');
        $permanent = $input->getBoolean('permanent');
        $relations = $this->db->sanitize($input->relations);
        $notes = $this->db->sanitize($input->notes);
        $today = $this->getNow();

        // If the user is no longer banned, make sure the permanent flag is unchecked
        if (!$banned && $permanent) {
            $permanent = 0;
        }

        // See if we need to update the ban history
        $query = "SELECT `banned`, `permanent` FROM `users` WHERE `users`.`user_id` = {$player_id}";
        $row = $this->db->querySingleRow($query, 'Failed to retrieve incident');

        if($row['banned'] != $banned || $row['permanent'] != $permanent) {
            $this->updateBanHistory($player_id, $user_id, $banned, $permanent);
        }

        // Perform the update
        $query = <<<SQL
UPDATE `users` SET
    `modified_date` = '{$today}',
    `rank` = '{$rank}',
    `relations` = '{$relations}',
    `notes` = '{$notes}',
    `banned` = '{$banned}',
    `permanent` = '{$permanent}'
 WHERE `users`.`user_id` = {$player_id}
SQL;

        $this->db->query($query);

        $this->output->success();
    }


    /**
     * Updates the ban history
     * @param int $player_id The ID of the user who's ban history is being updated.
     * @param int $user_id The ID of the logged in user.
     * @param boolean $banned Whether or not the user is banned.
     * @param boolean $permanent Wether or not the user is banned permanently.
     */
    public function updateBanHistory($player_id, $user_id, $banned, $permanent)
    {
        // Be sure the inputs are what the are supposed to be.
        $player_id = (int) $player_id;
        $user_id = (int) $user_id;
        $banned = (int) $banned;
        $permanent = (int) $permanent;

        $today = $this->getNow();

        $this->db->query("INSERT INTO `ban_history` (`user_id`, `moderator_id`, `date`, `banned`, `permanent`)
                VALUES ('{$player_id}', '{$user_id}', '{$today}', '{$banned}', '{$permanent}')");
    }


    /**
     * Updates an incident with new data.
     * @param FilteredInput $input The input to use to get the incident data.
     * @throws InvalidArgumentException
     */
    public function updateIncident(FilteredInput $input)
    {
        $id = $this->db->sanitize($input->incident_id, true);

        // Verify that we have an incident id
        if($id <= 0) {
            throw new InvalidArgumentException("Invalid incident ID");
        }

        $now = $this->getNow();
        $incident_date = $this->db->getDate($input->getTimestamp('incident_date'), false);
        $incident_type = $this->db->sanitize($input->incident_type);
        $notes         = $this->db->sanitize($input->notes);
        $action_taken  = $this->db->sanitize($input->action_taken);
        $world         = $this->db->sanitize($input->world);
        $coord_x       = $this->db->sanitize($input->coord_x, true);
        $coord_y       = $this->db->sanitize($input->coord_y, true);
        $coord_z       = $this->db->sanitize($input->coord_z, true);

        $query = <<<SQL
UPDATE `incident` SET
    `modified_date` = '{$now}',
    `incident_date` = '{$incident_date}',
    `incident_type` = '{$incident_type}',
    `notes` = '{$notes}',
    `action_taken` = '{$action_taken}',
    `world` = '{$world}',
    `coord_x` = '{$coord_x}',
    `coord_y` = '{$coord_y}',
    `coord_z` = '{$coord_z}'
  WHERE `incident`.`incident_id` = {$id}
SQL;

        $this->db->query($query, 'Failed to update incident');

        $this->output->success();
    }

    /**
     * Updates the user's Universally Unique Identifier, or adds a new user
     * if the users doesn't exist.
     * @param FilteredInput $input The input to use to get the user data from.
     * @throws InvalidArgumentException
     */
    public function upsertUserUUID(FilteredInput $input)
    {
        // TODO this should become an upsert user function, where if the UUID exists, it updates the known usernames
        // Get the user ID
        $username = $this->db->sanitize($input->username);
        $result = $this->db->query("SELECT user_id FROM `users` WHERE `users`.`username` = '{$username}'");

        if ($result->num_rows == 0) {
            // Insert a new user
            $this->addUser(1, $input);
        } else if ($input->exists('uuid')) {
            // Store the UUID
            $uuid = $this->prepareUUID($input->uuid);

            $row = $result->fetch_assoc();
            $result->free();

            // Perform the udpate
            $query = "UPDATE `users` SET uuid = '{$uuid}'
                       WHERE  `users`.`user_id` = {$row['user_id']}";

            $this->db->query($query);

            $this->output->success();
        } else {
            // No UUID provided
            throw new InvalidArgumentException("No UUID provided");
        }
    }

    /**
     * Takes a UUID and returns the database ready version.
     * @param $uuid String a Minecraft player UUID.
     * @param bool $check_length If the UUID should be check to make sure it is the proper length, if it fails this
     * check an InvalidArgumentException is thrown.
     * @return String The database ready UUID.
     */
    private function prepareUUID($uuid, $check_length = true)
    {
        $uuid = mb_ereg_replace('[^a-fA-F0-9]', '', $uuid);
        if ($check_length && strlen($uuid) != 32) {
            throw new InvalidArgumentException("Invalid UUID");
        }
        $uuid = strtolower($uuid);
        return $this->db->sanitize($uuid);
    }

    /**
     * Adds a username to the alias list for the user.
     * @param $player_id The user's ID.
     * @param $username The username to add an alias for.
     * @param $active bool If the username we are adding is the active one.
     */
    private function addUserAlias($player_id, $username, $active)
    {
        $username = $this->db->sanitize($username);
        $active = (bool) $active;
        $sql = "INSERT IGNORE INTO `user_aliases` (`user_id`, `username`, `active`) VALUES ({$player_id}, '{$username}', {$active})";
        $this->db->query($sql);
    }

    /**
     * Converts a date string to ISO 8601 format for returning as a response.
     * @param string $date_string The date time string to convert.
     * @return bool|string
     */
    private function formatDateForResponse($date_string)
    {
        return date('c', strtotime($date_string));
    }

}
