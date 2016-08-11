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

require_once('Log.php');
require_once('FilteredInput.php');
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
$input = new FilteredInput();
$settings = new Settings();
$output = new Output($settings);

// Initialize the logger
Log::initialize($settings);

try {
    // Make sure that we have an action before continuing
    $endpoint = filter_input(INPUT_GET, 'action');
    if (is_null($endpoint)) {
        $output->error('No endpoint provided');
        Log::debug('ban-manager: Invalid endpoint', $endpoint);
        exit();
    }

    $db = new Database($settings);
    $auth = new Authentication($db, $settings, $input);


    // If we are using wordpress load it now
    // Some plugins ('bbPress2 shortcode whitelist' and possibly others) cause a fatal
    // error when this is included inside of authentication.
    if ($auth->shouldLoadWordpress()) {
        $wp_load_file = $settings->getWordpressLoadFile();
        if (empty($wp_load_file) || !file_exists($wp_load_file)) {
            Log::alert("ban-manager: No wordpress configuration file provided, or file doesn't exist");
            $output->error('Configuration error. Unable to authenticate through wordpress!');
            exit();
        }
        require_once($wp_load_file);
    }

    if ($endpoint === 'login') {
        // Try to login the user
        if ($auth->loginUser()) {
            $output->success();
        } else {
            $output->error('Login Failed');
        }
    } else {
        // Authenticate the request
        if ($auth->authenticate() === false) {
            // Authentication failed
            Log::debug('ban-manager: Authentication failed');
            $output->error("Not logged in.");
        } else {
            // Authentication successful, continue with the request
            $user_id = $auth->getUserId();


            /* =============================
             *      PERFORM ACTIONS
             * =============================
             */

            // Get an instance of the controller
            $actions = new Controller($db, $output);

            switch ($endpoint) {
                case 'auto_complete':
                    $actions->autoComplete($input);
                    break;
                case 'lookup':
                    $actions->retrieveUserData($input);
                    break;
                case 'add_user':
                    $actions->addUser($user_id, $input);
                    break;
                case 'add_incident':
                    $actions->addIncident($user_id, $input);
                    break;
                case 'delete_incident':
                    $actions->deleteIncident($input);
                    break;
                case 'get_bans':
                    $actions->getBans();
                    break;
                case 'get_ranks':
                    $ranks = $actions->getRanks();
                    foreach($ranks as $rank) {
                        $output->append(
                            array('value' => $rank['rank_id'], 'label' => $rank['name'])
                        );
                    }
                    $output->reply();
                    break;
                case 'get_watchlist':
                    $actions->getWatchlist();
                    break;
                case 'search':
                    $actions->search($input);
                    break;
                case 'upsert_username':
                    $actions->upsertUsername($user_id, $input);
                    break;
                case 'update_user':
                    $actions->updateUser($user_id, $input);
                    break;
                case 'update_incident':
                    $actions->updateIncident($input);
                    break;
            }
        }
    }
} catch (AuthenticationException $ex) {
    Log::error("ban-manager: Authentication exception", $ex);
    $output->exception($ex);
} catch (DatabaseException $ex) {
    Log::error("ban-manager: Database exception", $ex);
    $output->exception(
        $ex,
        array(
            'errno' => $ex->getCode(),
            'error' => $ex->getErrorMessage(),
            'query' => $ex->getQuery(),
        )
    );
} catch (InvalidArgumentException $ex) {
    Log::error("ban-manager: Invalid argument exception", $ex);
    $output->exception($ex);
}

// Close the database connection
if (isset($db)) {
    $db->close();
}
