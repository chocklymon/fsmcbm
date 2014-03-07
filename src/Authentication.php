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

/**
 * Handles authenticating and permissioning the logged in users.
 * @author Curtis Oakley
 */
class Authentication
{
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
     * @param Settings $settings The settings to use.
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Authenticates the user.
     * @param Database $db
     * @param Output $output
     * @return bool <tt>true</tt> if the user was authenticated.
     */
    public function authenticate(Database $db)
    {
        $authenticated = false;

        if ($this->settings->debugMode()) {
            // TODO need a separate setting for this.
            // Debugging mode on, auto login as the first user
            $this->user_id = 1;
            $authenticated = true;
        } else {
            // TODO this is extremly insecure, need to find a better way.
            // Attempt to authenticate the user via Wordpress
            $username = $this->getLoggedInName();

            if ($username === false) {
                // User is not logged into wordpress
                // Expire the cookie
                setcookie($this->settings->getCookieName(), "", time() - 3600);

            } else {
                // User is logged into wordpress
                if (isset($_COOKIE[$this->settings->getCookieName()])) {
                    // Get the user information from the ban manager cookie
                    $user_info = json_decode($_COOKIE[$this->settings->getCookieName()], true);

                    // Make sure the user hasn't changed
                    if ($user_info['username'] == $username) {
                        $this->user_id = $user_info['id'];
                        $authenticated = true;
                    }
                } else {
                    // Get the user information from the database
                    $user_info = $this->getModeratorInfo($db, $username);

                    // User is a moderator+
                    if($user_info !== false) {
                        // Store the user information
                        setcookie(BM_COOKIE, json_encode($user_info), 0, "/");

                        $this->user_id = $user_info['id'];
                        $authenticated = true;
                    }
                }
            }
        }

        return $authenticated;
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
     * Gets name of the user logged into wordpress.
     * @return mixed FALSE if the user is not not logged into wordpress, otherwise
     * it return an unsanitized string contain the logged in user's name.
     */
    public function getLoggedInName()
    {
        // Search the wordpress cookies for the logged in user name
        $keys = array_keys($_COOKIE);
        foreach($keys as &$key) {
            if (strncmp("wordpress_logged_in_", $key, 20) === 0) {
                // Extract user name from the cookie
                $value = $_COOKIE[$key];
                return substr($value, 0, strpos($value, "|"));
            }
        }

        return false;
    }

    /**
     * Gets the information for the moderator using the ban manager.
     * @return mixed boolean <tt>false</tt> if the user is not a moderator/admin,
     * otherwise it returns and array with the user's information.
     */
    public function getModeratorInfo(Database $db, $moderator_name)
    {
        if (empty($moderator_name)) {
            return false;
        }

        $info = false;

        // Sanitize the provided user name
        if (!$db->isConnected()) {
            $db->connect($this->settings);
        }
        $moderator_name = $db->sanitize($moderator_name);

        // Request the user id from the database
        $row = $db->querySingleRow(
            "SELECT `users`.`user_id`, `rank`.`name` AS rank
             FROM `users`
             LEFT JOIN `rank` ON (`users`.`rank` = `rank`.`rank_id`)
             WHERE `username` = '$moderator_name'",
            'Moderator not found.'
        );

        // Only store the information of admins/moderators
        if ($row['rank'] == 'Admin' || $row['rank'] == 'Moderator') {
            $info = array(
                'id'=>$row['id'],
                'rank'=>$row['rank'],
                'username'=>$moderator_name,
            );
        }

        return $info;
    }

}
