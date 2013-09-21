Minecraft Ban Manager
=================================

This is the ban manager used by FinalScoreMC.
With a few modifications it could be used as a ban manager for other
minecraft servers.


Setup
--------------------------------

1. Create a database for the ban manager.
2. Run skeleton.sql in the database to set up the tables.
3. Modify bm-config.php with your MySQL database login information.
4. Add the user ranks into the rank table.
    * The ranks of Admin and Moderator will have access to use the ban manager.
5. Add your user into the users table with the rank of admin or moderator.

You should be able to start using the ban manager at this point.

