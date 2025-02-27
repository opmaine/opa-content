<?php
/*
 * Module Name:       OPA Post
 * Description:       Accepts a post of updated OPA data for the website
 * Author:            Jim Grace
 */

// ------------------------------------------------------------------------------
//    H e l p e r    f u n c t i o n s
// ------------------------------------------------------------------------------

// Prints a message and aborts
function abort($message) {
    echo $message;
    exit();
}

// Assembles a date and time into a mysql datetime (24h format)
function make_datetime($date, $time) {
    return $date . " " . date("H:i:s", strtotime($time));
}

// Makes a SQL query (not returning results), aborting on error
function query($sql) {
    global $wpdb;
    if ($wpdb->query($sql) == false) {
        abort("Error executing '" . $sql . "':<br>\n" . $wpdb->last_error);
    }
}

// Creates new opa database tables if needed
function create_opadata($code) {
    echo "Creating opa database tables...\n";
    query("
        create table opa_event
        (
            eventid int          not null
                primary key,
            year    int          not null,
            start   datetime     not null,
            end     datetime     not null,
            program varchar(255) null,
            event   json         not null
        )");

    query("
        create table opa_event_temp
        (
            eventid int          not null
                auto_increment primary key,
            year    int          not null,
            start   datetime     not null,
            end     datetime     not null,
            program varchar(255) null,
            event   json         not null
        )");

    query("
        create index event_year_index
            on opa_event (year)
        ");

    query("
        create index event_start_index
            on opa_event (start)
        ");

    query("
        create index event_end_index
            on opa_event (end)
        ");

    query("
        create index event_program_index
            on opa_event (program)
        ");

    query("
        create table opa_item
        (
            name    varchar(255) not null
                primary key,
            content longtext     null
        )");

    query("insert into opa_item values('code', '" . hash('sha256', $code) . "')");
    query("insert into opa_item values('memorials', '[]')");
}

// For temporary use in debugging / redeploying
function delete_opadata() {
    query("drop table if exists opa_event");
    query("drop table if exists opa_event_temp");
    query("drop table if exists opa_item");
}

// ------------------------------------------------------------------------------
//    H a n d l e    P O S T
// ------------------------------------------------------------------------------

function opa_post($request) {
    global $wpdb;

    // echo "args = " . json_encode($request['args']) . "\n";
    // echo "cal = " . json_encode($request['cal']) . "\n";
    
    // Uncomment the following if you want to initialize the database and generate the encrypted code:
    // Keep this commented! // delete_opadata();

    // --------------------------------------------------------------------------
    //    V a l i d a t e    c o m m o n    i n p u t
    // --------------------------------------------------------------------------

    if (!isset($request)) {
        abort("POST content not present.");
    }
    $args = $request['args'];
    if (!isset($args)) {
        abort("args not present in POST.");
    }
    $code = $args['code'];
    if (!isset($code)) {
        abort("code not present in args.");
    }
    $action = $args['action'];
    if (!isset($action)) {
        abort("action not present in args.");
    }

    // --------------------------------------------------------------------------
    //    G e t    c o d e    f r o m    d a t a b a s e
    // --------------------------------------------------------------------------

    $GET_CODE = "select content from opa_item where name = 'code'";

    try {
        $results = $wpdb->get_results($GET_CODE);
        if (!$results) {
            create_opadata($code);
            $results = $wpdb->get_results($GET_CODE);
        }
    }
    catch (mysqli_sql_exception $ex) {
        create_opadata($code);
        $results = $wpdb->get_results($GET_CODE);
    }

    $hashed_code = $results[0]->content;

//    echo "DB CODE = " . $dbcode . "\n";

    // --------------------------------------------------------------------------
    //    C h e c k    c o d e
    // --------------------------------------------------------------------------

    if (hash('sha256', $code) != $hashed_code) {
        abort("wrong code in args.");
    }

    // --------------------------------------------------------------------------
    //    S t o r e    c a l e n d a r    e v e n t s
    // --------------------------------------------------------------------------

    if ($action == 'calendar') {
        $year = (int)$args['year'];
        if(!isset($year)) {
            abort("year not present in calendar args.");
        }
        if ($year < 1881 || $year > 9999) {
            abort("year " . $year . "out of range.");
        }
        $cal = $request['cal'];
        if (!isset($cal)) {
            abort("cal not present for calendar.");
        }

        $wpdb->query($wpdb->prepare("delete from opa_event where year = %d", $year)); // Ignore error if 0 rows deleted

        // Insert the new rows into the database with non-colliding eventid values.
        $rows = $wpdb->get_results('select max(eventid) as max_eventid from opa_event');
        $eventid = reset($rows)->max_eventid;

        foreach($cal as $c) {
            $start = make_datetime($c['date'], $c['start']);
            $end = make_datetime($c['date'], $c['end']);
            $program = $c['program'];
            $event = json_encode($c);
            if (!$start || !$end || !$program || !$event) {
                abort("Illegal event: " . json_encode($c));
            }
            $sql = $wpdb->prepare("insert into opa_event (eventid, year, start, end, program, event)" .
                " values (%d,%d,%s,%s,%s,%s)", ++$eventid, $year, $start, $end, $program, $event);
            $wpdb->query($sql);
        }

        // Adjust all eventid values so they are in event date/time order
        // This was tried using row_number() but it didn't work under Wordpress
        // This was also tried with via set eventid = (@id := @id + 1) and also didn't work under Wordpress
        // (Both of these worked fine as standalone queries.)
        query("truncate table opa_event_temp");
        query("alter table opa_event_temp auto_increment = 1");
        query("insert into opa_event_temp (year, start, end, program, event) "
            . "select year, start, end, program, event from opa_event order by start, end");
        query("truncate table opa_event");
        query("insert into opa_event select * from opa_event_temp");
        query("truncate table opa_event_temp");
    }

    // --------------------------------------------------------------------------
    //    S t o r e    m e m o r i a l s
    // --------------------------------------------------------------------------

    elseif($action == 'memorial') {
        $memorial = $request['memorial'];
        if (!isset($memorial)) {
            abort("memorial data not present for memorial.");
        }
        $sql = $wpdb->prepare("update opa_item set content = %s where name = 'memorials'", json_encode($memorial));
        query($sql);
    }

    // --------------------------------------------------------------------------
    //    U n r e c o g n i z e d    a c t i o n
    // --------------------------------------------------------------------------

    else {
        abort("unrecognized action " . action . "in args.");
    }

    // --------------------------------------------------------------------------
    //    E x i t
    // --------------------------------------------------------------------------

    die;
//    return new WP_REST_Response(null, 200);
}
