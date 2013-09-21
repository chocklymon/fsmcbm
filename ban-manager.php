<?php

/* =============================
 * GLOBAL FUNCTIONS AND SETTINGS
 * =============================
 */

require_once 'bm-config.php';
require_once 'bm-output.php';
require_once 'bm-database.php';

/** The ID of the current user. */
$moderator = 0;



/* =============================
 *        VERIFY USER
 * =============================
 */

if (DEBUG_MODE) {
    // Debugging mode on, auto login as the first user
    $moderator = 1;
} else {
    if( getLoggedInName() === FALSE ) {
        // User is not logged in, set the ban manager cookie as expired.
        setcookie(BM_COOKIE, "", time() - 3600);
        Output::error("Not logged in.");

    } else if( isset($_COOKIE[BM_COOKIE]) ) {

        $user_info = explode("|", $_COOKIE[BM_COOKIE]);

        // Check if the user has changed
        if($user_info[2] == getLoggedInName())
        {
            // User is the same, store their ID
            $moderator = $user_info[0];
        }
    }

    if($moderator === 0) {
        $user_info = getModeratorInfo();

        if($user_info === FALSE) {
            // Not a moderator
            Output::error("Not logged in.");
        } else {
            // Mark the user as logged into the ban manager
            setcookie(BM_COOKIE, implode("|", $user_info), 0, "/", "finalscoremc.com");

            $moderator = $user_info[0];
        }
    }
}



/* =============================
 *      PERFORM ACTIONS
 * =============================
 */

// Get the connection to the database
$db = new Database();

if(isset($_GET['term'])){
    
    autoComplete();
    
} else if(isset($_GET['lookup'])){
    
    retrieveUserData();
    
} else if(isset($_GET['add_user'])){
    
    addUser();
    
} else if(isset($_GET['add_incident'])){
    
    addIncident();
    
} else if(isset($_GET['get'])){
    // Tab contents requested
    
    if($_GET['get'] == 'bans'){
        
        getBans();
        
    } else if($_GET['get'] == 'watchlist'){
        
        getWatchlist();
        
    }
} else if(isset($_GET['search'])){
    
    search();
    
} else if(isset($_GET['update_user'])) {
    
    updateUser();
    
} else if(isset($_GET['update_incident'])) {
    
    updateIncident();

}

$db->close();


/* =============================
 *           FUNCTIONS
 * =============================
 */


/**
 * Adds a new incident to the database.
 * Uses the data posted into the page to create the incident.
 * @global int $moderator The ID of the moderator/admin that is logged in.
 */
function addIncident() {
    
    global $moderator, $db;
    
    $user_id       = $db->sanitize($_POST['user_id'], true);
    $today         = getNow();
    $incident_date = $db->sanitize($_POST['incident_date']);
    $incident_type = $db->sanitize($_POST['incident_type']);
    $notes         = $db->sanitize($_POST['notes']);
    $action_taken  = $db->sanitize($_POST['action_taken']);
    $world         = $db->sanitize($_POST['world']);
    $coord_x       = $db->sanitize($_POST['coord_x'], true);
    $coord_y       = $db->sanitize($_POST['coord_y'], true);
    $coord_z       = $db->sanitize($_POST['coord_z'], true);
    
    // Verify that we have a user id
    if($user_id === null || $user_id <= 0) {
        Output::error("Please provide a user for this incident.");
    }
    
    // Check if we have an incident date.
    if($incident_date === null || strlen($incident_date) < 6) {
        $incident_date = substr($today, 0, 10);
    }
    
    $query = "INSERT INTO `incident` (`user_id`, `moderator_id`, `created_date`, `modified_date`, `incident_date`, `incident_type`, `notes`, `action_taken`, `world`, `coord_x`, `coord_y`, `coord_z`)
        VALUES ('$user_id', '$moderator', '$today', '$today', '$incident_date', '$incident_type', '$notes', '$action_taken', '$world', '$coord_x', '$coord_y', '$coord_z')";
    
    $incident_id = $db->insert($query);
    
    // Return the id
    Output::append($incident_id, 'incident_id');
    Output::reply();
}


/**
 * Adds a new user to the database.
 * User information is gathered from the data posted into this page.
 */
