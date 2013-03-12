<?php

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
 * Outputs an error message and stops the script.
 * @param string $message The error message to display, defaults to "uknown"
 */
function error($message = "unkown"){
    exit('{"error":"' . $message . '"}');
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

function buildTable($query, &$conn){
    
    $res = $conn->query($query);

    if($res === false){
        error("Nothing Found " . mysqli_error($conn));
    }

    $result = "<table class='list'><thead><tr><th>Name</th><th>Rank</th><th>Notes</th></tr></thead><tbody>";

    while($row = $res->fetch_assoc()){
        $result .= "<tr id='id-" . $row['id'] . "'><td>"
                . $row['username'] . "</td><td>"
                . $row['rank'] . "</td><td>"
                . truncate($row['notes']) . "</td></tr>";
    }

    $res->free();


    $conn->close();

    echo $result . "</tbody></table>";
}

function sanitize($input, &$mysqli_conn, $number = false) {
    
    if($number) {
        // Sanitize as a number
        $num = preg_replace('#\D#', '', $input);
        if(strlen($num) == 0){
            return null;
        } else {
            return $num*1;
        }
    } else {
        // Sanitize as a string
        return $mysqli_conn->real_escape_string($input);
    }
    
}


// TODO add field verification (make sure that required data is there).

if(isset($_GET['term'])){
    /*
     * AUTO COMPLETE USER NAMES
     * ========================
     */
    
    $conn = getConnection();
    
    $term = sanitize( $_GET['term'], $conn );
    
    $res = $conn->query("SELECT id,username FROM users WHERE username LIKE '$term%'");
    
    if($res === false){
        error("Nothing Found " . mysqli_error($conn));
    }
    
    $result = array();
    
    while($row = $res->fetch_assoc()){
        $result[] = array("label"=>$row['username'],"value"=>$row['id']);
    }
    
    $conn->close();
    
    echo json_encode($result);
    
} else if(isset($_GET['lookup'])){
    /*
     * PULL A USERS INFORMATION
     * ========================
     */
    $conn = getConnection();
    
    $lookup = sanitize($_GET['lookup'], $conn, true);
    
    // Get the user
    $res = $conn->query("SELECT * FROM users WHERE id = $lookup");
    
    if($res === false){
        error("Nothing Found " . mysqli_error($conn));
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
    $res = $conn->query("SELECT * FROM incident WHERE user_id = $lookup");
    
    if($res === false){
        error("Nothing Found " . mysqli_error($conn));
    }
    
    $user_ids = array();
    
    while($row = $res->fetch_assoc()){
        $result["incident"][] = $row;
        $user_ids[] = $row['moderator'];
    }
    
    $res->free();
    
    if(isset($result['incident'])){
        // Get the name of the moderators
        $res = $conn->query("SELECT id,username FROM users WHERE id IN (" . implode(",", $user_ids) . ")");

        if($res === false){
            error("Unable to extract moderator id's: " . mysqli_error($conn));
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
    
} else if(isset($_GET['add_user'])){
    /*
     * ADD A NEW USER
     * ==============
     */
    
    if( !isset($_POST['username'])){
        error("Username required");
    }
    
    $conn = getConnection();
    
    $username = sanitize($_POST['username'], $conn);
    $rank = sanitize($_POST['rank'], $conn);
    $relations = sanitize($_POST['relations'], $conn);
    $notes = sanitize($_POST['notes'], $conn);
    
    $banned = (isset($_POST['banned']) && $_POST['banned'] == 'on') ? '1' : '0';
    
    $permanent = (isset($_POST['permanent']) && $_POST['permanent'] == 'on') ? '1' : '0';
    
    $res = $conn->query("INSERT INTO `users` (`username`, `rank`, `relations`, `notes`, `banned`, `permanent`)
        VALUES ('$username', '$rank', '$relations', '$notes', $banned, $permanent);");
    
    if($res === false){
        error("Failed to add user " . mysqli_error($conn));
    }
    
    // Return the id
    $result = array('user_id'=>$conn->insert_id);
    
    $conn->close();
    
    echo json_encode($result);
    
} else if(isset($_GET['add_incident'])){
    /*
     * ADD A NEW INCIDENT
     * ==================
     */
    
    $conn = getConnection();
    
    // TODO get moderator ID
    
    $user_id = sanitize($_POST['user_id'], $conn);
    $today   = date('Y-m-d H:i:s');
    $incident_date = sanitize($_POST['incident_date'], $conn);
    $incident_type = sanitize($_POST['incident_type'], $conn);
    $notes   = sanitize($_POST['notes'], $conn);
    $action_taken = sanitize($_POST['action_taken'], $conn);
    $world   = sanitize($_POST['world'], $conn);
    $coord_x = sanitize($_POST['coord_x'], $conn, true);
    $coord_y = sanitize($_POST['coord_y'], $conn, true);
    $coord_z = sanitize($_POST['coord_z'], $conn, true);
    
    $query = "INSERT INTO `incident` (`user_id`, `created_date`, `incident_date`, `incident_type`, `notes`, `action_taken`, `world`, `coord_x`, `coord_y`, `coord_z`)
        VALUES ('$user_id', '$today', '$incident_date', '$incident_type', '$notes', '$action_taken', '$world', '$coord_x', '$coord_y', '$coord_z')";
    
    $res = $conn->query($query);
    
    if($res === false){
        error("Failed to add user " . mysqli_error($conn));
    }
    
    // Return the id
    $result = array('incident_id' => $conn->insert_id);
    
    $conn->close();
    
    echo json_encode($result);
    
} else if(isset($_GET['get'])){
    // Page Requested
     
    if($_GET['get'] == 'bans'){
        /*
         * PULL ALL THE BANNED USERS
         * =========================
         */
        
        $conn = getConnection();

        buildTable("SELECT * FROM users WHERE banned = TRUE", $conn);
        
    } else if($_GET['get'] == 'watchlist'){
        
        $conn = getConnection();
        
        buildTable("SELECT users.id, users.username, users.rank, users.notes FROM users, incident WHERE users.id=incident.user_id AND users.banned = FALSE",
                $conn);
        
    }
} else if(isset($_GET['search'])){
    
    $conn = getConnection();
    
    $search = sanitize($_GET['search'], $conn);
    
    buildTable(
       "SELECT DISTINCT u.id, u.username, u.rank, u.notes
        FROM users AS u, incident AS i
        WHERE u.id = i.user_id
            AND (i.notes LIKE '%$search%'
            OR i.incident_type LIKE '%$search%'
            OR i.action_taken LIKE '%$search%'
            OR i.appeal LIKE '%$search%'
            OR i.appeal_response LIKE '%$search%')
        UNION
        SELECT DISTINCT u.id, u.username, u.rank, u.notes
        FROM users AS u
        WHERE u.username LIKE '%$search%'
            OR u.relations LIKE '%$search%'
            OR u.notes LIKE '%$search%'",
            $conn);
}

?>
