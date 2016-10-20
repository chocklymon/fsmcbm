<?php
/**
 * Example configuration file.
 *
 * Take this file, modify it's values and save it as 'bm-config.php'
 */

$settings = array(
    /**
     * Define how to connect to the MySQL database.
     * REQUIRED
     */
    'db_host'     => 'localhost',
    'db_username' => 'root',
    'db_password' => '',
    'db_database' => 'ban_manager',

    /**
     * When true, the ban manager will be in debug mode and automatically log the
     * user in as an admin, as well as output additional debugging information.
     * Defaults to false.
     */
    'debug' => false,

    /**
     * Indicates how users should be authenticated. Accepts `wordpress` or `auth0`.
     * When set to wordpress, the `wp_load_file` setting must be set.
     * When set to auth0 the three auth0_* settings must be set.
     * There is also a `none` mode that disables authentication. This should only be used for testing since all users
     * will be logged in as user 1.
     * REQUIRED
     */
    'auth_mode' => '',

    /**
     * Indicates the location of the file to load to be able to authenticate
     * using wordpress.
     */
    'wp_load_file' => 'wp-load.php',

    /**
     * Set the lowest user level that a wordpress user must have in order to be able to use the ban manager.
     * See: https://codex.wordpress.org/Roles_and_Capabilities#User_Levels
     * Note: This is a temporary solution until role based access is implemented.
     * Defaults to 8.
     */
    'wp_minimum_user_level' => 8,

    /**
     * Set the auth0 authentication information.
     */
    'auth0_client_secret' => 'secret',
    'auth0_client_id' => 'id',
    'auth0_domain' => '[subdomain].auth0.com',

    /**
     * Set the secret key for use with the API.
     * This can be a single key for all, or a key for each accessor.
     */
    'auth_secret_keys' => array(),

    /**
     * Define a list of worlds for the server
     */
    'worlds' => array(
        array(
            'value' => '',
            'label' => ''
        ),
        array(
            'value' => 'world',
            'label' => 'World'
        ),
        array(
            'value' => 'nether',
            'label' => 'Nether'
        ),
        array(
            'value' => 'the_end',
            'label' => 'The End'
        ),
    ),
);
