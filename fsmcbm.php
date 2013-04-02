<?php

// Detect what action to perform
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



/* =============================
 *           FUNCTIONS
 * =============================
 */


/**
 * Adds a new incident to the database.
 * Uses the data posted into the page to create the incident.
 */
function addIncident() {
    
    $conn = getConnection();
    
    // Get the moderator ID
    $moderator = 0;
    
    // Search the wordpress cookies for the logged in user name
    $keys = array_keys($_COOKIE);
    foreach($keys as &$key) {
        if (strncmp("wordpress_logged_in_", $key, 20) === 0) {
            // Extract user name from the cookie
            $value = $_COOKIE[$key];
            $moderator_name = sanitize(
                    substr($value, 0, strpos($value, "|")), $conn);
            
            // Request the user id from the database
            $res = $conn->query("SELECT `id` FROM `users` WHERE `username` = '$moderator_name'");
            
            if($res === false){
                error("Failed to find moderator.");
            } else if($res->num_rows == 0) {
                // Nothing found
                error("Moderator not found.");
            }

            $row = $res->fetch_assoc();
            
            $moderator = $row['id'];
            
            $res->free();
            
            break;
        }
    }
    
    // Make sure we found the moderator
    if($moderator === 0) {
        error("Failed to identify moderator.");
    }
    
    $user_id = sanitize($_POST['user_id'], $conn, true);
    $today   = getNow();
    $incident_date = sanitize($_POST['incident_date'], $conn);
    $incident_type = sanitize($_POST['incident_type'], $conn);
    $notes   = sanitize($_POST['notes'], $conn);
    $action_taken = sanitize($_POST['action_taken'], $conn);
    $world   = sanitize($_POST['world'], $conn);
    $coord_x = sanitize($_POST['coord_x'], $conn, true);
    $coord_y = sanitize($_POST['coord_y'], $conn, true);
    $coord_z = sanitize($_POST['coord_z'], $conn, true);
    
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
    
    $res = $conn->query($query);
    
    if($res === false){
        error("Failed to add incident.");
    }
    
    // Return the id
    $result = array('incident_id' => $conn->insert_id);
    
    $conn->close();
    
    echo json_encode($result);
}


/**
 * Adds a new user to the database.
 * User information is gathered from the data posted into this page.
 */
