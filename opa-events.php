<?php
/**
 * Widget name:       OPA_Events
 * Description:       Displays a list of Ocean Park Association events
 * Author:            Jim Grace
 */

// ------------------------------------------------------------------------------
//    C o n s t a n t s
// ------------------------------------------------------------------------------

require_once ABSPATH . 'wp-admin/includes/file.php';

$PAGE_SIZE = 10;
$HOME_PAGE = 'Home page';
$PLUGIN_IMG_URL = plugin_dir_url(__FILE__) . "img/";
$UPLOADS_URL = site_url() . "/wp-content/uploads/";
$UPLOADS_DIR = get_home_path() . "wp-content/uploads/";

$DETAILS = '<img class="opa_img_details" width="13" height="13" src="'. $PLUGIN_IMG_URL . 'caret-down.svg" /><span class = opa_span_details>&nbsp;Details</span>';
$SUMMARY = '<img class="opa_img_details" width="13" height="13" src="'. $PLUGIN_IMG_URL . 'caret-up.svg" /><span class = opa_span_details>&nbsp;Summary</span>';
$DETAILS_INCLUDES = 'caret-down.svg';

$PROGRAM_IMAGE_HEIGHT = 200;

// ------------------------------------------------------------------------------
//    G e n e r a t e    h o m e    p a g e    n a v i g a t i o n
// ------------------------------------------------------------------------------

function get_max_eventid() {
    global $wpdb;

    $rows = $wpdb->get_results('select max(eventid) as max_eventid from opa_event');

    return reset($rows)->max_eventid;
}

// Finds the years for events in the database
function get_years() {
    global $wpdb;

    $sql = 'select distinct year from opa_event order by year';
    $rows = $wpdb->get_results($sql);

    $years = array();
    foreach ($rows as $row) {
        array_push($years, $row->year);
    }

    return $years;
}

// Returns an array of months within the year from the first to last month having events
// and including any months within this range whether they have events or not.
// Months having events have value true, and months not having events have value false.
function get_months($year) {
    global $wpdb;

    $sql = $wpdb->prepare('select distinct left(start,7) as month from opa_event where year = %d order by left(start,7)', $year);
//    echo 'get_months(' . $year . ') sql = ' . $sql . "<br>\n";
    $rows = $wpdb->get_results($sql);
    $months = array();
    $month_so_far = null;
    foreach ($rows as $row) {
        $month = (int)substr($row->month, 5);
        // Report any in-between months with no events
        if ($month_so_far) {
            for ($m = $month_so_far + 1; $m < $month; $m++) {
                $months[$m] = false;
            }
        }
        // Report a month with events
        $months[$month] = true;
        $month_so_far = $month;
    }
//    echo 'get_months(' . $year . ') => ' . json_encode($months) . "<br>\n";
    return $months;
}

// Returns an array of days within the month from the first to the last of the month.
// Days having events have value true, and days not having events have value false.
function get_days($year, $month) {
    global $wpdb;

    $year_month = sprintf("%d-%02d", $year, $month);
    $sql = $wpdb->prepare("select distinct left(start,10) as day from opa_event where left(start,7) = '%s' order by left(start,10)", $year_month);
//    echo 'get_days(' . $year . ',' . $month . ') sql = ' . $sql . "<br>\n";
    $rows = $wpdb->get_results($sql);
    $days = array();
    $day_so_far = 0;
    foreach ($rows as $row) {
        $day = (int)substr($row->day, 8);
        // Report any in-between days with no events
        for ($d = $day_so_far + 1; $d < $day; $d++) {
            $days[$d] = false;
        }
        // Report a day with events
        $days[$d] = true;
        $day_so_far = $d;
    }
    // Report any days at end of month with no events
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    for ($d = $day_so_far + 1; $d <= $days_in_month; $d++) {
        $days[$d] = false;
    }
    // echo 'get_days(' . $year . ',' . $month . ') => ' . $days_in_month . ' days, ' . json_encode($days) . "<br>\n";
    return $days;
}

