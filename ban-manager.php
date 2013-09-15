<?php

/* =============================
 * GLOBAL FUNCTIONS AND SETTINGS
 * =============================
 */

require_once 'bm-config.php';
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
        error("Not logged in.");

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
            error("Not logged in.");
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
    
} else if(isset($_GET['update'])) {
    // Update requested
    
    if($_GET['update'] == "user") {
        
        updateUser();
        
    } else if($_GET['update'] == "incident") {
        
        updateIncident();
    }
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
    
    $user_id = $db->sanitize($_POST['user_id'], true);
    $today   = getNow();
    $incident_date = $db->sanitize($_POST['incident_date']);
    $incident_type = $db->sanitize($_POST['incident_type']);
    $notes   = $db->sanitize($_POST['notes']);
    $action_taken = $db->sanitize($_POST['action_taken']);
    $world   = $db->sanitize($_POST['world']);
    $coord_x = $db->sanitize($_POST['coord_x'], true);
    $coord_y = $db->sanitize($_POST['coord_y'], true);
    $coord_z = $db->sanitize($_POST['coord_z'], true);
    
    // Verify that we have a user id
    if($user_id === null || $user_id <= 0) {
        error("Please provide a user for this incident.");
    }
    
    // Check if we have an incident date.
    if($incident_date === null || strlen($incident_date) < 6) {
        $incident_date = substr($today, 0, 10);
    }
    
    $query = "INSERT INTO `incident` (`user_id`, `moderator`, `created_date`, `modified_date`, `incident_date`, `incident_type`, `notes`, `action_taken`, `world`, `coord_x`, `coord_y`, `coord_z`)
        VALUES ('$user_id', '$moderator', '$today', '$today', '$incident_date', '$incident_type', '$notes', '$action_taken', '$world', '$coord_x', '$coord_y', '$coord_z')";
    
    $incident_id = $db->insert($query);
    
    // Return the id
    $result = array('incident_id' => $incident_id);
    
    echo json_encode($result);
}


/**
 * Adds a new user to the database.
 * User information is gathered from the data posted into this page.
 */
