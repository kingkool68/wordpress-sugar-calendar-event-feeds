<?php
class SC_Event_Google_Calendar {
    public function __construct() {}

    public function get_google_calendar_link( $event_id = false ) {
        // via http://stackoverflow.com/a/21653600/1119655
        if( !$event_id ) {
            $event = get_post();
        } else {
            $event = get_post( $event_id );
        }
        if( !is_object( $event ) || $event->post_type != 'sc_event' ) {
            return;
        }

        $event_date = sc_get_event_date( $event->ID );
        $event_time = sc_get_event_time( $event->ID );
        $event_description = apply_filters( 'sc_ical_event_description', $event->post_content, $event );
        $event_description = apply_filters( 'sc_google_calendar_event_description', $event_description, $event );
        $event_description = sc_html2plain( $event_description );
        $args = array(
            'event_name' => get_the_title( $event->ID ),
            'start_date' => $event_date . ' ' . $event_time['start'],
            'end_date' => $event_date . ' ' . $event_time['end'],
            'details' => $event_description,
            'location' => '',
        );

        if( function_exists( 'sc_get_event_address' ) ) {
            if( $address = sc_get_event_address( $event->ID, ', ' ) ) {
                $args['location'] = $address;
            }
        }

        $args = apply_filters( 'sc_google_calendar_link_data', $args, $event );

        // Convert the time to the weird Google Calendar time format
        $start_date = date('Y-m-d H:i:s', strtotime( $args['start_date'] ) );
        $end_date = date('Y-m-d H:i:s', strtotime( $args['end_date'] ) );
        $date = $this->get_google_calendar_time_format( $start_date, $end_date );
        unset( $args['start_date'] );
        unset( $args['end_date'] );
        $args['dates'] = $date;

        $args['text'] = $args['event_name'];
        unset( $args['event_name'] );

        $args = array_map( 'trim', $args );
        $args = array_map( 'urlencode', $args );

        // Google Calendar links require these paramters
        $args['action'] = 'TEMPLATE';
        $args['sf'] = 'true';
        $args['output'] = 'xml';

        $link = add_query_arg( $args, 'https://www.google.com/calendar/render' );

        return $link;
    }

    public function get_google_calendar_time_format( $start_date = '', $end_date = '' ) {
        if( !$start_date ) {
            return '';
        }
        $start_date = get_gmt_from_date( $start_date );
        $start_date = strtotime( $start_date );
        if( !$end_date ) {
            $end_date = $start_date * 3600; // Add 1 hour to the start time and be done with
        } else {
            $end_date = get_gmt_from_date( $end_date );
            $end_date = strtotime( $end_date );
        }

        return date( 'Ymd\\THis\\Z', $start_date ) . '/' . date( 'Ymd\\THis\\Z', $end_date );

    }
}

global $sc_event_google_calendar;
$sc_event_google_calendar = new SC_Event_Google_Calendar();

function sc_get_google_calendar_link( $event_id = false ) {
    // Note: using `esc_url()` will stirp out new line characters and could affect the formating of the Google Calendar event description.
    global $sc_event_google_calendar;
    return $sc_event_google_calendar->get_google_calendar_link( $event_id );
}