// Given a target date, finds the months, selected month, days, and selected day
// such that the selected month/day is the first on or after the target date with events,
// or the last one before the target date with events if there are none after.
function select_months_and_days($year, $target_date) {
//    echo 'select_months_and_days(' . $year . ',' . $target_date . ")<br>\n";
    $months = get_months($year);
    $days = null;
    $selected_month = null;
    $selected_day = null;
    foreach ($months as $month => $month_has_events) {
        if ($month_has_events) {
            $selected_month = $month;
            $days = get_days($year, $month);
            foreach ($days as $day => $day_has_events) {
                if ($day_has_events) {
//                    echo 'year ' . $year . ' month ' . $month . ' day ' . $day . ' date ' . sprintf("%d-%02d-%02d", $year, $month, $day) . "<br>\n";
                    $selected_day = $day;
                    if (sprintf("%d-%02d-%02d", $year, $month, $day) >= $target_date) {
                        break 2;
                    }
                }
            }
        }
    }
    return array('months' => $months, 'selected_month' => $selected_month,
        'days' => $days, 'selected_day' => $selected_day);
}

// Get a three-letter month name from a 2-digit month
function month_name($month) {
    $date = DateTime::createFromFormat('!m', $month);
    return $date->format('M'); // 3-letter month
}

function define_calendar_styles() {
    ?>
    <style type="text/css">
    .yearTD {
        background-color: #FFFFFF;
        font-weight: normal;
        text-align: center;
        padding: 0px 0px;
        vertical-align: bottom;
        height: 40px;
    }
    .yearSelected {
        background-color: #c2e7ff;
        border-radius: 50px;
        text-align: center;
        width: 60px;
        height: 26px;
    }
    .monthTD {
        background-color: #FFFFFF;
        font-weight: normal;
        text-align: center;
        padding: 0px 0px;
        vertical-align: middle;
        height: 40px;
    }
    .monthSelected {
        background-color: #c2e7ff;
        border-radius: 50px;
        text-align: center;
        width: 45px;
        height: 26px;
    }
    .dayTD {
        background-color: #FFFFFF;
        font-weight: normal;
        text-align: center;
        padding: 0px 0px;
    }
    .dayInactive {
        color: darkgrey;
        padding: 2px 10px;
    }
    .dayClickable {
        margin: auto;
        width: 26px;
        height: 26px;
        padding: 2px 10px;
    }
    .daySelected {
        background-color: #c2e7ff;
        border-radius: 50%;
        margin: auto;
        width: 26px;
        height: 26px;
    }
    .dateSelected {
        background-color: #c2e7ff;
        border-radius: 50px;
        text-align: center;
        height: 25px;
        margin: auto;
        display: table;
    }
    </style>
    <?php
}

// Display the year navigation line
function year_nav($selected_year, $target_date) {
    $years = get_years();

    echo '<span "align="center" style="margin:auto; display:table;">&nbsp;';
    // echo '<p class="op_p_year_navigtion" style="text-align:center;line-height:100%">&nbsp;';
    foreach ($years as $year) {
        if ($year == $selected_year) {
            echo '<span class="yearSelected"> &nbsp; ' . $year . ' &nbsp; </span>&nbsp;';
        }
        else {
            $date = "'" . $year . substr($target_date, 4, 6) . "'"; // Request the same month/day in the other year.
            // $date = "'" . $year . "-01-01'"; // Alternate implementation: Request January 1 of the other year.
            echo '<a class="op_a_link_year" href="javascript:void(0)" alt="' . $year . '" onclick="update_opa_events(' . $date . ');return false;"><u>' . $year . '</u></a>&nbsp;';
        }
    }
    echo '</span>';
}

