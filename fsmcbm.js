
var info;

(function($){
    
    info = function(options){
        return new DataStructure(options);
    };
    
    var DataStructure = function(options){
        var datum, d;
        
        datum = $.extend({
            // Default Options
            type:"text",
            format:"none",
            name:null,
            after:"<br/>",
            options:[],
            showEmpty:true
        }, options);
        
        for ( d in datum ) {
            this[d] = datum[d];
        }
        
        return this;
    };
    
    info.fn = DataStructure.prototype = {

        toHTML : function(value, name){
            /* Types:
             * - text
             * - label
             * - date
             * - textarea
             * - select
             * - int
             * - checkbox
             */
            if( !this.showEmpty ){
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
                    sel.append($("<option>").text(i));
                }
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
        
        formatValue : function(value){
            if(this.format == "date"){
                // Do date formating
                // TODO
                return value;
            }
            
            // Null should be empty strings
            if(value == null)
                return "";
            
            // No formating needed, return the value
            return value;
        }
    };
}(jQuery));


var bm = {
    
    incident : {
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
            options:[],
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
            showEmpty:false
        })
    },
    
    lookup : function(input, value, callback){
        jQuery(input).autocomplete({
            source : 'fsmcbm.php',
            minLength : 2,
            select : function( event, ui ){
                jQuery(this).val(ui.item.label);
                jQuery(value).val(ui.item.value);

                if(callback != null){
                    callback();
                }

                return false;
            }
        });
    },

    getInformation : function(){
        jQuery.get(
            "fsmcbm.php",
            { 'lookup': jQuery("#user_id").val() },
            function(data){
                if(data.error == null){
                    
                    // Fill in the fields
                    jQuery.each(data.user, function(index, value){
                        var field = jQuery("#user-info-" + index);
                        if(field.attr("type") == "checkbox"){
                            field.prop("checked", value == "1");
                        } else {
                            field.val(value);
                        }
                    });
                    
                    for(var i=0; i<data.incident.length; i++){
                        var incident = data.incident[i];
                        //console.log(incident);
                        
                        var div = jQuery("<div>").addClass("form").attr("id", "i-" + incident.id);
                        
                        jQuery.each(bm.incident, function(index, value){
                            div.append(value.toHTML(incident[index], index));
                        });
                        
                        div.appendTo("#incident-info");
                    }
                    
                    // Switch to the manage tab
                    $("#tabs").tabs("option", "active", 0);
                    
                } else {
                    // Error
                    bm.handleError(data.error);
                }
            },
            'json'
        );
    },
    
    handleError : function(error){
        // TODO
    }
}

jQuery(function($){
    bm.lookup("#lookup", "#user_id", bm.getInformation);
    
    // Set up the tabs
    $("#tabs").tabs({
        beforeLoad: function(event, ui){
            // Set up error handling
            ui.jqXHR.error(function(){
               ui.panel.html("Couldn't load this tab."); 
            });
        }
    });
    
    // Make buttons buttons
    $("button").button();
    
    // Set up the dialogs
    $("#dialog-add-user").dialog({
        autoOpen: false,
        modal: true,
        height: 400,
        width: 510,
        buttons: {
            Save : function(){
                // Save the user
                $.post(
                    "fsmcbm.php?add_user=true",
                    $("#add-user-form").serialize(),
                    function(data){
                        
                        if(data.error == null){
                            // Success
                            // Close the dialog
                            $(this).dialog("close");
                            
                        } else {
                            // Error occured
                            bm.handleError(data.error);
                        }
                    }, 'json'
                );
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
                
                // Save the incident.
                $(this).dialog("close");
            },
            Cancel : function(){
                $(this).dialog("close");
            }
        }
    });
    
    // Attach events
    $("#add-user").click(function(){
        $("#dialog-add-user").dialog("open");
    });
    $("#add-incident").click(function(){
        $("#dialog-add-incident").dialog("open");
    });
});
