<?php

// prevent Warning: preg_match(): JIT compilation failed: no more memory in ...
ini_set ("pcre.jit", 0); 

require_once ($_SERVER['PSITE_PHP']);

if (! @$cli_mode 
    && @$_SERVER['HTTPS'] == "" 
    && @$cfg['ssl_url'] != "") {
    $url = preg_replace ('|/$|', '', $cfg['ssl_url']);
    $path = preg_replace ('|^/|', '', $_SERVER['REQUEST_URI']);
    $t = sprintf ("%s/%s", $url, $path);
    header ("Location: $t");
    exit ();
}


$title_html = "wcauth";
$username = "";

function pstart () {
    global $pstart_timestamp;
    $pstart_timestamp = microtime (TRUE);

    psite_session ();

    ini_set ("display_errors", "1");

    global $body, $extra_javascript;
    $body = "";
    $extra_javascript = "";

    global $user_id;
    $user_id = intval (getsess ("user_id"));
    global $anon_ok;
    if (! @$anon_ok && $user_id == 0) {
        $t = sprintf ("login.php?return_to=%s", 
            urlencode ($_SERVER['REQUEST_URI']));
        redirect ($t);
    }

    $flash = trim (@$_SESSION['flash']);
    @$_SESSION['flash'] = "";
    if ($flash) {
		$body .= "<div class='flash'>";
		$body .= $flash;
		$body .= "</div>\n";
	}

    $body .= "<p>";
    $body .= mklink ("home", "/");
    $body .= " | ";
    $body .= mklink ("protected", "protected.php?example=123#abc");

    if ($user_id == 0) {
        $body .= " | ";
        $body .= mklink ("login", "login.php");
    } else {
        $body .= " | ";

        $user_name = sprintf ("user%d", $user_id);
        $body .= mklink ($user_name, "settings.php");

        $body .= " | ";
        $body .= mklink ("logout", "logout.php");
    }
    $body .= "</p>\n";

}

function pfinish () {
    $pg = "";

    $pg .= "<!doctype html>\n"
        ."<html lang='en'>\n"
        ."<head>\n"
        ."<meta charset='utf-8'>\n"
        ."<meta name='viewport' content='width=device-width,initial-scale=1'>\n";
    
    global $title_html;
    $pg .= "<title>";
    $pg .= $title_html;
    $pg .= "</title>\n";

    $pg .= sprintf ("<link rel='stylesheet' href='reset.css?c=%s' />\n",
                    get_cache_defeater ());
    $pg .= sprintf ("<link rel='stylesheet' href='style.css?c=%s' />\n",
                    get_cache_defeater ());

    $pg .= "<script src='https://ajax.googleapis.com"
        ."/ajax/libs/jquery/2.1.4/jquery.min.js'></script>\n";

    $pg .= "<link rel='stylesheet'"
        ." href='https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css'>\n";
    $pg .= "<script src='https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js'></script>\n";
    
    global $cfg;
    $pg .= "<script>\n";
    $pg .= sprintf ("var cfg = %s;\n", json_encode ($cfg));
    $pg .= "</script>\n";


    global $extra_javascript;
    if (@$extra_javascript)
        $pg .= $extra_javascript;

    $pg .= "</head>\n";
    
    $pg .= "<body>\n";

    $pg .= "<div class='content'>\n";

    $pg .= sprintf ("<h1 class='banner_title'>%s</h1>\n",
                    $title_html);

    echo ($pg);
    $pg = "";

    global $body;
    $pg .= $body;

    $pg .= "</div>\n";

    $pg .= sprintf ("<script src='scripts.js?c=%s.js'></script>\n",
                    get_cache_defeater ());

    global $username;
    if ($username) {
        global $pstart_timestamp;
        $secs = microtime(TRUE) - $pstart_timestamp;
        $pg .= sprintf ("<div id='generation_time'>%.0f msecs</div>\n",
                        $secs * 1000);
    }

    $pg .= "</body>\n";
    $pg .= "</html>\n";
    echo ($pg);


    do_commits ();
    exit (0);
}


if (! get_option ("flat") && ! @$cli_mode) {
    require (router());
    /* NOTREACHED */
}

function sess_file($name) {
    global $cfg;
    return (sprintf ("%s/sessions/%s-%s", 
                     $cfg['aux_dir'], session_id(), $name));
}

