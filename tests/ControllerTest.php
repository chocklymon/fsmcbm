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
 * Test the Ban Manager action controller
 * @author Curtis Oakley
 */
class ControllerTest extends PHPUnit_Framework_TestCase
{
    const USERNAME = 'Joe12';

    /**
     * @var MockSettings
     */
    private static $settings;

    /**
     * @var Output
     */
    private static $output;

    /**
     * @var FilteredInput
     */
    private $input;

    public static function setUpBeforeClass()
    {
        self::$settings = new MockSettings();
        self::$output = new Output(self::$settings);
    }

    protected function setUp()
    {
        // Set up a fake post
        $this->input = new FilteredInput(false, array(
            // Incident post fields
            'user_id'       => '5',
            'incident_date' => '',
            'incident_type' => 'Hi',
            'action_taken'  => 'Banned',
            'world'         => 'world',
            'coord_x'       => '150',
            'coord_y'       => '250',
            'coord_z'       => '-25',
            'incident_id'   => '28',

            // User post fields
            'username'      => self::USERNAME,
            'rank'          => '2',
            'relations'     => 'Friends with Jane12',
            'banned'        => 'on',
            'permanent'     => 'off',
            'user_uuid'     => 'a1634f374-80a4bb9a0b2200-266597ac0',

            // Shared
            'notes'         => "Don't worry, just have some cheese.",
        ));
    }

    protected function tearDown()
    {
        // Clear any residual output in the buffer
        self::$output->clear();
    }

