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
 * Controlls interactions with the database and output to the user.
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
     * The ouptut handler
     * @var Output
     */
    private $output;

    /**
     * Construct a new controller.
     * @param Database $database The databse instance to use.
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
        $incident_date = $this->db->sanitize($input->incident_date);
        $incident_type = $this->db->sanitize($input->incident_type);
        $notes         = $this->db->sanitize($input->notes);
        $action_taken  = $this->db->sanitize($input->action_taken);
        $world         = $this->db->sanitize($input->world);
        $coord_x       = $this->db->sanitize($input->coord_x, true);
        $coord_y       = $this->db->sanitize($input->coord_y, true);
        $coord_z       = $this->db->sanitize($input->coord_z, true);

        // Verify that we have a user id
        if($user_id === null || $user_id <= 0) {
            throw new InvalidArgumentException("Please provide a user for this incident.");
        }

        // Check if we have an incident date.
        if($incident_date === null || mb_strlen($incident_date) < 6) {
            $incident_date = mb_substr($today, 0, 10);
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
        // Get the username and make sure it isn't empty
        $username = $this->db->sanitize($input->username);
        if (empty($username)) {
            throw new InvalidArgumentException("Username required.");
        }
        
        // See if this user is a duplicate
        $res = $this->db->query("SELECT `user_id` FROM `users` WHERE `username` = '$username'");
        if ($res->num_rows == 1) {
            // Username already in the database
            throw new InvalidArgumentException("User already exits.");
        }
        $res->free();

        // Get the user's data from the post
        $insert = "INSERT INTO `users` (username,modified_date,";
        $values = "VALUES ('{$username}','{$this->getNow()}',";
        
        if ($input->exists('user_uuid')) {
            $insert .= 'uuid,';
            $uuid = pack("H*", mb_ereg_replace('-', '', $input->user_uuid));
            if (strlen($uuid) != 16) {
                throw new InvalidArgumentException("Invalid UUID");
            }
            $values .= "'{$this->db->sanitize($uuid)}',";
        }
        if ($input->exists('rank')) {
            $insert .= 'rank,';
            $values .= $this->db->sanitize($input->rank, true) . ',';
        }// TODO have a way to specify a default rank
        if ($input->exists('relations')) {
            $insert .= 'relations,';
            $values .= "'{$this->db->sanitize($input->relations)}',";
        }
        if ($input->exists('notes')) {
            $insert .= 'notes,';
            $values .= "'{$this->db->sanitize($input->notes)}',";
        }
        if ($input->exists('banned')) {
            $insert .= 'banned,';
            $banned = $input->getBoolean('banned');
            $values .= "{$banned},";
        } else {
            $banned = false;
        }
        if ($input->exists('permanent')) {
            $insert .= 'permanent,';
            $permanent = $input->getBoolean('permanent');
            $values .= "{$permanent},";
        } else {
            $permanent = false;
        }
        
        // Remove the trailing ','
        $insert = substr($insert, 0, -1);
        $values = substr($values, 0, -1);

        // Insert the user
        $player_id = $this->db->insert($insert . ') ' . $values . ')');

        // See if we need to add to the ban history
        if ($banned) {
            $this->updateBanHistory($player_id, $user_id, $banned, $permanent);
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
        if(empty($term) || mb_strlen($term) < 2) {
            throw new InvalidArgumentException("AutoComplete term must be longer than one.");
        }

        $term = $this->db->sanitize($term);

        $res = $this->db->query(
            "SELECT user_id, username FROM users WHERE username LIKE '$term%'",
            'Invalid autocomplete term.'
        );

        while($row = $res->fetch_assoc()){
            $this->output->append(
                array('label'=>$row['username'], 'value'=>$row['user_id'])
            );
        }

        $res->free();

        $this->output->reply();
    }


    /**
     * Performs the provided query and builds a table of users from the results.
     * @param string $query The query to retrieve the data, needs to return the
     * user name, rank, and notes.
     * @param array $headers The table headers and output IDs.
     * @param string $id_key The ID key to use for the table rows.
     */
    public function buildTable($query, $headers = array(), $id_key = 'user_id')
    {
        $this->output->setHTMLMode(true);

        if (empty($headers)) {
            $headers = array(
                'username' => 'Name',
                'incident_date' => 'Last Incident Date',
                'incident_type' => 'Last Incident Type',
                'action_taken'  => 'Last Action Taken'
            );
        }
        $keys   = array_keys($headers);

        $res = $this->db->query($query);

        if($res->num_rows == 0){
            // Nothing found
            $this->output->append("<div>Nothing Found</div>");

        } else {

            // Place the results into the table
            $this->output->append('<table class="list"><thead><tr>');
            foreach ($keys as $key) {
                $this->output->append('<th>' . $this->output->prepareHTML($headers[$key]) . '</th>');
            }
            $this->output->append('</tr></thead><tbody>');

            while($row = $res->fetch_assoc()){
                $this->output->append("<tr id='id-{$row[$id_key]}'>");
                foreach ($keys as $key) {
                    $this->output->append('<td>' . $this->output->prepareHTML($row[$key], true) . '</td>');
                }
                $this->output->append('</tr>');
            }

            $this->output->append("</tbody></table>");
        }

        $res->free();

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
        $query = "SELECT u.user_id, u.username, i.incident_date, i.incident_type, i.action_taken
                FROM users AS u
                LEFT JOIN (
                    SELECT *
                    FROM incident AS q
                    ORDER BY q.incident_date DESC
                ) AS i ON u.user_id = i.user_id
                WHERE u.banned = TRUE
                GROUP BY u.user_id
                ORDER BY i.incident_date DESC";

        $this->buildTable($query);
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
SELECT u.user_id, u.username, i.incident_date, i.incident_type, i.action_taken
FROM incident AS i
LEFT OUTER JOIN
 incident AS i2 ON (i2.user_id = i.user_id AND i.incident_date < i2.incident_date)
LEFT JOIN
 users AS u ON (i.user_id = u.user_id)
WHERE i2.user_id IS NULL
  AND u.banned = FALSE
ORDER BY i.incident_date DESC
SQL;

        $this->buildTable($query);
    }

    /**
     * Retrieves the information for a user.
     * This includes all the users incidents (if any) and their user information.
     * @param FilteredInput $input The input to use for getting the user's id.
     * @throws InvalidArgumentException
     */
    public function retrieveUserData(FilteredInput $input)
    {
        $lookup = $this->db->sanitize($input->lookup, true);

        if($lookup <= 0) {
            // Invalid lookup
            throw new InvalidArgumentException("Invalid user ID.");
        }


        // Get the user
        $this->output->append(
            $this->db->querySingleRow(
                "SELECT * FROM users WHERE user_id = '$lookup'",
                'User not found.'
            ),
            'user'
        );


        // Get the incidents
        $sql = <<<SQL
SELECT i.*, u.username AS moderator
FROM `incident` AS i
LEFT JOIN `users` AS u ON (i.moderator_id = u.user_id)
WHERE i.user_id = '$lookup'
ORDER BY i.incident_date
SQL;

        $this->db->queryRowsIntoOutput($sql, $this->output, 'incident');


        // Get the ban history
        $sql = <<<SQL
SELECT u.username AS moderator, bh.date, bh.banned, bh.permanent
FROM `ban_history` AS bh
LEFT JOIN `users` AS u ON (bh.moderator_id = u.user_id)
WHERE bh.`user_id` = '$lookup'
ORDER BY bh.`date`
SQL;

        $this->db->queryRowsIntoOutput($sql, $this->output, 'history');

        $this->output->reply();
    }


    /**
     * Searches the text fields in the database for the provided search keyword.
     * @param FilteredInput $input The input to use to get the search keyword.
     * @throws InvalidArgumentException
     */
    public function search(FilteredInput $input)
    {
        $search = $input->search;
        if (empty($search) || mb_strlen($search) < 2) {
            // Searches must contain at least two characters
            throw new InvalidArgumentException("Search string must be longer than one.");
        }
        $this->output->setHTMLMode(true);

        $search = $this->db->sanitize($search);


        // Get users matching the search
        $this->output->append('<h4>Players</h4>');

        $query = <<<SQL
SELECT u.user_id, u.username, u.banned, r.name AS rank, u.relations, u.notes
FROM `users` AS u
LEFT JOIN
  `rank` AS r ON (u.rank = r.rank_id)
WHERE
      u.username LIKE '%$search%'
   OR u.relations LIKE '%$search%'
   OR u.notes LIKE '%$search%'
SQL;

        $headers = array(
            'username'  => 'Name',
            'banned'    => 'Banned',
            'rank'      => 'Rank',
            'relations' => 'Relations',
            'notes'     => 'Notes'
        );

        $this->buildTable($query, $headers);


        // Get incidents matching the search
        $this->output->clear();
        $this->output->append('<h4>Incidents</h4>');

        $query = <<<SQL
SELECT  u.user_id, u.username, i.incident_date, i.incident_type, i.action_taken
FROM `incident` AS i
LEFT JOIN
  `users` AS u ON (i.user_id = u.user_id)
WHERE
      i.notes LIKE '%$search%'
   OR i.incident_type LIKE '%$search%'
   OR i.action_taken LIKE '%$search%'
SQL;

        $headers = array(
            'username'      => 'Player',
            'incident_date' => 'Date',
            'incident_type' => 'Type',
            'action_taken'  => 'Action Taken'
        );

        $this->buildTable($query, $headers);
    }


    /**
     * Updates an exisiting user with new data.
     * @param int $user_id The id of the moderator performing the update.
     * @param FilteredInput $input The input to get the user information from.
     * @throws InvalidArgumentException
     */
    public function updateUser($user_id, FilteredInput $input)
    {
        // Sanitize the inputs
        $player_id = $this->db->sanitize($input->id, true);

        // Verify that we have a valid user id
        if($player_id <= 0) {
            throw new InvalidArgumentException("Invalid user ID.");
        }

        $username = null;
        if ($input->exists('username')) {
            $username = $this->db->sanitize($input->username);
        }
        $rank = $this->db->sanitize($input->rank, true);
        $banned = $input->getBoolean('banned');
        $permanent = $input->getBoolean('permanent');
        $relations = $this->db->sanitize($input->relations);
        $notes = $this->db->sanitize($input->notes);
        $today = $this->getNow();

        // If the user is no longer banned, make sure the permanent flag is unchecked
        if (!$banned && $permanent) {
            $permanent = false;
        }

        // See if we need to update the ban history
        $query = "SELECT * FROM `users` WHERE `users`.`user_id` = $player_id";
        $row = $this->db->querySingleRow($query, "Failed to retrieve incident.");

        if($row['banned'] != $banned || $row['permanent'] != $permanent) {
            $this->updateBanHistory($player_id, $user_id, $banned, $permanent);
        }

        // Perform the udpate
        $query = "UPDATE  `users` SET ";

        if ($username != null && mb_strlen($username) > 0) {
            $query .= "`username` = '$username', ";
        }

        $query .=   "`modified_date` = '$today',
                    `rank` =  '$rank',
                    `relations` =  '$relations',
                    `notes` =  '$notes',
                    `banned` =  '$banned',
                    `permanent` =  '$permanent'
                    WHERE  `users`.`user_id` = $player_id";

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
        $banned = (boolean) $banned;
        $permanent = (boolean) $permanent;

        $today = $this->getNow();

        $this->db->query("INSERT INTO `ban_history` (`user_id`, `moderator_id`, `date`, `banned`, `permanent`)
                VALUES ('$player_id', '$user_id', '$today', '$banned', '$permanent')");
    }


    /**
     * Updates an incident with new data.
     * @param FilteredInput $input The input to use to get the incident data.
     * @throws InvalidArgumentException
     */
    public function updateIncident(FilteredInput $input)
    {
        $id = $this->db->sanitize($input->id, true);

        // Verify that we have an incident id
        if($id <= 0) {
            throw new InvalidArgumentException("Invalid incident ID.");
        }

        $now = $this->getNow();
        $incident_date = $this->db->sanitize($input->incident_date);
        $incident_type = $this->db->sanitize($input->incident_type);
        $notes         = $this->db->sanitize($input->notes);
        $action_taken  = $this->db->sanitize($input->action_taken);
        $world         = $this->db->sanitize($input->world);
        $coord_x       = $this->db->sanitize($input->coord_x, true);
        $coord_y       = $this->db->sanitize($input->coord_y, true);
        $coord_z       = $this->db->sanitize($input->coord_z, true);

        $query = "UPDATE `incident` SET
            `modified_date` = '$now',
            `incident_date` = '$incident_date',
            `incident_type` = '$incident_type',
            `notes` = '$notes',
            `action_taken` = '$action_taken',
            `world` = '$world',
            `coord_x` = '$coord_x',
            `coord_y` = '$coord_y',
            `coord_z` = '$coord_z'
            WHERE  `incident`.`incident_id` = $id";

        $this->db->query($query, 'Failed to update incident.');

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
        // Get the user ID
        $username = $this->db->sanitize($input->username);
        $result = $this->db->query("SELECT user_id FROM `users` WHERE `users`.`username` = '{$username}'");
        
        if ($result->num_rows == 0) {
            // Insert a new user
            $this->addUser(1, $input);
        } else if (empty($input->user_uuid)) {
            // No UUID provided
            $this->output->error('No UUID provided');
        } else {
            // Store the UUID
            $uuid = pack("H*", mb_ereg_replace('-', '', $input->user_uuid));
            if (strlen($uuid) != 16) {
                throw new InvalidArgumentException("Invalid UUID");
            }
            
            $row = $result->fetch_assoc();
            $result->free();

            // Perform the udpate
            $uuid = $this->db->sanitize($uuid);
            $query = "UPDATE `users` SET uuid = '{$uuid}' 
                       WHERE  `users`.`user_id` = {$row['user_id']}";

            $this->db->query($query);

            $this->output->success();
        }
    }

}
