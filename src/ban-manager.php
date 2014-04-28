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

function startsWith($haystack, $needle)
{
    return $needle === "" || strpos($haystack, $needle) === 0;
}


/* =============================
 *        SETUP
 * =============================
 */

// Make sure we are using UTF-8
mb_internal_encoding("UTF-8");

// Get an instance of the various needed classes
$settings = new Settings();
$output = new Output($settings);

// If we are using wordpress load it now
// Some plugins ('bbPress2 shortcode whitelist' and possibly others) cause a fatal
// error when this is included inside of authentication.
if ($settings->useWPLogin()
    && !(isset($_POST['accessor_token']) && isset($_POST['hmac']) && isset($_POST['uuid']))
) {
    $wp_load_file = $settings->getWordpressLoadFile();
    if (empty($wp_load_file) || !file_exists($wp_load_file)) {
        $output->error('Configuration error. Unable to authenticate through wordpress!');
        exit();
    }
    require_once($wp_load_file);
}

try {
    // Make sure that we have an action before continuing
    $endpoint = filter_input(INPUT_GET, 'action');
    if (is_null($endpoint)) {
        $output->error('No endpoint provided');
        exit();
    }
    
    $db = new Database($settings);
    $auth = new Authentication($db, $settings);
    
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
                    $actions->autoComplete();
                    break;
                case 'lookup':
                    // Modified from http://victorblog.com/2012/12/20/make-angularjs-http-service-behave-like-jquery-ajax/
                    // TODO make this into a class for filtering inputs
                    if (isset($_SERVER['CONTENT_TYPE']) && startsWith($_SERVER['CONTENT_TYPE'], 'application/json')) {
                        $_POST = json_decode(file_get_contents('php://input'), true);
                    }
                    $actions->retrieveUserData();
                    break;
                case 'add_user':
                    $actions->addUser($user_id);
                    break;
                case 'add_incident':
                    $actions->addIncident($user_id);
                    break;
                case 'delete_incident':
                    $actions->deleteIncident();
                    break;
                case 'get_bans':
                    $actions->getBans();
                    break;
                case 'get_watchlist':
                    $actions->getWatchlist();
                    break;
                case 'search':
                    $actions->search();
                    break;
                case 'set_user_uuid':
                    $actions->updateUserUUID();
                    break;
                case 'update_user':
                    $actions->updateUser($user_id);
                    break;
                case 'update_incident':
                    $actions->updateIncident();
                    break;
            }
        }
    }
} catch (AuthenticationException $ex) {
    $output->exception($ex);
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
if (isset($db)) {
    $db->close();
}
