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

    angular.module('banManager').controller('AddIncidentController', AddIncidentController);

    AddIncidentController.$inject = ['$scope', 'request', '$uibModalInstance', 'message', 'Confirm'];
    function AddIncidentController($scope, request, $uibModalInstance, message, Confirm) {
        $scope.incident = {};
        $scope.incidentForm = {};

        $scope.incident.selectUser = function($item) {
            $scope.incident.user_id = $item.user_id;
        };

        var addIncident = function() {
            $scope.submitting = true;
            request('add_incident', $scope.incident).then(
                function(response) {
                    message.successMsg('Incident added.', 6000);
                    $uibModalInstance.close(response);
                },
                function(err) {
                    var errorMsg = 'Problem adding incident. ';
                    if (err.data && err.data.error) {
                        errorMsg += err.data.error;
                    }
                    message.errorMsg(errorMsg);
                    $scope.submitting = false;
                }
            );
        };

        $scope.save = function() {
            // Validate that the incident is correctly set up
            if ($scope.incidentForm.$invalid) {
                if (!$scope.incidentForm.$dirty) {
                    $scope.incidentForm.$setDirty(true);
                }
                message.addMessage(message.WARNING, 'Please fill out all required fields!', 10000);
            } else if (!$scope.incident.user_id) {
                message.addMessage(message.WARNING, 'Please select a user!', 10000);
            } else if (!$scope.incident.notes) {
                Confirm(
                    'Are you sure that you wish to add an incident without any notes?<br><br>' +
                    'Press Ok to add the incident.<br>' +
                    'Press Cancel to close this dialog and add notes.'
                ).then(addIncident);
            } else {
                addIncident();
            }
        };
        $scope.cancel = function() {
            $uibModalInstance.dismiss('cancel');
        };
    }
})();
