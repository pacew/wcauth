<?php

/*
 * this file can be called several ways:
 *
 * login=1: start login sequence
 *   create challenge, store in session, and send to browser
 *   browser calls wcauth_login in login.js
 *
 * pub and sig supplied: finish login
 *   browser has signed the challenge.  if it is valid, find the server
 *   key_id that matches the public key and put that in the php session.
 *   this maps to a server user_id for the rest of the session
 *
 * logout=1: server logout
 *   delete the key_id from the php session
 *
 * delete=1: tell the browser to delete the key
 *   for now, we just have one key for the origin (SCHEMA://HOST:PORT)
 *
 */


require_once("app.php");

$anon_ok = 1;

pstart ();

$extra_javascript .= "<script src='login.js'></script>\n";

$arg_pub = trim (@$_REQUEST['pub']);
$arg_sig = trim (@$_REQUEST['sig']);
$arg_return_to = trim(@$_REQUEST['return_to']);
$arg_delete = intval(@$_REQUEST['delete']);
$arg_login = intval(@$_REQUEST['login']);
$arg_logout = intval(@$_REQUEST['logout']);
$arg_browser_nonce = trim (@$_REQUEST['browser_nonce']);

function get_nonce () {
    if (($nonce = @getsess ("nonce")) == "") {
        $nonce = generate_urandom_string(8);
        putsess ("nonce", $nonce);
    }
    return ($nonce);
}

function create_account ($public_key) {
    $key_id = get_seq ();

    while (1) {
        // try random user_id's until we find an unused one
        $ndigits = 4;
        $mod = pow(10, $ndigits); // e.g. 10000 for 4 digits */
        $user_id = rand () % $mod;

        // if the most significant digit is 0, change it to 1
        if ($user_id < $mod / 10)
            $user_id += $mod / 10;
        
        $q = query ("select 0 from users where user_id = ?", $user_id);
        if (fetch ($q) == NULL)
            break;
    }

    query ("insert into users (user_id) values (?)", $user_id);
    query ("insert into pub_keys (key_id, user_id, key_text)"
        ." values (?, ?, ?)",
        array ($key_id, $user_id, $public_key));

    return ($key_id);
}

/* this is the start of the login sequence */
if ($arg_login == 1) {
    putsess ("key_id", 0); /* make sure we're logged out */
    putsess ("login_return_to", $arg_return_to);

    $body .= sprintf ("<div style='display:none' id='wcauth_signin'>"
        ."%s</div>\n", h(base64_encode(get_nonce())));

    pfinish ();
    /*
     * login.js will compute the signature and redirect with sig set,
     * so we'll continue with the next if statement
     */
}

if ($arg_sig) {
    // login.js has responded to the challenge
    $sig_binary = base64_decode ($arg_sig);

    /*
     * the message that was signed is the concatenation of the
     * nonce that the browser generated, and the nonce that
     * the server created and has saved in the session
     */
    $message = base64_decode($arg_browser_nonce) . get_nonce();

    $val = openssl_verify ($message, $sig_binary, $arg_pub, 'sha256');

    if ($val <= 0) {
        flash ("invalid login");
        putsess ("nonce", "");
        putsess ("key_id", 0);
        redirect ("/");
    }

    $q = query ("select key_id from pub_keys where key_text = ?", $arg_pub);

    if (($r = fetch ($q)) == NULL) {
        $key_id = create_account ($arg_pub);
    } else {
        $key_id = intval ($r->key_id);
    }

    putsess ("key_id", $key_id);

    if (($t = getsess ("login_return_to")) != "")
        redirect ($t);

    redirect ("/");
}

if ($arg_logout == 1) {
    // just logout of the server
    putsess ("key_id", 0);
    redirect ("/");
}

if ($arg_delete == 1) {
    // delete private key in browser
    putsess ("key_id", 0);
    $body .= "<div style='display:none' id='wcauth_delete'></div>\n";
    pfinish ();
    /* javascript will redirect to / after it deletes the key */
}

/* shouldn't get here */
redirect ("/");