// Display the month navigation line
function month_nav($year, $months, $selected_month) {
    echo '<span "align="center" style="margin:auto; display:table;"> &nbsp;';
    foreach ($months as $month => $month_has_events) {
        $month_name = month_name($month);
        if ($month_has_events) {
            if ($month == $selected_month) {
                echo '<span class="monthSelected"> &nbsp; ' . $month_name . ' &nbsp; </span>&nbsp;';
            }
            else {
                $date = "'" . year_month_day($year, $month, 1) . "'"; // Request the first of the month.
                echo '<a class="op_a_active_month" href="javascript:void(0)" alt="' . $month_name . '" onclick="update_opa_events(' . $date . ');return false;"><u>' . $month_name . '</u></a>&nbsp;';
            }
        }
        else {
            echo ' <span class="op_span_inactive_month">' . $month_name . '</span> ';
//            echo '<div style="color: #9E9E9E"> ' . $month_name . ' </div>';
        }
    }
    echo '</span>';
}

// Display a table cell for one navigation day in the month
function one_day_nav($year, $month, $day, $day_has_events, $selected_day) {
    echo '<td class="dayTD">';
    if ($day_has_events) {
        if ($day == $selected_day) {
            echo '<div class="daySelected">' . $day . '</div>';
        }
        else {
            $date = "'" . year_month_day($year, $month, $day) . "'";
            echo "\n" . '<span class="dayClickable"><a href="javascript:void(0)" alt="' . $day . '" onclick="return update_opa_events(' .  $date . ');return false;"><u>' . $day . '</u></a></span> ';
        }
    }
    else {
        echo '<span class="dayInactive">' . $day . '</span> ';
    }
    echo '</td>';
}

// Display the navigation of days within the month
function day_nav($year, $month, $days, $selected_day) {
    ?>
    <table style="margin-left: auto; margin-right: auto;  width:1%; background-color:#FFFFFF;" class="op_span_calendar_navivation" align="center">
        <tr>
            <th class="dayTD">S</th>
            <th class="dayTD">M</th>
            <th class="dayTD">T</th>
            <th class="dayTD">W</th>
            <th class="dayTD">T</th>
            <th class="dayTD">F</th>
            <th class="dayTD">S</th>
        </tr>
        <tr>
    <?php

    $previous_month = $month - 1;
    $previous_month_year = $year;
    if ($previous_month < 1) {
        $previous_month = 12;
        $previous_month_year = $previous_month_year - 1;
    }

    $next_month = $month + 1;
    $next_month_year = $year;
    if ($next_month > 12) {
        $next_month = 1;
        $next_month_year = $next_month_year + 1;
    }

    $previous_month_days = get_days($previous_month_year, $previous_month);
    $next_month_days = get_days($next_month_year, $next_month);
    // echo 'Previous month ' . $previous_month_year . '-' . $previous_month . ': '; print_r($previous_month_days); echo "<br>\n";
    // echo 'Next month ' . $next_month_year . '-' . $next_month . ': '; print_r($next_month_days); echo "<br>\n";
    $weekday = 0; // So we can track the last weekday after the loop
    foreach ($days as $day => $day_has_events) {
        // Weekday modulo 7 returns Sun=0, Mon=1, ..., Fri=5, Sat=6
        $weekday = DateTime::createFromFormat('Y-m-d', $year . '-' . $month . '-' . $day)->format('N') % 7;

        if ($day==1) { // First day of the month -- display last days of previous month, if any:
            $last_month_count = count($previous_month_days);
            for ($d = $last_month_count - $weekday; $d < $last_month_count; $d++) {
                one_day_nav($previous_month_year, $previous_month, $d, $previous_month_days[$d], false);
            }
        }
        elseif ($weekday==0) {
            echo "</tr>\n<tr>";
        }

        one_day_nav($year, $month, $day, $day_has_events, $selected_day);

        // echo ' day ' . $day . ' weekday ' . $weekday . "<br>\n";
    }

    // Display first days of next month, if any:
    for ($d = 1; $d < 7 - $weekday; $d++) {
        one_day_nav($next_month_year, $next_month, $d, $next_month_days[$d], false);
    }

    echo '</tr></table>';
}