function addUser() {
    if( !isset($_POST['username'])){
        error("Username required");
    }
    
    $conn = getConnection();
    
    $username = sanitize($_POST['username'], $conn);
    
    // Make sure that the user name isn't empty
    if(strlen($username) == 0) {
        error("Please provide a user name.");
    }
    
    // See if this user is a duplicate
    $res = $conn->query("SELECT `id` FROM `users` WHERE `username` = '$username'");
    if($res === false){
        error("Query error.");
    } else if($res->num_rows == 1) {
        // Username already in the database
        error("User already exists.");
    }
    $res->free();
    
    // Get the user's data from the post
    $rank = sanitize($_POST['rank'], $conn);
    $relations = sanitize($_POST['relations'], $conn);
    $notes = sanitize($_POST['notes'], $conn);
    $banned = (isset($_POST['banned']) && $_POST['banned'] == 'on') ? '1' : '0';
    $permanent = (isset($_POST['permanent']) && $_POST['permanent'] == 'on') ? '1' : '0';
    $today = getNow();
    
    // Insert the user
    $res = $conn->query("INSERT INTO `users` (`username`, `modified_date`, `rank`, `relations`, `notes`, `banned`, `permanent`)
        VALUES ('$username', '$today', '$rank', '$relations', '$notes', $banned, $permanent);");
    
    if($res === false){
        error("Failed to add user.");
    }
    
    // Return the id
    $result = array('user_id'=>$conn->insert_id);
    
    $conn->close();
    
    echo json_encode($result);
    
}


/**
 * Finds possible user names to autocomplete a term provided to this page.
 */
function autoComplete() {
    
    // Make sure that the term is at least two characters long
    if(strlen($_GET['term']) < 2) {
        error("Invalid autocomplete term.");
    }
    
    $conn = getConnection();
    
    $term = sanitize( $_GET['term'], $conn );
    
    $res = $conn->query("SELECT id,username FROM users WHERE username LIKE '$term%'");
    
    if($res === false){
        error("Nothing Found.");
    }
    
    $result = array();
    
    while($row = $res->fetch_assoc()){
        $result[] = array("label"=>$row['username'],"value"=>$row['id']);
    }
    
    $res->free();
    
    $conn->close();
    
    echo json_encode($result);
}


/**
 * Performs the provided query and builds a table of users from the results.
 * @param string $query The query to retrieve the data, needs to return the
 * user name, rank, and notes.
 * @param mysqli $conn The MySQLi connection to the database.
 */
function buildTable($query, &$conn){
    
    $res = $conn->query($query);

    if($res === false){
        error("Nothing Found.");
    }
    
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

    $conn->close();

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
    $conn = getConnection();
    
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
    
    buildTable($query, $conn);
}


/**
 * Gets the connection to the mysql database.
 * @return mysqli The MySQLi database connection.
 */
function getConnection(){
    $mysqli = mysqli_connect("localhost", "root", "", "fsmcbm");
    if($mysqli->connect_errno){
        error("DB Connection Issue");
    }
    
    return $mysqli;
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
 * Gets all the users on the watchlist.
 * The watchlist is defined as a user that isn't banned, but has an incident
 * attached to them.
 */
function getWatchlist() {
    $conn = getConnection();
    
    $query = "SELECT u.id, u.username, i.incident_date, i.incident_type, i.action_taken
            FROM users AS u, incident AS i
            WHERE u.banned = FALSE
            AND u.id = i.user_id
            GROUP BY u.id
            ORDER BY i.incident_date DESC ";
    
    buildTable($query, $conn);
}

/**
 * Retrieves the information for a user.
 * This includes all the users incidents (if any) and their user information.
 */
function retrieveUserData() {
    
    $conn = getConnection();
    
    $lookup = sanitize($_GET['lookup'], $conn, true);
    
    if($lookup === null || $lookup <= 0) {
        // Invalid lookup
        error("Invalid user ID.");
    }
    
    // Get the user
    $res = $conn->query("SELECT * FROM users WHERE id = $lookup");
    
    if($res === false){
        error("Nothing Found.");
    }
    
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
    $res = $conn->query("SELECT * FROM incident WHERE user_id = $lookup ORDER BY incident_date");
    
    if($res === false){
        error("Nothing Found.");
    }
    
    $user_ids = array();
    
    while($row = $res->fetch_assoc()){
        $result["incident"][] = $row;
        $user_ids[] = $row['moderator'];
    }
    
    $res->free();
    
    
    if( isset($result['incident']) ){
        // Get the name of the moderators
        $res = $conn->query("SELECT id,username FROM users WHERE id IN (" . implode(",", $user_ids) . ")");

        if($res === false){
            error("Unable Found.");
        }

        while($row = $res->fetch_assoc()){
            $user_ids[$row['id']] = $row['username'];
        }

        $res->free();
        
        // change the moderator id to username
        foreach($result['incident'] as &$incident){
            $incident['moderator_id'] = $incident['moderator'];
            $incident['moderator'] = $user_ids[$incident['moderator']];
        }
    }

    $conn->close();
    
    echo json_encode($result);
    
}


/**
 * Sanitizes input for insertion into the database.
 * @param string $input The string input to sanitize.
 * @param mysqli $mysqli_conn The MySQLi connection to the database (required
 * for real escape string).
 * @param boolean $number Wether or not the input should be treated as a number.
 * True to sanitize as a number. Defaults to false.
 * @return mixed The sanitized string, or the sanitized number if number is set
 * to true.
 */
function sanitize($input, &$mysqli_conn, $number = false) {
    
    if(isset($input) && $input !== null) {
        if($number) {
            // Sanitize as a number
            $num = preg_replace('[^0-9\-]', '', $input);
            if(strlen($num) == 0){
                return null;
            } else {
                return $num*1;
            }
        } else {
            // Remove magic quote escaping if needed
            if (get_magic_quotes_gpc()) {
                $input = stripslashes($input);
            }
            
            // Sanitize as a string
            return $mysqli_conn->real_escape_string($input);
        }
    } else {
        return null;
    }
    
}


/**
 * Searches the text fields in the database for the provided search keyword.
 */
function search() {
    
    if( strlen($_GET['search']) < 2) {
        // Searches must contain at least two characters
        error("Search string to short.");
    }
    
    $conn = getConnection();
    
    $search = sanitize($_GET['search'], $conn);
    
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
    
    buildTable( $query, $conn );
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
    $conn = getConnection();
        
    $id = sanitize($_POST['id'], $conn, true);
    
    // Verify that we have a user id
    if($id === null || $id <= 0) {
        error("No user id found.");
    }
    
    $rank = sanitize($_POST['rank'], $conn);
    $banned = $_POST['banned'] == "true";
    $permanent = $_POST['permanent'] == "true";
    $relations = sanitize($_POST['relations'], $conn);
    $notes = sanitize($_POST['notes'], $conn);
    $today = getNow();

    $query = "UPDATE  `fsmcbm`.`users` SET
                `modified_date` = '$today',
                `rank` =  '$rank',
                `relations` =  '$relations',
                `notes` =  '$notes',
                `banned` =  '$banned',
                `permanent` =  '$permanent'
                WHERE  `users`.`id` = $id";

    $res = $conn->query($query);
    
    $conn->close();

    if($res === false){
        error("Failed to update user.");
    }

    echo json_encode( array("success" => true ));
}


/**
 * Updates an incident with new data.
 * Data is retrieved from the data posted into this page.
 */
function updateIncident() {
    $conn = getConnection();
        
    $id = sanitize($_POST['id'], $conn, true);
    
    // Verify that we have an incident id
    if($id === null || $id <= 0) {
        error("No incident id found.");
    }
    
    $now = getNow();
    $incident_date = sanitize($_POST['incident_date'], $conn);
    $incident_type = sanitize($_POST['incident_type'], $conn);
    $notes   = sanitize($_POST['notes'], $conn);
    $action_taken = sanitize($_POST['action_taken'], $conn);
    $world   = sanitize($_POST['world'], $conn);
    $coord_x = sanitize($_POST['coord_x'], $conn, true);
    $coord_y = sanitize($_POST['coord_y'], $conn, true);
    $coord_z = sanitize($_POST['coord_z'], $conn, true);
    $appeal_response = isset($_POST['appeal_response']) ? sanitize($_POST['appeal_response'], $conn) : '';

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

    $res = $conn->query($query);
    
    $conn->close();

    if($res === false){
        error("Failed to update incident.");
    }

    echo json_encode( array("success" => true ));
}

?>