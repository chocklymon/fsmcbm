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
                <tabset justified="true">
                    <tab heading="User" select="tab.switch('user')"></tab>
                    <tab heading="Bans" select="tab.switch('bans')"></tab>
                    <tab heading="Watchlist" select="tab.switch('watchlist')"></tab>
                    <tab heading="Search" select="tab.switch('search')"></tab>
                </tabset>
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
                <input type="text"
                       ng-model="search.text" />
            </div>
            <div data-ng-view></div>
        </div>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.16/angular.min.js"></script>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.16/angular-route.min.js"></script>
        <script type="text/javascript" src="presentation/assets/js/ui-bootstrap-tpls-0.10.0.min.js"></script>
        <script type="text/javascript" src="presentation/assets/js/app.js"></script>
    </body>
</html>
