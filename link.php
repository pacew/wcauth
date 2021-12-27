<?php

require_once("app.php");

pstart ();

$arg_token = trim(@$_REQUEST['token']);

$q = query ("select user_id, key_id, email from link_requests where token = ?",
    $arg_token);

if (($r = fetch ($q)) == NULL) {
    $body .= "<p>invalid link request</p>\n";
    pfinish ();
}

$link_request_user_id = intval($r->user_id);
$link_request_key_id = intval ($r->key_id);
$link_request_email = trim($r->email);

if ($link_request_email) {
    $q = query ("select user_id"
        ." from users"
        ." where email = ?",
        $link_request_email);

    if (($r = fetch ($q)) == NULL) {
        /*
         * this is the first time we've seen this email address,
         * so it can be attached to the account of $link_request_user_id
         */
        $target_user_id = $link_request_user_id;

        query ("update users set email = ? where user_id = ?",
            array ($link_request_email, $target_user_id));
            
    } else {
        $target_user_id = intval($r->user_id);

        /*
         * the person who made this link request using $link_request_key_id
         * has proven that they control the email address used by
         * the existing account $target_user_id, so change the key_id
         * to point the existing account
         */
        query ("update pub_keys set user_id = ? where key_id = ?",
            array ($target_user_id, $link_request_key_id));
    }
} else {
    /*
     * the link request doesn't mention an email address, so we know
     * we're just trying to affect $link_request_user_id
     */
    $target_user_id = $link_request_user_id;
}

if ($target_user_id != $user->user_id) {
    /*
     * change the public key that we're logged in as to point to
     * $target_user_id
     */
    query ("update pub_keys set user_id = ? where key_id = ?",
        array ($target_user_id, $user->key_id));
}

/* clean up requests */
if ($link_request_email) {
    query ("delete from link_requests where email = ?", $link_request_email);
}
query ("delete from link_requests where user_id = ?", $link_request_user_id);
query ("delete from link_requests where token = ?", $arg_token);
query ("delete from link_requests where user_id = ?", $target_user_id);

redirect ("settings.php");
