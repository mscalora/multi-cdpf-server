<?php

    $defaultConfig =  <<<MODEL
;; CDPF conf file model
;; lines with one ; - uncomment to change the value
;; lines with two ; - comments to explain the value on the following line
[main]

;; title of your site
;title="Smith Family Photos"

;; exiftool path on the server
;exiftoolPath=/usr/local/bin/exiftool

;; names used on upload website
;albumNames[]=Vacation Photos
;albumNames[]=Holiday Photos
;albumNames[]=Family Photo
;albumNames[]=Grandkids

;; thumbnail size [delete all cached files in /thumb/ directory]
;thumbWidth=360
;thumbHeight=225

;; if you want to use google analytics, put your site ID here
;googleAnalyticsID=UA-99999999-9

;;## custom login ####

;; instruction to user
;authPrompt="Enter the name of the dog in the photo."

;; this image will be shown on login page under the prompt
;authImage="http://some.domain/secret.jpg"

;; password field label
;authLabel="Name:"

;; valid response hashes
;authHashes[]="$1\$T7aH.0CT\$nYa8gRQN.EJfPtw70edxJ1"
;authHashes[]="$1\$3b5Z/Znv\$3GSoLfyxb7xXbrdvFAZJv0"
;authHashes[]="$1\$H2KHScA4\$LJu3H2K8J1O07D3yIyFJL1"

;; how many seconds logins last, 31536000 is one year, 2592000 is 30 days, 604800 is one week, 86400 = 1 day
;authSeconds=31536000

;; set to 1 if passwords should be lower-cased before testing
;authCaseInsensitive=1

MODEL;

    $configReadOnly = (is_file($configFile) && !is_writable($configFile)) ||
        (!is_file($configFile) && !is_writable($configDir));

    if (isset($_REQUEST['config-save']) && isset($_REQUEST['config-text'])) {
        $configText = $_REQUEST['config-text'];
        $oldConfigTest = @file_get_contents($configFile);
        file_put_contents($configFile.".bak",$oldConfigTest);
        file_put_contents($configFile,$configText);
        $twigData['message'] = "Config Changes Saved";
        $twigData['messageType'] = "confirmation";
    }

    echo $twig->render('config.twig', array_merge($twigData,array(
        'configText' => is_file($configFile) ? file_get_contents($configFile) : $defaultConfig,
        'configReadOnly' => $configReadOnly,
        'configFile' => $configFile,
        'albumCount' => $count,
    )));
    exit;