// Display the entire year/month/date navigation system
function date_nav($year, $target_date) {
    // echo 'date_nav ' . $year . ' ' . $target_date;
    $months_and_days = select_months_and_days($year, $target_date);
    $months = $months_and_days['months'];
    $selected_month = $months_and_days['selected_month'];
    $days = $months_and_days['days'];
    $selected_day = $months_and_days['selected_day'];

    echo '<span class="op_span_calendar_navivation">';
    echo '<table>';

    echo '<tr><td class="yearTD">';
    year_nav($year, $target_date);
    echo '</td></tr>';

    echo '<tr><td class="monthTD">';
    month_nav($year, $months, $selected_month);
    echo '</td></tr>';

    echo '</table>';

    if ($days) {
        day_nav($year, $selected_month, $days, $selected_day);
    }

    echo '</span>';

    // return opa_date_from_string($year . '-' . $selected_month . '-' . $selected_day);
    return $year . '-' . $selected_month . '-' . $selected_day;
}

function pillar_legend() {
    global $PLUGIN_IMG_URL;
    echo "<br>\n" . '<span align="center" style="margin:auto; display:table;" class="opa_span_pillar_legend">';
    // Note that the spacing before and after the recreational icon is not like the others.
    // This is because the recreational icon is skinnier than the others. The difference in
    // actual spacing makes it look to the eye like consistent spacing before and after the icon.
    echo '<span style="white-space:nowrap"><img class="opa_img_pillar_key" width="20" height="20" src="'. $PLUGIN_IMG_URL . 'spiritual_icon.svg" alt="Spiritual icon">&nbsp;&nbsp;Spiritual</span> &nbsp; &nbsp; &nbsp; ' . "\n";
    echo '<span style="white-space:nowrap"><img class="opa_img_pillar_key" width="20" height="20" src="'. $PLUGIN_IMG_URL . 'cultural_icon.svg" alt="Spiritual icon">&nbsp;&nbsp;Cultural</span> &nbsp; &nbsp; &nbsp; ' . "\n";
    echo '<span style="white-space:nowrap"><img class="opa_img_pillar_key" width="20" height="20" src="'. $PLUGIN_IMG_URL . 'educational_icon.svg" alt="Spiritual icon">&nbsp;&nbsp;Educational</span> &nbsp; &nbsp; ' . "\n";
    echo '<span style="white-space:nowrap"><img class="opa_img_pillar_key" width="20" height="20" src="'. $PLUGIN_IMG_URL . 'recreational_icon.svg" alt="Spiritual icon">&nbsp;Recreational</span>' . "\n";
    echo "</span><br>\n";
}

// ------------------------------------------------------------------------------
//    G e n e r a t e    h o m e    p a g e    e v e n t s    l i s t
// ------------------------------------------------------------------------------

// Formats WeekdayName, MonthName day, year from date / time
function opa_date($row) {
    $date = DateTime::createFromFormat('Y-m-d H:i:s',$row->start);
    return $date->format('l, F j, Y');
}

// Formats WeekdayName, MonthName day, year from date only
// Returns input if it wasn't a valid date string
function opa_date_from_string($date_string) {
    $date = DateTime::createFromFormat('Y-m-d',$date_string);
    return $date ? $date->format('l, F j, Y') : $date_string;
}

// Formats start(am/pm only if different from end) - end(am/pm)
function opa_time($row) {
    $sta = DateTime::createFromFormat('Y-m-d H:i:s',$row->start);
    $end = DateTime::createFromFormat('Y-m-d H:i:s',$row->end);
    $sf = ($sta->format('a') == $end->format('a')) ? 'g:i' : 'g:ia';
    return $sta->format($sf) . ' - ' . $end->format('g:ia');
}

// Returns the date part (first 7 characters) of a date/time string
function date_part($date) {
    return substr($date, 0, 10);
}

// Formats date<br>time
function opa_date_and_time($row) {
    return opa_date($row) . '<br>' . opa_time($row);
}

// Formats date string as yyyy-mm-dd
function year_month_day($year, $month, $day) {
    return sprintf("%04d-%02d-%02d", $year, $month, $day);
}
// Generates the image tag for a pillar (if any)
function pillar_image($event)
{
    global $PLUGIN_IMG_URL;

    $img = '1x1.png';
    $alt = '';

    if (property_exists($event, 'pillar')) {
        $alt = $event->pillar;
        $img = strtolower($alt) . '_icon.svg';
    }

    return '<img class="opa_img_pillar" width="30" height="30" src="' . $PLUGIN_IMG_URL . $img . '" alt="' . $alt . '">';
}

