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

    angular.module('banManager').controller('AddPlayerController', AddPlayerController);

    AddPlayerController.$inject = ['$scope', 'Player', '$uibModalInstance', 'message', 'Confirm'];
    function AddPlayerController($scope, Player, $uibModalInstance, message, Confirm) {
        var addPlayer = function() {
            $scope.submitting = true;
            Player.add($scope.player.info).then(
                function(response) {
                    message.successMsg('Player added.', 6000);
                    $uibModalInstance.close(response);
                },
                function(err) {
                    var errorMsg = 'Problem adding player. ';
                    if (err.data && err.data.error) {
                        errorMsg += err.data.error;
                    }
                    message.errorMsg(errorMsg);
                    $scope.submitting = false;
                }
            );
        };

        $scope.player = {
            info: {}
        };
        $scope.playerForm = {};
        $scope.save = function() {
            $scope.playerForm.attempted = true;
            if ($scope.playerForm.$invalid) {
                message.addMessage(message.WARNING, 'Please fill out all required fields!', 10000);
            } else if (!$scope.player.info.username) {
                Confirm(
                    'Are you sure that you wish to add a player without a username?<br><br>' +
                    'Press Ok to add the player.<br>' +
                    'Press Cancel to close this dialog and add a username.'
                ).then(addPlayer);
            } else {
                addPlayer();
            }
        };
        $scope.cancel = function() {
            $uibModalInstance.dismiss('cancel');
        };
    }
})();
