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

/* *****************************************************************************
 *
 * This script updates the ban manager database so that it is the correct
 * version in case the skeleton has been changed since the database was created.
 *
 * This script should be able to run multiple times over the same database
 * without causing errors.
 *
 * Assumes that the users has at least v1 of the database set up.
 *
 * *****************************************************************************
 */

// Set up for running the database update
require_once 'Settings.php';
require_once 'Database.php';
require_once 'Output.php';

$settings = new Settings();
$db = new Database();

$db->connect($settings);

// Begin database update code

/* *****
 * v1 -> v2 updates
 * *****
 */

/* ************
 * Define the Ranks that are currently being used by the ban manager.
 */
$ranks = array(
    'Everyone',
    'Regular',
    'Donor',
    'Builder',
    'Engineer',
    'Moderator',
    'Admin',
    'Default'
);



// Rename the moderator column in the incident table
if ($db->columnExists('incident', 'moderator')) {
    $sql = <<<SQL
ALTER TABLE `incident`
CHANGE `moderator` `moderator_id` INT( 10 ) UNSIGNED NOT NULL
SQL;
    $db->query($sql);
}
// End rename incident moderator id column


// Rename the moderator column in the ban_history table
if ($db->columnExists('ban_history', 'moderator')) {
    $sql = <<<SQL
ALTER TABLE `ban_history`
CHANGE `moderator` `moderator_id` INT( 10 ) UNSIGNED NOT NULL
SQL;
    $db->query($sql);
}
// End rename ban_history moderator id column


// Rename the id column in the incident table
if ($db->columnExists('incident', 'id')) {
    $sql = <<<SQL
ALTER TABLE `incident`
CHANGE `id` `incident_id` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT
SQL;
    $db->query($sql);
}
// End rename incident id column


// Rename the id column in the user table
if ($db->columnExists('users', 'id')) {
    $sql = <<<SQL
ALTER TABLE `users`
CHANGE `id` `user_id` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT
SQL;
    $db->query($sql);
}
// End rename user id column


// Issue #15
// Increase the size of the incident type column
$sql = "SELECT CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE table_name = 'incident'
AND table_schema = '" . $settings->getDatabaseName() . "'
AND column_name = 'incident_type'
LIMIT 0 , 1";
$result = $db->querySingleRow($sql);

if ($result['CHARACTER_MAXIMUM_LENGTH'] != 30) {
    $sql = <<<SQL
ALTER TABLE `incident`
CHANGE `incident_type` `incident_type` VARCHAR( 30 ) NULL DEFAULT NULL
SQL;
    $db->query($sql);
}
// End issue #15


// Move the appeals into a seperate appeal table
if (!$db->tableExits('appeal')) {
    // Create the appeal table
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `appeal` (
  `appeal_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `author_id` int(10) unsigned NOT NULL,
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  `date` datetime DEFAULT NULL,
  `message` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`appeal_id`),
  KEY `user_id` (`user_id`),
  KEY `author_id` (`author_id`),
  KEY `closed` (`closed`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
SQL;
    $db->query($sql);

    //
    // Copy over any existing appeals
    //
    $sql = <<<SQL
SELECT
`user_id`, `moderator_id`, `appeal_date`, `appeal`, `appeal_response`
FROM `incident`
WHERE `appeal_date` IS NOT NULL
SQL;
    $rows = $db->queryRows($sql);

    $values = '';

    foreach ($rows as $appeal) {
        $message = trim($appeal['appeal']);
        $response = trim($appeal['appeal_response']);

        // Assume that incident appeals with a response are closed.
        $closed = !empty($response);

        if (!empty($message)) {
            $message = $db->sanitize($message);
            $values .= " ('{$appeal['user_id']}', '{$appeal['user_id']}', $closed, '{$appeal['appeal_date']}', '{$message}'),";
        }
        if ($closed) {
            $response = $db->sanitize($response);
            $values .= " ('{$appeal['user_id']}', '{$appeal['moderator_id']}', $closed, '{$appeal['appeal_date']}', '{$response}'),";
        }
    }

    if (!empty($values)) {
        // Remove the trailing ','
        $values = substr($values, 0, -1);

        $sql = "INSERT INTO `appeal` (`user_id`, `author_id`, `closed`, `date`, `message`) VALUES $values";

        $db->query($sql);
    }

    // Remove the appeal columns
    $sql = <<<SQL
ALTER TABLE `incident`
  DROP `appeal_date`,
  DROP `appeal`,
  DROP `appeal_response`
SQL;
    $db->query($sql);
}
// End appeals table


// Create the ranks table
if (!$db->tableExits('rank')) {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `rank` (
  `rank_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(20),
  PRIMARY KEY (`rank_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
SQL;
    $db->query($sql);

    // Add the ranks to the Database
    $values = '';

    foreach($ranks as $rank) {
        if (!empty($values)) {
            $values .= ",\n";
        }
        $rank = $db->sanitize($rank);
        $values .= "('{$rank}')";
    }

    if (!empty($values)) {
        $sql = "INSERT INTO `rank` (`name`) VALUES $values";

        $db->query($sql);
    }

    // Switch over the ranks
    $sql = <<<SQL
UPDATE `users` AS u, `rank` AS r
SET u.`rank` = r.`rank_id`
WHERE u.`rank` = r.`name`;
SQL;
    $db->query($sql);

    // Change the rank column in the users table to an INT
    $sql = <<<SQL
ALTER TABLE `users`
  CHANGE `rank` `rank` INT( 10 ) NOT NULL DEFAULT '1',
  ADD INDEX ( `rank` )
SQL;
    $db->query($sql);
}
// End ranks table

$db->close();