function addUser() {
    global $db;
    
    if( !isset($_POST['username'])){
        error("Username required");
    }
    
    $username = $db->sanitize($_POST['username']);
    
    // Make sure that the user name isn't empty
    if(strlen($username) == 0) {
        error("Please provide a user name.");
    }
    
    // See if this user is a duplicate
    $res = $db->query("SELECT `id` FROM `users` WHERE `username` = '$username'");
    if($res->num_rows == 1) {
        // Username already in the database
        error("User already exists.");
    }
    $res->free();
    
    // Get the user's data from the post
    $rank = $db->sanitize($_POST['rank']);
    $relations = $db->sanitize($_POST['relations']);
    $notes = $db->sanitize($_POST['notes']);
    $banned = (isset($_POST['banned']) && $_POST['banned'] == 'on') ? '1' : '0';
    $permanent = (isset($_POST['permanent']) && $_POST['permanent'] == 'on') ? '1' : '0';
    $today = getNow();
    
    // Insert the user
    $user_id = $db->insert("INSERT INTO `users` (`username`, `modified_date`, `rank`, `relations`, `notes`, `banned`, `permanent`)
        VALUES ('$username', '$today', '$rank', '$relations', '$notes', $banned, $permanent);");
    
    // Get the ID
    $result = array('user_id' => $user_id);
    
    // See if we need to add to the ban history
    if($banned === '1') {
        updateBanHistory($user_id, $banned, $permanent);
    }
    
    echo json_encode($result);
    
}


/**
 * Finds possible user names to autocomplete a term provided to this page.
 */
function autoComplete() {
    global $db;
    
    // Make sure that the term is at least two characters long
    if(strlen($_GET['term']) < 2) {
        error("Invalid autocomplete term.");
    }
    
    $term = $db->sanitize( $_GET['term'] );
    
    $res = $db->query("SELECT id,username FROM users WHERE username LIKE '$term%'");
    
    $result = array();
    
    while($row = $res->fetch_assoc()){
        $result[] = array("label"=>$row['username'],"value"=>$row['id']);
    }
    
    $res->free();
    
    echo json_encode($result);
}


/**
 * Performs the provided query and builds a table of users from the results.
 * @param string $query The query to retrieve the data, needs to return the
 * user name, rank, and notes.
 */
function buildTable($query) {
    global $db;
    
    $res = $db->query($query);
    
    if($res->num_rows == 0){
        // Nothing found
        $result = "<div>Nothing Found</div>";
        
    } else {
    
        // Place the results into the table
        $result = "<table class='list'><thead><tr><th>Name</th><th>Last Incident Date</th><th>Last Incident Type</th><th>Last Action Taken</th></tr></thead><tbody>";

        while($row = $res->fetch_assoc()){
            $result .= "<tr id='id-" . $row['id'] . "'><td>"
                    . $row['username'] . "</td><td>"
                    . $row['incident_date'] . "</td><td>"
                    . truncate($row['incident_type']) . "</td><td>"
                    . truncate($row['action_taken']) . "</td></tr>";
        }
        
        $result .= "</tbody></table>";
    }

    $res->free();

    echo $result;
}


/**
 * Outputs an error message and stops the script.
 * @param string $message The error message to display, defaults to "Unkown error occured"
 */
function error($message = "Unkown error occured"){
    exit('{"error":"' . $message . '"}');
}


/**
 * Retrieves a list of all banned users.
 */
function getBans() {
    $query = "SELECT u.id, u.username, i.incident_date, i.incident_type, i.action_taken
            FROM users AS u
            LEFT JOIN (
                SELECT * 
                FROM incident AS q
                ORDER BY q.incident_date DESC
            ) AS i ON u.id = i.user_id
            WHERE u.banned = TRUE
            GROUP BY u.id
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
    
    $id = FALSE;
    
    // Get the moderators name from the cookie
    $moderator_name = getLoggedInName();
    
    if($moderator_name === FALSE) {
        return FALSE;
    }

    $moderator_name = $db->sanitize($moderator_name);

    // Request the user id from the database
    $res = $db->query("SELECT `id`,`rank` FROM `users` WHERE `username` = '$moderator_name'");

    if($res->num_rows == 0) {
        // Nothing found
        error("Moderator not found.");
    }

    $row = $res->fetch_assoc();

    // Only store the information of admins/moderators
    if($row['rank'] == 'Admin' || $row['rank'] == 'Moderator') {
        $id = array($row['id'], $row['rank'], $moderator_name);
    }

    $res->free();
    
    return $id;
}


/**
 * Gets all the users on the watchlist.
 * The watchlist is defined as a user that isn't banned, but has an incident
 * attached to them.
 */
function getWatchlist() {
    $query = "SELECT u.id, u.username, i.incident_date, i.incident_type, i.action_taken
            FROM users AS u, incident AS i
            WHERE u.banned = FALSE
            AND u.id = i.user_id
            GROUP BY u.id
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
    
    if($lookup === null || $lookup <= 0) {
        // Invalid lookup
        error("Invalid user ID.");
    }
    
    // Get the user
    $res = $db->query("SELECT * FROM users WHERE id = $lookup");
    
    if($res->num_rows == 0){
        // Nothing found
        error("User not found.");
    }
    
    $result = array();
    
    while($row = $res->fetch_assoc()){
        $result["user"] = $row;
    }
    
    $res->free();
    
    
    // Get the incidents
    $res = $db->query("SELECT * FROM incident WHERE user_id = $lookup ORDER BY incident_date");

    $user_ids = array();
    
    while($row = $res->fetch_assoc()){
        $result["incident"][] = $row;
        $user_ids[] = $row['moderator'];
    }
    
    $res->free();
    
    
    // Get the ban history
    $res = $db->query("SELECT * FROM `ban_history` WHERE `user_id` = $lookup ORDER BY `date`;");
    
    while($row = $res->fetch_assoc()) {
        $result['history'][] = $row;
        $user_ids[] = $row['moderator'];
    }
    
    
    // Get the name of the moderators
    $user_ids = array_unique($user_ids);

    if( count($user_ids) != 0 ){
        
        $res = $db->query("SELECT id,username FROM users WHERE id IN (" . implode(",", $user_ids) . ")");

        while($row = $res->fetch_assoc()){
            $user_ids[$row['id']] = $row['username'];
        }

        $res->free();
        
        // change the moderator id to username
        if( isset($result['incident']) ) {
            foreach($result['incident'] as &$incident){
                $incident['moderator_id'] = $incident['moderator'];
                $incident['moderator'] = $user_ids[$incident['moderator']];
            }
        }
        if( isset($result['history']) ) {
            foreach($result['history'] as &$history){
                $history['moderator_id'] = $history['moderator'];
                $history['moderator'] = $user_ids[$history['moderator']];
            }
        }
    }
    
    echo json_encode($result);
    
}


/**
 * Searches the text fields in the database for the provided search keyword.
 */
function search() {
    global $db;
    
    if( strlen($_GET['search']) < 2) {
        // Searches must contain at least two characters
        error("Search string to short.");
    }
    
    $search = $db->sanitize($_GET['search']);
    
    $query = "SELECT * FROM (
                SELECT u.id, u.username, u.banned, i.incident_date, i.incident_type, i.action_taken
                FROM users AS u, incident AS i
                WHERE u.id = i.user_id
                    AND (i.notes LIKE '%$search%'
                    OR i.incident_type LIKE '%$search%'
                    OR i.action_taken LIKE '%$search%'
                    OR i.appeal LIKE '%$search%'
                    OR i.appeal_response LIKE '%$search%')
                UNION
                SELECT u.id, u.username, u.banned, u.modified_date AS incident_date, u.rank, u.relations
                FROM users AS u
                WHERE u.username LIKE '%$search%'
                    OR u.relations LIKE '%$search%'
                    OR u.notes LIKE '%$search%'
                ORDER BY incident_date DESC
                ) AS r
        GROUP BY r.id";
    
    buildTable($query);
}


