/* Final Score MC Ban Manager
 * 
 */

// TODO make a better way to handle different domains than this
// Create the bm object if we don't have it.
if (window.bm == null || bm.url == null) {
    var bm = {
        url : ''
    };
}

(function($) {
    
    /* ----------------------------- *
     *  VARIABLES AND CONFIGURATION  *
     * ----------------------------- */
    var 
        // Data structure, see documentation below
        info,
        DataStructure,
        
        // Data structure for the user
        user,
        
        // Data structure for incidents
        incident,
        
        // Used internally by add incident functions
        attachNewUser = false;

        
    /* ----------------------------- *
     *   DATA STRUCTURE OBJECT       *
     * ----------------------------- */
    
    /**
     * Convenience constructor for a DataStructure.
     * @param {Object} options The options/settings for the DataStructure.
     * Options:
     * type {String} Type of the DataStructure valid type strings: text, date, textarea, select, int, checkbox. Default is text.
     * disabled {boolean} Whether this field should be disabled. Default is false.
     * name {String} The name of the Data Stucture. Used when ouptuing it's form label.
     * after {Mixed} The element to insert after the DataStructure when it is generated. Default is "<br/>"
     * options {Array} An array of Strings or objects to use as the options when the type is select. Objects need label and value properties.
     * showEmpty {Mixed} Indicates if this DataStructure should be generated when it has no value. Accepts boolean or a function that returns true. The function is passed the id number of the html field.
     * special {Function} This function is called immediatly after the HTML is generated and is passed the jQuery object that contains the HTML for the datastructure. Used to attach special handlers to the field or provided additional features to the html structure. The returned value is used for insertion into the DOM. Default is null.
     * serialize {Function} This function is called when the field is serialized. It is passed in the jQuery object containing the datastructure's node in the DOM, it should return the data/object to be serialized or false if the field shouldn't be serialized. When null the field will use the default serialization method. Default is null.
     */
    info = function(options) {
        return new DataStructure(options);
    };
    
    /**
     * DataStructure Constructor.
     * See the info documentation for information about datastructures.
     */
    DataStructure = function(options) {
        var datum, i;
        
        datum = $.extend({
            // Default Options
            type:"text",
            disabled: false,
            name:null,
            after:"<br/>",
            options:[],
            showEmpty:true,
            special:null,
            serialize:null
        }, options);
        
        // Attach each option to the data structure
        for ( i in datum ) {
            this[i] = datum[i];
        }
        
        return this;
    };
    
    // Define the data structure API
    info.fn = DataStructure.prototype = {
        
        // Data Structure Functions

        /**
         * Takes a data structre and turns into into a set of HTML tags.
         * @parm {String} value The value of the data structure.
         * @parm {String} name The name of the data structure.
         * @parm {int} idNum An ID number to attach to the html tags.
         * @return A jQuery object containing the HTML nodes for the data structure.
         */
        toHTML : function(value, name, idNum) {
            /* Types:
             * - text
             * - date
             * - textarea
             * - select
             * - int
             * - checkbox
             */
            var showEmpty,
                label,
                field,
                id = name + "_" + idNum;
            
            // See if this field should be shown when empty
            if( typeof(this.showEmpty) == 'function' ) {
                showEmpty = this.showEmpty(idNum);
            } else {
                showEmpty = this.showEmpty;
            }
            
            if( ! showEmpty ) {
                if(value == null || value == "") {
                    // Don't show empty fields
                    return "";
                }
            }
            
            value = this.formatValue(value);
            
            label = $("<label>").text(this.name + ":").attr("for", id);
            
            if(this.type == "textarea") {
                // Text area
                field = $("<textarea>").text(value);
                
            } else if(this.type == "select") {
                // Drop Down
                field = $("<select>")
                
                // Attach each of the options
                var i;
                for(i in this.options) {
                    i = this.options[i];
                    if(i.label == null) {
                        field.append($("<option>").text(i));
                    } else {
                        field.append($("<option>").attr("value", i.value).text(i.label));
                    }
                }
                
                field.val(value);
                
            } else if(this.type == "text" || this.type == "int" || this.type == "date") {
                // Text, date, and int types
                field = $("<input>").attr('type', 'text').val(value);
                
                if(this.type == "int") {
                    label.addClass("int");
                    field.addClass("int");
                }
                
            } else if(this.type == "checkbox") {
                // Checkbox
                field = $("<input>").attr({
                    'type' : 'checkbox'
                });
                
                if(value == 1) {
                    // Check the checkbox
                    field.prop("checked", true);
                }
                
            } else {
                // Unknown
                console.log("Unkown Type: " + this.type);
                return "";
            }
            
            // Add common attributes to the field
            field.attr({
                "name": name,
                "id"  : id
            });
            
            // Attach the date picker to dates
            if( this.disabled ) {
                // Disabled field
                field.prop('readonly', true);
                
                // Date check part of this if else so that disabled fields don't have the datpicker attached.
            } else if(this.type == "date") {
                field.datepicker({
                   showOn: "both",
                   buttonImage: bm.url + "calendar-month.png",
                   buttonImageOnly : true,
                   dateFormat : "yy-mm-dd",
                   maxDate : 0
                });
            }
            
            field = label.add(field).add(this.after);
            
            if (this.special != null && typeof(this.special) == 'function') {
                field = this.special(field);
            }
            
            return field;
        },
        
        formatValue : function(value) {
            // Null should be empty strings
            if(value == null)
                return "";
            
            if(this.type == "date") {
                // Do date formating (change from yy-mm-dd hh:mm:ss to yy-mm-dd)
                var index = value.indexOf(" ");
                if(index > 0) {
                    value = value.substring(0, index);
                }
                return value;
            }
            
            // No formating needed, return the value
            return value;
        }
    };
    
    
    
    /* ----------------------------- *
     *      DATA STRUCTURES          *
     * ----------------------------- */
    
    
    /**
     * Contains the data structures for a user.
     */
    user = {
        username : info({
            name : "Username",
            disabled : true,
            after : "",
            special : function(field) {
                
                // When the user name field is double clicked make it editable
                $(field[1]).dblclick(function(){
                    $(this).prop('readonly', false);
                })
                // Store the orginal username
                .data("orginal", $(field[1]).val());
                
                return field;
            },
            serialize : function(field) {
                // TODO
                if (!field.prop("readonly")) {
                    var original = field.data("orginal");
                    if (original != field.val() && confirm("Are you sure that you wish to change the username?\nPress OK to have the username changed.")) {
                        return field.val();
                    } else {
                        field.val(original);
                    }
                }
                return false;
            }
        }),
        modified_date : info({
            name : "Modified Date",
            disabled : true,
            type : "date"
        }),
        banned : info({
            name : "Banned",
            type : "checkbox",
            after : ""
        }),
        permanent : info({
            name : "Permanent",
            type : "checkbox",
            after: "",
            special : function(field) {
                return field.wrapAll("<span id='user-info-permanent-box' />").parent().add("<br/>");
            }
        }),
        rank : info({
            name : "Rank",
            type : "select",
            options : [{
                        value:1,
                        label:"Everyone"
                    },{
                        value:2,
                        label:"Regular"
                    },{
                        value:3,
                        label:"Donor"
                    },{
                        value:4,
                        label:"Builder"
                    },{
                        value:5,
                        label:"Engineer"
                    },{
                        value:6,
                        label:"Moderator"
                    },{
                        value:7,
                        label:"Admin"
                    },{
                        value:8,
                        label:"Default"
                    }]
        }),
        relations : info({
            name : "Relations",
            type : "textarea"
        }),
        notes : info({
            name : "Notes",
            type : "textarea"
        })
    };
    
    /**
     * Contains the Data Structures for incidents.
     */
    incident = {
        moderator : info({
            name:"Moderator",
            disabled: true
        }),
        created_date : info({
            type:"date",
            disabled: true,
            name:"Created Date",
            after:""
        }),
        modified_date : info({
           type:"date",
           disabled: true,
           name: "Last Modified"
        }),
        incident_type : info({
            name: "Incident Type",
            after: ""
        }),
        incident_date : info({
            type:"date",
            name:"Incident Date"
        }),
        notes : info({
            name: "Notes",
            type: "textarea"
        }),
        action_taken : info({
            type:"textarea",
            name:"Action Taken",
            after:"<br/>Key Location<br/>"
        }),
        world : info({
            name: "World",
            type: "select",
            options:[{
                        value:"",
                        label:""
                    },
                    {
                        value:"world",
                        label:"Alpha"
                    },
                    {
                        value:"world3",
                        label:"Delta"
                    },
                    {
                        value:"world4",
                        label:"Gamma"
                    },
                    {
                        value:"world_nether",
                        label:"Alpha Nether"
                    },
                    {
                        value:"world3_nether",
                        label:"Delta Nether"
                    },
                    {
                        value:"world4_nether",
                        label:"Gamma Nether"
                    },
                    {
                        value:"world_the_end",
                        label:"The End"
                    },
                    {
                        value:"custom",
                        label:"Custom"
                    },
                    {
                        value:"dev",
                        label:"Dev"
                    },
                    {
                        value:"outworld",
                        label:"Outworld"
                    }],
            after: ""
        }),
        coord_x : info({
            type: "int",
            name: "X",
            after:""
        }),
        coord_y : info({
            type: "int",
            name: "Y",
            after:""
        }),
        coord_z : info({
            type: "int",
            name: "Z"
        }),
        appeal_date : info({
            type    : "date",
            disabled: true,
            name:  "Appeal Date",
            showEmpty:false
        }),
        appeal : info({
            name: "Appeal",
            type: "textarea",
            disabled: true,
            showEmpty:false
        }),
        appeal_response : info({
            type: "textarea",
            name: "Appeal Response",
            showEmpty: function(idNum) {
                return $("#appeal_" + idNum).length > 0;
            }
        })
    };
    
    
    
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
        setTimeout(function() {$("#highlight").slideUp();}, 4000);
    }
    
    
    /**
     *  Retrieves the user information, and then displays it into the manage
     *  users tab.
     */
    function getInformation() {
        $.get(
            bm.url + "ban-manager.php",
            { 'lookup': $("#lookup-user_id").val() },
            function(data) {
                if(data.error == null) {
                    
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
                    $.each(user, function(index, value) {
                        el = value.toHTML(data.user[index], index, "info");
                        userInfo.append( el );
                    });
                    
                    // Update the permanent banned state
                    $("#banned_info").change({id:"#user-info-permanent-box"}, togglePermanentBox);
                    $("#banned_info").change();
                    
                    // Attach the save button
                    userInfo.append($("<button>").text("Save").click(function() {
                        // Save the user information //
                        
                        // Verify that there is a user
                        var user_id = $("#lookup-user_id").val();
                        if(user_id == null || user_id == "") {
                            displayMessage("Please select a user.");
                            return;
                        }

                        // Serialize the user information
                        var datum = serialize(user, "info");

                        datum.id = user_id;

                        // Send in the changes
                        $.post( bm.url + "ban-manager.php?update=user",
                            datum,
                            function(data) {
                                if(data.error == null) {
                                    displayMessage("User updated.");
                                } else {
                                    handleError(data.error);
                                }
                            },
                            'json' );
                    }));
                    
                    
                    if(data.incident != null) {
                        
                        // Attach all the incidents
                        for(i=0; i<data.incident.length; i++) {
                            datum = data.incident[i];

                            el = $("<div>").addClass("form").attr("id", "i-" + datum.incident_id);
                            el.appendTo(incidents);

                            $.each(incident, function(index, value) {
                                el.append(value.toHTML(datum[index], index, datum.incident_id));
                            });

                            // Add the save button
                            el.append($("<button>").text("Save").attr("id","i-s-" + datum.incident_id).click(function() {

                                // Get the ID and disable the button (to prevent repeatedly clicking the button)
                                var id = $(this).addClass("disabled").prop("disabled", true).attr('id').substring(4);
                                
                                // Serialize the incident fields
                                var datum = serialize(incident, id);

                                datum.id = id;
                                
                                // Post in the updated incident
                                $.post(bm.url + "ban-manager.php?update=incident",
                                    datum,
                                    function(data) {
                                        // Re-enable the button
                                        $("#i-s-" + id).removeClass("disabled").prop("disabled", false);
                                        if(data.error == null) {
                                            // Success
                                            displayMessage("Incident updated.");
                                        } else {
                                            // Error occured
                                            handleError(data.error);
                                        }
                                    },
                                    'json');
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
                    
                } else {
                    // Error
                    handleError(data.error);
                }
            },
            'json'
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
        setTimeout(function() {$("#error").slideUp();}, 6000);
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
            source : function (request, response) {
                $.get(bm.url + "ban-manager.php",{
                        term: request.term
                    }, function (data) {
                        if (data.length == 0) {
                            data.push({
                                'value': 0,
                                'label': emptyLabel
                            });
                        }
                        response(data);
                    }, 'json');
            },
            minLength : 2,
            select : function( event, ui  ) {
                if(ui.item.value == 0) {
                    if(emptyCallback != null) {
                        emptyCallback();
                    }
                } else {
                    $(this).val(ui.item.label);
                    $(value).val(ui.item.value);

                    if(callback != null) {
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
    
    
    /**
     * Performs a lookup on a user based on which table row was clicked.
     */
    function rowLookup() {
        $("#lookup-user_id").val($(this).attr("id").substring(3));
        getInformation();
    }
    
    
    function search() {
        if( $("#search").val().length < 2) {

            displayMessage("Search must be two or more characters long.");

        } else {
            $.get(bm.url + "ban-manager.php",
                { search : $("#search").val() },
                function(data) {
                    // Check for error with the search
                    try {
                        $.parseJSON(data);
                        if(data.error != null) {
                            handleError(data.error);
                            return;
                        }
                    // Catch and ignore any errors thrown by parseJSON (since HTML is returned it will error out).
                    } catch(ignore) { }

                    // Set the tab contents
                    $("#search-tab").html(data);

                    // Attach the row lookup event
                    $("#search-tab tr").click(rowLookup)

                    // Re-enable the search tab, and switch to it.
                    $("#tabs").tabs("enable", 3).tabs("option", "active", 3);
                },
                'html' );
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
            if (value.serialize != null && typeof(value.serialize) == 'function') {
                var result = value.serialize(field);
                if (result === false)
                    return;
                datum[index] = result;
            } else if (value.type == "checkbox") {
                // Handle checkboxes
               datum[index] = field.is(":checked") ? "true" : "false";
            } else {
                // Generic type, get it's value
                datum[index] = field.val();
            }
        });
        
        return datum;
    }
    
    
    function togglePermanentBox(event) {
        $(event.data.id).css("display", $(this).prop("checked") ? "inline" : "none");
    }
    
    
    
    /* ----------------------------- *
     *           INITALIZE           *
     * ----------------------------- */

    // Runs on document ready.
    $( function($) {
        
        // Save variables
        var incidentForm = $("#add-incident-form"),
            addUserForm = $("#add-user-form"),
            temp;
            
        // Build the add incident dialog form
        $.each(incident, function(index, value) {
            // Don't attach read only fields
            if( ! value.disabled ) {
                incidentForm.append(value.toHTML("", index, "add"));
            }
        });
        
        // Build the add user dialog form
        $.each(user, function(index, value) {
            // Don't attach read only fields
            if( ! value.disabled ) {
                temp = value.toHTML("", index, "add_u");
                
                // Handle the special case for the permanent checkbox
                if(index === "permanent") {
                    temp = $("<span id='user-add-permanent-box' />").append(temp).after("<br>");
                }
                
                addUserForm.append(temp);
            }
        });
        
        // Set up the tabs
        $("#tabs").tabs({
            beforeLoad: function(event, ui) {
                // Set up error handling
                ui.jqXHR.error(function() {
                   ui.panel.html("Couldn't load this tab."); 
                });
            },
            load: function(event, ui) {
                $(ui.panel).find("tr").click(rowLookup);
            },
            disabled: [3]// Search tab is disabled by default
        });

        // Set up the dialogs
        $("#dialog-add-user").dialog({
            autoOpen: false,
            modal: true,
            height: 450,
            width: 540,
            buttons: {
                Save : function() {
                    // Verify that we have a username
                    if( $("#user_add_username").val() == "" ) {
                        displayMessage("Please provide a username.");
                        return;
                    }
                    
                    // Save the user
                    $.post(
                        bm.url + "ban-manager.php?add_user=true",
                        $("#add-user-form").serialize(),
                        function(data) {
                            if(data.error == null) {
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
                                
                            } else {
                                // Error occured
                                handleError(data.error);
                            }
                        }, 'json'
                    );

                    $(this).dialog("close");
                },

                Cancel : function() {
                    $(this).dialog("close");
                }
            }
        });
        $("#dialog-add-incident").dialog({
            autoOpen: false,
            modal: true,
            height: 480,
            width: 645,
            buttons: {
                Save : function() {
                    // Verify that we have data
                    if( $("#user_id").val() == "" ) {
                        displayMessage("Please enter a user.");
                        return;
                    } else if( $("#notes_add").val() == "" ) {
                        if( ! confirm("You didn't enter any notes!\n\nPress OK to save this incident anyways.") ) {
                            return;
                        }
                    }
                    
                    // Save the incident.
                    $.post(
                        bm.url + "ban-manager.php?add_incident=true",
                        $("#add-incident-form").serialize(),
                        function(data) {
                            if(data.error == null) {
                                // Success
                                displayMessage("Incident added.");
                                
                                // Reset the add incident fields
                                $("#add-incident-form")[0].reset();
                            } else {
                                // Error occured
                                handleError(data.error);
                            }
                        }, 'json'
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
        $("#banned_add").change({id:"#user-add-permanent-box"}, togglePermanentBox);
        
        // Search box
        $("#search-button").click(search);
        $("#search").keyup(function(event) {
            // Run when the enter key is pressed
            if(event.keyCode == 13) {
                search();
            }
        });
        
    });

})(jQuery);
