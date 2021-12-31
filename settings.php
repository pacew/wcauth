<?php

require_once("app.php");
require_once("totp.php");

pstart ();

$arg_link = intval (@$_REQUEST['link']);
$arg_save = intval (@$_REQUEST['save']);
$arg_email = trim (@$_REQUEST['email']);
$arg_attach = intval (@$_REQUEST['attach']);
$arg_user_name = trim (@$_REQUEST['user_name']);
$arg_auth_code = trim (@$_REQUEST['auth_code']);

if ($arg_link == 1) {
    $token = generate_urandom_string (10);

    $request_id = get_seq ();
    $email = "";
    query ("insert into link_requests (request_id, user_id, email, token"
    ." ) values (?, ?, ?, ?)",
        array ($request_id, $user->user_id, $email, $token));

    $body .= "<p>Paste this link into a different browser"
          ." to link accounts.</p>\n";

    $t = sprintf ("link.php?token=%s", $token);
    $url = make_absolute ($t);
    $body .= "<p>\n";
    $body .= sprintf ("<input type='text' size='50' value='%s'"
        ." readonly='readonly' />\n", $url);
    $body .= "</p>\n";

    $body .= "<p>\n";
    $body .= mklink ($url, $url);
    $body .= "</p>\n";


    $db_totp_key = "";
    
    $q = query ("select totp_key"
        ." from users"
        ." where user_id = ?",
        $user->user_id);
    
    if (($r = fetch ($q)) != NULL) {
        $body .= "<hr/>\n";

        $body .= "<p>Scan this into your authentication app,"
              ." then use your username to set up a different browser.</p>\n";

        $db_totp_key = trim ($r->totp_key);


        $body .= "<p>\n";
        $issuer = "wcauth";
        $label = sprintf ("%s:%s", $issuer, $cfg['conf_key']);
        $totp_link = sprintf ("otpauth://totp/%s?secret=%s&issuer=%s",
            rawurlencode ($label),
            rawurlencode ($db_totp_key),
            rawurlencode ($issuer));
        $body .= make_qr_svg ($totp_link);
        $body .= "</p>\n";

        $body .= "<p>\n";
        $body .= sprintf ("<input type='text' name='totp_key'"
            ." size='40' value='%s' readonly='readonly' />\n",
            h($db_totp_key));
        $body .= "</p>\n";

        $body .= "<p>\n";
        $body .= totp ($db_totp_key);
        $body .= "</p>\n";
    }

    pfinish ();
}

if ($arg_attach == 1) {
    $body .= "<form action='settings.php'>\n";
    $body .= "<input type='hidden' name='attach' value='2' />\n";
    $body .= "<table class='twocol'>\n";
    $body .= "<tr><th>User name</th><td>\n";
    $body .= "<input type='text' name='user_name' />\n";
    $body .= "</tr></th>\n";
    $body .= "<tr><th>Authorization code</th><td>\n";
    $body .= "<input type='text' name='auth_code' />\n";
    $body .= "</td></tr>\n";
    $body .= "<tr><th></th><td>";
    $body .= "<input type='submit' value='Login' />\n";
    $body .= "</td></tr>\n";
    $body .= "</table>\n";
    $body .= "</form>\n";
    pfinish ();
}
        
function find_user_id ($user_name) {
    global $arg_user_name;
    $val = preg_match ('/^([0-9]*)$/', $arg_user_name, $parts);
    if ($val) {
        $user_id = intval(@$parts[1]);
        if ($user_id)
            return ($user_id);
    }

    if (preg_match ('/^user([0-9]*)$/', $arg_user_name, $parts)) {
        $user_id = intval(@$parts[1]);
        if ($user_id)
            return ($user_id);
    }

    $q = query ("select user_id from users where email = ?", $user_name);
    if (($r = fetch ($q)) != NULL)
        return (intval ($r->user_id));

    return (-1);
}

if ($arg_attach == 2) {
    if (($user_id = find_user_id ($arg_user_name)) <= 0) {
        flash ("invalid user id");
        redirect ("settings.php");
    }

    $q = query ("select totp_key from users where user_id = ?",
        $user_id);
    if (($r = fetch ($q)) == NULL) {
        flash ("internal error");
        redirect ("settings.php");
    }
    
    $totp_key = $r->totp_key;

    $expect =totp ($totp_key);

    if (strcmp ($arg_auth_code, $expect) == 0) {
        query ("update pub_keys set user_id = ? where key_id = ?",
            array ($user_id, $user->key_id));
        redirect ("settings.php");
    }

    flash ("invalid login");
    redirect ("settings.php");
}


$q = query ("select distinct email"
    ." from link_requests"
    ." where user_id = ?",
    $user->user_id);

$pending_requests = [];
while (($r = fetch ($q)) != NULL) {
    $email = trim ($r->email);
    if ($email) {
        $pending_requests[] = $email;
    }
}

if ($arg_save == 1) {
    if ($arg_email == "") {
        query ("update users set email = NULL where user_id = ?", 
            $user->user_id);
    } else if (strcmp ($arg_email, $user->email) != 0) {
        $token = generate_urandom_string (10);
        
        $request_id = get_seq ();
        query ("insert into link_requests ("
            ." request_id, user_id, key_id, email, token"
            ." ) values (?,?,?,?,?)",
            array ($request_id, $user->user_id, $user->key_id, 
                $arg_email, $token));
        
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
    
$body .= "<p>settings</p>\n";


$body .= "<form action='settings.php'>\n";
$body .= "<input type='hidden' name='save' value='1' />\n";
$body .= "<table class='twocol'>\n";
$body .= "<tr><th>email</th><td>";
$body .= sprintf ("<input type='text' name='email' value='%s' size='40' />",
    h($user->email));

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
$body .= mklink ("make a link to export this account", "settings.php?link=1");
$body .= "</p>\n";

$body .= "<p>\n";
$body .= mklink ("attach this browser to a different account", 
    "settings.php?attach=1");
$body .= "</p>\n";


$body .= "<div class='debug_box'>\n";
$body .= sprintf ("<p>user_id %d; active key_id %d</p>", 
    $user->user_id, $user->key_id);
$q = query ("select key_id from pub_keys where user_id = ?", $user->user_id);
$keys = [];
while (($r = fetch ($q)) != NULL) {
    $keys[] = intval($r->key_id);
}
$body .= sprintf ("<p>all key_ids for this user: %s</p>\n", 
    implode(',', $keys));
$body .= "</div>\n";

$body .= "<p>";
$body .= mklink ("delete key", "login.php?delete=1");
$body .= "</p>";

pfinish ();
