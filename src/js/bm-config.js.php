<?php
require_once '../Settings.php';
require_once '../Output.php';
require_once '../Database.php';

$settings = new Settings();
$output = new Output($settings);
$db = new Database();

header('Content-Type: text/javascript');
// Since this won't change very often, set the cache to store this for two days
header('Cache-Control: public, max-age=172800');
flush();
?>
(function($) {
    /* ----------------------------- *
     *   Data Structure Variables    *
     * ----------------------------- */
    var
        // Data structure, see documentation below
        info,
        DataStructure,

        // Data structure for the user
        user,

        // Data structure for incidents
        incident,

        // Data structure for appeals
        appeal;

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

        datum = $.extend(
            {
                // Default Options
                type      : "text",
                disabled  : false,
                name      : null,
                after     : "<br/>",
                options   : [],
                showEmpty : true,
                special   : null,
                serialize : null
            },
            options
        );

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
            if (typeof this.showEmpty == 'function') {
                showEmpty = this.showEmpty(idNum);
            } else {
                showEmpty = this.showEmpty;
            }

            if (!showEmpty && (value == null || value == '')) {
                // Don't show empty fields
                return '';
            }

            value = this.formatValue(value);

            label = $("<label>").text(this.name + ":").attr("for", id);

            if(this.type == 'textarea') {
                // Text area
                field = $("<textarea>").text(value);

            } else if(this.type == 'select') {
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
                return '';
            }

            // Add common attributes to the field
            field.attr({
                'name' : name,
                'id'   : id
            });

            // Attach the date picker to dates
            if( this.disabled ) {
                // Disabled field
                field.prop('readonly', true);

                // Date check part of this if else so that disabled fields don't have the datpicker attached.
            } else if(this.type == "date") {
                field.datepicker({
                   showOn: "both",
                   buttonImage: bm.url + "calendar-month.png",// TODO get the button image to actually work
                   buttonImageOnly : true,
                   dateFormat : "yy-mm-dd",
                   maxDate : 0
                });
            }

            field = label.add(field).add(this.after);

            if (typeof this.special == 'function') {
                field = this.special(field);
            }

            return field;
        },

        formatValue : function(value) {
            // Null should be empty strings
            if(value == null)
                return '';

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
                return field.wrapAll("<span class='user-info-permanent-box' />").parent().add("<br/>");
            }
        }),
        rank : info({
            name : "Rank",
            type : "select",
            options : <?php
// Get the ranks from the database
$sql = <<<SQL
SELECT *
FROM `rank`
SQL;

$db->connect($settings);
$rows = $db->queryRows($sql);

foreach($rows as $rank) {
    $output->append(
        array('value'=>$rank['rank_id'], 'label'=>$rank['name'])
    );
}
$db->close();

$output->reply();
?>
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
                        value:"omega",
                        label:"Omega"
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
                        value:"omega_nether",
                        label:"Omega Nether"
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
        })
    };

    // TODO refine the appeal object
    appeal = {
        author : info({
            disabled: true,
            name:  "Author"
        }),
        date : info({
            name: "Date",
            type: "date",
            disabled: true
        }),
        message : info({
            type: "textarea",
            name: "Message",
            disabled: true
        })
    };

    // Expose the ban manager datastructure objects
    if (window.bm == null) {
        window.bm = {};
    }
    bm.user = user;
    bm.incident = incident;
    bm.appeal = appeal;
})(jQuery);