function addUser() {
    global $db;
    
    if( !isset($_POST['username'])){
        Output::error("Username required");
    }
    
    $username = $db->sanitize($_POST['username']);
    
    // Make sure that the user name isn't empty
    if(strlen($username) == 0) {
        Output::error("Please provide a user name.");
    }
    
    // See if this user is a duplicate
    $res = $db->query("SELECT `user_id` FROM `users` WHERE `username` = '$username'");
    if($res->num_rows == 1) {
        // Username already in the database
        Output::error("User already exists.");
    }
    $res->free();
    
    // Get the user's data from the post
    $rank      = $db->sanitize($_POST['rank'], true);
    $relations = $db->sanitize($_POST['relations']);
    $notes     = $db->sanitize($_POST['notes']);
    $banned    = (isset($_POST['banned']) && $_POST['banned'] == 'on') ? '1' : '0';
    $permanent = (isset($_POST['permanent']) && $_POST['permanent'] == 'on') ? '1' : '0';
    $today     = getNow();
    
    // Insert the user
    $user_id = $db->insert(
        "INSERT INTO `users` (`username`, `modified_date`, `rank`, `relations`, `notes`, `banned`, `permanent`)
        VALUES ('$username', '$today', '$rank', '$relations', '$notes', $banned, $permanent)"
    );

    // See if we need to add to the ban history
    if($banned === true) {
        updateBanHistory($user_id, $banned, $permanent);
    }
    
    // Return the new users ID
    Output::append($user_id, 'user_id');
    Output::reply();
}


/**
 * Finds possible user names to autocomplete a term provided to this page.
 */
