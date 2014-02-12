<?php
$settings = array(
    // DATABSE CONNECTION
    'db_host'     => 'localhost',
    'db_username' => 'root',
    'db_password' => '',
    'db_database' => 'fsmcbm',
    
    /** The name of the cookie to use for storing the user's information. */
    'cookie_name' => 'fsmcbm',
    
    /**
     * When true, the ban manager will be in debug mode and automatically log the
     * user in as an admin, as well as output additional debugging information.
     */
    'debug' => true,
    
    /**
     * This is the base URL for ban manager. This needs to be pointing to the
     * folder where the ban manager files stored are on the server. It can be an
     * absolote or relative URL. If relative, it should be relative to the webpage
     * that is displaying the ban manager, in most cases that will be index.php. 
     */
    'url' => '',
);
