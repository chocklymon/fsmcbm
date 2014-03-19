/* Copyright 2014 Curtis Oakley Licensed MIT
 * http://chockly.org/
 */

/**
 * Loads the Javascript and CSS files needed by the ban manager into the page,
 * and places the ban managers HTML into the page. This means that the ban
 * manager will be inserted into any page that includes this file.
 *
 * @requires jQuery 1.8+
 */

(function($) {

    var
        // CSS files needed for the ban manger
        css = [
            'css/custom-theme/jquery-ui-1.10.2.custom.min.css',
            'css/main.min.css'
        ],

        // Javascript files needed by the ban manger.
        script = [
            'js/jquery-ui-1.10.2.custom.min.js',
            'js/bm-config.js.php',
            'js/ban-manager.js'
        ],

        // The URL to the folder where the ban manager files are.
        url = '';// TODO

    // Attach the CSS
    for(var i=0; i<css.length; i++) {
        $('head').append( $('<link>').attr('type', 'text/css').attr('rel', 'stylesheet').attr('href', url + css[i]) );
    }

    // Insert the HTML (this is a compressed version of index.php)
    document.write('<div class="left-menu"><div id="search-box" class="form"><label for="lookup">Lookup:</label><input type="text" id="lookup" /><input type="hidden" id="lookup-user_id" /><br/><label for="search">Search:</label><input type="text" id="search" /><button id="search-button">Go</button></div><div id="actions"><button id="add-user">Add User</button><button id="add-incident">Add Incident</button></div></div><div id="tabs" class="main-column"><ul><li><a href="#manage">Manage</a></li><li>\
<a href="' + url + 'ban-manager.php?get=bans">Bans</a></li><li>\
<a href="' + url + 'ban-manager.php?get=watchlist">Watch-List</a></li><li><a href="#search-tab">Search</a></li></ul><div id="manage"><div id="user-info" class="form"><h5>Select a user to manage.</h5></div><div id="incident-info"></div><div id="ban-history"></div></div><div id="search-tab"></div></div><div id="error" class="ui-widget"><div class="ui-state-error ui-corner-all"><p><span class="ui-icon ui-icon-info"></span><strong>Error: </strong><span id="error-msg"></span></p></div></div><div id="highlight" class="ui-widget"><div class="ui-state-highlight ui-corner-all"><p><span class="ui-icon ui-icon-alert"></span><span id="highlight-msg"></span></p></div></div><div id="dialog-add-user" title="Add User"><form id="add-user-form" class="form"><label for="user_add_username">Username:</label><input type="text" value="" id="user_add_username" name="username"><br/></form></div><div id="dialog-add-incident" title="Add Incident"><form class="form" id="add-incident-form"><label for="user">Username:</label><input type="text" id="user_name" /><input type="hidden" id="user_id" name="user_id" /><br/></div>');


    // Attach the JavaScript
    for(var i=0; i<script.length; i++) {
        $('head').append( $('<script>').attr('type', 'text/javascript').attr('src', url + script[i]) );
    }

})(jQuery);
