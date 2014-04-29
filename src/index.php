<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>Final Score MC Ban Manager</title>
        <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css">
    </head>
    <body>
        <div class="container" data-ng-app="banManager">
            <div class="bm-header ui-tabs">
                <ul class="ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all">
                    <li><a id='manage_user' data-ng-href="#/user/{{data.username}}">Manage</a></li>
                    <li><a href="#/bans">Bans</a></li>
                    <li><a href="#/watchlist">Watch-List</a></li>
                    <li><a href="#/search">Search</a></li>
                </ul>
            </div>
            <div data-ng-controller="TypeaheadCtrl">
                <input type="text"
                       ng-model="selected"
                       placeholder="Find user by name"
                       typeahead="user.label for user in getLocation($viewValue)"
                       typeahead-loading="loadingLocations"
                       typeahead-min-length="2"
                       typeahead-on-select="welcome()"
                       class="form-control" />
                <i ng-show="loadingLocations" class="glyphicon glyphicon-refresh"></i>
            </div>
            <div data-ng-view></div>
        </div>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.16/angular.min.js"></script>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.16/angular-route.min.js"></script>
        <script type="text/javascript" src="presentation/assets/js/ui-bootstrap-tpls-0.10.0.min.js"></script>
        <script type="text/javascript" src="presentation/assets/js/app.js"></script>
    </body>
</html>
