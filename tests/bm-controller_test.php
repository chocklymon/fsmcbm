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

require_once 'src/bm-output.php';
require_once 'bm-database_mock.php';
require_once 'src/bm-controller.php';

/**
 * Test the Ban Manager action controller
 * @author Curtis Oakley
 */
class BanManagerTest extends PHPUnit_Framework_TestCase
{

    public static function setUpBeforeClass()
    {
        // Set up a fake post
        $_POST = array(
            // Incident post fields
            'user_id'       => '5',
            'incident_date' => '',
            'incident_type' => 'Hi',
            'notes'         => "Don't worry, just have some cheese.",
            'action_taken'  => 'Banned',
            'world'         => 'world',
            'coord_x'       => '150',
            'coord_y'       => '250',
            'coord_z'       => '-25'
        );
    }
    
    protected function tearDown()
    {
        // Clear any residual output in the buffer
        Output::clear();
    }
    
    public function testAddIncident()
    {
        // Set Up //
        $expectedOutput = '{"incident_id":29}';
        $this->expectOutputString($expectedOutput);
        
        // Construct the database
        $db = new MockDatabase(array(29));
        
        // Create the controller
        $controller = new Controller($db);
        $now = date('Y-m-d H:i:s');
        
        
        // Run the test //
        $controller->addIncident(1);
        
        
        // Test that the query was constructed correctly //
        $expected = "INSERT INTO `incident` (`user_id`, `moderator_id`, `created_date`, `modified_date`, `incident_date`, `incident_type`, `notes`, `action_taken`, `world`, `coord_x`, `coord_y`, `coord_z`)
            VALUES ('5', '1', '$now', '$now', '" . substr($now, 0, 10) . "', 'Hi', 'Don\'t worry, just have some cheese.', 'Banned', 'world', '150', '250', '-25')";
        
        $this->assertEquals($expected, $db->getLastQuery());
    }

    public function testAddIncident_invalidUserId()
    {
        // Set Up //
        $expectedOutput = '{"error":"Please provide a user for this incident."}';
        $this->expectOutputString($expectedOutput);
        
        // The user id needs to be a positive number greater than zero.
        $_POST['user_id'] = 0;
        
        // Construct the database
        $db = new MockDatabase(array(29));
        
        // Create the controller
        $controller = new Controller($db);
        $now = date('Y-m-d H:i:s');
        
        
        // Run the test //
        $controller->addIncident(1);
        
        
        // Test that there was no query //
        $this->assertEquals('', $db->getLastQuery());
    }
    
}
