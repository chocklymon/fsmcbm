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
     * @var MockDatabase
     */
    private static $empty_db;

    /**
     * @var Authentication
     */
    private $auth;

    public static function setUpBeforeClass()
    {
        self::$settings = new MockSettings();
        self::$empty_db = new MockDatabase();
    }

    protected function setUp()
    {
        $_COOKIE = array();
        self::$settings->setDebugMode(false);
        $this->auth = new Authentication(self::$empty_db, self::$settings);
    }

    protected function tearDown()
    {
        $_POST = array();
    }

    public function testAuthenticateAPIRequest()
    {
        $this->setUpAPIRequest();
        $db = new MockDatabase(array(array(0)));
        $auth = new Authentication($db, self::$settings);

        $authenticated = $auth->authenticate();
        $this->assertTrue($authenticated);
    }

    public function testAuthenticateAPIRequest_badHMAC()
    {
        $this->setUpAPIRequest();
        $_POST['hmac'] = 'invalid';

        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateAPIRequest_badTimestamp()
    {
        $this->setUpAPIRequest();
        $_POST['timestamp'] = time() - 8000;

        $authenticated = $this->auth->authenticate();
        $this->assertFalse($authenticated);
    }

    public function testAuthenticateAPIRequest_nonceUsed()
    {
        $this->setUpAPIRequest();
        $db = new MockDatabase(array(array(1)));
        $auth = new Authentication($db, self::$settings);

        $authenticated = $auth->authenticate();
        $this->assertFalse($authenticated);
    }

    /**
     * @expectedException AuthenticationException
     */
    public function testAuthenticateAPIRequest_databaseError()
    {
        $this->setUpAPIRequest();
        $db = new MockDatabase(array(), true);
        $auth = new Authentication($db, self::$settings);

        $auth->authenticate();
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
        $db = new MockDatabase(array(array('user_id'=>self::USER_ID, 'rank'=>'Regular')));
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

    public function testGetModeratorInfo()
    {
        $db = $this->getModeratorMockDB();
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
        $db = new MockDatabase(array(array('user_id'=>self::USER_ID, 'rank'=>'Regular')));
        $auth = new Authentication($db, self::$settings);
        $info = $auth->getModeratorInfo(self::USERNAME);
        $this->assertFalse($info);
    }

    private function setLoggedInCookie() {
        $_COOKIE = array('wordpress_logged_in_28'=> self::USERNAME . '|1393260248|7fe9e5132050a0ef139492791867b659');
    }

    private function getModeratorMockDB() {
        return new MockDatabase(array(array('user_id'=>self::USER_ID, 'rank'=>'Admin')));
    }

    public function setUpAPIRequest()
    {
        $accessor = 'test';
        $secret_key = 'secret';
        self::$settings->setSetting('auth_secret_keys', array($accessor => $secret_key));
        $nonce = mt_rand(0, 400000);
        $timestamp = MockDatabase::getDate();

        $_POST = array(
            'accessor' => $accessor,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
        );

        $payload = '';
        foreach ($_POST as $key => $value) {
            $payload .= $key . $value;
        }

        $hmac = hash_hmac('sha1', $payload, $secret_key);

        $_POST['hmac'] = $hmac;
    }
}