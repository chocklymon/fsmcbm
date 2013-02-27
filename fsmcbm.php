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

function buildTable($query){
    $conn = getConnection();

    $res = $conn->query($query);

    if($res === false){
        error("Nothing Found " . mysqli_error($conn));
    }

    $result = "<table><thead><tr><th>Name</th><th>Rank</th><th>Notes</th></tr></thead><tbody>";

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

if(isset($_GET['term'])){
    /*
     * AUTO COMPLETE USER NAMES
     * ========================
     */
    
    $conn = getConnection();
    
    $res = $conn->query("SELECT id,username FROM users WHERE username LIKE \"{$_GET['term']}%\"");
    
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
    
    // Get the user
    $res = $conn->query("SELECT * FROM users WHERE id = {$_GET['lookup']}");
    
    if($res === false){
        error("Nothing Found " . mysqli_error($conn));
    }
    
    $result = array();
    
    while($row = $res->fetch_assoc()){
        $result["user"] = $row;
    }
    
    $res->free();
    
    
    // Get the incidents
    $res = $conn->query("SELECT * FROM incident WHERE user_id = {$_GET['lookup']}");
    
    if($res === false){
        error("Nothing Found " . mysqli_error($conn));
    }
    
    $user_ids = array();
    
    while($row = $res->fetch_assoc()){
        $result["incident"][] = $row;
        $user_ids[] = $row['moderator'];
    }
    
    $res->free();
    
    /* TODO
    // Get the name of the moderators
    $query = "SELECT ,username FROM users WHERE id IN ";
    $user_ids = array_unique($user_ids);
    for($i = 0; ; $i++){
        $query .= $user_ids[$i];
        if($i < count($user_ids))
            return;
        
        $query .= ",";
    }
    
    $res = $conn->query($query);
    
    if($res === false){
        error("Nothing Found " . mysqli_error($conn));
    }

    while($row = $res->fetch_assoc()){
        $result["incident"][] = $row;
        $user_ids[] = $row['moderator'];
    }
    
    $res->free();
    */
    
    
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
    
    $username = $conn->real_escape_string($_POST['username']);
    $rank = $conn->real_escape_string($_POST['rank']);
    $relations = $conn->real_escape_string($_POST['relations']);
    $notes = $conn->real_escape_string($_POST['notes']);
    
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
    
} else if(isset($_GET['get'])){
    // Page Requested
     
    if($_GET['get'] == 'bans'){
        /*
         * PULL ALL THE BANNED USERS
         * =========================
         */

        buildTable("SELECT * FROM users WHERE banned = TRUE");
        
    } else if($_GET['get'] == 'watchlist'){
        
        buildTable("SELECT * FROM users, incident WHERE users.id=incident.user_id AND users.banned = FALSE");
        
    }
}

?>