// Converts a name to valid file name characters. Changes anything that is not
// alphanumeric, underscore, dash, or space to a dash.
function escape_filename($name) {
    return preg_replace('/[^A-Za-z0-9_\- ]/', '-', $name);
}

function encode_spaces($name) {
    return str_replace(' ', '%20',$name);
}

// Generates the image tag for a program page event (if any)
function program_event_image($event, $year)
{
    global $UPLOADS_URL;
    global $UPLOADS_DIR;
    global $PROGRAM_IMAGE_HEIGHT;

    if (property_exists($event, 'image')) {
        $subdirectory = escape_filename($event->program);
        $url = $UPLOADS_URL . $year . "/" . encode_spaces($subdirectory) . "/" . encode_spaces($event->image);
        $file = $UPLOADS_DIR . $year . "/" . $subdirectory . "/" . $event->image;
        $alt = str_replace('.jpg','',$event->image);
        try {
            if (file_exists($file)) {
                $size = getimagesize($file);
                $width = $size[0];
                $height = $size[1];
                $scaled_height = $PROGRAM_IMAGE_HEIGHT;
                $scaled_width = round($width * ($scaled_height / $height));
                return '<img class="op_img_event"' . 'width="' . $scaled_width . '" height="' . $scaled_height
                    . '" src="' . $url . '" alt="' . $alt . '" />';
            } else {
                return "[Image missing: '" . $subdirectory . "/" . $event->image . "']";
            }
        }
        catch (Exception $e) {
            return "[Error accessing '" . $subdirectory . "/" . $event->image . "': " . $e->getMessage() . "]";
        }
    }
    return '';
}

// Gets at least all the events on the selected date. If there are not a page worth of events on that date,
// gets subsequent events so that a full page of events is returned.
function get_rows($selected_date) {
    global $wpdb;
    global $PAGE_SIZE;

    $sql = $wpdb->prepare("select * from opa_event where left(start,10) = %s", $selected_date);
    $rows = $wpdb->get_results($sql);
    // echo "<br>" . count($rows) . " rows from " . $sql;
    if (count($rows) < $PAGE_SIZE) {
        if ($rows) {
            $last_row = $rows[array_key_last($rows)];
            $sql = $wpdb->prepare("select * from opa_event where eventid > %d limit %d", $last_row->eventid, $PAGE_SIZE - count($rows));
        }
        else {
            $sql = $wpdb->prepare("select * from opa_event where start >= %s limit %d", $selected_date, $PAGE_SIZE);
        }
        $more_rows = $wpdb->get_results($sql);
        // echo "<br>" . count($more_rows) . " more rows from " . $sql;
        $rows = array_merge($rows, $more_rows);
    }
    return $rows;
}

