<?php
/* Copyright (c) 2014 Curtis Oakley
 * http://chockly.org/
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/* =============================
 * GLOBAL FUNCTIONS AND SETTINGS
 * =============================
 */

require_once('Settings.php');
require_once('Output.php');
require_once('Database.php');
require_once('Controller.php');

/** The ID of the current user. */
$moderator = 0;



/* =============================
 *        VERIFY USER
 * =============================
 */
$settings = new Settings();
$output = new Output($settings);

if ($settings->debugMode()) {
    // Debugging mode on, auto login as the first user
    $moderator = 1;
} else {
    if( getLoggedInName() === FALSE ) {
        // User is not logged in, set the ban manager cookie as expired.
        setcookie($settings->getCookieName(), "", time() - 3600);
        $output->error("Not logged in.");
        exit();
    } else if (isset($_COOKIE[$settings->getCookieName()])) {

        $user_info = explode("|", $_COOKIE[$settings->getCookieName()]);

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
            $output->error("Not logged in.");
            exit();
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

$db = null;

try {
    // Get the connection to the database
    $db = new Database($settings);
    $actions = new Controller($db, $output);

    if (isset($_GET['term'])) {

        $actions->autoComplete();

    } else if (isset($_GET['lookup'])) {

        $actions->retrieveUserData();

    } else if (isset($_GET['add_user'])) {

        $actions->addUser();

    } else if (isset($_GET['add_incident'])) {

        $actions->addIncident($moderator);

    } else if (isset($_GET['get'])) {
        // Tab contents requested

        if ($_GET['get'] == 'bans') {

            $actions->getBans();

        } else if ($_GET['get'] == 'watchlist') {

            $actions->getWatchlist();

        }
    } else if (isset($_GET['search'])) {

        $actions->search();

    } else if (isset($_GET['update_user'])) {

        $actions->updateUser();

    } else if (isset($_GET['update_incident'])) {

        $actions->updateIncident();

    }
} catch (DatabaseException $ex) {
    $output->exception(
        $ex,
        array(
            'errno' => $ex->getCode(),
            'error' => $ex->getErrorMessage(),
            'query' => $ex->getQuery(),
        )
    );
} catch (InvalidArgumentException $ex) {
    $output->exception($ex);
}

if (!is_null($db)) {
    $db->close();
}
