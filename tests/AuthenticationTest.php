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

use PHPUnit_Framework_TestCase;

/**
 * Test the Ban Manager Authentication
 * @author Curtis Oakley
 */
class AuthenticationTest extends PHPUnit_Framework_TestCase
{
    const USERNAME = 'JaneDoe';
    const USER_ID = 28;

    /**
     * @var MockSettings
     */
    private static $settings;

    /**
     * @var string
     */
    private static $wp_load_file;

    /**
     * @var Authentication
     */
    private $auth;

    /**
     * @var FilteredInput
     */
    private $input;

    public static function setUpBeforeClass()
    {
        self::$settings = new MockSettings();

        // Store the true wp_load_file
        self::$wp_load_file = self::$settings->getWordpressLoadFile();
    }

    protected function setUp()
    {
        // Set up a fake post
        $nonce = mt_rand(0, 400000);
        $timestamp = MockDatabase::getDate();

        $this->input = new FilteredInput(false);
        $this->input->nonce = $nonce;
        $this->input->timestamp = $timestamp;

        $this->auth = new Authentication($this->getNonceFreeMockDB(), self::$settings, $this->input);
    }

    protected function tearDown()
    {
        // Reset anything that may have been changed
        self::$settings->setSetting('wp_load_file', self::$wp_load_file);
    }

