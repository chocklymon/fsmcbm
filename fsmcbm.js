/* Final Score MC Ban Manager
 * 
 */

(function($){
    
    /* ----------------------------- *
     *   DATA STRUCTURE OBJECT       *
     * ----------------------------- */
    
    // Convenience constructor
    var info = function(options) {
        return new DataStructure(options);
    };
    
    // Constructor
    var DataStructure = function(options) {
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
        
        for ( i in datum ) {
            this[i] = datum[i];
        }
        
        return this;
    };
    
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
                if(value == null){
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
                   // TODO get my own calendar icon
                   buttonImage: "http://jqueryui.com/resources/demos/datepicker/images/calendar.gif",
                   buttonImageOnly : true,
                   dateFormat : "yy-mm-dd"
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

    // Incident Data Structure
    var incident = {
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

                            $.each(incident, function(index, value){
                                div.append(value.toHTML(datum[index], index, datum.id));
                            });

                            // Add the save and cancel buttons
                            div.append($("<button>").text("Save").attr("id","i-s-" + datum.id).button().click(function(){
                                // TODO save incident button code here
                            }));
                            div.append($("<button>").text("Cancel").attr("id","i-c-" + datum.id).button().click(function(){
                                // TODO cancel incident save button code here
                            }));
                                
                            div.appendTo(incidents);
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
     * Opens the add user jQuery UI dialog.
     */
    function openAddUser() {
        $("#dialog-add-user").dialog("open");
    }
    
    function rowLookup(){
        $("#lookup-user_id").val($(this).attr("id").substring(3));
        getInformation();
    }
    
    /**
     * Opens the add user dialog, and after the new user is saved, adds them to the incident.
     */
    function addUserToIncident() {
        openAddUser();
        // TODO have the user added to the DB, and updated into the add incident dialog
    }
    
    
    /* ----------------------------- *
     *           INITALIZE           *
     * ----------------------------- */

    // Runs on document ready.
    $( function($) {
        
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

        // Make buttons jQuery UI buttons
        $("button").button();

        // Set up the dialogs
        $("#dialog-add-user").dialog({
            autoOpen: false,
            modal: true,
            height: 400,
            width: 510,
            buttons: {
                Save : function(){
                    // TODO add verification
                    // Save the user
                    $.post(
                        "fsmcbm.php?add_user=true",
                        $("#add-user-form").serialize(),
                        function(data){
                            if(data.error == null){
                                // Success
                                displayMessage("User added succesfully.");
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
                    // TODO add verification
                    // Save the incident.
                    $.post(
                        "fsmcbm.php?add_incident=true",
                        $("#add-incident-form").serialize(),
                        function(data){
                            if(data.error == null){
                                // Success
                                displayMessage("Incident added.");
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
        lookup("#user_name", "#user_id", null, "Not Found - Add New", addUserToIncident);

        // Attach events \\
        
        // Add user button
        $("#add-user").click(openAddUser);
        
        // Add incident button
        $("#add-incident").click(function(){
            $("#dialog-add-incident").dialog("open");
        });
        
        // Search box
        $("#search").change(function(){
            $.get("fsmcbm.php",
                { search : $(this).val() },
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
                'html'
            );
        });
        
    });

})(jQuery);