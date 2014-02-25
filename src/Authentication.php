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
            // Debugging mode on, auto login as the first user
            $this->user_id = 1;
            $authenticated = true;
        } else {
            // Attempt to authenticate the user
            $username = $this->getLoggedInName();

            if ($username === false) {
                // User is not logged into wordpress
                // Expire the cookie
                setcookie($this->settings->getCookieName(), "", time() - 3600);

            } else {
                // User is logged into wordpress
                if (isset($_COOKIE[$this->settings->getCookieName()])) {
                    // Get the user information from the ban manager cookie
                    $user_info = explode("|", $_COOKIE[$this->settings->getCookieName()]);

                    // Make sure the user hasn't changed
                    if ($user_info[2] == $username) {
                        $this->user_id = $user_info[0];
                        $authenticated = true;
                    }
                } else {
                    // Get the user information from the database
                    $user_info = $this->getModeratorInfo($db, $username);

                    // User is a moderator+
                    if($user_info !== false) {
                        // Store the user information
                        setcookie(BM_COOKIE, implode("|", $user_info), 0, "/");

                        $this->user_id = $user_info[0];
                        $authenticated = true;
                    }
                }
            }
        }

        return $authenticated;
    }

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
     * @return mixed FALSE if the user is not a moderator/admin or is not logged into
     * wordpress, otherwise it return an array where index zero is the user id,
     * index one is the user's rank, and index two is the user's name.
     */
    public function getModeratorInfo(Database $db, $moderator_name)
    {
        if (empty($moderator_name)) {
            return false;
        }

        $info = false;

        $moderator_name = $db->sanitize($moderator_name);

        // Request the user id from the database
        if (!$db->isConnected()) {
            $db->connect($this->settings);
        }

        $row = $db->querySingleRow(
            "SELECT `users`.`user_id`, `rank`.`name` AS rank
             FROM `users`
             LEFT JOIN `rank` ON (`users`.`rank` = `rank`.`rank_id`)
             WHERE `username` = '$moderator_name'",
            'Moderator not found.'
        );

        // Only store the information of admins/moderators
        if ($row['rank'] == 'Admin' || $row['rank'] == 'Moderator') {
            $info = array($row['id'], $row['rank'], $moderator_name);
        }

        return $info;
    }

}
