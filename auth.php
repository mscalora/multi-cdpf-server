<?php
	$authed = false;
	$pwfile = "$WORK/.passwd";

    if (!is_file("$pwfile")) {
		if (isset($_REQUEST['setup']) && isset($_REQUEST['secret']) && strlen($_REQUEST['secret'])>=6) {
			$HASH = crypt($_REQUEST['secret']);
			file_put_contents($pwfile,$HASH);
			header('Location: /');
			exit;
		}
		
		if ($_SERVER["REQUEST_METHOD"]!="GET") {
			header('Location: /');
			exit;		
		}
		
		echo $twig->render('auth.twig', array_merge($twigData,array(
			'prompt' => "Enter password of at least 6 characters to secure your site.",
			'setup' => true
		)));
		exit;
	}

	$UUID = isset($_ENV['OPENSHIFT_GEAR_UUID']) ? $_ENV['OPENSHIFT_GEAR_UUID'] :
		(isset($_SERVER["SERVER_SIGNATURE"]) ? $_SERVER["SERVER_SIGNATURE"] : "multicdpf-1234567890ABCDEF");
	$DATE = date("dlFmY");
	$IP = $_SERVER["REMOTE_ADDR"];
	$PWHASH = file_get_contents($pwfile);
	
	$TOKEN = sha1("$UUID.$DATE.$IP.$PWHASH");
	

	if (isset($_REQUEST['logout'])) {
		setcookie("ticket", "", time()-60*60*24, "/");
		header('Location: /');
		exit;
	} elseif (isset($_REQUEST['login']) && isset($_REQUEST['secret'])) {
        if (isset($config['authHashes'])) {
            foreach($config['authHashes'] as $hash) {
                $secret = trim(isset($config['authCaseInsensitive'])?strtolower($_REQUEST['secret']):$_REQUEST['secret']);
                if (crypt($secret,$hash)==$hash) {
                    setcookie("ticket", $TOKEN, time()+(isset($config['authSeconds'])?$config['authSeconds']:60*60*24), "/");
                    header('Location: /');
                    exit;
                }
            }
        } elseif (crypt($_REQUEST['secret'],$PWHASH)==$PWHASH) {
			setcookie("ticket", $TOKEN, time()+60*60*24, "/");
			header('Location: /');
			exit;
		}
	} elseif(isset($_COOKIE['ticket'])) {
		if ($_COOKIE['ticket']==$TOKEN) {
			$authed = true;
		}
	}

	if (!$authed) {
		if ($_SERVER["REQUEST_METHOD"]!="GET") {
			header('Location: /');
			exit;		
		}
		
		echo $twig->render('auth.twig', array_merge($twigData,array(
			'prompt' => "Login",
			'setup' => false
		)));
		exit;		
	}


?>
