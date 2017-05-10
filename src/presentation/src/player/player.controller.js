/**
 * The MIT License
 *
 * Copyright 2016 Curtis Oakley.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

(function() {
    'use strict';

    angular.module('banManager').controller('PlayerController', PlayerController);

    PlayerController.$inject = ['$scope', '$routeParams', 'Player', 'request', 'formatUUID', 'message'];
    function PlayerController($scope, $routeParams, Player, request, formatUUID, message) {
        // Create a function to set the data in the scope
        var setPlayer = function(player) {
            // Set the player data into the scope
            $scope.player = {
                user: player.user,
                incidents: player.incident,
                history: player.history
            };
            $scope.player.user.uuid = formatUUID(player.user.uuid);
        };

        // Button functions
        $scope.reset = function() {
            // Reload from the server
            Player.get($routeParams.uuid, true).then(setPlayer);
        };
        $scope.saveIncident = function(incident) {
            request('update_incident', incident).success(function() {
                message.successMsg('Incident updated.', 6000);
            });
        };
        $scope.saveUser = function() {
            Player.save().then(function() {
                message.successMsg('Player updated.', 6000);
            });
        };

        // If we have a UUID in the router parameters, load up the player
        if ($routeParams.uuid) {
            Player.get($routeParams.uuid).then(setPlayer);
        }
    }
})();
