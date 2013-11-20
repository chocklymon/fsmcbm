<?php

/* =============================
 * GLOBAL FUNCTIONS AND SETTINGS
 * =============================
 */

require_once 'bm-config.php';
require_once 'bm-output.php';
require_once 'bm-database.php';
require_once 'bm-controller.php';

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
$actions = new Controller($db);

if(isset($_GET['term'])){
    
    $actions->autoComplete();
    
} else if(isset($_GET['lookup'])){
    
    $actions->retrieveUserData();
    
} else if(isset($_GET['add_user'])){
    
    $actions->addUser();
    
} else if(isset($_GET['add_incident'])){
    
    $actions->addIncident($moderator);
    
} else if(isset($_GET['get'])){
    // Tab contents requested
    
    if($_GET['get'] == 'bans'){
        
        $actions->getBans();
        
    } else if($_GET['get'] == 'watchlist'){
        
        $actions->getWatchlist();
        
    }
} else if(isset($_GET['search'])){
    
    $actions->search();
    
} else if(isset($_GET['update_user'])) {
    
    $actions->updateUser();
    
} else if(isset($_GET['update_incident'])) {
    
    $actions->updateIncident();

}

$db->close();

?>