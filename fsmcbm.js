/* Final Score MC Ban Manager
 * 
 */

(function($){
    
    // General Variables
    var info,
        DataStructure,
        incident,
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
     */
    info = function(options) {
        return new DataStructure(options);
    };
    
    // DataStructure Constructor
    DataStructure = function(options) {
        var datum, i;
        
        datum = $.extend({
            // Default Options
            type:"text",
            disabled: false,
            name:null,
            after:"<br/>",
            options:[],
            showEmpty:true
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
            var showEmpty, label, field, id = name + "_" + idNum;
            
            // See if this field should be shown when empty
            if(typeof(this.showEmpty) === 'function') {
                showEmpty = this.showEmpty(idNum);
            } else {
                showEmpty = this.showEmpty;
            }
            
            if( ! showEmpty ){
                if(value == null || value == ""){
                    // Don't show empty fields
                    return "";
                }
            }
            
            value = this.formatValue(value);
            
            label = $("<label>").text(this.name).attr("for", id);
            
            if(this.type == "textarea"){
                // Text area
                field = $("<textarea>").text(value);
                
            } else if(this.type == "select"){
                // Drop Down
                field = $("<select>")
                
                var i;
                for(i in this.options){
                    i = this.options[i];
                    if(i.label == null){
                        field.append($("<option>").text(i));
                    } else {
                        field.append($("<option>").attr("value", i.value).text(i.label));
                    }
                }
                field.val(value);
                
            } else if(this.type == "text" || this.type == "int" || this.type == "date"){
                // Text, date, and int types
                field = $("<input>").attr('type', 'text').val(value);
                
                if(this.type == "int"){
                    label.addClass("int");
                    field.addClass("int");
                }
                
            } else if(this.type == "checkbox"){
                // Checkbox
                field = $("<input>").attr({
                    'type' : 'checkbox'
                });
                
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
                field.prop('disabled', true);
                
                // Date check part of this if else so that disabled fields don't have the datpicker attached.
            } else if(this.type == "date") {
                field.datepicker({
                   showOn: "both",
                   buttonImage: "calendar-month.png",
                   buttonImageOnly : true,
                   dateFormat : "yy-mm-dd",
                   maxDate : 0
                });
            }
            
            return label.add(field).add(this.after);
        },
        
        formatValue : function(value) {
            // Null should be empty strings
            if(value == null)
                return "";
            
            if(this.type == "date"){
                // Do date formating (change from yy-mm-dd hh:mm:ss to yy-mm-dd)
                value = value.substring(0, value.indexOf(" "));
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
        incident_date : info({
            type:"date",
            name:"Incident Date"
        }),
        incident_type : info({
            name: "Incident Type"
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
            showEmpty: function(idNum){
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
        $("#user-add-username").val($("#user_name").val());
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
        setTimeout(function(){$("#highlight").slideUp();}, 4000);
    }
    
    
    /**
     *  Retrieves the user information, and then displays it into the manage
     *  users tab.
     */
    function getInformation() {
        $.get(
            "fsmcbm.php",
            { 'lookup': $("#lookup-user_id").val() },
            function(data){
                if(data.error == null){
                    
                    // Empty out any previous incidents
                    var incidents = $("#incident-info").empty();
                    
                    // Fill in the fields
                    $.each(data.user, function(index, value){
                        var field = $("#user-info-" + index);
                        if(field.attr("type") == "checkbox"){
                            field.prop("checked", value == "1");
                        } else {
                            field.val(value);
                        }
                    });
                    
                    if(data.incident != null){
                        // Attach all the incidents
                        for(var i=0; i<data.incident.length; i++){
                            var datum = data.incident[i];

                            var div = $("<div>").addClass("form").attr("id", "i-" + datum.id);
                            div.appendTo(incidents);

                            $.each(incident, function(index, value){
                                div.append(value.toHTML(datum[index], index, datum.id));
                            });

                            // Add the save button
                            div.append($("<button>").text("Save").attr("id","i-s-" + datum.id).click(function(){

                                // Get the ID and disable the button
                                var id = $(this).addClass("disabled").prop("disabled", true).attr('id').substring(4);
                                
                                // Save the fields
                                var datum = {
                                    "id" : id
                                };
                                
                                $.each(incident, function(index, value){
                                    datum[index] = $("#" + index + "_" + id).val();
                                });
                                
                                $.post("fsmcbm.php?update=incident",
                                    datum,
                                    function(data){
                                        // Re-enable the button
                                        $("#i-s-" + id).removeClass("disabled").prop("disabled", false);
                                        if(data.error == null){
                                            // Success
                                            displayMessage("Incident updated.");
                                        } else {
                                            // Error occured
                                            handleError(data.error);
                                        }
                                    },
                                    'json');
                            }));
                            
                            // Add the cancel button
                            div.append($("<button>").text("Cancel").attr("id", "i-c-" + datum.id).click(function(){
                                // TODO cancel incident save button code here
                            }));
                        }
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
        setTimeout(function(){$("#error").slideUp();}, 6000);
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
                $.get("fsmcbm.php",{
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
            select : function( event, ui ){
                if(ui.item.value == 0){
                    if(emptyCallback != null){
                        emptyCallback();
                    }
                } else {
                    $(this).val(ui.item.label);
                    $(value).val(ui.item.value);

                    if(callback != null){
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
    function rowLookup(){
        $("#lookup-user_id").val($(this).attr("id").substring(3));
        getInformation();
    }
    
    
    function search() {
        if( $("#search").val().length < 2) {

            displayMessage("Search must be two or more characters long.");

        } else {
            $.get("fsmcbm.php",
                { search : $("#search").val() },
                function(data) {
                    // Check for error with the search
                    try {
                        $.parseJSON(data);
                        if(data.error != null){
                            handleError(data.error);
                            return;
                        }
                    // Catch and ignore any errors thrown by parseJSON (since it HTML is returned it will error out).
                    } catch(ignore){}

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
    
    
    
    /* ----------------------------- *
     *           INITALIZE           *
     * ----------------------------- */

    // Runs on document ready.
    $( function($) {
        
        // Build the add incident dialog form
        var incidentForm = $("#add-incident-form");
        $.each(incident, function(index, value){
            // Don't attach the created date and moderator
            if(index != "moderator" && index != "created_date"){
                incidentForm.append(value.toHTML("", index, "add"));
            }
        });
        
        // Set up the tabs
        $("#tabs").tabs({
            beforeLoad: function(event, ui){
                // Set up error handling
                ui.jqXHR.error(function(){
                   ui.panel.html("Couldn't load this tab."); 
                });
            },
            load: function(event, ui){
                $(ui.panel).find("tr").click(rowLookup);
            },
            disabled: [3]// Search tab is disabled by default
        });

        // Set up the dialogs
        $("#dialog-add-user").dialog({
            autoOpen: false,
            modal: true,
            height: 400,
            width: 510,
            buttons: {
                Save : function(){
                    // Verify that we have a username
                    if( $("#user-add-username").val() == "" ) {
                        displayMessage("Please provide a username.");
                        return;
                    }
                    
                    // Save the user
                    $.post(
                        "fsmcbm.php?add_user=true",
                        $("#add-user-form").serialize(),
                        function(data){
                            if(data.error == null){
                                // Success
                                displayMessage("User added succesfully.");
                                
                                // See if the user needs to be attached to an incident
                                if(attachNewUser){
                                    $("#user_name").val($("#user-add-username").val());
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

                Cancel : function(){
                    $(this).dialog("close");
                }
            }
        });
        $("#dialog-add-incident").dialog({
            autoOpen: false,
            modal: true,
            buttons: {
                Save : function(){
                    // Verify that we have data
                    if( $("#user_id").val() == "" ) {
                        displayMessage("Please enter a user.");
                        return;
                    } else if( $("#notes_add").val() == "" ) {
                        if( ! confirm("You didn't enter any notes!\n\nPress OK to save this incident anyways.") ){
                            return;
                        }
                    }
                    
                    // Save the incident.
                    $.post(
                        "fsmcbm.php?add_incident=true",
                        $("#add-incident-form").serialize(),
                        function(data){
                            if(data.error == null){
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
                Cancel : function(){
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
        $("#add-incident").click(function(){
            $("#dialog-add-incident").dialog("open");
        });
        
        // Save user information button
        $("#user-info-save").click(function(){
            
            // Verify that there is a user
            var user_id = $("#lookup-user_id").val();
            if(user_id == null || user_id == ""){
                displayMessage("Please select a user.");
                return;
            }
            
            // Serialize the user information
            
            var data = {};
            
            data.id = user_id;
            data.rank = $("#user-info-rank").val();
            data.banned = $("#user-info-banned").is(":checked");
            data.permanent = $("#user-info-permanent").is(":checked");
            data.relations = $("#user-info-relations").val();
            data.notes = $("#user-info-notes").val();
            
            // Send in the changes
            $.post("fsmcbm.php?update=user", data, function(data){
                if(data.error == null){
                    displayMessage("User updated.");
                } else {
                    handleError(data.error);
                }
            }, 'json');
            
        });
        
        // User information cancel button
        $("#user-info-cancel").click(function(){
            // TODO
        });
        
        // Search box
        $("#search-button").click(search);
        $("#search").keyup(function(event){
            // Run when the enter key is pressed
            if(event.keyCode == 13) {
                search();
            }
        });
        
    });

})(jQuery);