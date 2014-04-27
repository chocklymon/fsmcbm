<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>Final Score MC Ban Manager</title>
        <link href="css/custom-theme/jquery-ui-1.10.2.custom.css" type="text/css" rel="stylesheet" />
        <link href="css/main.css" type="text/css" rel="stylesheet" />
    </head>
    <body>
        <div class="container" data-ng-app="banManager">
            <div class="bm-header ui-tabs">
                <ul class="ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all">
                    <li><a href="#/user">Manage</a></li>
                    <li><a href="#/bans">Bans</a></li>
                    <li><a href="#/watchlist">Watch-List</a></li>
                    <li><a href="#/search">Search</a></li>
                </ul>
            </div>
            <div data-ng-view></div>
        </div>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js" ></script>
        <script type="text/javascript" src="js/jquery-ui-1.10.2.custom.min.js"></script>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.16/angular.min.js"></script>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.16/angular-route.min.js"></script>
        <script type="text/javascript" src="presentation/assets/js/app.js"></script>
    </body>
</html>
