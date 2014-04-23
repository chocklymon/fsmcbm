<?php
/**
 * Example configuration file.
 *
 * Take this file, modify it's values and save it as 'bm-config.php'
 */

$settings = array(
    // DATABASE CONNECTION
    'db_host'     => 'localhost',
    'db_username' => 'root',
    'db_password' => '',
    'db_database' => 'ban_manager',

    /** The name of the cookie to use for storing the user's information. */
    'cookie_name' => 'bm',

    /**
     * The secret key to validate the cookie. Set to any random characters.
     * Changing this will cause all current users to have to log in again.
     */
    'cookie_secret' => 'secret',

    /**
     * The maximum time in seconds that is a user is allowed to be logged in
     * before they have to log in again. Zero means no timeout.
     */
    'session_duration' => 43200,

    /**
     * When true, the ban manager will be in debug mode and automatically log the
     * user in as an admin, as well as output additional debugging information.
     */
    'debug' => false,

    /**
     * Indicates wether or not to use wordpress to login users, or to use
     * the built in authentication system.
     */
    'use_wp_login' => false,

    /**
     * Indicates the location of the file to load to be able to authenticate
     * using wordpress.
     */
    'wp_load_file' => 'wp-load.php',

    /**
     * Set the secret key for use with the API.
     * This can be a single key for all, or a key for each accessor.
     */
    'auth_secret_keys' => array(),

);