function autoComplete() {
    global $db;
    
    // Make sure that the term is at least two characters long
    if(strlen($_GET['term']) < 2) {
        Output::error('Invalid autocomplete term.');
    }
    
    $term = $db->sanitize( $_GET['term'] );
    
    $res = $db->query(
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
 */
function buildTable($query) {
    global $db;
    
    Output::setHTMLMode(true);
    
    $res = $db->query($query);
    
    if($res->num_rows == 0){
        // Nothing found
        Output::append("<div>Nothing Found</div>");
        
    } else {
    
        // Place the results into the table
        Output::append("<table class='list'><thead><tr><th>Name</th><th>Last Incident Date</th><th>Last Incident Type</th><th>Last Action Taken</th></tr></thead><tbody>");

        while($row = $res->fetch_assoc()){
            Output::append(
                "<tr id='id-" . $row['user_id'] . "'><td>"
               . Output::prepareHTML($row['username'])            . "</td><td>"
               . Output::prepareHTML($row['incident_date'])       . "</td><td>"
               . Output::prepareHTML($row['incident_type'], true) . "</td><td>"
               . Output::prepareHTML($row['action_taken'], true)  . "</td></tr>"
            );
        }
        
        Output::append("</tbody></table>");
    }

    $res->free();

    Output::reply();
}


/**
 * Retrieves a list of all banned users.
 */
function getBans() {
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
function getLoggedInName() {
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
function getNow() {
    return date('Y-m-d H:i:s');
}


/**
 * Gets the information for the moderator using the ban manager.
 * @return mixed FALSE if the user is not a moderator/admin or is not logged into
 * wordpress, otherwise it return an array where index zero is the user id,
 * index one is the user's rank, and index two is the user's name.
 */
function getModeratorInfo() {
    global $db;
    
    $info = FALSE;
    
    // Get the moderators name from the cookie
    $moderator_name = getLoggedInName();
    
    if($moderator_name === FALSE) {
        return FALSE;
    }

    $moderator_name = $db->sanitize($moderator_name);

    // Request the user id from the database
    $row = $db->querySingleRow(
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
function getWatchlist() {
    $query = "SELECT u.user_id, u.username, i.incident_date, i.incident_type, i.action_taken
            FROM users AS u, incident AS i
            WHERE u.banned = FALSE
            AND u.user_id = i.user_id
            GROUP BY u.user_id
            ORDER BY i.incident_date DESC ";
    
    buildTable($query);
}

/**
 * Retrieves the information for a user.
 * This includes all the users incidents (if any) and their user information.
 */
function retrieveUserData() {
    global $db;
    
    $lookup = $db->sanitize($_GET['lookup'], true);
    
    if($lookup <= 0) {
        // Invalid lookup
        Output::error("Invalid user ID.");
    }
    
    
    // Get the user
    Output::append(
        $db->querySingleRow(
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

    $db->queryRowsIntoOutput($sql, 'incident');

    
    // Get the ban history
    $sql = <<<SQL
SELECT u.username AS moderator, bh.date, bh.banned, bh.permanent
FROM `ban_history` AS bh
LEFT JOIN `users` AS u ON (bh.moderator_id = u.user_id)
WHERE bh.`user_id` = '$lookup'
ORDER BY bh.`date`
SQL;
    
    $db->queryRowsIntoOutput($sql, 'history');

    Output::reply();
}


/**
 * Searches the text fields in the database for the provided search keyword.
 */
function search() {
    global $db;
    
    if( strlen($_GET['search']) < 2) {
        // Searches must contain at least two characters
        Output::setHTMLMode(true);
        Output::error("Search string to short.");
    }
    
    $search = $db->sanitize($_GET['search']);
    
    // TODO this query needs to be re-written, probably make it three queries.
    // and then return three tables. One for users, another for incidents, and finally one for appeals.
    $query = "SELECT * FROM (
                SELECT u.user_id, u.username, u.banned, i.incident_date, i.incident_type, i.action_taken
                FROM users AS u, incident AS i
                WHERE u.user_id = i.user_id
                    AND (i.notes LIKE '%$search%'
                    OR i.incident_type LIKE '%$search%'
                    OR i.action_taken LIKE '%$search%')
                UNION
                SELECT u.user_id, u.username, u.banned, u.modified_date AS incident_date, r.name, u.relations
                FROM users AS u
                LEFT JOIN `rank` AS r ON (u.`rank` = r.`rank_id`)
                WHERE u.username LIKE '%$search%'
                    OR u.relations LIKE '%$search%'
                    OR u.notes LIKE '%$search%'
                ORDER BY incident_date DESC
                ) AS results
        GROUP BY results.user_id";
    
    buildTable($query);
}


/**
 * Updates an exisiting user with new data.
 * The data is retrieved from the data posted into this page.
 */
function updateUser() {
    global $db;
    
    // Sanitize the inputs
    $id = $db->sanitize($_POST['id'], true);
    
    // Verify that we have a valid user id
    if($id <= 0) {
        Output::error("Invalid user ID.");
    }
    
    $username = null;
    if (isset($_POST['username'])) {
        $username = $db->sanitize($_POST['username']);
    }
    $rank = $db->sanitize($_POST['rank']);
    $banned = $_POST['banned'] == "true";
    $permanent = $_POST['permanent'] == "true";
    $relations = $db->sanitize($_POST['relations']);
    $notes = $db->sanitize($_POST['notes']);
    $today = getNow();
    
    // If the user is no longer banned, make sure the permanent flag is unchecked
    if (!$banned && $permanent) {
        $permanent = false;
    }
    
    // See if we need to update the ban history
    $query = "SELECT * FROM `users` WHERE `users`.`user_id` = $id";
    $row = $db->querySingleRow($query, "Failed to retrieve incident.");
    
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

    $db->query($query);

    Output::success();
}


/**
 * Updates the ban history
 * @global int $moderator The ID of the moderator/admin that is logged in.
 * @param int $user_id The ID of the user who's ban history is being updated.
 * @param boolean $banned Whether or not the user is banned.
 * @param boolean $permanent Wether or not the user is banned permanently.
 */
function updateBanHistory($user_id, $banned, $permanent) {
    global $moderator, $db;
    
    // Be sure the inputs are what the are supposed to be.
    $user_id = (int) $user_id;
    $banned = (boolean) $banned;
    $permanent = (boolean) $permanent;
    
    $today = getNow();
    
    $db->query("INSERT INTO `ban_history` (`user_id`, `moderator_id`, `date`, `banned`, `permanent`)
            VALUES ('$user_id', '$moderator', '$today', '$banned', '$permanent');");
}


/**
 * Updates an incident with new data.
 * Data is retrieved from the data posted into this page.
 */
function updateIncident() {
    global $db;
        
    $id = $db->sanitize($_POST['id'], true);
    
    // Verify that we have an incident id
    if($id <= 0) {
        Output::error("Invalid incident id.");
    }
    
    $now = getNow();
    $incident_date = $db->sanitize($_POST['incident_date']);
    $incident_type = $db->sanitize($_POST['incident_type']);
    $notes         = $db->sanitize($_POST['notes']);
    $action_taken  = $db->sanitize($_POST['action_taken']);
    $world         = $db->sanitize($_POST['world']);
    $coord_x       = $db->sanitize($_POST['coord_x'], true);
    $coord_y       = $db->sanitize($_POST['coord_y'], true);
    $coord_z       = $db->sanitize($_POST['coord_z'], true);

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

    $db->query($query, 'Failed to update incident.');

    Output::success();
}

?>