    public function testAddIncident()
    {
        // Set Up //
        $expectedOutput = '{"incident_id":29}';
        $this->expectOutputString($expectedOutput);

        // Construct the database
        $db = new MockDatabase(array(29));

        // Create the controller
        $controller = new Controller($db, self::$output);
        $now = $db->getDate();


        // Run the test //
        $controller->addIncident(1, $this->input);


        // Test that the query was constructed correctly //
        $expected = "INSERT INTO `incident` (`user_id`, `moderator_id`, `created_date`, `modified_date`, `incident_date`, `incident_type`, `notes`, `action_taken`, `world`, `coord_x`, `coord_y`, `coord_z`)
            VALUES ('5', '1', '$now', '$now', '" . mb_substr($now, 0, 10) . "', 'Hi', 'Don\'t worry, just have some cheese.', 'Banned', 'world', '150', '250', '-25')";

        $this->assertEquals($expected, $db->getLastQuery());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAddIncident_invalidUserId()
    {
        // Set Up //
        // The user id needs to be a positive number greater than zero.
        $this->input->user_id = 0;
        $controller = new Controller(new MockDatabase(), self::$output);

        // Run the test //
        $controller->addIncident(1, $this->input);
    }

    public function testAddUser()
    {
        // Set Up //
        $moderator_id = 4;
        $new_user_id = 29;
        $expectedOutput = "{\"user_id\":{$new_user_id}}";
        $this->expectOutputString($expectedOutput);

        // Construct the database
        $db = new MockDatabase(array(new FakeQueryResult(), $new_user_id));

        // Create the controller
        $controller = new Controller($db, self::$output);
        $now = $db->getDate();


        // Run the test //
        $controller->addUser($moderator_id, $this->input);


        // Test that the query was constructed correctly //
        $uuid = pack("H*", mb_ereg_replace('-', '', $this->input->user_uuid));
        $expected_user = "INSERT INTO `users` (username,modified_date,uuid,rank,relations,notes,banned,permanent) VALUES ('" . self::USERNAME . "','{$now}','{$uuid}',2,'Friends with Jane12','Don\'t worry, just have some cheese.',1,0)";
        $expected_ban_history = "INSERT INTO `ban_history` (`user_id`, `moderator_id`, `date`, `banned`, `permanent`)
                VALUES ('{$new_user_id}', '{$moderator_id}', '{$now}', '1', '')";

        $queries = $db->getQueries();
        $this->assertEquals($expected_user, $queries[1]);
        $this->assertEquals($expected_ban_history, $queries[2]);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAddUser_noUsername()
    {
        // Set Up //
        // The user id needs to not be empty
        $this->input->username = null;
        $controller = new Controller(new MockDatabase(), self::$output);

        // Run the test //
        $controller->addUser(1, $this->input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAddUser_userExists()
    {
        // Set Up //
        $db = new MockDatabase(array(new FakeQueryResult(array(1))));
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->addUser(1, $this->input);
    }

    public function testAutoComplete()
    {
        // Set Up //
        $input = new FilteredInput(false, array('term'=>self::USERNAME));

        $expected = '[{"label":"' . self::USERNAME . '","value":5}]';
        $this->expectOutputString($expected);

        $db = new MockDatabase(
            array(new FakeQueryResult(array(array('username'=>self::USERNAME, 'user_id'=>5))))
        );
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->autoComplete($input);

        // Validate the query
        $expected_query = "SELECT user_id, username FROM users WHERE username LIKE '" . self::USERNAME . "%'";
        $this->assertEquals($expected_query, $db->getLastQuery());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAutoComplete_invalidTerm()
    {
        // Set Up //
        $input = new FilteredInput(false, array('term'=>'a'));
        $controller = new Controller(new MockDatabase(), self::$output);

        // Run the test //
        $controller->autoComplete($input);
    }

    public function testDeleteIncident()
    {
        $this->expectOutputString('{"success":true}');

        $incident_id = 2;
        $input = new FilteredInput(false, array('incident_id' => $incident_id));
        $db = new MockDatabase();
        $controller = new Controller($db, self::$output);

        $controller->deleteIncident($input);

        $this->assertEquals("DELETE FROM `incident` WHERE `incident_id` = $incident_id", $db->getLastQuery());
    }

    public function testDeleteIncident_invalidId()
    {
        $this->expectOutputString('{"error":"Invalid Incident ID"}');

        $incident_id = 'hippo';
        $input = new FilteredInput(false, array('incident_id' => $incident_id));
        $db = new MockDatabase();
        $controller = new Controller($db, self::$output);

        $controller->deleteIncident($input);

        $this->assertEmpty($db->getLastQuery());
    }

    public function testGetBans()
    {
        // Set Up //
        $expected = "[]";
        $this->expectOutputString($expected);

        $db = new MockDatabase(array(new FakeQueryResult()));
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->getBans();
    }

    public function testGetWatchlist()
    {
        // Set Up //
        $expected = "[]";
        $this->expectOutputString($expected);

        $db = new MockDatabase(array(new FakeQueryResult()));
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->getWatchlist();
    }

    public function testRetrieveUserData()
    {
        // Set Up //
        $user_id = 69;
        $input = new FilteredInput(false, array('lookup'=>$user_id));

        $expectedOutput = '{"user":{"username":"'. self::USERNAME .'","uuid":"61"}}';
        $this->expectOutputString($expectedOutput);

        // Construct the database
        $db = new MockDatabase(array(
            array('username' => self::USERNAME, 'uuid' => 'a'),
            new FakeQueryResult(),
            new FakeQueryResult()
        ));

        // Create the controller
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->retrieveUserData($input);


        // Test that the query was constructed correctly //
        $expected_user = "SELECT * FROM users WHERE user_id = '$user_id'";

        $queries = $db->getQueries();
        $this->assertEquals($expected_user, $queries[0]);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRetrieveUserData_invalidId()
    {
        // Set Up //
        $input = new FilteredInput(false, array('lookup'=>'INVALID'));

        // Construct the database
        $db = new MockDatabase();
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->retrieveUserData($input);
    }

    public function testSearch()
    {
        // Set Up //
        $input = new FilteredInput(false, array('search'=>self::USERNAME));

        $expectedOutput = "[]";
        $this->expectOutputString($expectedOutput);

        // Construct the database
        $db = new MockDatabase(array(
            new FakeQueryResult(),
            new FakeQueryResult()
        ));

        // Create the controller
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->search($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSearch_invalidSearch()
    {
        // Set Up //
        $input = new FilteredInput(false, array('search'=>'b'));

        // Construct the database
        $db = new MockDatabase();
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->search($input);
    }

    public function testUpdateBanHistory()
    {
        $player_id = 28;
        $moderator_id = 2;
        $banned = true;

        $db = new MockDatabase();
        $controller = new Controller($db, self::$output);
        $now = $db->getDate();

        $controller->updateBanHistory($player_id, $moderator_id, $banned, $banned);

        $lastQuery = $db->getLastQuery();
        $expectedQuery = "INSERT INTO `ban_history` (`user_id`, `moderator_id`, `date`, `banned`, `permanent`)
                VALUES ('{$player_id}', '{$moderator_id}', '{$now}', '{$banned}', '{$banned}')";
        $this->assertEquals($expectedQuery, $lastQuery);
    }

    public function testUpdateUser()
    {
        // Set Up //
        // Swap banned and permanent flags
        $this->input->banned = 'false';
        $this->input->permanent = 'true';

        $expectedOutput = '{"success":true}';
        $this->expectOutputString($expectedOutput);

        // Construct the database
        $db = new MockDatabase(array(
            array('banned'=>1, 'permanent'=>0),
            new FakeQueryResult(),
        ));

        // Create the controller
        $controller = new Controller($db, self::$output);
        $now = $db->getDate();

        // Run the test //
        $controller->updateUser(1, $this->input);

        $expected_select = "SELECT * FROM `users` WHERE `users`.`user_id` = 5";
        $expected_update = "UPDATE  `users` SET `username` = '" . self::USERNAME . "', `modified_date` = '$now',
                    `rank` =  '2',
                    `relations` =  'Friends with Jane12',
                    `notes` =  'Don\'t worry, just have some cheese.',
                    `banned` =  '0',
                    `permanent` =  ''
                    WHERE  `users`.`user_id` = 5";
        $queries = $db->getQueries();
        $this->assertEquals($expected_select, $queries[0]);
        $this->assertEquals($expected_update, $queries[2]);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testUpdateUser_invalidId()
    {
        // Set Up //
        $input = new FilteredInput(false, array('id'=>'bad'));

        // Construct the database
        $db = new MockDatabase();
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->updateUser(1, $input);
    }

    public function testUpdateIncident()
    {
        // Set Up //
        $expectedOutput = '{"success":true}';
        $this->expectOutputString($expectedOutput);

        $db = new MockDatabase();
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->updateIncident($this->input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testUpdateIncident_invalidId()
    {
        // Set Up //
        $input = new FilteredInput(false, array('id'=>'bad'));

        // Construct the database
        $db = new MockDatabase();
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->updateIncident($input);
    }

    public function testUpsertUserUUID_newUser()
    {
        $new_user_id = 29;
        $this->expectOutputString('{"user_id":'.$new_user_id.'}');

        // Construct the database
        $db = new MockDatabase(array(new FakeQueryResult(), new FakeQueryResult(), $new_user_id));

        // Create the controller
        $controller = new Controller($db, self::$output);

        $controller->upsertUserUUID($this->input);
    }

    public function testUpsertUserUUID_updateUser()
    {
        $expectedOutput = '{"success":true}';
        $this->expectOutputString($expectedOutput);

        // Construct the database
        $db = new MockDatabase(array(new FakeQueryResult(array(array('user_id'=>29))), new FakeQueryResult()));

        // Create the controller
        $controller = new Controller($db, self::$output);

        $controller->upsertUserUUID($this->input);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid UUID
     */
    public function testUpsertUserUUID_badUUID()
    {
        // Set up the controller and input
        $this->input->user_uuid = '';
        $db = new MockDatabase(array(new FakeQueryResult(array('user_id'=>29)), new FakeQueryResult()));
        $controller = new Controller($db, self::$output);

        $controller->upsertUserUUID($this->input);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage No UUID provided
     */
    public function testUpsertUserUUID_noUUID()
    {
        $db = new MockDatabase(array(new FakeQueryResult(array(1))));
        $input = new FilteredInput(false);
        $controller = new Controller($db, self::$output);

        $controller->upsertUserUUID($input);
    }

}
