<?php
/*
 * Plugin Name:       OPA Content
 * Description:       Provides database content to the Ocean Park Association website
 * Version:           1.0.7
 * Author:            Jim Grace
 */

 require_once dirname( __FILE__ ) . '/opa-events.php';
 require_once dirname( __FILE__ ) . '/opa-post.php';

// Register and load the OPA_Events widget
function load_opa_events_widgit() {
    register_widget( 'OPA_Events' );
}

add_action( 'widgets_init', 'load_opa_events_widgit' );

function register_opa_post() {
    register_rest_route( 'opacontent', '/opapost', array(
        'methods' => 'POST',
        'callback' => 'opa_post' ) );
}

add_action( 'rest_api_init', 'register_opa_post');

function register_opa_events() {
    register_rest_route( 'opaevents', '/homepage', array(
        'methods' => 'GET',
        'callback' => 'ajax_opa_events' ) );
}

add_action( 'rest_api_init', 'register_opa_events');
