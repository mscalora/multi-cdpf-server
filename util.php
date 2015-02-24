<?php

    // Globals:
    //
    //  $WORK     e.g.  "/bla/bla/bla"
    //
    //  $dataDir  e.g.  "/bla/bla/bla/data"
    //

    require_once('lib/Mobile_Detect.php');

    $protocol = !empty($_SERVER['HTTPS'])?"https":"http";
    $url = "$protocol://${_SERVER['SERVER_NAME']}${_SERVER['REQUEST_URI']}";
    $url = preg_replace('/\?.*/','',$url);
    $url = preg_replace('/\/$|\/\w+\.php$/','',$url);

    if (isset($_ENV['OPENSHIFT_DATA_DIR'])) {
        $WORK = rtrim($_ENV['OPENSHIFT_DATA_DIR'], DIRECTORY_SEPARATOR);
    } else if (isset($_SERVER['OPENSHIFT_DATA_DIR'])) {
        $WORK = $_SERVER['OPENSHIFT_DATA_DIR'];
    } else {
        $WORK = trim(is_file('.datadir_path') ?
            file_get_contents('.datadir_path') :
            $_SERVER['DOCUMENT_ROOT']);
    }

    function mkpath() {
        global $WORK;
        $args = func_get_args();
        array_unshift($args, $WORK);
        return join(DIRECTORY_SEPARATOR, $args);
    }

    $fromPi = (isset($_REQUEST['list']) && count($_REQUEST)==1)
        || (isset($_SERVER["HTTP_USER_AGENT"]) && $_SERVER["HTTP_USER_AGENT"]=="CDPF")
        || isset($_REQUEST["CDPF"]);

    $appDir = isset($OPENSHIFT_REPO_DIR) ? $OPENSHIFT_REPO_DIR : __DIR__;
    $appBin = join(DIRECTORY_SEPARATOR, array($appDir, 'bin'));

    $verbose = isset($_REQUEST['verbose']);
    $verboseURL = $verbose ? '?verbose=1' : '';

    function verboseLog() {
        global $verbose;
        if ($verbose) {
            $args = func_get_args();
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $func = isset($trace[1]['function']) ? $trace[1]['function'] : "main";
            array_push($args, "($func)");
            error_log(join(" ", $args));
        }
    }

    putenv('PATH='.getenv('PATH').':/usr/local/bin:'.$appBin);
