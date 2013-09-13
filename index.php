<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=windows-1252">
        <title>Final Score MC Ban Manager</title>
        <link href="css/custom-theme/jquery-ui-1.10.2.custom.min.css" type="text/css" rel="stylsheet" />
        <link href="css/main.min.css" type="text/css" rel="stylesheet" />
        <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js" ></script>
        <script type="text/javascript" src="jquery-ui-1.10.2.custom.min.js"></script>
        <script type="text/javascript" src="ban-manager.js"></script>
    </head>
    <body class="page">
        <div id="container">
            <div id="content">
                <div class="left-menu">
                    <div id="search-box" class="form">
                        <label for="lookup">Lookup:</label>
                        <input type="text" id="lookup" />
                        <input type='hidden' id="lookup-user_id" />
                        <br/>
                        <label for="search">Search:</label>
                        <input type="text" id="search" />
                        <button id="search-button">Go</button>
                    </div>
                    <div id="actions">
                        <button id="add-user">Add User</button>
                        <button id="add-incident">Add Incident</button>
                    </div>
                </div>
                <div id="tabs" class="main-column" style="overflow:auto"><!-- Overflow auto - fixes tabs height being weird with floats -->
                    <ul>
                        <li><a href="#manage">Manage</a></li>
                        <li><a href="ban-manager.php?get=bans">Bans</a></li>
                        <li><a href="ban-manager.php?get=watchlist">Watch-List</a></li>
                        <li><a href="#search-tab">Search</a></li>
                    </ul>
                    <div id="manage">
                        <div id="user-info" class="form">
                            <h5>Select a user to manage.</h5>
                        </div>
                        <div id="incident-info">

                        </div>
                        <div id="ban-history">
                            
                        </div>
                    </div>
                    <div id="search-tab">

                    </div>
                </div>
                <!-- Message Boxes -->
                <div id="error" class="ui-widget">
                    <div class="ui-state-error ui-corner-all">
                        <p>
                            <span class="ui-icon ui-icon-info"></span>
                            <strong>Error: </strong><span id="error-msg"></span>
                        </p>
                    </div>
                </div>
                <div id="highlight" class="ui-widget">
                    <div class="ui-state-highlight ui-corner-all">
                        <p>
                            <span class="ui-icon ui-icon-alert"></span>
                            <span id="highlight-msg"></span>
                        </p>
                    </div>
                </div>
                <!-- Dialogs -->
                <div id="dialog-add-user" title="Add User">
                    <form id="add-user-form" class="form">
                        <label for="user_add_username">Username:</label>
                        <input type="text" value="" id="user_add_username" name="username">
                        <br/>
                    </form>
                </div>
                <div id="dialog-add-incident" title="Add Incident">
                    <form class="form" id="add-incident-form">
                        <label for="user">Username:</label>
                        <input type="text" id="user_name" />
                        <input type='hidden' id="user_id" name="user_id" />
                        <br/>
                </div>
            </div>
        </div>
    </body>
</html>
