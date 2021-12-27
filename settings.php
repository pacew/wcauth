<?php

require_once("app.php");

pstart ();

$arg_link = intval (@$_REQUEST['link']);
$arg_save = intval (@$_REQUEST['save']);
$arg_email = trim (@$_REQUEST['email']);

$body .= "<p>settings</p>\n";

if ($arg_link == 1) {
    $token = generate_urandom_string (10);

    $request_id = get_seq ();
    $email = "";
    query ("insert into link_requests (request_id user_id, email, token"
    ." ) values (?, ?, ?, ?)",
        array ($request_id, $user_id, $email, $token));

    $body .= "<p>Paste this link into a different browser"
          ." to link accounts.</p>\n";

    $t = sprintf ("link.php?token=%s", $token);
    $url = make_absolute ($t);
    $body .= "<p>\n";
    $body .= sprintf ("<input type='text' size='50' value='%s'"
        ." readonly='readonly' />\n", $url);
    $body .= "</p>\n";

    $body .= mklink ($url, $url);
    pfinish ();
}

$db_email = "";

$q = query ("select email"
    ." from users"
    ." where user_id = ?",
    $user_id);
if (($r = fetch ($q)) != NULL) {
    $db_email = trim ($r->email);
}

$q = query ("select distinct email"
    ." from link_requests"
    ." where user_id = ?",
    $user_id);

$pending_requests = [];
while (($r = fetch ($q)) != NULL) {
    $email = trim ($r->email);
    if ($email) {
        $pending_requests[] = $email;
    }
}

if ($arg_save == 1) {
    if ($arg_email == "") {
        query ("update users set email = NULL where user_id = ?", $user_id);
    } else if (strcmp ($arg_email, $db_email) != 0) {
        $token = generate_urandom_string (10);
        
        $request_id = get_seq ();
        query ("insert into link_requests ("
            ." request_id, user_id, key_id, email, token"
            ." ) values (?,?,?,?,?)",
            array ($request_id, $user_id, $key_id, $arg_email, $token));
        
        $t = sprintf ("link.php?token=%s", $token);
        $url = make_absolute ($t);

        global $cfg;
        
        $text = "";
        $text .= "This link will allow you to connect this email address"
              ." to your account on " . $cfg['main_url'] . "\n\n";
        
        $text .= $url . "\n\n";

        $text .= "This link will expire after one day.  If you did not"
              ." recently request an account link,\n"
              ."please ignore this message.\n";

        if (0) {
            $body .= "<pre>\n";
            $body .= h($text);
            $body .= "</pre>\n";
        
            $body .= mklink ($url, $url);
            
            pfinish ();
        }

        $args = (object)NULL;
        $args->to_email = $arg_email;
        $args->from_email = "pace.willisson@gmail.com";
        $args->from_name = "Pace Willisson";
        $args->subject = sprintf ("%s account link", $cfg['siteid']);
        $args->body_text = $text;
        if (($err = send_email ($args)) != "") {
            flash ("email send error " . h($err));
        } else {
            flash ("email sent");
        }
    }
        
    redirect ("settings.php");
}
    


$body .= "<form action='settings.php'>\n";
$body .= "<input type='hidden' name='save' value='1' />\n";
$body .= "<table class='twocol'>\n";
$body .= "<tr><th>email</th><td>";
$body .= sprintf ("<input type='text' name='email' value='%s' size='40' />",
    h($db_email));

if ($pending_requests) {
    $body .= "<br/>";
    $body .= "pending email link requests: ";
    foreach ($pending_requests as $email) {
        $body .= h($email) . " ";
    }
}

$body .= "</td></tr>\n";

$body .= "<tr><th></th><td>";
$body .= "<input type='submit' value='Save' />\n";
$body .= mklink ("cancel", "settings.php");
$body .= "</td></tr>\n";
$body .= "</table>\n";
$body .= "</form>\n";


$body .= "<p>\n";
$body .= mklink ("make a link for this account", "settings.php?link=1");
$body .= "</p>\n";


$q = query ("select key_id from pub_keys where user_id = ?", $user_id);
$keys = [];
while (($r = fetch ($q)) != NULL) {
    $keys[] = intval($r->key_id);
}
$body .= sprintf ("<p>key_ids %s</p>\n", implode(',', $keys));

pfinish ();
