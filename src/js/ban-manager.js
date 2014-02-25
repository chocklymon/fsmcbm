/* Ban Manager
 * Main JavaScript file.
 */

// Make sure that there is a bm object
if (!window.bm) {
    alert("Ban manager failed to load correctly and will not function correctly.");
}

// If there is no console, make a fake one
if (!window.console || !window.console.log) {
    console = {
        log : function(msg) {}
    };
}

/**
 * Anonymous wrapper for the code.
 * @param {jQuery} $ The jQuery instance.
 * @param {undefined} empty Not set so we can compare for undefined.
 */
(function($, empty) {

    /* ----------------------------- *
     *          VARIABLES            *
     * ----------------------------- */
    var
        // Used by the add incident functions
        attachNewUser = false,
        // Used to store the timeout IDs
        timeouts = [];



    /* ----------------------------- *
     *          FUNCTIONS            *
     * ----------------------------- */


    /**
     * Opens the add user dialog, and after the new user is saved, adds them to the incident.
     */
    function attachNewUserToIncident() {
        attachNewUser = true;
        $("#user_add_username").val($("#user_name").val());
        openAddUser();
    }


    /**
     * Displays a message to a user in a jQuery UI highlight box.
     * @param {String} message The message to display.
     */
    function displayMessage(message) {
        // Display the message
        $("#highlight-msg").text(message);
        $("#highlight").slideDown();

        // Display for four seconds
        timeouts['messages'] = setTimeout(
            function() {
                $("#highlight").slideUp();// TODO have a way to hide the message earlier.
            },
            4000
        );
    }


    /**
     *  Retrieves the user information, and then displays it into the manage
     *  users tab.
     */
    function getInformation() {
        request(
            {'lookup' : $("#lookup-user_id").val()},
            function(data) {
                // Empty out any previous incidents and save the incidents div
                var incidents = $("#incident-info").empty(),

                    // Empty out an previous ban history and save the element
                    history = $("#ban-history").empty(),

                    // Emtpy out the previous user field and save the element
                    userInfo = $("#user-info").empty(),

                    // Misc Variables
                    datum, el, i;


                // Fill in the fields //

                // Attach the user data
                $.each(bm.user, function(dsName, ds) {
                    el = ds.toHTML(data.user[dsName], dsName, "info");
                    userInfo.append( el );
                });

                // Update the permanent banned state
                $("#banned_info").change(togglePermanentBox).change();

                // Attach the save button
                userInfo.append($("<button>").text("Save").click(function() {
                    // Save the user information //

                    // Verify that there is a user
                    var user_id = $("#lookup-user_id").val();
                    if (user_id == '') {
                        displayMessage("Please select a user.");
                        return;
                    }

                    // Serialize the user information
                    var datum = serialize(bm.user, "info");

                    datum.id = user_id;

                    // Send in the changes
                    send(
                        datum,
                        'update_user',
                        function(data) {
                            displayMessage("User updated.");
                        }
                    );
                }));


                if(data.incident != null) {

                    // Attach all the incidents
                    for(i=0; i<data.incident.length; i++) {
                        datum = data.incident[i];

                        el = $("<div>").addClass("form").attr("id", "i-" + datum.incident_id);
                        el.appendTo(incidents);

                        $.each(bm.incident, function(dsName, ds) {
                            el.append(ds.toHTML(datum[dsName], dsName, datum.incident_id));
                        });

                        // Add the save button
                        el.append($("<button>").text("Save").attr("id","i-s-" + datum.incident_id).click(function() {

                            // Get the ID and disable the button (to prevent repeatedly clicking the button)
                            var id = $(this).addClass("disabled").prop("disabled", true).attr('id').substring(4);

                            // Serialize the incident fields
                            var datum = serialize(bm.incident, id);

                            datum.id = id;

                            // Post in the updated incident
                            send(
                                datum,
                                'update_incident',
                                function(data) {
                                    // Success
                                    displayMessage("Incident updated.");
                                },
                                function() {
                                    // Re-enable the save button when the AJAX completes
                                    $("#i-s-" + id).removeClass("disabled").prop("disabled", false);
                                }
                            );
                        }));
                    }
                }

                // Attach the ban history
                if(data.history != null) {
                    el = $("<table>");
                    el.append("<tr><th>Moderator</th><th>Date</th><th>Banned</th><th>Permanent</th></tr>");

                    for(i=0; i < data.history.length; i++) {
                        datum = data.history[i];
                        el.append("<tr><td>" + datum.moderator + "</td><td>" + datum.date + "</td><td>" + (datum.banned == 1 ? "Yes" : "") + "</td><td>" + (datum.permanent == 1 ? "Yes" : "") + "</td></tr>");
                    }

                    history.append( $("<h3>").text("Ban History") ).append(el);
                }

                // Attach the cancel button, if needed
                if($("#cancel").length === 0) {
                    $("<button>").attr('id','cancel').text("Cancel").click(getInformation).appendTo("#manage");
                }

                // Switch to the manage tab
                $("#tabs").tabs("option", "active", 0);

            }
        );
    }


    /**
     * Handles errors.
     * @param {String} error The error message. Optional.
     */
    function handleError(error) {
        // Log the error message
        console.error(error);

        // Display the error message
        $("#error-msg").text(error);
        $("#error").slideDown();

        // Display for six seconds
        timeouts['errors'] = setTimeout(
            function() {
                $("#error").slideUp();
            },
            6000
        );
    }


    /**
     * Sets up an field to be an jQuery UI AutoComplete enabled field.
     * @param {String} input The jQuery selector to apply the autocomplete to.
     * @param {String} value The jQuery selector for the element to set the
     * autocompletes value to.
     * @param {Function} callback A function to call after the a value has been
     * selected from the autocomplete options. Can be null.
     * @param {String} emptyLabel The option text to display when no results are
     * found.
     * @param {Function} emptyCallback A function to call if the no result option
     * is selected. Can be null.
     */
    function lookup(input, value, callback, emptyLabel, emptyCallback) {
        $(input).autocomplete({
            source : function (payload, response) {
                // Request the autocomplete matching terms
                request(
                    {term: payload.term},
                    function (data) {
                        if (data.length === 0) {
                            // Nothing returned, create the custom empty option
                            data.push({
                                'value': 0,
                                'label': emptyLabel
                            });
                        }
                        response(data);
                    }
                );
            },
            minLength : 2,
            select : function( event, ui  ) {
                if(ui.item.value == 0) {
                    // Empty option selected
                    if(typeof emptyCallback == 'function') {
                        emptyCallback();
                    }
                } else {
                    // Option selected
                    $(this).val(ui.item.label);
                    $(value).val(ui.item.value);

                    if(typeof callback == 'function') {
                        callback();
                    }
                }
                return false;
            }
        });
    }


    /**
     * Opens the add user jQuery UI dialog.
     */
    function openAddUser() {
        $("#dialog-add-user").dialog("open");
    }


    function request(payload, callback, completed, urlExtra, method, dataType) {
        if (method === empty) {
            method = 'get';
        }
        if (urlExtra === empty) {
            urlExtra = '';
        }
        if (dataType === empty) {
            dataType = 'json';
        }
        $.ajax({
            url      : "ban-manager.php" + urlExtra,
            data     : payload,
            dataType : dataType,
            type     : method,
            complete : completed,
            error    : function(jqXHR, textStatus, errorThrown) {
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);

                handleError("Problem with request to server.");
            },
            success  : function(data) {
                if (dataType == 'json' && data.error !== empty) {
                    // An error has occured
                    handleError(data.error);
                } else if (typeof callback == 'function') {
                    // Success, call the calback function
                    callback(data);
                }
            }
        });
    }

    function send(payload, command, callback, completed) {
        command = '?' + command + '=true';
        request(payload, callback, completed, command, 'post');
    }

    /**
     * Performs a lookup on a user based on which table row was clicked.
     */
    function rowLookup() {
        console.log(this);
        $("#lookup-user_id").val($(this).attr("id").substring(3));
        getInformation();
    }


    function search() {
        if ($("#search").val().length < 2) {

            displayMessage("Search must be two or more characters long.");

        } else {
            request(
                {search : $("#search").val()},
                function(data) {
                    // Set the tab contents
                    $("#search-tab").html(data);

                    // Attach the row lookup event
                    $("#search-tab tr").click(rowLookup)

                    // Re-enable the search tab, and switch to it.
                    $("#tabs").tabs("enable", 3).tabs("option", "active", 3);
                },
                empty,
                empty,
                empty,
                'html'
            );
        }
    }


    /**
     * Takes a datastructure on the page and serializes it to an object.
     * @param {DataStructure} structure The data structure that is being serialized.
     * @param {String} id The ID number of the HTML tags containing the data.
     * This should be the same as the idNum passed to the generated HTML function
     * of datastructure.
     * @return {Object} The serialized data.
     */
    function serialize(structure, id) {
        var datum = {};

        $.each(structure, function(index, value) {
            var field = $("#" + index + "_" + id);
            if (typeof value.serialize == 'function') {
                var result = value.serialize(field);
                if (result === false)
                    return;
                datum[index] = result;
            } else if (value.type == "checkbox") {
                // Handle checkboxes
               datum[index] = field.is(":checked") ? "true" : "false";
            } else {
                // Generic type, get its value
                datum[index] = field.val();
            }
        });

        return datum;
    }


    function togglePermanentBox() {
        var cb = $(this);
        cb.next('.user-info-permanent-box').css(
            'display',
            cb.prop('checked') ? 'inline' : 'none'
        );
    }



    /* ----------------------------- *
     *           INITALIZE           *
     * ----------------------------- */

    // Runs on document ready.
    $( function($) {

        // Save variables
        var incidentForm = $("#add-incident-form"),
            addUserForm = $("#add-user-form");

        // Build the add incident dialog form
        $.each(bm.incident, function(index, value) {
            // Don't attach read only fields
            if (!value.disabled) {
                incidentForm.append(value.toHTML("", index, "add"));
            }
        });

        // Build the add user dialog form
        $.each(bm.user, function(index, value) {
            // Don't attach read only fields
            if (!value.disabled) {
                addUserForm.append(value.toHTML("", index, "add_u"));
            }
        });

        // Set up the tabs
        $("#tabs").tabs({
            beforeLoad : function(event, ui) {
                // Set up error handling
                ui.jqXHR.error(function() {
                   ui.panel.html("Couldn't load this tab.");
                });
            },
            load       : function(event, ui) {
                $(ui.panel).find("tr").click(rowLookup);
            },
            disabled   : [3]// Search tab is disabled by default
        });

        // Set up the dialogs
        $("#dialog-add-user").dialog({
            autoOpen : false,
            modal    : true,
            height   : 450,
            width    : 540,
            buttons  : {
                Save   : function() {
                    // Verify that we have a username
                    if ($("#user_add_username").val() == '') {
                        displayMessage("Please provide a username.");
                        return;
                    }

                    // Save the user
                    send(
                        $("#add-user-form").serialize(),
                        'add_user',
                        function(data) {
                            // Success
                            displayMessage("User added succesfully.");

                            // See if the user needs to be attached to an incident
                            if(attachNewUser) {
                                $("#user_name").val($("#user_add_username").val());
                                $("#user_id").val(data.user_id);
                                attachNewUser = false;
                            }

                            // Reset the add user fields
                            $("#add-user-form")[0].reset();
                        }
                    );

                    $(this).dialog("close");
                },
                Cancel : function() {
                    $(this).dialog("close");
                }
            }
        });
        $("#dialog-add-incident").dialog({
            autoOpen : false,
            modal    : true,
            height   : 480,
            width    : 645,
            buttons  : {
                Save   : function() {
                    // Verify that we have data
                    if ($("#user_id").val() == '') {
                        displayMessage("Please enter a user.");
                        return;
                    } else if ($("#notes_add").val() == '') {
                        if (!confirm("You didn't enter any notes!\n\nPress OK to save this incident anyways.")) {
                            return;
                        }
                    }

                    // Save the incident.
                    send(
                        $("#add-incident-form").serialize(),
                        'add_incident',
                        function(data) {
                            // Success
                            displayMessage("Incident added.");

                            // Reset the add incident fields
                            $("#add-incident-form")[0].reset();
                        }
                    );

                    $(this).dialog("close");
                },
                Cancel : function() {
                    $(this).dialog("close");
                }
            }
        });

        // Set up the autocomplete lookup fields
        lookup("#lookup", "#lookup-user_id", getInformation, "No users found");
        lookup("#user_name", "#user_id", null, "Not Found - Add New", attachNewUserToIncident);

        // Attach events \\

        // Add user button
        $("#add-user").click(openAddUser);

        // Add incident button
        $("#add-incident").click(function() {
            $("#dialog-add-incident").dialog("open");
        });

        // Permanent banned checkbox display
        $("#banned_add_u").change(togglePermanentBox);

        // Search box
        $("#search-button").click(search);
        $("#search").keyup(function(event) {
            // Run when the enter key is pressed
            if(event.keyCode == 13) {
                search();
            }
        });

        // Message boxes
        $("#highlight").click(function(){
            clearTimeout(timeouts['messages']);
            $(this).slideUp(200);
        });
        $("#error").click(function(){
            clearTimeout(timeouts['errors']);
            $(this).slideUp(200);
        });
    });

})(jQuery);
