<?php
/* MIT License
 *
 * Copyright (c) 2016 Curtis Oakley
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

// SEE: http://wiki.vg/Mojang_API

use Chocklymon\fsmcbm\Database;
use Chocklymon\fsmcbm\Settings;

require_once __DIR__ . '/../src/vendor/autoload.php';

class UsernameHistoryGenerator
{
    const PROFILE_URL = 'https://api.mojang.com/user/profiles/';// {{uuid}}/names

    private $settings;
    private $limiter;
    private $db;

    public function __construct()
    {
        // Generate the max requests per second
        $this->limiter = 600 / (10 * 60);// Rate limit of 600 requests per 10 minutes

        $this->settings = new Settings();
        $this->db = new Database($this->settings);
    }

    public function run()
    {
        $select_all_users_sql = "SELECT uuid FROM users WHERE uuid NOT LIKE '00000000000000000000%'";

        $query_results = $this->db->query($select_all_users_sql);

        while ($row = $query_results->fetch_assoc()) {
            $uuid = $row['uuid'];
            $name_history = $this->doJSONGet(self::PROFILE_URL . $uuid . '/names');
            /* Response:
            [
              {
                "name": "Gold" // Original Name
              },
              {
                "name": "Diamond", // Name with the highest timestamp (or original if that is the only one, is the current?)
                "changedToAt": 1414059749000 // Java (unix?) in milliseconds
              }
            ]
            */
            // TODO, add the user alias from the name_history response

            sleep($this->limiter);
        }
    }

    private function doJSONGet($url) {
        $curl = curl_init($url);
        $headers = array(
            'Accept: application/json',
        );
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $curl_result = curl_exec($curl);
        return json_decode($curl_result);
    }

    private function addUserAlias($player_id, $username, $active)
    {
        $username = $this->db->sanitize($username);
        $active = $active ? 1 : 0;

        // Get any existing usernames
        $sql = "SELECT `username`, `active` FROM `user_aliases` WHERE `user_id` = {$player_id}";
        $result = $this->db->queryRows($sql);

        foreach ($result as $row) {
            if ($row['username'] == $username && $row['active'] == $active) {
                // Alias already exists in the correct state
                // TODO handle when the username exists, but in the wrong active state
                return;
            }
        }

        // Deactivate any currently active usernames
        if ($active && !empty($result)) {
            $sql = "UPDATE `user_aliases` SET `active` = FALSE WHERE `user_id` = {$player_id}";
            $this->db->query($sql);
        }

        $sql = "INSERT INTO `user_aliases` (`user_id`, `username`, `active`) VALUES ({$player_id}, '{$username}', {$active})";
        $this->db->query($sql);
    }
}


// Run
header('Content-Type: text/plain');

$history_generator = new UsernameHistoryGenerator();
//$history_generator->run();

echo "\nDONE\n";
