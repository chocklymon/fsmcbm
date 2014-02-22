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
    
    public function __construct(&$database)
    {
        $this->db = $database;
    }
    
    /**
     * Adds a new incident to the database.
     * Uses the data posted into the page to create the incident.
     * @param int $moderator_id The id of the logged in moderator.
     */
    public function addIncident($moderator_id)
    {
        $user_id       = $this->db->sanitize($_POST['user_id'], true);
        $today         = $this->getNow();
        $incident_date = $this->db->sanitize($_POST['incident_date']);
        $incident_type = $this->db->sanitize($_POST['incident_type']);
        $notes         = $this->db->sanitize($_POST['notes']);
        $action_taken  = $this->db->sanitize($_POST['action_taken']);
        $world         = $this->db->sanitize($_POST['world']);
        $coord_x       = $this->db->sanitize($_POST['coord_x'], true);
        $coord_y       = $this->db->sanitize($_POST['coord_y'], true);
        $coord_z       = $this->db->sanitize($_POST['coord_z'], true);

        // Verify that we have a user id
        if($user_id === null || $user_id <= 0) {
            throw new InvalidArgumentException("Please provide a user for this incident.");
        }

        // Check if we have an incident date.
        if($incident_date === null || strlen($incident_date) < 6) {
            $incident_date = substr($today, 0, 10);
        }

        $query = "INSERT INTO `incident` (`user_id`, `moderator_id`, `created_date`, `modified_date`, `incident_date`, `incident_type`, `notes`, `action_taken`, `world`, `coord_x`, `coord_y`, `coord_z`)
            VALUES ('$user_id', '$moderator_id', '$today', '$today', '$incident_date', '$incident_type', '$notes', '$action_taken', '$world', '$coord_x', '$coord_y', '$coord_z')";

        $incident_id = $this->db->insert($query);

        // Return the id
        Output::append($incident_id, 'incident_id');
        Output::reply();
    }


    /**
     * Adds a new user to the database.
     * User information is gathered from the data posted into this page.
     */
    public function addUser()
    {
        // Make sure that the user name isn't empty
        if (empty($_POST['username'])) {
            throw new InvalidArgumentException("Username required.");
        }

        $username = $this->db->sanitize($_POST['username']);

        // See if this user is a duplicate
        $res = $this->db->query("SELECT `user_id` FROM `users` WHERE `username` = '$username'");
        if ($res->num_rows == 1) {
            // Username already in the database
            throw new InvalidArgumentException("User already exits.");
        }
        $res->free();

        // Get the user's data from the post
        $rank      = $this->db->sanitize($_POST['rank'], true);
        $relations = $this->db->sanitize($_POST['relations']);
        $notes     = $this->db->sanitize($_POST['notes']);
        $banned    = (isset($_POST['banned']) && $_POST['banned'] == 'on') ? '1' : '0';
        $permanent = (isset($_POST['permanent']) && $_POST['permanent'] == 'on') ? '1' : '0';
        $today     = $this->getNow();

        // Insert the user
        $user_id = $this->db->insert(
            "INSERT INTO `users` (`username`, `modified_date`, `rank`, `relations`, `notes`, `banned`, `permanent`)
            VALUES ('$username', '$today', '$rank', '$relations', '$notes', $banned, $permanent)"
        );

        // See if we need to add to the ban history
        if ($banned) {
            $this->updateBanHistory($user_id, $banned, $permanent);
        }

        // Return the new users ID
        Output::append($user_id, 'user_id');
        Output::reply();
    }


    /**
     * Finds possible user names to autocomplete a term provided to this page.
     */
    public function autoComplete()
    {
        // Make sure that the term is at least two characters long
        if(strlen($_GET['term']) < 2) {
            throw new InvalidArgumentException("AutoComplete term must be longer than one.");
        }

        $term = $this->db->sanitize( $_GET['term'] );

        $res = $this->db->query(
            "SELECT user_id, username FROM users WHERE username LIKE '$term%'",
            'Invalid autocomplete term.'
        );

        while($row = $res->fetch_assoc()){
            Output::append(
                array('label'=>$row['username'], 'value'=>$row['user_id'])
            );
        }

        $res->free();

        Output::reply();
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
        Output::setHTMLMode(true);

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
            Output::append("<div>Nothing Found</div>");

        } else {

            // Place the results into the table
            Output::append('<table class="list"><thead><tr>');
            foreach ($keys as $key) {
                Output::append('<th>' . Output::prepareHTML($headers[$key]) . '</th>');
            }
            Output::append('</tr></thead><tbody>');

            while($row = $res->fetch_assoc()){
                Output::append("<tr id='id-{$row[$id_key]}'>");
                foreach ($keys as $key) {
                    Output::append('<td>' . Output::prepareHTML($row[$key], true) . '</td>');
                }
                Output::append('</tr>');
            }

            Output::append("</tbody></table>");
        }

        $res->free();

        Output::reply();
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

        buildTable($query);
    }

    /**
     * Gets name of the user logged into wordpress.
     * @return mixed FALSE if the user is not not logged into wordpress, otherwise 
     * it return an unsanitized string contain the logged in user's name. 
     */
    public function getLoggedInName()
    {
        // Search the wordpress cookies for the logged in user name
        $keys = array_keys($_COOKIE);
        foreach($keys as &$key) {
            if (strncmp("wordpress_logged_in_", $key, 20) === 0) {
                // Extract user name from the cookie
                $value = $_COOKIE[$key];
                return substr($value, 0, strpos($value, "|"));
            }
        }

        return FALSE;
    }


    /**
     * Gets the current time as a string ready for insertion into a MySQL datetime
     * field (Y-m-d H:i:s).
     * @return string The current time as a string.
     */
    public function getNow()
    {
        return date('Y-m-d H:i:s');
    }


    /**
     * Gets the information for the moderator using the ban manager.
     * @return mixed FALSE if the user is not a moderator/admin or is not logged into
     * wordpress, otherwise it return an array where index zero is the user id,
     * index one is the user's rank, and index two is the user's name.
     */
    public function getModeratorInfo()
    {
        $info = FALSE;

        // Get the moderators name from the cookie
        $moderator_name = getLoggedInName();

        if($moderator_name === FALSE) {
            return FALSE;
        }

        $moderator_name = $this->db->sanitize($moderator_name);

        // Request the user id from the database
        $row = $this->db->querySingleRow(
            "SELECT `users`.`user_id`, `rank`.`name` AS rank
             FROM `users`
             LEFT JOIN `rank` ON (`users`.`rank` = `rank`.`rank_id`)
             WHERE `username` = '$moderator_name'",
            'Moderator not found.'
        );

        // Only store the information of admins/moderators
        if($row['rank'] == 'Admin' || $row['rank'] == 'Moderator') {
            $info = array($row['id'], $row['rank'], $moderator_name);
        }

        return $info;
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

        buildTable($query);
    }

    /**
     * Retrieves the information for a user.
     * This includes all the users incidents (if any) and their user information.
     */
    public function retrieveUserData()
    {
        $lookup = $this->db->sanitize($_GET['lookup'], true);

        if($lookup <= 0) {
            // Invalid lookup
            throw new InvalidArgumentException("Invalid user ID.");
        }


        // Get the user
        Output::append(
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

        $this->db->queryRowsIntoOutput($sql, 'incident');


        // Get the ban history
        $sql = <<<SQL
SELECT u.username AS moderator, bh.date, bh.banned, bh.permanent
FROM `ban_history` AS bh
LEFT JOIN `users` AS u ON (bh.moderator_id = u.user_id)
WHERE bh.`user_id` = '$lookup'
ORDER BY bh.`date`
SQL;

        $this->db->queryRowsIntoOutput($sql, 'history');

        Output::reply();
    }


    /**
     * Searches the text fields in the database for the provided search keyword.
     */
    public function search()
    {
        Output::setHTMLMode(true);
        if( strlen($_GET['search']) < 2) {
            // Searches must contain at least two characters
            throw new InvalidArgumentException("Search string must be longer than one.");
        }

        $search = $this->db->sanitize($_GET['search']);


        // Get users matching the search
        Output::append('<h4>Players</h4>');

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

        buildTable($query, $headers);


        // Get incidents matching the search
        Output::clear();
        Output::append('<h4>Incidents</h4>');

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

        buildTable($query, $headers);
    }


    /**
     * Updates an exisiting user with new data.
     * The data is retrieved from the data posted into this page.
     */
    public function updateUser()
    {
        // Sanitize the inputs
        $id = $this->db->sanitize($_POST['id'], true);

        // Verify that we have a valid user id
        if($id <= 0) {
            throw new InvalidArgumentException("Invalid user ID.");
        }

        $username = null;
        if (isset($_POST['username'])) {
            $username = $this->db->sanitize($_POST['username']);
        }
        $rank = $this->db->sanitize($_POST['rank']);
        $banned = $_POST['banned'] == "true";
        $permanent = $_POST['permanent'] == "true";
        $relations = $this->db->sanitize($_POST['relations']);
        $notes = $this->db->sanitize($_POST['notes']);
        $today = getNow();

        // If the user is no longer banned, make sure the permanent flag is unchecked
        if (!$banned && $permanent) {
            $permanent = false;
        }

        // See if we need to update the ban history
        $query = "SELECT * FROM `users` WHERE `users`.`user_id` = $id";
        $row = $this->db->querySingleRow($query, "Failed to retrieve incident.");

        if($row['banned'] != $banned || $row['permanent'] != $permanent) {
            updateBanHistory($id, $banned, $permanent);
        }

        // Perform the udpate
        $query = "UPDATE  `users` SET ";

        if ($username != null && strlen($username) > 0)
            $query .= "`username` = '$username', ";

        $query .=   "`modified_date` = '$today',
                    `rank` =  '$rank',
                    `relations` =  '$relations',
                    `notes` =  '$notes',
                    `banned` =  '$banned',
                    `permanent` =  '$permanent'
                    WHERE  `users`.`user_id` = $id";

        $this->db->query($query);

        Output::success();
    }


    /**
     * Updates the ban history
     * @global int $moderator The ID of the moderator/admin that is logged in.
     * @param int $user_id The ID of the user who's ban history is being updated.
     * @param boolean $banned Whether or not the user is banned.
     * @param boolean $permanent Wether or not the user is banned permanently.
     */
    public function updateBanHistory($user_id, $banned, $permanent)
    {
        global $moderator;

        // Be sure the inputs are what the are supposed to be.
        $user_id = (int) $user_id;
        $banned = (boolean) $banned;
        $permanent = (boolean) $permanent;

        $today = $this->getNow();

        $this->db->query("INSERT INTO `ban_history` (`user_id`, `moderator_id`, `date`, `banned`, `permanent`)
                VALUES ('$user_id', '$moderator', '$today', '$banned', '$permanent')");
    }


    /**
     * Updates an incident with new data.
     * Data is retrieved from the data posted into this page.
     */
    public function updateIncident()
    {
        $id = $this->db->sanitize($_POST['id'], true);

        // Verify that we have an incident id
        if($id <= 0) {
            throw new InvalidArgumentException("Invalid incident ID.");
        }

        $now = getNow();
        $incident_date = $this->db->sanitize($_POST['incident_date']);
        $incident_type = $this->db->sanitize($_POST['incident_type']);
        $notes         = $this->db->sanitize($_POST['notes']);
        $action_taken  = $this->db->sanitize($_POST['action_taken']);
        $world         = $this->db->sanitize($_POST['world']);
        $coord_x       = $this->db->sanitize($_POST['coord_x'], true);
        $coord_y       = $this->db->sanitize($_POST['coord_y'], true);
        $coord_z       = $this->db->sanitize($_POST['coord_z'], true);

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

        Output::success();
    }

}
