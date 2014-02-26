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
 *           IMPORTS
 * =============================
 */

require_once('Settings.php');
require_once('Output.php');
require_once('Database.php');
require_once('Authentication.php');
require_once('Controller.php');



/* =============================
 *        SETUP
 * =============================
 */

// Make sure we are using UTF-8
mb_internal_encoding("UTF-8");

// Get an instance of the various needed classes
$settings = new Settings();
$output = new Output($settings);
$db = new Database($settings);
$auth = new Authentication($settings);

try {
    // Authenticate the user
    if ($auth->authenticate($db) === false) {
        $output->error("Not logged in.");
        exit();
    }



/* =============================
 *      PERFORM ACTIONS
 * =============================
 */

    // Make sure the database is connected
    if (!$db->isConnected()) {
        $db->connect($settings);
    }

    // Get an instance of the controller
    $actions = new Controller($db, $output);

    if (isset($_GET['term'])) {

        $actions->autoComplete();

    } else if (isset($_GET['lookup'])) {

        $actions->retrieveUserData();

    } else if (isset($_GET['add_user'])) {

        $actions->addUser();

    } else if (isset($_GET['add_incident'])) {

        $actions->addIncident($auth->getUserId());

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

// Close the database connection
if ($db->isConnected()) {
    $db->close();
}
