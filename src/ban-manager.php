<?php
/* Copyright (c) 2014-2016 Curtis Oakley
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

use Chocklymon\fsmcbm\Log;

/* =============================
 *           IMPORTS
 * =============================
 */
// We just need the composer autoloader
require_once('vendor/autoload.php');


/* =============================
 *        SETUP
 * =============================
 */

// Make sure we are using UTF-8
mb_internal_encoding("UTF-8");

// Get an instance of the various needed classes
$input = new Chocklymon\fsmcbm\FilteredInput();
$settings = new Chocklymon\fsmcbm\Settings();
$output = new Chocklymon\fsmcbm\Output($settings);

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
    if ($endpoint === 'get_config.js') {
        // Return the javascript configuration
        $config = array(
            'authMode' => $settings->getAuthenticationMode(),
        );
        if ($config['authMode'] == 'auth0') {
            $config['clientId'] = $settings->get('auth0_client_id');
            $config['domain'] = $settings->get('auth0_domain');
        }

        header('Content-Type: application/javascript');
        echo '(function() {window.bmConfig = ' . json_encode($config) . ';})();';
        exit();
    }

    $db = new Chocklymon\fsmcbm\Database($settings);
    $auth = new Chocklymon\fsmcbm\Authentication($db, $settings, $input);


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

    // Authenticate the request
    $authenticated = $auth->authenticate();

    if ($endpoint === 'authenticated') {
        // Request to check if the user is logged in
        $output->append($authenticated, 'authenticated');
        $output->append($auth->shouldLoadWordpress(), 'use-wordpress');
        $output->reply();
    } else if ($authenticated === false) {
        // Authentication failed
        Log::debug('ban-manager: Authentication failed');
        header('HTTP/1.1 401 UNAUTHORIZED');
        $output->append($authenticated, 'authenticated');
        $output->error("Not logged in.");
    } else {
        // Authentication successful, continue with the request
        $user_id = $auth->getUserId();

        /* =============================
         *      PERFORM ACTIONS
         * =============================
         */

        // Get an instance of the controller
        $actions = new Chocklymon\fsmcbm\Controller($db, $output);

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
            case 'get_worlds':
                $worlds = $settings->get('worlds');
                foreach ($worlds as $world) {
                    $output->append($world);
                }
                $output->reply();
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
} catch (Chocklymon\fsmcbm\AuthenticationException $ex) {
    Log::error("ban-manager: Authentication exception", $ex);
    $output->exception($ex);
} catch (Chocklymon\fsmcbm\DatabaseException $ex) {
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
