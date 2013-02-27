<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=windows-1252">
        <title>Final Score MC Ban Manager</title>
        <link rel="stylesheet" type="text/css" href="css/ui-lightness/jquery-ui-1.10.1.custom.css" />
        <link rel="stylesheet" type="text/css" href="css/main.css" />
        <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js" ></script>
        <script type="text/javascript" src="jquery-ui-1.10.1.custom.js"></script>
        <script type="text/javascript" src="fsmcbm.js"></script>
    </head>
    <body>
        <div id="wrapper">
            <div id="sidebar">
                <div id="search-box" class="form">
                    <label for="lookup">Lookup:</label>
                    <input type="text" id="lookup" />
                    <input type='hidden' id="user_id" />
                    <br/>
                    <label for="search">Search:</label>
                    <input type="text" id="search" />
                </div>
                <div id="actions">
                    <button id="add-user">Add User</button>
                    <button id="add-incident">Add Incident</button>
                </div>
            </div>
            <div id="tabs">
                <ul>
                    <li><a href="#manage">Manage</a></li>
                    <li><a href="fsmcbm.php?get=bans">Bans</a></li>
                    <li><a href="fsmcbm.php?get=watchlist">Watch-List</a></li>
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
            </div>
        </div>
        <!-- Dialogs -->
        <div id="dialog-add-user" title="Add User">
            <form id="add-user-form" class="form">
                <label for="user-info-username">Username:</label>
                <input type="text" value="" id="user-add-username" name="username">
                <br/>

                <label for="user-info-rank">Rank:</label>
                <select id="user-add-rank" name="rank">
                    <option>Everyone</option>
                    <option>Regular</option>
                    <option>Builder</option>
                    <option>Engineer</option>
                    <option>Moderator</option>
                    <option>Admin</option>
                </select>
                <br/>

                <label for="user-add-banned">Banned:</label>
                <input type="checkbox" id="user-add-banned" />
                <span id="user-info-permanent-box">
                    <label for="user-add-permanent">Permanent:</label>
                    <input type="checkbox" id="user-add-permanent"/>
                </span>
                <br/>

                <label for="user-add-relations">Relations:</label>
                <textarea id="user-info-relations" name="relations"></textarea>
                <br/>

                <label for="user-add-notes">Notes:</label>
                <textarea id="user-info-notes" name="notes"></textarea>
            </form>
        </div>
        <div id="dialog-add-incident" title="Add Incident">
            <form class="form" id="add-incident-form">
                <label>Incident Date</label>
                <input type="text" name="incident_date" />
                <br/>
                <label>Notes</label>
                <textarea name="notes"></textarea>
                <br/>
                <label>Action Taken</label>
                <textarea name="action_taken"></textarea>
                <br/>
                Key Location
                <br/>
                <label>World</label>
                <select name="world">
                    <option value=""></option>
                    <option value="world1">Alpha</option>
                    <option value="world3">Delta</option>
                    <option value="world4">Gamma</option>
                </select>
                <label>X</label>
                <input type="text" name="coord_x" class="int" />
                <label>Y</label>
                <input type="text" name="coord_y" class="int" />
                <label>Z</label>
                <input type="text" name="coord_z" class="int" />
        </div>
    </body>
</html>
