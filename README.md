Minecraft Ban Manager
=================================

This is the ban manager used by FinalScoreMC.
With a few modifications it could be used as a ban manager for other
minecraft servers.

[![Build Status](http://img.shields.io/travis/chocklymon/fsmcbm.svg)](https://travis-ci.org/chocklymon/fsmcbm)


Setup
--------------------------------

1. Create a database for the ban manager.
2. Run skeleton.sql in the database to set up the tables.
3. Add the player ranks into the rank table.
    * The ranks of Admin and Moderator will have access to use the ban manager.
4. Add your user into the users table with the rank of admin or moderator.
5. Modify bm-config.php with your MySQL database login information.
6. Run `bower install`

You should be able to start using the ban manager at this point.


#### Example Setup ####

This example setup assumes a *nix system with mysql and node with bower installed.

1. Create and setup the database using MySQL:
```
CREATE USER 'username'@'localhost' IDENTIFIED BY 'password';
CREATE DATABASE IF NOT EXISTS ban_manager;
GRANT ALL PRIVILEGES ON ban_manager.* TO 'username'@'localhost' IDENTIFIED BY 'password';
```
2. `mysql -uroot < src/skeleton.sql`
3. Setup the player ranks:
    1. `mysql -uroot`
    2. `use ban_manager`
    3. `INSERT INTO rank ('name') VALUES ('Admin'), ('Moderator');`
4. Add an admin:
    1. `INSERT INTO users ('uuid', 'rank') VALUES ('A1634F37480A4BB9A0B2200266597AC0', 1)`
5. Setup the configuration:
    1. `cp src/bm-config.sample.php src/bm-config.php`
    2. Edit the `src/bm-config.php` file.
        - Set the `db_username` and `db_password` and setup the username and password entered in step one.
        - Modify the authentication and other settings as needed. See comments in sample file.
6. `bower install`

You may need to disable the only full group by sql mode:  
`SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))`


#### License ####

Copyright (c) 2014-2016 Curtis Oakley  
Licensed under the MIT license. See LICENSE.txt
