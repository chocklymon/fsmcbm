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
/**
 * Backups the database
 */
$start_time = microtime(true);

require_once __DIR__ . '/../src/vendor/autoload.php';

$settings = new Chocklymon\fsmcbm\Settings();

$command = 'mysqldump --opt -h'
           . $settings->getDatabaseHost()
           . ' -u' . $settings->getDatabaseUsername()
           . ' -p' . $settings->getDatabasePassword()
           . ' ' . $settings->getDatabaseName();

// To split the inserts onto multiple lines
// | perl -pane "s{\),\(}{),\n(}smg"

// Set the headers
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="bm_backup_' . date('Y-m-d') . '.sql"');

// Run the command
passthru($command, $worked);

switch($worked){
    case 1:
        echo "\n-- There was a warning during the backup.";
        break;
    case 2:
        echo "\n-- There was an error during the backup.";
        break;
}

$total_time = microtime(true) - $start_time;
echo "\n-- Time: {$total_time}";
flush();
exit();
