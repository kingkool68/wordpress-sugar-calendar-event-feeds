<?php
class SC_Event_ICal_Feed {
    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ), 10 );

        // Testing!
        // add_action( 'dynamic_sidebar', array( $this, 'dynamic_sidebar' ) );
    }

    public function dynamic_sidebar() {

        $link = sc_get_ical_link();
        $link_text = '+ iCal';
        if( !$link ) {
            $link = sc_get_ical_feed_link();
            $link_text = '+ iCal Feed';
        }

        if( $link ) {
            echo '<p><a href="' . esc_url( $link ) . '">' . $link_text . '</a></p>';
        }
    }

    public function init() {
        add_feed( 'ical', array( $this, 'ical_feed' ) );
    }

    public function ical_feed() {
        global $wp_query;
        if ( !have_posts() ) {
            $wp_query->set_404();
            $wp_query->max_num_pages = 0;
            header('Content-Type: text/html; charset=' . get_option('blog_charset'), true);
            locate_template( '404.php', true );
            die();
        }

        $display_format = '';
        if( isset( $_GET['format'] ) ) {
            $display_format = strtolower( $_GET['format'] );
        }
        if( $display_format == 'xml' ) {
            nocache_headers();
            header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);
        }

        if( $display_format == 'json' ) {
            nocache_headers();
            header('Content-Type: application/json; charset=' . get_option('blog_charset'), true);
        }

        //TODO: Add filters for these things
        $calendar_name = get_bloginfo( 'name' ) . ' Events';
        $calendar_description = 'Events found on ' . get_site_url();
        $timezone = get_option( 'timezone_string' ); // define time zone
        $args = array(
            'unique_id' => get_site_url(),
            'TZID' => $timezone,
        );
        $v = new vcalendar( $args ); // create a new calendar instance
        $v->setProperty( 'method', 'PUBLISH' ); // required of some calendar software
        $v->setProperty( 'x-wr-calname', $calendar_name ); // required of some calendar software
        $v->setProperty( 'X-WR-CALDESC', $calendar_description ); // required of some calendar software
        $v->setProperty( 'X-WR-TIMEZONE', $timezone ); // required of some calendar software
        $xprops = array( 'X-LIC-LOCATION' => $timezone ); // required of some calendar software
        iCalUtilityFunctions::createTimezone( $v, $timezone, $xprops ); // create timezone component(-s) opt. 1 based on present date

        while ( have_posts() ) : the_post();
            $event_id = get_the_ID();
            $event = get_post( $event_id );
            $event_date = sc_get_event_date( $event_id );
            $event_time = sc_get_event_time( $event_id );

            $event_start = strtotime( $event_date . ' ' . $event_time['start'] );
            $event_end = strtotime( $event_date . ' ' . $event_time['end'] );

            $event_location = '';
            if( function_exists( 'sc_get_event_address' ) ) {
                $event_location = sc_get_event_address();
            }

            $event_description = apply_filters( 'sc_ical_event_description', get_the_content(), $event );
            $event_description = sc_html2plain( $event_description );

            $vevent = &$v->newComponent( 'vevent' ); // create an event calendar component
            $start = array(
                'year'  => date( 'Y', $event_start ),
                'month' => date( 'n', $event_start ),
                'day'   => date( 'j', $event_start ),
                'hour'  => date( 'G', $event_start ),
                'min'   => date( 'i', $event_start ),
                'sec'   => date( 's', $event_start ),
            );
            $vevent->setProperty( 'dtstart', $start );
            $end = array(
                'year'  => date( 'Y', $event_end ),
                'month' => date( 'n', $event_end ),
                'day'   => date( 'j', $event_end ),
                'hour'  => date( 'G', $event_end ),
                'min'   => date( 'i', $event_end ),
                'sec'   => date( 's', $event_end ),
            );
            $vevent->setProperty( 'dtend',   $end );
            $vevent->setProperty( 'LOCATION', $event_location ); // property name - case independent
            if( function_exists( 'sc_get_the_organizer' ) ) {
                $organizer_details = sc_get_the_organizer( $event_id );
                $organizer_details = array_filter( $organizer_details );
                if( isset( $organizer_details['name'] ) && isset( $organizer_details['email'] ) ) {
                    $organizer_args = array(
                        'CN' => $organizer_details['name'],
                    );
                    $x_params = array( 'Phone', 'Website' );
                    foreach( $x_params as $param ) {
                        $lowercase_param = strtolower( $param );
                        if( isset( $organizer_details[ $lowercase_param ] ) ) {
                            $arg_key = 'X-' . $param;
                            $val = $organizer_details[ $lowercase_param ];
                            $organizer_args[ $arg_key ] = $val;
                        }
                    }

                    $vevent->setProperty( 'organizer' , $organizer_details['email'], $organizer_args );
                }
            }
            $vevent->setProperty( 'summary', $event->post_title );
            $vevent->setProperty( 'description', $event_description );
        endwhile;

        // all calendar components are described in rfc5545
        // a complete method list in iCalcreator manual

        iCalUtilityFunctions::createTimezone( $v, $timezone, $xprops);
        // create timezone component(-s) opt. 2
        // based on all start dates in events (i.e. dtstart)

        if( $display_format == 'xml' ) {
            echo iCal2XML( $v );
            exit();
        }
        if( $display_format == 'json' ) {
            $xml = simplexml_load_string( iCal2XML( $v ) );
            echo json_encode( $xml );
            exit();
        }
        $v->returnCalendar(); // redirect calendar file to browser
        exit();
    }

    public function pre_get_posts( $query ) {
        if( !is_feed( 'ical' ) || !$query->is_main_query() || is_admin() ) {
            return;
        }

        $query->set( 'post_type', 'sc_event' );
        if( $query->get_queried_object() && !is_post_type_archive( 'sc_event' ) && !is_singular( 'sc_event' ) ) {
            $query->set( 'orderby', 'meta_value_num' );
    		$query->set( 'meta_key', 'sc_event_date_time' );
    		$query->set( 'order', 'DESC' );

    		if( isset( $_GET['event-display'] ) ) {
    			$mode = urldecode( $_GET['event-display'] );
    			$query->set( 'meta_value', current_time('timestamp') );
    			switch($mode) {
    				case 'past':
    					$query->set('meta_compare', '<');
    					break;
    				case 'upcoming':
    					$query->set('meta_compare', '>=');
    					break;
    			}
    		}

    		if( isset( $_GET['event-order'] ) ) {
    			$order = urldecode( $_GET['event-order'] );
    			$query->set( 'order', $order );
    		}
        }

        $posts_per_rss = apply_filters( 'sc_number_of_ical_feed_items', 250 );
        $query->set( 'posts_per_rss', $posts_per_rss );
    }

}
global $sc_event_ical_feed;
$sc_event_ical_feed = new SC_Event_ICal_Feed();

function sc_get_ical_link( $event_id = 0 ) {
    $event_id = intval( $event_id );
    if( $event_id ) {
        if( $url = get_permalink( $event_id ) ) {
            return $url . 'ical/';
        }
    }

    if( is_singular( 'sc_event' ) ) {
        $event = get_post();
        $event_id = $event->ID;

         $ical_feed_link = '';
        if( $url = get_permalink( $event_id ) ) {
            $ical_feed_link = $url . 'ical/';
        }

        return $ical_feed_link;
    }
}

function sc_get_ical_feed_link() {
    global $wp;
    $allowed_ical_feed_tax = apply_filters( 'sc_allowed_ical_feed_tax', get_object_taxonomies( 'sc_event' ) );
    if( is_tax( $allowed_ical_feed_tax ) ) {
        $url = home_url( add_query_arg( array(),$wp->request ) ); // via http://stephenharris.info/how-to-get-the-current-url-in-wordpress/
        // Strip any pagination...
        $url = preg_replace( '/\/page\/(\d+)/i', '', $url );
        return trailingslashit( $url ) . 'ical/';
    }

    // Generic, all events.
    return get_post_type_archive_link( 'sc_event' ) . 'ical/';
}
