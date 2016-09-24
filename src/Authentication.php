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
     * The name of the hash algorithm to use when generating and checking HMAC's.
     */
    const HMAC_ALGO = "sha1";

    /**
     * The password algorithm to use for generating and checking password hashes.
     */
    const PASSWORD_ALGO = PASSWORD_DEFAULT;

    /**
     * The max allowed length of passwords.
     */
    const MAX_PASSWORD_LENGTH = 72;

    const NUM_COOKIE_PARTS = 4;

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
        } else if ($this->settings->getAuthenticationMode() === 'none') {
            $user_id = 1;
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
            $uuid = $this->db->sanitize($this->input->accessor_id);// TODO handle this the same as all uuids
            $row = $this->db->querySingleRow(
                "SELECT `moderators`.`user_id`
                 FROM `users`
                 LEFT JOIN `moderators` ON (`users`.`user_id` = `moderators`.`user_id`)
                 WHERE `users`.`uuid` = '$uuid'",
                'Moderator not found.'
            );
            if ($row['user_id']) {
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
                return $moderator_info['user_id'];
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
     * @return array|bool The moderator user_id and username in an array, or FALSE if the moderator name or doesn't
     * exist in the database.
     */
    public function getModeratorInfo($moderator_name)
    {
        if (empty($moderator_name)) {
            return false;
        }

        // Sanitize the provided user name
        $moderator_name = $this->db->sanitize($moderator_name);

        try {
            // Request the user id from the database
            $row = $this->db->querySingleRow(
                "SELECT `moderators`.`user_id`, `moderators`.`username`
                 FROM `moderators`
                 WHERE `moderators`.`username` = '$moderator_name'",
                'Moderator not found.'
            );

            return $row;
        } catch (DatabaseException $ex) {
            Log::debug('Problem querying for moderator', $ex);
            return false;
        }
    }

    /**
     * Logs in the user.
     * @return boolean true if the user was logged in correctly.
     */
    public function loginUser()
    {
        if ($this->input->exists('username') && $this->input->exists('password')) {
            $username = $this->db->sanitize($this->input->username);
            // TODO: https://www.owasp.org/index.php/Password_Storage_Cheat_Sheet
            // See also: http://php.net/manual/en/function.password-hash.php

            $sql = <<<EOF
SELECT `moderators`.`user_id`, `moderators`.`username`, `moderators`.`needs_password_change`, `moderators`.`password`
FROM
`moderators`
WHERE
     `moderators`.`username` = '{$username}'
EOF;
            try {
                // Find the user
                $result = $this->db->querySingleRow($sql);

                if ($this->passwordMatches($this->input->password, $result['password'])) {
                    if ($this->passwordNeedsRehash($result['password'])) {
                        $this->setUserPassword($result['user_id'], $this->input->password);
                    }

                    // User found and password matches, set the login cookie and return true
                    $this->setCookie($result['user_id'], $result['username']);
                }
                return true;
            } catch (DatabaseException $ex) {
                Log::info(__LINE__, array('Invalid user login attempt', $ex->getMessage()));
            }
        }
        return false;
    }

    /**
     * Returns if the input indicates that it is an API request.
     * @return boolean true if the input data indicates we have an API request.
     */
    public function isAPIRequest()
    {
        return $this->input->exists('accessor_token') && $this->input->exists('hmac') && $this->input->exists('accessor_id');
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
            if ($logout_time > 0) {
                // Sessions are limited to a given duration
                $logged_in_time = (int) $cookie[2];
                if (time() - $logged_in_time > $logout_time) {
                    // Max login time has been reached.
                    return false;
                }
            }

            // Check the cookies HMAC
            $value = $cookie[0] . $cookie[1] . $cookie[2];
            $key = $this->settings->getCookieKey();
            if (empty($key)) {
                throw new AuthenticationException('Configuration error.');
            }
            $expected_hmac = hash_hmac(self::HMAC_ALGO, $value, $key, true);
            return $this->hashEquals($expected_hmac, $cookie[3]);
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

        $expected_hmac = hash_hmac(self::HMAC_ALGO, $msg, $hmac_key);
        $valid_hmac =  $this->hashEquals($expected_hmac, $hmac);
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

            // The timestamp can be valid for ten minutes from the current time.
            // This gives a buffer to compensate for time differences and network latency.
            $valid_timestamp = $timestamp > ($current_time - 600) && $timestamp < ($current_time + 600);
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
            $cookie = base64_decode($_COOKIE[$cookie_name], true);
            if ($cookie) {
                return explode('|', $cookie, self::NUM_COOKIE_PARTS);
            }
        }
        return null;
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

        $timestamp = time();
        $value = $user_id . $username . $timestamp;
        $hmac = hash_hmac(self::HMAC_ALGO, $value, $key, true);
        $cookie_str = "{$user_id}|{$username}|{$timestamp}|${hmac}";
        $cookie_value = base64_encode($cookie_str);

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

    /**
     * Creates a new password hash using a strong one-way hashing algorithm.
     * This calls the function password_hash().
     * @param string $password The user's password.
     * @return bool|string Returns the hashed password, or FALSE on failure.
     */
    protected function hashPassword($password)
    {
        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            return false;
        }
        return password_hash($password, self::PASSWORD_ALGO);
    }

    /**
     * Verifies that the given hash matches the given password.
     * This calls the function password_verify().
     * @param string $password The user's password.
     * @param string $hash A hash created by password_hash().
     * @return bool Returns TRUE if the password and hash match, or FALSE otherwise.
     */
    protected function passwordMatches($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * This function checks to see if the supplied hash implements the algorithm and options provided. If not, it is
     * assumed that the hash needs to be rehashed.
     * This calls the function password_needs_rehash.
     * @param string $hash A hash created by password_hash().
     * @return boolean Returns TRUE if the hash should be rehashed, or FALSE otherwise.
     */
    protected function passwordNeedsRehash($hash)
    {
        return password_needs_rehash($hash, self::PASSWORD_ALGO);
    }

    /**
     * @param $user_id
     * @param $password
     */
    private function setUserPassword($user_id, $password)
    {
        $hash = $this->hashPassword($password);
        if ($hash === false) {
            throw new AuthenticationException('Failed to hash the given password');
        }
        $hash = $this->db->sanitize($hash);
        $user_id = $this->db->sanitize($user_id, true);
        $query = "UPDATE `moderators` SET `password` = '$hash' WHERE `user_id` = '$user_id'";
        $this->db->query($query);
    }

    /**
     * Compares two strings.
     * If PHP >= 5.6 this calls the builtin function hash_equals(), otherwise it falls back to a timing resistant
     * comparison algorithm.
     * @param string $known_string The string of known length to compare against
     * @param string $user_string The user-supplied string
     * @return bool Returns TRUE when the two strings are equal, FALSE otherwise.
     */
    private function hashEquals($known_string , $user_string)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($known_string, $user_string);
        } else {
            // Timing attack resistant hash equals replacement, not perfect but works fairly well.
            if(strlen($known_string) != strlen($user_string)) {
                return false;
            } else {
                $res = $known_string ^ $user_string;
                $ret = 0;
                for($i = strlen($res) - 1; $i >= 0; $i--) {
                    $ret |= ord($res[$i]);
                }
                return $ret === 0;
            }
        }
    }
}
