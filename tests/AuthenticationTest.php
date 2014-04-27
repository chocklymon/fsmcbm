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

require_once('MockDatabase.php');
require_once('MockSettings.php');
require_once('src/Output.php');
require_once('src/Authentication.php');

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

        $_POST = array(
            'nonce' => $nonce,
            'timestamp' => $timestamp,
        );

        $this->auth = new Authentication($this->getNonceFreeMockDB(), self::$settings);
    }

    protected function tearDown()
    {
        // Reset anything that may have been changed
        $_POST = array();
        self::$settings->setSetting('wp_load_file', self::$wp_load_file);
    }

    public function testAuthenticate_badTimestamp()
    {
        $this->setUpAPIRequest();
        $_POST['timestamp'] = time() - 8000;

        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    /**
     * @expectedException AuthenticationException
     */
    public function testAuthenticate_databaseError()
    {
        $this->setUpAPIRequest();
        $db = new MockDatabase(array(false));
        $auth = new Authentication($db, self::$settings);

        $auth->authenticate();
    }

    public function testAuthenticate_nonceUsed()
    {
        $this->setUpAPIRequest();
        $db = new MockDatabase(array(array('count'=>1)));
        $auth = new Authentication($db, self::$settings);

        $authenticated = $auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateAPIRequest()
    {
        $this->setUpAPIRequest();
        $auth = new Authentication($this->getModeratorMockDB(true), self::$settings);

        $authenticated = $auth->authenticate();
        $this->assertTrue($authenticated);
    }

    public function testAuthenticateAPIRequest_nonModerator()
    {
        $this->setUpAPIRequest();
        $auth = new Authentication($this->getNonModeratorMockDB(true), self::$settings);

        $authenticated = $auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateAPIRequest_badHMAC()
    {
        $this->setUpAPIRequest();
        $_POST['uuid'] = '489';

        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    /**
     * @expectedException AuthenticationException
     */
    public function testAuthenticateAPIRequest_databaseError()
    {
        $this->setUpAPIRequest();
        $db = new MockDatabase(array(array('count'=>0), array(), false));
        $auth = new Authentication($db, self::$settings);

        $auth->authenticate();
    }

    public function testAuthenticateAPIRequest_noAccessor()
    {
        $this->setUpAPIRequest();
        $_POST['accessor_token'] = 'invalid';

        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateUsingCookie()
    {
        $_COOKIE[self::$settings->getCookieName()] = '1|notch|139656221|8c6e7d97248140d2155f36094d955a8f53339a89';
        self::$settings->setSetting('cookie_secret', 'secret_key');
        self::$settings->setSetting('session_duration', 0);
        $this->assertTrue($this->auth->authenticate(), "Cookie user should have been authenticated correctly.");
    }

    /**
     * Run in a separate process since this method sets cookies, and the
     * headers have already been set by PHPUnit.
     * @runInSeparateProcess
     */
    public function testAuthenticateUsingCookie_badTimeStamp()
    {
        $_COOKIE[self::$settings->getCookieName()] = '1|notch|139656221|8c6e7d97248140d2155f36094d955a8f53339a89';// timestamp = 2032-12-12
        self::$settings->setSetting('cookie_secret', 'secret_key');
        self::$settings->setSetting('session_duration', 1);
        $this->assertFalse($this->auth->authenticate(), "Cookie user should NOT have been authenticated.");
    }

    /**
     * @expectedException AuthenticationException
     */
    public function testAuthenticateUsingCookie_badConfiguration()
    {
        $_COOKIE[self::$settings->getCookieName()] = '1|notch|139656221|8c6e7d97248140d2155f36094d955a8f53339a89';
        self::$settings->setSetting('cookie_secret', '');
        $this->assertFalse($this->auth->authenticate(), "Cookie user should NOT have been authenticated.");
    }

    public function testAuthenticateUsingCookie_noCookie()
    {
        $_COOKIE = array();
        $this->assertFalse($this->auth->authenticate(), "Cookie user should NOT have been authenticated.");
    }

    public function testAuthenticateUsingCookie_badCookie()
    {
        $_COOKIE[self::$settings->getCookieName()] = '1|notch';
        $this->assertFalse($this->auth->authenticate(), "Cookie user should NOT have been authenticated.");
    }

    public function testAuthenticateUsingCookie_badHMAC()
    {
        $_COOKIE[self::$settings->getCookieName()] = '1|notchy|139656221|8c6e7d97248140d2155f36094d955a8f53339a89';
        self::$settings->setSetting('cookie_secret', 'secret_key');
        $this->assertFalse($this->auth->authenticate(), "Cookie user should NOT have been authenticated.");
    }

    public function testAuthenticateUsingWP()
    {
        // Set up so the user will be logged in correctly
        global $wp_user_logged_in, $wp_current_user;
        $wp_user_logged_in = true;
        $wp_current_user = (object) array('user_login'=>self::USERNAME);
        self::$settings->setSetting('use_wp_login', true);
        $db = $this->getModeratorMockDB();
        $auth = new Authentication($db, self::$settings);

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
        self::$settings->setSetting('use_wp_login', true);

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
        self::$settings->setSetting('use_wp_login', true);
        $db = $this->getNonModeratorMockDB();
        $auth = new Authentication($db, self::$settings);

        // Run the test
        $authenticated = $auth->authenticate();
        $this->assertFalse($authenticated);
    }

    /**
     * @expectedException AuthenticationException
     */
    public function testAuthenticateUsingWP_badConfiguration()
    {
        // Set up  so there will be an authentication exception
        self::$settings->setSetting('wp_load_file', null);
        $this->auth->authenticate();
    }

    public function testCleanUpNonce()
    {
        $db = new MockDatabase();
        $auth = new Authentication($db, self::$settings);
        $auth->cleanUpNonce(1);
        // TODO assert
        $this->assertEquals(1, $db->getQueryCount());
        $this->assertEquals(0, strpos($db->getLastQuery(), 'DELETE FROM `auth_nonce'));
    }

    public function testGetModeratorInfo()
    {
        $db = new MockDatabase(array(array('user_id'=>self::USER_ID, 'rank'=>'Admin')));;
        $auth = new Authentication($db, self::$settings);
        $info = $auth->getModeratorInfo(self::USERNAME);

        $expected = array('id'=>28, 'rank'=>'Admin', 'username'=>self::USERNAME);
        $this->assertEquals($expected, $info);
    }

    public function testGetModeratorInfo_false()
    {
        $info = $this->auth->getModeratorInfo("");
        $this->assertFalse($info);
    }

    public function testGetModeratorInfo_nonAdmin()
    {
        $db = new MockDatabase(array(array('user_id'=>self::USER_ID, 'rank'=>'Regular')));;
        $auth = new Authentication($db, self::$settings);
        $info = $auth->getModeratorInfo(self::USERNAME);
        $this->assertFalse($info);
    }

    /**
     * Run in a separate process since this method sets cookies, and the
     * headers have already been set by PHPUnit.
     * @runInSeparateProcess
     */
    public function testLoginUser()
    {
        $_POST['username'] = self::USERNAME;
        $_POST['password'] = 'password1';

        $db = $this->getModeratorMockDB();
        $auth = new Authentication($db, self::$settings);

        $this->assertTrue($auth->loginUser());
    }

    /**
     * @expectedException AuthenticationException
     */
    public function testLoginUser_databaseError()
    {
        $_POST['username'] = self::USERNAME;
        $_POST['password'] = 'password1';

        $db = new MockDatabase(array(false));
        $auth = new Authentication($db, self::$settings);

        $auth->loginUser();
    }

    /**
     * @expectedException AuthenticationException
     */
    public function testLoginUser_configurationError()
    {
        self::$settings->setSetting('cookie_secret', '');

        $_POST['username'] = self::USERNAME;
        $_POST['password'] = 'password1';

        $db = $this->getModeratorMockDB();
        $auth = new Authentication($db, self::$settings);

        $auth->loginUser();
    }

    public function testLoginUser_noUsername()
    {
        $_POST['password'] = 'password1';
        $this->assertFalse($this->auth->loginUser());
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
     * @return \MockDatabase
     */
    private function getNonceFreeMockDB()
    {
        return new MockDatabase($this->getNonceFreeArray());
    }

    /**
     * Gets a MockDatabase instance that will return a user that is not a
     * moderator.
     * @param boolean $include_nonce Whether or not the MockDatase should return
     * a free nonce result first before the user.
     * @return \MockDatabase
     */
    private function getNonModeratorMockDB($include_nonce = false)
    {
        return $this->getUserMockDB('Regular', $include_nonce);
    }

    /**
     * Gets a MockDatabase instance that will return a user that is a moderator.
     * @param boolean $include_nonce Whether or not the MockDatase should return
     * a free nonce result first before the user.
     * @return \MockDatabase
     */
    private function getModeratorMockDB($include_nonce = false)
    {
        return $this->getUserMockDB('Admin', $include_nonce);
    }
    
    /**
     * Returns a MockDatabase that will return a user with the provided rank.
     * @param string $rank The user's rank.
     * @param boolean $include_nonce Whether or not the MockDatase should return
     * a free nonce result first before the user.
     * @return \MockDatabase
     */
    private function getUserMockDB($rank, $include_nonce)
    {
        $user = array('user_id'=>self::USER_ID, 'rank'=>$rank);
        if ($include_nonce) {
            $mock_db_array = $this->getNonceFreeArray();
            $mock_db_array[] = $user;
            return new MockDatabase($mock_db_array);
        } else {
            return new MockDatabase(array($user));
        }
    }

    /**
     * Set up a valid API request, this modifies the global $_POST.
     */
    private function setUpAPIRequest()
    {
        $accessor = 'test';
        $secret_key = 'secret';
        self::$settings->setSetting('auth_secret_keys', array($accessor => $secret_key));

        $_POST['accessor_token'] = $accessor;
        $_POST['uuid'] = 'd9';

        $payload = '';
        ksort($_POST);
        foreach ($_POST as $key => $value) {
            $payload .= $key . $value;
        }

        $hmac = hash_hmac(Authentication::HASH_ALGO, $payload, $secret_key);

        $_POST['hmac'] = $hmac;
    }

}