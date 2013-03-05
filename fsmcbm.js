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
            format:"none",
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
         * @return A jQuery object containing the HTML nodes for the data structure.
         */
        toHTML : function(value, name) {
            /* Types:
             * - text
             * - label
             * - date
             * - textarea
             * - select
             * - int
             * - checkbox
             */
            
            // See if this field should be shown when empty
            var showEmpty;
            
            if(typeof(this.showEmpty) === 'function') {
                showEmpty = this.showEmpty();
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
            
            if(this.type == "label"){
                // Non editable label
                return "<em>" + this.name + "</em><code>" + value + "</code>" + this.after;
                
            }
            
            var html = $("<label>").text(this.name);
            
            if(this.type == "textarea"){
                // Text area
                html = html.add($("<textarea>").attr("name", name).text(value));
                
            } else if(this.type == "select"){
                // Drop Down
                var sel = $("<select>").attr("name", name);
                var i;
                for(i in this.options){
                    i = this.options[i];
                    if(i.label == null){
                        sel.append($("<option>").text(i));
                    } else {
                        sel.append($("<option>").attr("value", i.value).text(i.label));
                    }
                }
                sel.val(value);
                
                html = html.add(sel);
                
            } else if(this.type == "text" || this.type == "int"){
                // Text and int types
                var input = $("<input>").val(value).attr({
                    'type' : "text",
                    'name' : name
                });
                
                if(this.type == "int"){
                    input.addClass("int");
                }
                html = html.add(input);
                
            } else if(this.type == "date"){
                // Date field
                html = html.add($("<input>").attr({
                    'type' : "text",
                    'name' : name
                }).val(value).datepicker({
                   showOn: "both",
                   // TODO get my own calendar icon
                   buttonImage: "http://jqueryui.com/resources/demos/datepicker/images/calendar.gif",
                   buttonImageOnly : true,
                   dateFormat : "yy-mm-dd"
                }));
                
            } else if(this.type == "checkbox"){
                // Checkbox
                html = html.add($("<input>").attr({
                    'type' : 'checkbox',
                    'name' : name
                }));
                
            } else {
                // Unknown
                console.log("Unkown Type: " + this.type);
                return "";
            }
            
            return html.add($(this.after));
        },
        
        formatValue : function(value) {
            // Null should be empty strings
            if(value == null)
                return "";
            
            if(this.format == "date"){
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
            type:"label"
        }),
        created_date : info({
            type:"label",
            format:"date",
            name:"Created Date",
            after:""
        }),
        incident_date : info({
            type:"date",
            format:"date",
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
            type:  "label",
            format:"date",
            name:  "Appeal Date",
            showEmpty:false
        }),
        appeal : info({
            name: "Appeal",
            type: "label",
            showEmpty:false
        }),
        appeal_response : info({
            type: "textarea",
            name: "Appeal Response",
            showEmpty:false // TODO: This should only be false when there is no appeal.
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
                                div.append(value.toHTML(datum[index], index));
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
                $(ui.panel).find("tr").click(function(){
                    $("#lookup-user_id").val($(this).attr("id").substring(3));
                    getInformation();
                });
            }
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

        // Attach events
        $("#add-user").click(openAddUser);
        $("#add-incident").click(function(){
            $("#dialog-add-incident").dialog("open");

        });
    });

})(jQuery);