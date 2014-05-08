<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Final Score MC Ban Manager</title>
        <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css">
    </head>
    <body>
        <div class="container" data-ng-app="banManager">
            <div data-ng-include="src='presentation/views/navigation.html'" data-ng-controller="NavigationController"></div>
            <div data-ng-view></div>
        </div>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.16/angular.min.js"></script>
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/angularjs/1.2.16/angular-route.min.js"></script>
        <script type="text/javascript" src="presentation/assets/js/ui-bootstrap-tpls-0.10.0.min.js"></script>
        <script type="text/javascript" src="presentation/assets/js/app.js"></script>
    </body>
</html>
