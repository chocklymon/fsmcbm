<?php

// DATABSE CONNECTION
define("DB_HOST",     "localhost");
define("DB_USERNAME", "root");
define("DB_PASSWORD", "");
define("DB_DATABASE", "fsmcbm");

/** The name of the cookie to use for storing the user's information. */
define("BM_COOKIE", 'fsmcbm');

/**
 * When true, the ban manager will be in debug mode and automatically log the
 * user in as an admin, as well as output additional debugging information.
 */
define("DEBUG_MODE", true);

/**
 * This is the base URL for ban manager. This needs to be pointing to the
 * folder where the ban manager files stored are on the server. It can be an
 * absolote or relative URL. If relative, it should be relative to the webpage
 * that is displaying the ban manager, in most cases that will be index.php. 
 */
$url = "";
