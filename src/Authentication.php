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

require_once('AuthenticationException.php');

/**
 * Handles authenticating and permissioning the logged in users.
 * @author Curtis Oakley
 */
class Authentication
{
    /**
     * @var Database
     */
    private $db;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var int
     */
    private $user_id;

    /**
     * Create a new authenticator class.
     * @param Database $db The database instance.
     * @param Settings $settings The settings to use.
     */
    public function __construct(Database $db, Settings $settings)
    {
        $this->db = $db;
        $this->settings = $settings;
    }

    /**
     * Authenticates the user.
     * @return bool <tt>true</tt> if the user was authenticated.
     */
    public function authenticate()
    {
        $authenticated = false;

        if (isset($_POST['accessor']) && isset($_POST['nonce']) && isset($_POST['timestamp']) && isset($_POST['hmac'])) {
            // API call
            $authenticated = $this->authenticateAPIRequest();
        } else if ($this->settings->useWPLogin()) {
            // Authenticate using WordPress
            $authenticated = $this->authenticateUsingWP();
        } else {
            // Authenticate using our authentication
            $authenticated = $this->authenticateUser();
        }

        return $authenticated;
    }

    /**
     * Authenticates that an API request is valid.
     * @return boolean <tt>true</tt> if the post's hmac, nonce, and timestamp
     * are valid for the accessor.
     * @throws AuthenticationException If a database exception occurs.
     */
    private function authenticateAPIRequest()
    {
        // Validate the payload
        $timestamp = strtotime($_POST['timestamp']);
        $current_time = time();

        // Give the time a ten minute range from the curren time to be valid
        if ($timestamp > ($current_time - 600) && $timestamp < ($current_time + 600)) {

            $msg = '';
            $hmac = '';
            foreach ($_POST as $key => $value) {
                if ($key == 'hmac') {
                    $hmac = $value;
                } else {
                    $msg .= $key . $value;
                }
            }
            $key = $this->settings->getAccessorKey($_POST['accessor']);
            if ($key !== false && hash_hmac('sha1', $msg, $key) == $hmac) {
                // HMAC valid
                try {
                    // Check the nonce
                    $nonce = $this->db->sanitize($_POST['nonce'], true);
                    $sql = "SELECT COUNT(*) FROM `auth_nonce` WHERE `nonce` = '{$nonce}'";
                    $row = $this->db->querySingleRow($sql);
                    if ($row[0] == 0) {
                        // Nonce hasn't been used, save it and return true
                        $date_time = $this->db->getDate($current_time);
                        $sql = "INSERT INTO `auth_nonce` (`nonce`, `timestamp`) VALUES ('{$nonce}', '{$date_time}')";
                        $this->db->query($sql);

                        $this->cleanUpNonce();

                        // TODO user ID
                        return true;
                    }
                } catch (DatabaseException $ex) {
                    throw new AuthenticationException("Authentication failed due to database issue.", $ex->getCode(), $ex);
                }
            }
        }

        return false;
    }

    private function authenticateUser()
    {
        // TODO
    }

    /**
     * Attempts to authenticate the user by checking if they are logged into
     * wordpress.
     * @return boolean <tt>true</tt> if the user is logged into wordpress and
     * is a moderator.
     * @throws AuthenticationException If there is a problem loading wordpress.
     */
    private function authenticateUsingWP()
    {
        // Load the needed wordpress functions
        $wp_load = $this->settings->getWordpressLoadFile();
        if (empty($wp_load) || !file_exists($wp_load)) {
            throw new AuthenticationException("Configuration error. Unable to authenticate through wordpress!");
        }
        require_once($wp_load);

        if (is_user_logged_in()) {
            $wp_current_user = wp_get_current_user();
            $moderator_name = $wp_current_user->user_login;

            $moderator_info = $this->getModeratorInfo($moderator_name);
            if ($moderator_info !== false) {
                // User is logged into wordpress, and is a moderator
                $this->user_id = $moderator_info['id'];
                return true;
            }
        }
        return false;
    }

    /**
     * Get the user ID of the authenticated user.
     * @return int
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * Gets the information for the moderator using the ban manager.
     * @param string $moderator_name The name of the moderator to get the information of.
     * @return mixed boolean <tt>false</tt> if the user is not a moderator/admin,
     * otherwise it returns and array with the user's information.
     */
    public function getModeratorInfo($moderator_name)
    {
        if (empty($moderator_name)) {
            return false;
        }

        $info = false;

        // Sanitize the provided user name
        $moderator_name = $this->db->sanitize($moderator_name);

        // Request the user id from the database
        $row = $this->db->querySingleRow(
            "SELECT `users`.`user_id`, `rank`.`name` AS rank
             FROM `users`
             LEFT JOIN `rank` ON (`users`.`rank` = `rank`.`rank_id`)
             WHERE `username` = '$moderator_name'",
            'Moderator not found.'
        );

        // Only store the information of admins/moderators
        if ($row['rank'] == 'Admin' || $row['rank'] == 'Moderator') {
            $info = array(
                'id'=>$row['user_id'],
                'rank'=>$row['rank'],
                'username'=>$moderator_name,
            );
        }

        return $info;
    }

    /**
     * Deletes old records from the nonce table.
     */
    public function cleanUpNonce()
    {
        $date_time = $this->db->getDate(time() - 86400);// One day
        $sql = "DELETE FROM `auth_nonce` WHERE `timestamp` < '{$date_time}'";
        $this->db->query($sql);
    }
}