    public function testAuthenticate_badTimestamp()
    {
        $this->setUpAPIRequest();
        $this->input->timestamp = time() - 8000;

        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticate_noTimestamp()
    {
        $this->setUpAPIRequest();
        $this->input->timestamp = null;

        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticate_nonceUsed()
    {
        $this->setUpAPIRequest();
        $db = new MockDatabase(array(array('count'=>1)));
        $auth = new Authentication($db, self::$settings, $this->input);

        $authenticated = $auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateAPIRequest()
    {
        $this->setUpAPIRequest();
        $auth = new Authentication($this->getUserMockDB(true), self::$settings, $this->input);

        $authenticated = $auth->authenticate();
        $this->assertTrue($authenticated);
    }

    public function testAuthenticateAPIRequest_nonModerator()
    {
        $this->setUpAPIRequest();
        $mock_db_array = $this->getNonceFreeArray();
        $mock_db_array[] = array('user_id' => null);
        $mock_db = new MockDatabase($mock_db_array);

        $auth = new Authentication($mock_db, self::$settings, $this->input);

        $authenticated = $auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateAPIRequest_badHMAC()
    {
        $this->setUpAPIRequest();
        $this->input->accessor_id = '489';

        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateAPIRequest_noAccessor()
    {
        $this->setUpAPIRequest();
        $this->input->accessor_token = 'invalid';

        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateUsingWP()
    {
        // Set up so the user will be logged in correctly
        global $wp_user_logged_in, $wp_current_user;
        $wp_user_logged_in = true;
        $wp_current_user = (object) array('user_login'=>self::USERNAME);
        self::$settings->setSetting('auth_mode', 'wordpress');
        $db = $this->getUserMockDB();
        $auth = new Authentication($db, self::$settings, $this->input);

        // Run the test
        $authenticated = $auth->authenticate();
        $this->assertTrue($authenticated);
        $this->assertEquals(self::USER_ID, $auth->getUserId());
    }

    public function testAuthenticateUsingWP_notLoggedIn()
    {
        // Set up so the user will not be logged in
        global $wp_user_logged_in;
        $wp_user_logged_in = false;
        self::$settings->setSetting('auth_mode', 'wordpress');

        // Run the test
        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateUsingWP_nonModerator()
    {
        // Set up so the user will be logged in correctly
        global $wp_user_logged_in, $wp_current_user;
        $wp_user_logged_in = true;
        $wp_current_user = (object) array('user_login'=>self::USERNAME);
        self::$settings->setSetting('auth_mode', 'wordpress');
        $mock_db = new MockDatabase(array(false));

        $auth = new Authentication($mock_db, self::$settings, $this->input);

        // Run the test
        $authenticated = $auth->authenticate();
        $this->assertFalse($authenticated);
    }

    /**
     * @expectedException \Chocklymon\fsmcbm\AuthenticationException
     */
    public function testAuthenticateUsingWP_badConfiguration()
    {
        // Set up  so there will be an authentication exception
        self::$settings->setSetting('auth_mode', 'wordpress');
        self::$settings->setSetting('wp_load_file', null);
        $this->auth->authenticate();
    }

    public function testCleanUpNonce()
    {
        $db = new MockDatabase();
        $auth = new Authentication($db, self::$settings, $this->input);
        $auth->cleanUpNonce(1);
        // TODO assert
        $this->assertEquals(1, $db->getQueryCount());
        $this->assertEquals(0, strpos($db->getLastQuery(), 'DELETE FROM `auth_nonce'));
    }

    public function testGetModeratorInfo()
    {
        $db = new MockDatabase(array(array('user_id' => self::USER_ID, 'username' => self::USERNAME)));;
        $auth = new Authentication($db, self::$settings, $this->input);
        $info = $auth->getUserIdFromName(self::USERNAME);

        $expected = array('user_id'=>28, 'username'=>self::USERNAME);
        $this->assertEquals($expected, $info);
    }

    public function testGetModeratorInfo_false()
    {
        $info = $this->auth->getUserIdFromName("");
        $this->assertFalse($info);
    }

    public function testGetModeratorInfo_nonAdmin()
    {
        $db = new MockDatabase(array(false));;
        $auth = new Authentication($db, self::$settings, $this->input);
        $info = $auth->getUserIdFromName(self::USERNAME);
        $this->assertFalse($info);
    }

    public function testShouldLoadWordpress()
    {
        self::$settings->setSetting('auth_mode', 'none');
        $this->assertFalse($this->auth->shouldLoadWordpress());
    }


    //
    // Test Helper Functions
    //

    /**
     * Gets an array sutable to be set into a MockDatabase that needs to
     * return that the nonce hasn't been used.
     * @return array
     */
    private function getNonceFreeArray()
    {
        return array(array('count'=>0), array());
    }

    /**
     * Get a mock database that will return that the nonce hasn't been used yet.
     * @return MockDatabase
     */
    private function getNonceFreeMockDB()
    {
        return new MockDatabase($this->getNonceFreeArray());
    }

    /**
     * Returns a MockDatabase that will return a user with the provided rank.
     * @param boolean $include_nonce Whether or not the MockDatase should return
     * a free nonce result first before the user.
     * @return MockDatabase
     */
    private function getUserMockDB($include_nonce = false)
    {
        $user = array(
            'user_id' => self::USER_ID,
            'username' => self::USERNAME,
            'password' => '$2y$10$IvV0j3.oBy8/OKQS8EcHquPhBCi1M7LRZLeI4UjZ9hDmKrrns5/MG',// password1
            'needs_password_change' => FALSE,
        );
        if ($include_nonce) {
            $mock_db_array = $this->getNonceFreeArray();
            $mock_db_array[] = $user;
            return new MockDatabase($mock_db_array);
        } else {
            return new MockDatabase(array($user));
        }
    }

    /**
     * Set up a valid API request, this modifies the input variable.
     */
    private function setUpAPIRequest()
    {
        $accessor = 'test';
        $secret_key = 'secret';
        self::$settings->setSetting('auth_secret_keys', array($accessor => $secret_key));

        $this->input->accessor_token = $accessor;
        $this->input->accessor_id = '00000000-0000-0000-0000-000000000001';

        $this->input->keySort();
        $payload = '';
        foreach ($this->input as $key => $value) {
            $payload .= $key . $value;
        }

        $hmac = hash_hmac(Authentication::HMAC_ALGO, $payload, $secret_key);

        $this->input->hmac = $hmac;
    }
}