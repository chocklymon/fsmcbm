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
     * The name of the hash algorithm to use when hashing passwords and checking
     * HMAC's. The database can hold up to 64 bytes for the password hash, so
     * this algorithm should not produce a hash longer than this.
     */
    const HASH_ALGO = "sha1";

    /**
     * @var Database
     */
    private $db;

    /**
     * @var FilteredInput
     */
    private $input;

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
     * @param FilteredInput $input The post input
     */
    public function __construct(Database $db, Settings $settings, FilteredInput $input)
    {
        $this->db = $db;
        $this->settings = $settings;
        $this->input = $input;
    }

    /**
     * Authenticates the user.
     * @return bool <tt>true</tt> if the user was authenticated.
     */
    public function authenticate()
    {
        // Figure out what kind of authentication we will be using
        if ($this->isAPIRequest()) {
            // API call
            $user_id = $this->authenticateAPIRequest();
        } else if ($this->settings->useWPLogin()) {
            // Authenticate using WordPress
            $user_id = $this->authenticateUsingWP();
        } else {
            // Authenticate using our authentication
            $user_id = $this->authenticateUser();
        }

        if ($user_id != null) {
            // User validated
            $this->user_id = $user_id;
            $this->cleanUpNonce();
            return true;
        }

        return false;
    }

    /**
     * Authenticates that an API request is valid.
     * @return int The user ID or <tt>null</tt> if the user doesn't validate.
     */
    private function authenticateAPIRequest()
    {
        $accessor_key = $this->settings->getAccessorKey($this->input->accessor_token);
        if ($accessor_key !== false && $this->validatePost($accessor_key)) {
            // Use the universally unique identifier to get the user info
            $uuid = $this->db->sanitize(pack('H*', $this->input->uuid));
            $row = $this->db->querySingleRow(
                "SELECT `users`.`user_id`, `rank`.`name` AS rank
                 FROM `users`
                 LEFT JOIN `rank` ON (`users`.`rank` = `rank`.`rank_id`)
                 WHERE `uuid` = '$uuid'",
                'Moderator not found.'
            );
            if ($row['rank'] == 'Admin' || $row['rank'] == 'Moderator') {
                return $row['user_id'];
            } else {
                Log::debug('Failed Login Attempt: Bad API user UUID');
            }
        }
        return null;
    }

    /**
     * Authenticate a user using the ban manager cookie.
     * @return int The user ID or <tt>null</tt> if the user doesn't validate.
     */
    private function authenticateUser()
    {
        $cookie = $this->getCookie();
        if ($this->isCookieValid($cookie)) {
            // Cookie valid, return the ID from the cookie
            return (int) $cookie[0];
        } else if (!empty($cookie)) {
            // Cookie provided, but invalid. Expire it now
            $this->expireCookie();
        }
        return null;
    }

    /**
     * Attempts to authenticate the user by checking if they are logged into
     * wordpress.
     * @return int The user ID or <tt>null</tt> if the user doesn't validate.
     * @throws AuthenticationException If there is a problem loading wordpress.
     */
    private function authenticateUsingWP()
    {
        // Load the needed wordpress functions
        $wp_load = $this->settings->getWordpressLoadFile();
        if (empty($wp_load) || !file_exists($wp_load)) {
            throw new AuthenticationException('Configuration error. Unable to authenticate through wordpress!');
        }
        require_once($wp_load);

        if (is_user_logged_in()) {// Wordpress function
            $wp_current_user = wp_get_current_user();// Wordpress function
            $moderator_name = $wp_current_user->user_login;

            $moderator_info = $this->getModeratorInfo($moderator_name);
            if ($moderator_info !== false) {
                // User is logged into wordpress, and is a moderator
                return $moderator_info['id'];
            }
        }
        return null;
    }

    /**
     * Deletes old records from the nonce table.
     * @param int $chance The chance that a cleanup will actually occur. This
     * specifies the ration (<tt>1/$chance</tt) of times that a cleanup will occur.
     * One means always clean up. Defaults to four (25% chance of cleanup).
     */
    public function cleanUpNonce($chance = 4)
    {
        if (mt_rand(1, $chance) == 1) {
            $date_time = $this->db->getDate(time() - 86400);// One day
            $sql = "DELETE FROM `auth_nonce` WHERE `timestamp` < '{$date_time}'";
            $this->db->query($sql);
            Log::debug('Authentication: Nonce cleared');
        }
    }

    /**
     * Sets the cookie as expired.
     */
    private function expireCookie()
    {
        setcookie($this->settings->getCookieName(), "", time() - 3600);
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
     * Logs in the user.
     * @return boolean true if the user was logged in correctly.
     */
    public function loginUser()
    {
        if ($this->input->exists('username') && $this->input->exists('password')) {
            $username = $this->db->sanitize($this->input->username);
            $password = $this->db->sanitize(hash(self::HASH_ALGO, $this->input->password, true));

            $sql = <<<EOF
SELECT `users`.`user_id`, `rank`.`name` AS rank
FROM
`users`
LEFT JOIN `passwords` ON (`users`.`user_id` = `passwords`.`user_id`)
LEFT JOIN `rank` ON (`users`.`rank` = `rank`.`rank_id`)
WHERE
     `users`.`username` = '{$username}'
AND  `passwords`.`password_hash` = '{$password}'
EOF;
            // Query a single row, this will throw an exception if a single row isn't found
            $result = $this->db->querySingleRow($sql);

            // User found and password matches, set the login cookie and return true
            $this->setCookie($result['user_id'], $username);
            return true;
        }
        return false;
    }

    /**
     * Returns if the input indicates that it is an API request.
     * @return boolean true if the input data indicates we have an API request.
     */
    public function isAPIRequest()
    {
        return $this->input->exists('accessor_token') && $this->input->exists('hmac') && $this->input->exists('uuid');
    }

    /**
     * Checks if the provided cookie data is valid.
     * @param array $cookie The cookie data as an array.
     * @return boolean true if the cookie is valid
     * @throws AuthenticationException If the cookie key is not set in the
     * configuration.
     */
    private function isCookieValid($cookie)
    {
        // The cookie should have four parts
        if (!empty($cookie) && count($cookie) == 4) {
            // Check if the logout time has been reached, if there is one
            $logout_time = $this->settings->getLogoutTime();
            if (($logout_time > 0) && (time() - $cookie[2] > $logout_time)) {
                // Max login time has been reached.
                return false;
            }

            // Check the cookies HMAC
            $value = $cookie[0] . $cookie[1] . $cookie[2];
            $key = $this->settings->getCookieKey();
            if (empty($key)) {
                throw new AuthenticationException('Configuration error.');
            }
            return hash_hmac(self::HASH_ALGO, $value, $key) == $cookie[3];
        }
        return false;
    }

    /**
     * Validates that all the post data's HMAC generated with the provided
     * hmac_key matches the HMAC in the post.
     * @param string $hmac_key
     * @return boolean TRUE if the HMAC in the post is valid given the provided
     * $hmac_key.
     */
    private function isHMACValid($hmac_key)
    {
        $msg = '';
        $hmac = '';
        foreach ($this->input as $key => $value) {
            if ($key == 'hmac') {
                $hmac = $value;
            } else {
                $msg .= $key . $value;
            }
        }

        $valid_hmac = hash_hmac(self::HASH_ALGO, $msg, $hmac_key) == $hmac;
        if (!$valid_hmac) {
            Log::debug('Failed Login Attempt: Bad API HMAC');
        }
        return $valid_hmac;
    }

    /**
     * Checks if the nonce inside of the input is valid
     * @return boolean true if the nonce is valid.
     */
    private function isNonceValid()
    {
        if ($this->input->exists('nonce')) {
            // Check the nonce
            // Get the md5 hash of the nonce (using md5 hash so the nonce will always be 16 bytes long)
            $nonce = $this->db->sanitize(hash('md5', $this->input->nonce, true));
            $sql = "SELECT COUNT(*) AS count FROM `auth_nonce` WHERE `nonce` = '{$nonce}'";
            $row = $this->db->querySingleRow($sql);
            if ($row['count'] == 0) {
                // Nonce hasn't been used, save it and return true
                $date_time = $this->db->getDate();
                $sql = "INSERT INTO `auth_nonce` (`nonce`, `timestamp`) VALUES ('{$nonce}', '{$date_time}')";
                $this->db->query($sql);

                return true;
            }
        }
        Log::debug('Failed Login Attempt: Bad Nonce');
        return false;
    }

    /**
     * Checks if the timestamp inside of the input is valid.
     * @return boolean true if the timestamp falls within the acceptable range
     */
    private function isTimestampValid()
    {
        if ($this->input->exists('timestamp')) {
            // Validate the timestamp
            $timestamp = strtotime($this->input->timestamp);
            $current_time = time();

            // The timestamp can be valid for five minutes from the current time.
            // This gives a buffer to compensate for time differences and network latency.
            $valid_timestamp = $timestamp > ($current_time - 300) && $timestamp < ($current_time + 300);
            if (!$valid_timestamp) {
                Log::debug('Failed Login Attempt: Bad API timestamp');
            }
            return $valid_timestamp;
        }
        return false;
    }

    /**
     * Gets the authentication cookie.
     * @return array The cookie's data as an array, or null if the cookie
     * doesn't exist.
     */
    private function getCookie()
    {
        $cookie_name = $this->settings->getCookieName();
        if (isset($_COOKIE[$cookie_name])) {
            return explode('|', $_COOKIE[$cookie_name]);
        } else {
            return null;
        }
    }

    /**
     * Sets the authentication cookie.
     * @param int $user_id The ID of the user.
     * @param string $username The user's name.
     * @throws AuthenticationException If the cookie key is not set in the
     * configuration.
     */
    private function setCookie($user_id, $username)
    {
        // Cookie: id|username|timestamp|hmac
        $key = $this->settings->getCookieKey();
        if (empty($key)) {
            throw new AuthenticationException('Configuration error.');
        }

        $cookie = array($user_id, $username, time());
        $cookie[] = hash_hmac(self::HASH_ALGO, implode("", $cookie), $key);
        $cookie_value = implode("|", $cookie);

        setcookie($this->settings->getCookieName(), $cookie_value, 0, '/', null, false, true);
    }

    /**
     * Indicate if wordpress should be loaded for use with authentication.
     * @return boolean true if Wordpress should be loaded
     */
    public function shouldLoadWordpress()
    {
        return $this->settings->useWPLogin() && !$this->isAPIRequest();
    }

    /**
     * Validates that the post data is valid.
     * More specifically checks that the post's nonce hasn't been used, that the
     * timestamp is in range, and that the HMAC is correct for the given key.
     * @param string $hmac_key
     * @return boolean <tt>true</tt> if the post passes validation.
     */
    private function validatePost($hmac_key)
    {
        return $this->isTimestampValid() && $this->isHMACValid($hmac_key) && $this->isNonceValid();
    }
}
