<?php
    //require_once(join(DIRECTORY_SEPARATOR, array(__DIR__, 'lib', 'Mobile_Detect.php')));
    require_once('lib/Mobile_Detect.php');
    require_once("util.php");
    require_once("image-util.php");

    $list = isset($_REQUEST['list']) ? $_REQUEST['list'] : '1';

    $count = 1;
    $selected = 1;

    while($count<100 && is_dir(mkpath("data",$count+1))) {
        $count++;
    }

    $ipmapPath = mkpath("ipmap.txt");

    $configDir = mkpath();
    $configFile = mkpath("cdpf.ini");
    $configDefaults = array(
        "title" => "Connected Digital Photo Frame",
        "thumbWidth" => 1440/4,
        "thumbHeight" => 900/4,
        "albumNames" => array()
    );
    if (is_file($configFile)) {
        $config =  array_merge($configDefaults,parse_ini_file($configFile));
    }
    if (!isset($config) || $config===false) {
        $config = $configDefaults;
    }

    if (count($config['albumNames'])>0) {
        $count = count($config['albumNames']);
    }

    $exiftool = isset($config['exiftoolPath']) ? $config['exiftoolPath'] : trim(`which exiftool`);
    $jpegtran = isset($config['jpegtranPath']) ? $config['jpegtranPath'] : trim(`which jpegtran`);

    if ($verbose) {
        error_log("WORK: $WORK");
        error_log("data dir: " . mkpath("data"));
        error_log("configFile: $configFile");
        error_log("\$exiftool: $exiftool");
        error_log("\$jpegtran: $jpegtran");
        error_log("CONFIG:");
        foreach($config as $name => $value) {
            error_log("    $name: " . (is_scalar($value) ? $value : json_encode($value)));
        }
        error_log("ENV:");
        foreach($_ENV as $name => $value) {
            error_log("    $name: " . (is_scalar($value) ? $value : json_encode($value)));
        }
        error_log("REQUEST:");
        foreach($_REQUEST as $name => $value) {
            error_log("    $name: " . (is_scalar($value) ? $value : json_encode($value)));
        }
    }

    $twigData = array();
    $twigKeys = array(
        "title", "albumNames",
        "thumbWidth", "thumbHeight",
        "googleAnalyticsID",
        "authImage", "authPrompt", "authLabel", "authHashes", "authCaseInsensitive",
        "customCSS", "customJS"
    );
    foreach ($twigKeys as $key) {
        if (isset($config[$key])) $twigData[$key] = $config[$key];
    }

	if (isset($_REQUEST["debug"])) {
		echo "<div class='division'>";
		echo "<style> html { background: pink; } b { display: inline-block; margin: 0; padding: 0; text-weight: bold; min-width: 125px; font-family: monospace; } .division div { font-family: monospace; }</style>\n";
		echo "<h2>CDPF Configuration</h2>\n";
		echo "<div><b>exiftool</b>$exiftool</div>\n";
		echo "<div><b>dataDir</b>".mkpath("data")."</div>\n";
		echo "<div><b>fromPi</b>$fromPi</div>\n";
		echo "<div><b>album count</b>$count</div>\n";
        echo isset($_REQUEST["config"]) ? ("<div><b>config</b>${_REQUEST["config"]}</div>\n") : "";
        echo isset($_REQUEST["config"]) ? ("<div><b>secret</b>".crypt($_REQUEST["config"])."</div>\n") : "";
		echo "</div>";
		echo "<div class='division'><h2>GLOBALS</h2>\n";
		echo "<div style='white-space: pre; font-family: monospace; font-size: 120%;'>";
		var_dump($GLOBALS);
		echo "</div>";

        exit;
    }

    if (isset($_REQUEST["count"])) {
        echo $count;
        if (isset($_REQUEST["ip"])) {
            $ip = $_REQUEST["ip"];
            if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/',$ip)==1) {
                $map = @unserialize(@file_get_contents($ipmapPath));
                $map = is_array($map) ? $map : array();
                $map['latest'] = $ip;
                if (isset($_SERVER["REMOTE_ADDR"])) {
                    $map[$_SERVER["REMOTE_ADDR"]] = $ip;
                }
                file_put_contents($ipmapPath, serialize($map));
            }
        }
        exit;
    }

    file_put_contents("$WORK/count.txt","$count");

	require_once 'twig/lib/Twig/Autoloader.php';
	Twig_Autoloader::register();
	$twig = new Twig_Environment(new Twig_Loader_Filesystem('.'));

    $function = new Twig_SimpleFunction('filetimestamp', function ($file) {
        return "".filemtime($file);
    }, array('is_safe' => array('html')));
    $twig->addFunction($function);

    $detect = new Mobile_Detect;
    $htmlClasses =
        ($detect->isMobile() ? "ismobile" : "isnotmobile") .
        ($detect->isTablet() ? " istablet" : " isnottablet") .
        ($detect->isiPhone() ? " isiphone" : "") .
        ($detect->isiPad() ? " isipad" : "") .
        ($detect->isiOS() ? " isios" : "") .
        ($detect->isAndroidOS() ? " isandroid" : "") .
        ($detect->isSafari() ? " issafari" : "") .
        "";
    $twigData['htmlClasses'] = $htmlClasses;


	if (!$fromPi) {
		require("auth.php");
	}

    if (isset($_REQUEST["save-config"]) || isset($_REQUEST["config"])) {
        require("config.php");
        exit;
    }

    // test for abs path to image
    function isImagePath($path) {
        $dataDir = mkpath("data");
        $re = '!^/?[0-9]+/[^./][^/]*\.(jpe?g|png|gif)$!i';
        if (strpos($path,$dataDir)===0) {
            $relName = substr($path, strlen($dataDir));
            $result = preg_match($re, $relName);
            verboseLog($relName, var_export($result, true));
            return $result!==false && $result>0;
        }
        return false;
    }

    // test for image file names
    function isLegalImageFileName($name) {
        $re = '!^[^/.][^/]*(jpe?g|gif|png)$!i';
        $result = preg_match($re, $name);
        verboseLog($name, var_export($result, true));
        return $result!==false && $result>0;
    }

    // for debugging
	if (isset($_REQUEST['phpinfo'])) {
		phpinfo();
		exit;
	}

    foreach(array("data", "thumb", "undo") as $folder) {
        $path = mkpath($folder);
        if (!is_dir($path)) {
            verboseLog("Making directory: $path");
            mkdir($path);
        }
    }

    verboseLog("Making directory: $count");
    for($num = 1; $num <= $count; $num++) {
        $path = mkpath("data","$num");
        if (!is_dir($path)) {
            verboseLog("Making directory: $path");
            mkdir($path);
        }
    }

	// handle serving image files 
	if (isset($_REQUEST['f'])) {

        verboseLog("File:", $_REQUEST['f']);

        if (isLegalImageFileName($_REQUEST['f'])) {
            $file = join(DIRECTORY_SEPARATOR, array(__DIR__, $_REQUEST['f']));
            $safeImage = is_file($file);
        } else {
            $file = mkpath("data", $_REQUEST['f']);
            $safeImage = isImagePath($file);
        }

        verboseLog("File:", $file);

        if ($safeImage && is_file($file)) {

			// set content type if a legal image extention
			if (preg_match('/\.gif/i',$file)) {
				header('Content-Type: image/gif');
			} else if (preg_match('/\.jpe?g/i',$file)) {
				header('Content-Type: image/jpeg');
			} else if (preg_match('/\.png/i',$file)) {
				header('Content-Type: image/png');
			} else {
				header ("HTTP/1.0 404 Not Found");
				exit;
			}

			// Checking if the client is validating his cache and if it is current.
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == filemtime($file))) {
				// Client's cache IS current, so we just respond '304 Not Modified'.
				header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 304);
				exit;
			}
			
			// insert Last-Modified header for wget's -N option
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file)).' GMT', true, 200);
			header('Content-Length: '.filesize($file));
			// for HEAD requests (which WGET claims to use) we don't send the content
			if ($_SERVER['REQUEST_METHOD']=='HEAD') {
				exit;
			}
			
			// tell clients to cache for 5 years
			$seconds_to_cache = 60*60*24*365*5;
			$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
			header("Expires: $ts");
			header("Pragma: cache");
			header("Cache-Control: max-age=$seconds_to_cache");
			header('Pragma: public');
			
			// send image to client
			readfile($file);
			exit;
			
		}
		
		// error
		header ("HTTP/1.0 404 Not Found");
		exit;
	}

    if (isset($_REQUEST['save'])) {
        $caption = isset($_REQUEST['caption']) ? $_REQUEST['caption'] : "";
        $image = isset($_REQUEST['image']) ? $_REQUEST['image'] : "";

        $cmd = "$exiftool -UserComment=" . escapeshellarg($caption) . " " . escapeshellarg(mkpath("data", $image)) . " 2>&1";

        $out = exec($cmd);

        if ($verbose) {
            error_log("Modify Caption Operation for " . $image);
            error_log("New caption: <" . $caption . ">");
            error_log("Command: " . $cmd);
            error_log("Output: " . $out);
        }

        $exif = exif_read_data(mkpath("data", $image), 0, false);
        $caption = isset($exif['COMPUTED']['UserComment']) ? $exif['COMPUTED']['UserComment'] : "";

        print $caption;

        exit;
    }

    $aspectRatio = isset($config['aspectRatio']) ? 1.0*$config['aspectRatio'] : false;

    if (isset($_REQUEST['crop'])) {
        verboseLog("CROP hit");

        $right = (isset($_REQUEST['dir']) && $_REQUEST['dir']=='left') ? false : true;
        $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : false;

        $dataPath = mkpath("data", $name);
        $validPath = preg_match('/^\d+\/[^\/]+\.(jpeg|jpg|gif|png)$/i',$name)==1 && is_file($dataPath);

        verboseLog("$dataPath ".is_file($dataPath).", $validPath");

        if ($validPath && $name!==false && is_file($dataPath)) {
            $undoName = "${name}_CROP-" . date('Ymd\THisT',filemtime($dataPath));
            $undoFile = mkpath("undo", $undoName);
            if (!is_dir(dirname($undoFile))) {
                mkdir(dirname($undoFile),0777, true);
            }
            rename($dataPath, $undoFile);

            $result = imageCropAspect($undoFile, $dataPath, $aspectRatio);

            if ($result!==FALSE) {
                echo $undoName;
            } else {
                rename($undoFile, $dataPath);
                header("HTTP/1.0 500 Server Error");
                exit;
            }
            if (is_file($dataPath)) touch($dataPath);
            exit;
        } else {
            header("HTTP/1.0 403 Forbidden");
            exit;
        }
        exit;
    }

    if (isset($_REQUEST['rotate'])) {
        verboseLog("ROTATE hit");
        $right = (isset($_REQUEST['dir']) && $_REQUEST['dir']=='left') ? false : true;
        $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : false;

        $dataPath = mkpath("data", $name);
        $validPath = preg_match('/^\d+\/[^\/]+\.(jpeg|jpg|gif|png)$/i',$name)==1 && is_file($dataPath);

        verboseLog("$dataPath ".is_file($dataPath).", $validPath");

        if ($validPath && $name!==false && is_file($dataPath)) {
            $undoName = "${name}_ROT" . ($right ? "R-" : "L-") . date('Ymd\THisT',filemtime($dataPath));
            $undoFile = mkpath("undo", $undoName);
            if (!is_dir(dirname($undoFile))) {
                mkdir(dirname($undoFile),0777, true);
            }
            rename($dataPath, $undoFile);
            $cmd = "$jpegtran -v -v -v -rotate " . ($right?90:270) . " -outfile " . escapeshellarg($dataPath) . " " . escapeshellarg($undoFile) . " 2>&1";

            verboseLog("$dataPath ".is_file($dataPath).", $undoFile".is_file($undoFile));

            $out = exec($cmd);
            if (is_file($dataPath)) touch($dataPath);
            echo filemtime($dataPath);
            verboseLog("ROT CMD: '$cmd'", "OUTPUT:", $out);
        } else {
            header("HTTP/1.0 403 Forbidden");
            exit;
        }
        exit;
    }

	$message = "";

    function getImageData($path,$name) {
		global $aspectRatio, $config;
        $exif = exif_read_data($path, 0, false);
        $result = array(
            "caption" => (isset($exif['COMPUTED']['UserComment']) ? $exif['COMPUTED']['UserComment'] : ""),
            "name" => $name,
            "size" => getimagesize($path)
        );
		if ($aspectRatio!==false) {
			$size = $result['size'];
			$imageRatio = $size[0]/$size[1];
			if ($aspectRatio>$imageRatio) {
				$result['crop'] = [$size[0]*$config['thumbHeight']/$size[1], $size[0]*$config['thumbHeight']/$size[1]/$aspectRatio];
				$result['pos'] = [($config['thumbWidth']-$result['crop'][0])/2, ($config['thumbHeight']-$result['crop'][1])/2];
			} else {
				$result['crop'] = [$size[1]*$config['thumbWidth']/$size[0]*$aspectRatio, $size[1]*$config['thumbWidth']/$size[0]];
				$result['pos'] = [($config['thumbWidth']-$result['crop'][0])/2, ($config['thumbHeight']-$result['crop'][1])/2];
			}
		}
        verboseLog(sprintf("EXIF: %s %s %d '%s'", $path, $name, $result['size'], $result['caption']=='' ? '-none-' : $result['caption']));
        return $result;
    }


    if (isset($_REQUEST['upload-submit']) && isset($_FILES["the-file"])) {
	
		$file = $_FILES["the-file"];
		$message = "Missing or illegal file name!";
		
		if (isset($file["name"]) && isLegalImageFileName($file["name"])) {
		
			$name = preg_replace('/^[.]|[^\w.]/i','-', $file['name']);

            $album = isset($_REQUEST['the-album']) ? $_REQUEST['the-album'] : '1';

            if (preg_match('/^[0-9]+$/',$album)>0) {
                $albumPath = mkpath("data",$album);
                if (!is_dir($albumPath)) {
                    mkdir($albumPath);
                }
                $i = 1;
                $suffix = "";
                do {
                    $newName = preg_replace('/^(.*)\.([^.]*)$/',"$1$suffix.$2",$name);
                    $path = mkpath("data",$album,$newName);
                    $i++;
                    $suffix = "_$i";
                } while(is_file($path));

                $name = $newName;
                $result = move_uploaded_file($file["tmp_name"],$path);

                if ($result) {
                    $message = "The photo $name has been uploaded";
                    echo $twig->render('image.twig', array_merge($twigData,array(
                        'ajaxMessage' => $message,
                        'item' => getImageData($path,$name),
                        'i' => $album,
                    )));
                    exit;
                } else {
                    $message = "Error receiving the file: $name";
                }
            }
		}
        echo "<div class='message error'>".htmlentities($message)."</div>";
		exit;
	}

	$images = array();
    foreach(range(1,$count) as $i) {
        $names = @array_filter(@scandir(mkpath("data",$i,"")),'isLegalImageFileName');
        $album = array();
        foreach($names as $name) {
            $path = mkpath("data", $i, $name);
            $album[] = getImageData($path,$name);
        }
        $images[$i] = $album;
    }

	if (isset($_REQUEST['delete-image'])) {
        $file = mkpath("data", $_REQUEST['delete-image']);
        $thumb = mkpath("thumb", $_REQUEST['delete-image']);
        $validPath = isImagePath($file);
        verboseLog("Delete:", $file, $validPath ? "PASS":"FAIL");

		if ($validPath) {
            $name = basename($file);
            $undo = mkpath("undo", "${name}_DEL" . date('Ymd\THisT',filemtime($file)));
            if (!is_dir(dirname($undo))) {
                mkdir(dirname($undo),0777, true);
            }
            verboseLog("    UNDO:", $undo);
            verboseLog("    Thumb:", $thumb);
            rename($file, $undo);
            @unlink($thumb);
			header('Location: /');
			exit;
		}
	}

    $ip = false;
    $map = @unserialize(@file_get_contents($ipmapPath));
    if (is_array($map)) {
        if ($verbose) {
            verboseLog("IP Map:");
            foreach($mas as $key => $value) {
                verboseLog("    $key: $value");
            }
        }
        $remote = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'latest';
        $ip = isset($map[$remote]) ? $map[$remote] : $map['latest'];
    }

	echo $twig->render($fromPi ? 'list.twig' : 'index.twig', array_merge($twigData,array(
        'message' => $message,
		'images' => $images,
		'ui' => !$fromPi,
        'albumCount' => $count,
        'albumSelected' => $selected,
        'list' => $list,
        'ip' => $ip,
        'verbose' => $verbose
	)));

?>
