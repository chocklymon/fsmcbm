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

namespace Chocklymon\fsmcbm;

use Auth0\SDK\JWTVerifier;

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
        } else {
            switch ($this->settings->getAuthenticationMode()) {
                case 'wordpress':
                    $user_id = $this->authenticateUsingWP();
                    break;
                case 'auth0':
                    $user_id = $this->authenticateUsingAuth0();
                    break;
                case 'none':
                    $user_id = 1;
                    break;
                default:
                    throw new ConfigurationException('Invalid authentication mode');
            }
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
            $uuid = Util::formatUUID($this->input->accessor_id);
            $uuid = $this->db->sanitize($uuid);
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
     * Authenticate a user using auth0
     * @return int The user ID or <tt>null</tt> if the user doesn't validate.
     */
    private function authenticateUsingAuth0()
    {
        // TODO replace apache_request_headers with other method in case the function doesn't exist
        $requestHeaders = apache_request_headers();
        $authorizationHeader = $requestHeaders['Authorization'];

        if ($authorizationHeader == null) {
            return null;
        }

        // validate the token
        $token = str_replace('Bearer ', '', $authorizationHeader);
        $secret = $this->settings->get('auth0_client_secret');
        $client_id = $this->settings->get('auth0_client_id');
        $decoded_token = null;
        try {
            $verifier = new JWTVerifier([
                'valid_audiences' => [$client_id],
                'client_secret' => $secret
            ]);

            $decoded_token = $verifier->verifyAndDecode($token);
        } catch(\Auth0\SDK\Exception\CoreException $e) {
            return null;
        }

        $id = $this->db->sanitize($decoded_token->sub);
        $result = $this->db->query(
            "SELECT user_id FROM user_authentication WHERE external_id = '{$id}'"
        );

        if ($result->num_rows == 0) {
            // Get the username from Auth0
            $auth0Api = new \Auth0\SDK\API\Authentication($this->settings->get('auth0_domain'), $client_id, $secret);
            $profile = $auth0Api->tokeninfo($token);

            $user_id = $this->getUserIdFromName($profile['user_metadata']['minecraft_username']);

            if ($user_id !== false) {
                $this->db->query(
                    "INSERT INTO user_authentication (user_id, external_id) VALUES ({$user_id}, '{$id}')"
                );
            }
        } else {
            $row = $result->fetch_assoc();
            $user_id = $row['user_id'];
        }
        $result->free();

        return $user_id;
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

            $needs_level = 'level_' . $this->settings->get('wp_minimum_user_level');
            if ($wp_current_user->allcaps[$needs_level]) {
                $moderator_name = $wp_current_user->user_login;

                $user_id = $this->getUserIdFromName($moderator_name);

                if ($user_id !== false) {
                    // User is logged into wordpress, and is a moderator
                    return $user_id;
                }
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
    public function getUserIdFromName($moderator_name)
    {
        if (empty($moderator_name)) {
            return false;
        }

        $username = $this->db->sanitize($moderator_name);
        $result = $this->db->query(
            "SELECT user_id FROM user_aliases WHERE username = '{$username}'"
        );

        if ($result->num_rows !== 1) {
            // TODO - How to handle this?
            Log::error('Multiple or no username results found!', $username);
            $user_id = false;
        } else {
            $row = $result->fetch_assoc();
            $user_id = $row['user_id'];
        }
        $result->free();
        return $user_id;
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
     * Indicate if wordpress should be loaded for use with authentication.
     * @return boolean true if Wordpress should be loaded
     */
    public function shouldLoadWordpress()
    {
        return $this->settings->getAuthenticationMode() == 'wordpress' && !$this->isAPIRequest();
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
