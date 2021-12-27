<?php

require_once("app.php");

pstart ();

$arg_token = trim(@$_REQUEST['token']);

$q = query ("select user_id from link_requests where token = ?",
    $arg_token);

if (($r = fetch ($q)) == NULL) {
    $body .= "<p>invalid link request</p>\n";
    pfinish ();
}

$link_user_id = intval($r->user_id);

if ($link_user_id == $user_id) {
    $body .= "<p>already linked</p>\n";
    query ("delete from link_requests where token = ?", $arg_token);
    pfinish ();
}

/* potentially transfer user data from $user_id to $link_user_id */

query ("update pub_keys set user_id = ? where key_id = ?",
    array ($link_user_id, $key_id));

query ("delete from link_requests where token = ?", $arg_token);

putsess ("user_id", $link_user_id);

$body .= "<p>linked ... new user id will take effect during next refresh</p>\n";


pfinish ();