// Formats some rows for the home page event table. This can be used to format the
// initial set of rows presented on the home page, or it may be used to format
// additional rows to add on the end if the user requests them.
function home_page_table_rows($rows, $selected_date, $previous_date) {
    global $DETAILS;

    $formatted_selected_date = opa_date_from_string($selected_date);
    $last_row = null;
    foreach ($rows as $row) {
        $last_row = $row;
        $tr_id = "'tr" . $row->eventid . "'";
        $det_id = "'details" . $row->eventid . "'";
        $details = '<a class="op_a_detail" href="javascript:void(0)" alt="Details" id=' . $det_id .
            ' onclick="toggle_details(' . $tr_id . ',' . $det_id . ');return false;">' . $DETAILS . '</a>';
        $event = json_decode($row->event);
        $address = property_exists($event, 'address') ? $event->address : '';

        $date = opa_date($row);
        if ($date != $previous_date) {
            $selected_class = ($date == $formatted_selected_date) ? ' dateSelected' : '';
            echo "\n" . '<tr class="op_tr_date"><td class="op_td_date" colspan="4" style="text-align:center;font-style:italic">';
            if ($date == $formatted_selected_date) {
                echo '<span class="dateSelected"> &nbsp; &nbsp; ' . $date . ' &nbsp; &nbsp; </span>';
            } else {
                echo $date;
            }
            echo "</td></tr>\n";
            $previous_date = $date;
        }

        $style = '';
        if (property_exists($event, 'cancelled')) {
            $style = ' style="color:red; text-decoration: line-through;"';
        }
        else if (property_exists($event, 'alert')) {
            $style = ' style="color:red;"';
        }

        // Main row
        echo '<tr class="op_tr_event"><td class="op_td_event"><table class="op_table_event2"><tr class="op_tr_event2"><td class="op_td_title">' .
            '<span' . $style . '>' . htmlspecialchars($event->title) . '</span><br>' .
            '<span class="op_span_detail">' . $details . '</span></td><td class="op_td_pillar">' . pillar_image($event) .
            '</td><td class="op_td_time"' . $style . '>' . opa_time($row) . '</td></tr></table></td><td class="op_td_address"' . $style . '>' . htmlspecialchars($event->location) . "</td></tr>\n";

        // Detail row (may be displayed or not)
        echo '<tr id=' . $tr_id . ' class="op_tr_detail" style="display:none"><td class="op_td_detail">' . htmlspecialchars($event->calenderDescription) . '</td><td class="op_td_address">' . htmlspecialchars($address) . "</td></tr>\n";
    }
    return $last_row;
}

// Generates the events table for the home page
function home_page_events($target_date) {
    global $PLUGIN_IMG_URL;

    echo '<p class="op_p_events">Events</p>';

    $year = substr($target_date, 0, 4);
    $selected_date = date_nav($year, $target_date);
    // echo "<br> Target: " . $target_date . ", selected: " . $selected_date;
    pillar_legend();

    $rows = get_rows($selected_date);
    $previous_date = '';

    echo "<table class='op_table_events' id='home_page_event_table'>\n";
    $last_row = home_page_table_rows($rows, $selected_date, $previous_date);
    echo '</table>';
    if (!$last_row) {
        return null;
    }

    ?>
    <p id="opa_p_more">
        <a id="more_opa_events" class="op_a_more" href="javascript:void(0)" alt="More events" onclick="add_more_events();return false;">
            <img class="opa_img_more" width="15" height="15" src="<?php echo $PLUGIN_IMG_URL?>add.svg" />
            <span class="opa_span_more">More</span>
        </a>
    </p>
    <script type="text/javascript">
        var last_eventid = "<?php echo $last_row->eventid?>";
        var previous_date = "<?php echo date_part($last_row->start)?>";
        disable_more_if_maxed_out();
    </script>
    <?php
    return $last_row;
}

// Refreshes events using a different start date
function home_page_events_by_date($target_date) {
    ob_start(); // Collect output into output buffer

    $last_row = home_page_events($target_date);
    $response = array();
    $response['rows'] = ob_get_clean();
    $response['last_eventid'] = $last_row->eventid;
    $response['previous_date'] = date_part($last_row->start);
    return $response;
}

// Adds more table rows to home page events table when requested by the user
function more_home_page_events($last_eventid, $previous_date) {
    global $wpdb;
    global $PAGE_SIZE;

    ob_start(); // Collect output into output buffer

    $sql = $wpdb->prepare("select * from opa_event where eventid > %d limit %d", $last_eventid, $PAGE_SIZE);
    $rows = $wpdb->get_results($sql);

    $last_row = home_page_table_rows($rows, '0001-01-01', opa_date_from_string($previous_date));

    $response = array();
    $response['rows'] = ob_get_clean();
    $response['last_eventid'] = $last_row->eventid;
    $response['previous_date'] = date_part($last_row->start);
    return $response;
}

// Responds to Ajax request from the user to display home page events.
// The request may be for a complete navigation + table for a new date,
// or it may be for additional events to add onto the end of the existing table.
function ajax_opa_events()
{
    if (isset($_GET['date'])) {
        $response = home_page_events_by_date($_GET['date']);
        echo json_encode($response);
    }
    else if (isset($_GET['last_eventid']) and isset($_GET['previous_date'])) {
        $response = more_home_page_events($_GET['last_eventid'], $_GET['previous_date']);
        echo json_encode($response);
    }
    die;
}