/**
 * Takes a string and truncates it, if it is over 120 characters long, replacing
 * the characters over 120 with an ellipsis.
 * @param string $string The string to truncate.
 * @return string The truncated string.
 */
function truncate($string){
    if(strlen($string) > 120){
        return substr($string, 0, 120) . " ...";
    } else {
        return $string;
    }
}


/**
 * Updates an exisiting user with new data.
 * The data is retrieved from the data posted into this page.
 */
function updateUser() {
    global $db;
        
    $id = $db->sanitize($_POST['id'], true);
    
    // Verify that we have a user id
    if($id === null || $id <= 0) {
        error("No user id found.");
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
    
    // See if we need to update the ban history
    $query = "SELECT * FROM `users` WHERE `users`.`id` = $id";
    
    $res = $db->query($query);
    
    if($res->num_rows == 0) {
        error("Failed to retrieve incident.");
    }
    
    $row = $res->fetch_assoc();
    
    if($row['banned'] != $banned || $row['permanent'] != $permanent) {
        updateBanHistory($id, $banned, $permanent);
    }
    
    $res->free();

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
                WHERE  `users`.`id` = $id";

    $res = $db->query($query);

    if ($res === false) {
        error("Failed to update user.");
    }

    echo json_encode( array("success" => true ));
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
    
    $today = getNow();
    
    $res = $db->query("INSERT INTO `ban_history` (`user_id`, `moderator`, `date`, `banned`, `permanent`)
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
    if($id === null || $id <= 0) {
        error("No incident id found.");
    }
    
    $now = getNow();
    $incident_date = $db->sanitize($_POST['incident_date']);
    $incident_type = $db->sanitize($_POST['incident_type']);
    $notes   = $db->sanitize($_POST['notes']);
    $action_taken = $db->sanitize($_POST['action_taken']);
    $world   = $db->sanitize($_POST['world']);
    $coord_x = $db->sanitize($_POST['coord_x'], true);
    $coord_y = $db->sanitize($_POST['coord_y'], true);
    $coord_z = $db->sanitize($_POST['coord_z'], true);
    $appeal_response = isset($_POST['appeal_response']) ? $db->sanitize($_POST['appeal_response']) : '';

    $query = "UPDATE `incident` SET
        `modified_date` = '$now',
        `incident_date` = '$incident_date',
        `incident_type` = '$incident_type',
        `notes` = '$notes',
        `action_taken` = '$action_taken',
        `world` = '$world',
        `coord_x` = '$coord_x',
        `coord_y` = '$coord_y',
        `coord_z` = '$coord_z',
        `appeal_response` = '$appeal_response'
        WHERE  `incident`.`id` = $id";

    $res = $db->query($query);

    echo json_encode( array("success" => true ));
}

?>