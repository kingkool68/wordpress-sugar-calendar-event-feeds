<?php
/*
Plugin Name: Sugar Calendar - Event Feeds
Description: Adds iCal feeds and Google Calendar integration functionality to Sugar Calendar.
Author: Russell Heimlich
Version: 0.1
Author URI: http://www.russellheimlich.com
*/

class SC_Event_Feeds {

    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_filter( 'sc_ical_event_description', array( $this, 'add_url_to_ical_event_description' ), 10, 2 );
    }

    public function add_google_calendar_link() {
        $event = get_post();
        if( !is_object( $event ) || $event->post_type != 'sc_event' ) {
            return;
        }

        $link = sc_get_google_calendar_link( $event->ID );
        // Don't use esc_url() because it strips out encoded new line characters for some reason. This will effect the formatting
        echo '<p><a href="' . $link . '">+ Google Calendar</a></p>';
    }

    public function add_ical_link() {
        echo '<p><a href="' . esc_url( sc_get_ical_link() ) . '">+ iCal</a></p>';
    }

    public function init() {
        $this->load_libraries();

        if( class_exists('SC_Event_Venues') ) {
            $this->supports_locations = true;
        }

    }
    public function load_libraries() {
        include 'lib/iCalcreator/iCalcreator.php';
        include 'sc-event-google-calendar.php';
        include 'sc-event-ical-feed.php';
    }

    public function add_url_to_ical_event_description( $description, $event ) {
        if( $url = get_permalink( $event->ID ) ) {
            $description .= "\n\n" . 'More Details: ' . $url;
        }
        return $description;
    }

    public function html2plain( $str ) {
        $str = wp_kses( $str, array() );
        // Now that we've removed some HTML elements we need to de-dupe new lines characters to remove ugly large gaps in the text
        $str = preg_replace( '#\R{3,}#', PHP_EOL . PHP_EOL, $str );

        return $str;
    }

}
global $sc_event_feeds;
$sc_event_feeds = new SC_Event_Feeds();

function sc_html2plain( $str = '' ) {
    global $sc_event_feeds;
    return $sc_event_feeds->html2plain( $str );
}
