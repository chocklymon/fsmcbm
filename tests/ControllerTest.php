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
    const UUID = 'a1634f37-480a-4bb9-a0b2-200266597ac0';

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
            'uuid'          => self::UUID,

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
        $uuid = mb_ereg_replace('-', '', $this->input->uuid);
        $expected_user = "INSERT INTO `users` (`uuid`,`modified_date`,`rank`,`relations`,`notes`,`banned`,`permanent`) VALUES ('{$uuid}','{$now}',2,'Friends with Jane12','Don\'t worry, just have some cheese.',1,0)";
        $expected_ban_history = "INSERT INTO `ban_history` (`user_id`, `moderator_id`, `date`, `banned`, `permanent`)
                VALUES ('{$new_user_id}', '{$moderator_id}', '{$now}', '1', '0')";

        $queries = $db->getQueries();
        $this->assertEquals($expected_user, $queries[1]);
        $this->assertEquals($expected_ban_history, $queries[2]);
    }

    public function testAddUserMinumum()
    {
        // Set Up //
        $input = new FilteredInput(false, array('uuid' => self::UUID));
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
        $controller->addUser($moderator_id, $input);


        // Test that the query was constructed correctly //
        $uuid = mb_ereg_replace('-', '', self::UUID);
        $expected_user = "INSERT INTO `users` (`uuid`,`modified_date`) VALUES ('{$uuid}','{$now}')";

        $queries = $db->getQueries();
        $this->assertEquals(2, count($queries));
        $this->assertEquals($expected_user, $queries[1]);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAddUserNoUUID()
    {
        // Set Up //
        // The user id needs to not be empty
        $this->input->uuid = null;
        $controller = new Controller(new MockDatabase(), self::$output);

        // Run the test //
        $controller->addUser(1, $this->input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAddUserUserExists()
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

        $expected = '[{"username":"' . self::USERNAME . '","user_id":5,"uuid":"a"}]';
        $this->expectOutputString($expected);

        $db = new MockDatabase(
            array(new FakeQueryResult(array(array('username'=>self::USERNAME, 'user_id'=>5, 'uuid' => 'a'))))
        );
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->autoComplete($input);

        // Validate the query
        $expected_query = <<<SQL
    SELECT `users`.`user_id`, `users`.`uuid`, `user_aliases`.`username`
     FROM `users`
     LEFT JOIN `user_aliases` ON (`users`.`user_id` = `user_aliases`.`user_id`)
     WHERE `users`.`uuid` LIKE 'e12%'
SQL;
        $this->assertEquals($expected_query, $db->getLastQuery());
    }

    public function testAutoCompleteUUID()
    {
        // Set Up //
        $input = new FilteredInput(false, array('term' => self::UUID));

        $expected = '[{"username":"' . self::USERNAME . '","user_id":5,"uuid":"a"}]';
        $this->expectOutputString($expected);

        $db = new MockDatabase(array(
            new FakeQueryResult(),
            new FakeQueryResult(array(array('username'=>self::USERNAME, 'user_id'=>5, 'uuid' => 'a')))
        ));
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->autoComplete($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testAutoCompleteInvalidTerm()
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

    public function testRetrieveUserDataByUserId()
    {
        $user_id = 69;
        $this->runRetrieveUserData(array('user_id'=>$user_id), null, $user_id);
    }

    public function testRetrieveUserDataByUUID()
    {
        $user_id = 69;
        $this->runRetrieveUserData(array('uuid'=>self::UUID), array('user_id' => $user_id), $user_id);
    }

    public function testRetrieveUserDataByUsername()
    {
        $user_id = 69;
        $this->runRetrieveUserData(array('username'=>self::USERNAME), array('user_id' => $user_id), $user_id);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRetrieveUserDataInvalidId()
    {
        // Set Up //
        $input = new FilteredInput(false, array('user_id'=>'INVALID'));

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

        $expected_select = "SELECT `banned`, `permanent` FROM `users` WHERE `users`.`user_id` = 5";
        $expected_update = <<<SQL
UPDATE `users` SET
    `modified_date` = '$now',
    `rank` = '2',
    `relations` = 'Friends with Jane12',
    `notes` = 'Don\'t worry, just have some cheese.',
    `banned` = '0',
    `permanent` = '0'
 WHERE `users`.`user_id` = 5
SQL;
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
        $this->input->uuid = '';
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

    private function runRetrieveUserData(array $input, $dbMockExtra = null, $user_id = 69)
    {
        // Set Up //
        $queryIndex = 0;
        $input = new FilteredInput(false, $input);
        $expectedOutput = '{"user":{"uuid":"a","modified_date":"2016-07-19T14:39:59+00:00","banned":true,"permanent":true,"usernames":[{"username":"' . self::USERNAME . '","active":true}]},"incident":[],"history":[]}';
        $this->expectOutputString($expectedOutput);

        // Construct the database
        $mockQueryResults = array(
            array('uuid' => 'a', 'modified_date' => '2016-07-19 14:39:59', 'banned' => 'false', 'permanent' => 'false'),// Get user info
            array(array('username' => self::USERNAME, 'active'=>true)),// Get user aliases
            array(),// Get incidents
            array()// Get ban history
        );
        if ($dbMockExtra) {
            $queryIndex++;
            array_unshift($mockQueryResults, $dbMockExtra);
        }
        $db = new MockDatabase($mockQueryResults);

        // Create the controller
        $controller = new Controller($db, self::$output);

        // Run the test //
        $controller->retrieveUserData($input);


        // Test that the query was constructed correctly //
        $expected_user = "SELECT * FROM users WHERE user_id = '{$user_id}'";

        $queries = $db->getQueries();
        $this->assertEquals($expected_user, $queries[$queryIndex]);
    }

}
