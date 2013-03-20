<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=windows-1252">
        <title>Final Score MC Ban Manager</title>
        <!-- Final Score Themeing -->
<link rel='stylesheet' id='admin-bar-css'  href='http://finalscoremc.com/wp-includes/css/admin-bar.min.css?ver=3.5.1' type='text/css' media='all' />
<link rel='stylesheet' id='bp-admin-bar-css'  href='http://finalscoremc.com/wp-content/plugins/buddypress/bp-core/css/admin-bar.css?ver=1.6.4' type='text/css' media='all' />
<link rel='stylesheet' id='bbp-default-bbpress-css'  href='http://finalscoremc.com/wp-content/plugins/bbpress/templates/default/css/bbpress.css?ver=2.2.4' type='text/css' media='screen' />
<link rel='stylesheet' id='bp-default-main-css'  href='http://finalscoremc.com/wp-content/plugins/buddypress/bp-themes/bp-default/_inc/css/default.css?ver=1.6.4' type='text/css' media='all' />
<link rel='stylesheet' id='bp-default-responsive-css'  href='http://finalscoremc.com/wp-content/plugins/buddypress/bp-themes/bp-default/_inc/css/responsive.css?ver=1.6.4' type='text/css' media='all' />
<style type="text/css">
        body { background-color: #fdfeb8; background-image: url('http://finalscoremc.com/wp-content/uploads/2012/04/fsbackground.png'); background-repeat: repeat-x; background-position: top left; background-attachment: scroll; }
</style>
        <!-- END -->
        <link rel="stylesheet" type="text/css" href="css/custom-theme/jquery-ui-1.10.2.custom.css" />
        <link rel="stylesheet" type="text/css" href="css/main.css" />
        <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js" ></script>
        <script type="text/javascript" src="jquery-ui-1.10.2.custom.min.js"></script>
        <script type="text/javascript" src="fsmcbm.js"></script>
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
                        <li><a href="fsmcbm.php?get=bans">Bans</a></li>
                        <li><a href="fsmcbm.php?get=watchlist">Watch-List</a></li>
                        <li><a href="#search-tab">Search</a></li>
                    </ul>
                    <div id="manage">
                        <div id="user-info" class="form">
                            <label for="user-info-username">Username:</label>
                            <input type="text" id="user-info-username" disabled="disabled" />
                            <br/>

                            <label for="user-info-rank">Rank:</label>
                            <select id="user-info-rank" name="rank">
                                <option>Everyone</option>
                                <option>Regular</option>
                                <option>Donor</option>
                                <option>Builder</option>
                                <option>Engineer</option>
                                <option>Moderator</option>
                                <option>Admin</option>
                            </select>
                            <br/>

                            <label for="user-info-banned">Banned:</label>
                            <input type="checkbox" id="user-info-banned" />
                            <span id="user-info-permanent-box">
                                <label for="user-info-permanent">Permanent:</label>
                                <input type="checkbox" id="user-info-permanent"/>
                            </span>
                            <br/>

                            <label for="user-info-relations">Relations:</label>
                            <textarea id="user-info-relations" name="relations"></textarea>
                            <br/>

                            <label for="user-info-notes">Notes:</label>
                            <textarea id="user-info-notes" name="notes"></textarea>
                            <br/>

                            <button id="user-info-save">Save</button>
                            <button id="user-info-cancel">Cancel</button>
                        </div>
                        <div id="incident-info">

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
                        <label for="user-add-username">Username:</label>
                        <input type="text" value="" id="user-add-username" name="username">
                        <br/>

                        <label for="user-add-rank">Rank:</label>
                        <select id="user-add-rank" name="rank">
                            <option>Everyone</option>
                            <option>Regular</option>
                            <option>Donor</option>
                            <option>Builder</option>
                            <option>Engineer</option>
                            <option>Moderator</option>
                            <option>Admin</option>
                        </select>
                        <br/>

                        <label for="user-add-banned">Banned:</label>
                        <input type="checkbox" id="user-add-banned" name="banned" />
                        <span id="user-add-permanent-box">
                            <label for="user-add-permanent">Permanent:</label>
                            <input type="checkbox" id="user-add-permanent" name="permanent" />
                        </span>
                        <br/>

                        <label for="user-add-relations">Relations:</label>
                        <textarea id="user-add-relations" name="relations"></textarea>
                        <br/>

                        <label for="user-add-notes">Notes:</label>
                        <textarea id="user-add-notes" name="notes"></textarea>
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
