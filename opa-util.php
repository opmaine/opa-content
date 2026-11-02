<?php
/*
 * Module Name:       OPA util
 * Description:       opacontent plugin utility functions
 * Author:            Jim Grace
 */

// Wrapper for writing things to the WP debug log if enabled
if ( ! function_exists( 'opa_log' ) ) {
    function opa_log( $data ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( is_array( $data ) || is_object( $data ) ) {
                error_log( print_r( $data, true ) );
            } else {
                error_log( $data );
            }
        }
    }
}

// Creates new opa database tables if needed
function create_opa_schema() {
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

    query("insert into opa_item values('memorials', '[]')");
}

function set_db_update_code($code) {
    query("insert into opa_item values('code', '" . hash('sha256', $code) . "')");
}

// For temporary use in debugging / redeploying
function delete_opadata() {
    query("drop table if exists opa_event");
    query("drop table if exists opa_event_temp");
    query("drop table if exists opa_item");
}

function create_opadata_if_needed() {

}