// ------------------------------------------------------------------------------
//    W i d g e t    O P A _ E v e n t s
// ------------------------------------------------------------------------------
class OPA_Events extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'opa_events', // Base ID
            __('OPA Events'), // Widget name in the UI
            array('description' => __('Ocean Park Association events')) // Widget description
        );
    }

    // Initially display home page events division
    private function home_page_widget($args, $instance) {
        global $DETAILS;
        global $SUMMARY;
        global $DETAILS_INCLUDES;

        ?>
        <script type="text/javascript">

            // Get new selection of home page events
            function update_opa_events(date) {
                // console.log("update_opa_events(" + date + ')');
                jQuery(document).ready(function($) {
                    var url = "<?php echo rest_url()?>opaevents/homepage?date=" + date;
                    jQuery.get(url, function(response) {
                        // console.log("update_opa_events()['last_eventid'] => " + response['last_eventid']);
                        // console.log("update_opa_events()['previous_date'] => " + response['previous_date']);
                        // console.log("update_opa_events(" + date + ") => " + response);
                        last_eventid = response['last_eventid'];
                        previous_date = response['previous_date'];
                        document.getElementById("home_page_events_table").innerHTML = response['rows'];
                        disable_more_if_maxed_out();
                    });
                });
            }

            // The max eventid in the database, so we know if we can ask for more
            var max_eventid = "<?php echo get_max_eventid()?>";

            // Disable the more events option if we are at the end of events for the year
            function disable_more_if_maxed_out() {
                // console.log("maxed out? last_event = " . last_eventid . ", max_eventid = " . max_eventid);
                if(last_eventid == max_eventid || ! last_eventid) {
                    aStyle = document.getElementById("opa_p_more").innerHTML = '(no more events)';
                }
            }

            // Add to the end of home page events
            function add_more_events() {
                jQuery(document).ready(function($) {
                    // console.log("add_more_events(), last_eventid = " + last_eventid + ", previous_date = " + previous_date);
                    var url = "<?php echo rest_url()?>opaevents/homepage?last_eventid=" + last_eventid + "&previous_date=" + previous_date;
                    jQuery.get(url, function(response) {
                        // console.log("add_more_events() => " + response);
                        // console.log("add_more_events()['last_eventid'] => " + response['last_eventid']);
                        // console.log("add_more_events()['previous_date'] => " + response['previous_date']);
                        // console.log("add_more_events()['rows'] => " + response['rows']);
                        last_eventid = response['last_eventid'];
                        previous_date = response['previous_date'];
                        const event_table = document.getElementById('home_page_event_table');
                        // console.log("event_table => " + event_table.innerHTML);
                        const new_dom = document.implementation.createHTMLDocument();
                        new_dom.body.innerHTML = '<table>' + response['rows'] + '</table>';
                        // new_rows.innerHTML = '<!doctype html><html><head></head><body><table>' + response['rows'] + '</table></body></html>';
                        // console.log("--------------------------------------------------------------");
                        // console.log("new_rows.innerHTML = " + new_rows.innerHTML);
                        // const trs = new_dom.getElementsByClassName("op_tr_row");
                        // const trs = new_dom.querySelectorAll("tr");
                        const trs = new_dom.querySelectorAll("tr.op_tr_date, tr.op_tr_event, tr.op_tr_detail");
                        // const trs = new_dom.body.querySelectorAll("tr.op_tr_row");
                        // const trs = new_dom.getElementsByTagName("tr");
                        // console.log("trs.length = " + trs.length);
                        var trlist = Array.prototype.slice.call(trs);
                        // console.log("trlist.length = " + trlist.length);
                        trlist.forEach(tr => {
                            // console.log("tr => " + tr.innerHTML);
                            event_table.appendChild(tr);
                        });
                        disable_more_if_maxed_out();
                    });
                });
            }

            function toggle_details(tr_id, details_id) {
                // alert(tr_id + ' ' + getElementById(tr_id).innerHTML);
                if (document.getElementById(details_id).innerHTML.includes('<?php echo $DETAILS_INCLUDES ?>')) {
                    document.getElementById(details_id).innerHTML = '<?php echo $SUMMARY ?>';
                    document.getElementById(tr_id).style.display = 'contents';
                }
                else {
                    document.getElementById(details_id).innerHTML = '<?php echo $DETAILS ?>';
                    document.getElementById(tr_id).style.display = 'none';
                }
            }

        </script>
        <?php

        define_calendar_styles();
        echo '<div class="op_div_events" id="home_page_events_table">';
        home_page_events(date('Y-m-d'));
        echo '</div>';
    }

    // Tests to see if any event in a row set has an image
    private function has_images($rows) {
        foreach ($rows as $row) {
            $event = json_decode($row->event);
            if (property_exists($event, 'image')) {
                return true;
            }
        }
        return false;
    }

    // Tests whether an event should be excluded from program page
    private function exclude_from_program_page($event) {
        return property_exists($event, 'excludeFromProgramPage')
            && $event->excludeFromProgramPage;
    }

    private function program_page_widget($args, $instance) {
        global $wpdb;

        $program = $instance['title'];
        $sql = $wpdb->prepare("select * from opa_event where program = %s", $program);
        $rows = $wpdb->get_results($sql);
        $has_images = $this->has_images($rows);

        echo "\n<table class='op_table_program_events'><tbody>\n";
        foreach ($rows as $row) {

            $event = json_decode($row->event);
            $description = '';
            if (property_exists($event, 'programDescription')) {
                $description = $event->programDescription;
            }
            elseif (property_exists($event, 'calenderDescription')) {
                $description = $event->calenderDescription;
            }
            $image_cell = $has_images ? ('<td class="op_td_image">' . program_event_image($event, $row->year) . '</td>') : '';
            if (!$this->exclude_from_program_page($event)) {
                echo '<tr class="op_tr_program_events">' . $image_cell . '<td class="op_td_program_title">' . $event->title .
                    '</td><td class="op_td_program_date"">' . opa_date_and_time($row) . '</td><td class="op_td_program_desc">' . $description . "</td></tr>\n";
            }
        }
        echo "</table>\n";
    }

    // Widget front-end display to user
    public function widget($args, $instance) {
        global $HOME_PAGE;

        $title = $instance['title'];
        echo $args['before_widget'];

        if ($instance['title'] == $HOME_PAGE)
        {
            $this->home_page_widget($args, $instance);
        }
        else
        {
            $this->program_page_widget($args, $instance);
        }
        echo $args['after_widget'];
    }

    // Widget Backend admin configuration
    public function form($instance) {
        global $wpdb;
        global $HOME_PAGE;

        $program = $instance['title'] ?: $HOME_PAGE;
        $rows = $wpdb->get_results('select distinct program from opa_event order by program');
        $programs = array();
        $programs[] = $HOME_PAGE;
        foreach ($rows as $row) {
            $programs[] = $row->program;
        }

        // Widget admin configuration form
        ?>
        <p>
            <script type="text/javascript"> // Get title from dropdown, so it will add to the widget config title.
                function updateOpaEventsTitle(control) {
                    document.getElementById("opa-events-hidden-title").innerHTML = control.value;
                }
            </script>
            <input type="hidden" id="opa-events-hidden-title" name="widget-title"
            class="widget-title" value="<?php echo $program?>">
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php echo esc_html__('Home page or Program', 'opa-content') . ':'; ?>
            </label>
            <select class="program" onchange="updateOpaEventsTitle(this)"
            id="<?php echo esc_attr($this->get_field_id('title')); ?>"
            name="<?php echo esc_attr($this->get_field_name('title')); ?>" >
            <?php
            foreach ($programs as $p) {
                echo '<option value="' . esc_attr($p) . '"' . ($p == $program ? " selected" : "") . '>'
                . $p . "</option>\n";
            } ?>
            </select>
        </p>
        <?php
    }

    // Updating widget after form is filled, replacing old instances with new
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }

// Class OPA_Events ends here
}

?>
