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

    angular.module('banManager').factory('Confirm', Confirm);

    Confirm.$inject = ['$rootScope', '$sce', '$uibModal'];
    function Confirm($rootScope, $sce, $uibModal) {
        var $uibModalInstance;
        var modalScope = $rootScope.$new();
        var defaultButtons = [
            {
                action: closeModal,
                disabled: false,
                class: 'btn-primary',
                txt: 'Ok'
            },
            {
                action: dismissModal,
                disabled: false,
                class: 'btn-warning',
                txt: 'Cancel'
            }
        ];

        // Set the button function
        modalScope.callButtonFunction = executeButtonAt;

        return openConfirmation;


        function closeModal() {
            $uibModalInstance.close();
        }

        function dismissModal() {
            $uibModalInstance.dismiss();
        }

        function executeButtonAt($index) {
            modalScope.buttons[$index].action.call(modalScope.buttons[$index].action, $uibModalInstance);
        }

        function openConfirmation(msg) {
            if (!angular.isObject(msg)) {
                msg = { body: msg };
            }
            modalScope.title = msg.title || 'Confirm';
            if (msg.templateUrl) {
                modalScope.isTemplate = true;
                modalScope.body = msg.templateUrl;
            } else {
                modalScope.isTemplate = false;
                modalScope.body = $sce.trustAsHtml(msg.body);
            }
            if (msg.buttons) {
                modalScope.buttons = [];
                angular.forEach(msg.buttons, function(btn) {
                    var button = {};
                    button.action = btn.action || closeModal;
                    button.class = btn.class || 'btn-default';
                    button.txt = btn.txt || '';
                    if ('disabled' in btn) {
                        button.disabled = btn.disabled;
                    } else {
                        button.disabled = false;
                    }
                    modalScope.buttons.push(button);
                });
            } else {
                modalScope.buttons = defaultButtons;
            }
            $uibModalInstance = $uibModal.open({
                scope: modalScope,
                templateUrl: 'presentation/src/confirm-dialog/template.html',
                backdrop: 'static'
            });

            return $uibModalInstance.result;
        }
    }
})();
