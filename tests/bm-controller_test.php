<?php

require_once '../bm-database.php';
require_once '../bm-output.php';
require_once 'bm-database_mock.php';
require_once '../bm-controller.php';

/**
 * Test the Ban Manager action controller
 * @author Curtis Oakley
 */
class BanManagerTest extends PHPUnit_Framework_TestCase {
    
    protected function tearDown() {
        // Clear any residual output in the buffer
        Output::clear();
    }
    
    public function testAddIncident() {
        // Set Up
        $expectedOutput = '{"incident_id":29}';
        $this->expectOutputString($expectedOutput);
        
        // Set up the incident information in the POST
        $_POST = array(
            'user_id'       => 5,
            'incident_date' => '',
            'incident_type' => 'Hi',
            'notes'         => "Don't worry, just have some cheese.",
            'action_taken'  => 'Banned',
            'world'         => 'world',
            'coord_x'       => '150',
            'coord_y'       => '250',
            'coord_z'       => '-25'
        );
        
        // Construct the database
        $db = new MockDatabase(array(29));
        
        // Create the controller
        $controller = new Controller($db);
        $now = date('Y-m-d H:i:s');
        
        // Run the test
        $controller->addIncident(1);
        
        // Test that the query was constructed correctly
        $expected = "INSERT INTO `incident` (`user_id`, `moderator_id`, `created_date`, `modified_date`, `incident_date`, `incident_type`, `notes`, `action_taken`, `world`, `coord_x`, `coord_y`, `coord_z`)
            VALUES ('5', '1', '$now', '$now', '" . substr($now, 0, 10) . "', 'Hi', 'Don\'t worry, just have some cheese.', 'Banned', 'world', '150', '250', '-25')";
        
        $this->assertEquals($expected, $db->getLastQuery());
    }
}
