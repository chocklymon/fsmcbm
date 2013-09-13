/**
 * Loads the Javascript and CSS files needed by the ban manager into the page.
 * @requires jQuery 1.8+
 */

var bm = {
    /**
     * The URL to the folder where the ban manager files are located.
     */
    url : ''
}

(function($) {

    var 
        // CSS files needed for the ban manger
        css = [
            'css/custom-theme/jquery-ui-1.10.2.custom.min.css',
            'css/main.min.css'
        ],
        
        // Javascript files needed by the ban manger.
        script = [
            'jquery-ui-1.10.2.custom.min.js',
            'ban-manager.js'
        ];
    
    
    // Attach the CSS
    for(var i=0; i<css.length; i++) {
        $('head').append( $('<link>').attr('type', 'text/css').attr('rel', 'stylesheet').attr('href', bm.url + css[i]) );
    }
    
    // Attach the JavaScript
    for(var i=0; i<script.length; i++) {
        $('head').append( $('<script>').attr('type', 'text/javascript').attr('src', bm.url + script[i]) );
    }
    
})(jQuery);
