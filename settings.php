<?php

require_once("app.php");

pstart ();

$arg_link = intval(@$_REQUEST['link']);

$body .= "<p>settings</p>\n";

if ($arg_link == 0) {
    $body .= "<p>\n";
    $body .= mklink ("link this account", "settings.php?link=1");
    $body .= "</p>\n";
} else {
    $token = generate_urandom_string (10);

    query ("insert into link_requests (user_id, token) values (?, ?)",
        array ($user_id, $token));

    $t = sprintf ("link.php?token=%s", $token);
    $url = make_absolute ($t);
    $body .= "<p>\n";
    $body .= sprintf ("<input type='text' size='50' value='%s'"
        ." readonly='readonly' />\n", $url);
    $body .= "</p>\n";

    $body .= mklink ($url, $url);

}

pfinish ();
