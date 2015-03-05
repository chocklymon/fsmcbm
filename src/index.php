<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Final Score MC Ban Manager</title>
        <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" />
        <link rel="stylesheet" href="presentation/assets/vendor/angular-loading-bar/build/loading-bar.min.css" />
        <link rel="stylesheet" href="presentation/assets/css/base.css" />
    </head>
    <body>
        <div class="container" data-ng-app="banManager">
            <div data-ng-include="src='presentation/views/navigation.html'" data-ng-controller="NavigationController"></div>
            <div data-ng-view class="tabarea">
                Loading...
            </div>
        </div>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.28/angular.min.js"></script>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.28/angular-route.min.js"></script>
        <script type="text/javascript" src="presentation/assets/vendor/angular-bootstrap/ui-bootstrap-tpls.min.js"></script>
        <script type="text/javascript" src="presentation/assets/vendor/angular-loading-bar/build/loading-bar.min.js"></script>
        <script type="text/javascript" src="presentation/assets/js/app.js"></script>
    </body>
</html>
