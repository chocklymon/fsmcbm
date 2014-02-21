<?php
/**
 * Example configuration file.
 * 
 * Take this file, modify it's values and save it as 'bm-config.php'
 */

$settings = array(
    // DATABSE CONNECTION
    'db_host'     => 'localhost',
    'db_username' => 'root',
    'db_password' => '',
    'db_database' => 'ban_manager',
    
    /** The name of the cookie to use for storing the user's information. */
    'cookie_name' => 'bm',
    
    /**
     * When true, the ban manager will be in debug mode and automatically log the
     * user in as an admin, as well as output additional debugging information.
     */
    'debug' => false
    
);
