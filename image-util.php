<?php

    function imageCropAspect($inFile, $outFile, $aspectRatio, $transparent=TRUE) {

        $outRatio = floatval($aspectRatio);
        $info = @getimagesize($inFile);

        if ($info===FALSE) {
            error_log("Can't determine input image type: $inFile");
            return FALSE;
        }

        $inWidth = $info[0];
        $inHeight = $info[1];
        $typeCode = $info[2];

        if ($typeCode!==IMAGETYPE_GIF && $typeCode!==IMAGETYPE_JPEG && $typeCode!==IMAGETYPE_PNG) {
            error_log("Input image $inFile of unsupported type: $typeCode");
            return FALSE;
        }

        $inRatio = $inWidth / $inHeight;
        verboseLog("Image: $inWidth x $inHeight of type $typeCode at $inRatio");

        if (abs($inRatio-$outRatio)*max($inWidth,$inHeight)<1.75) {
            error_log("Image already at correct ratio");
            return FALSE;
        }

        if ($inRatio>$outRatio) {
            $outHeight = $inHeight;
            $outWidth = round($outHeight * $outRatio);
            $inTop = 0;
            $inLeft = round(($inWidth-$outWidth)/2);
        } else {
            $outWidth = $inWidth;
            $outHeight = round($outWidth / $outRatio);
            $inTop = round(($inHeight-$outHeight)/2);
            $inLeft = 0;
        }

        verboseLog("New Image: $outWidth x $outHeight from $inTop,$inLeft of type $typeCode at $outRatio ".($outWidth/$outHeight));

        if ($typeCode===IMAGETYPE_GIF) {
            $inImage = imagecreatefromgif($inFile);
        } elseif ($typeCode===IMAGETYPE_PNG) {
            $inImage = imagecreatefrompng($inFile);
        } else {
            $inImage = imagecreatefromjpeg($inFile);
        }

        if ($inImage===FALSE) {
            error_log("Failed to load image: $inFile");
            return FALSE;
        }

        $outImage = imagecreatetruecolor($outWidth, $outHeight);

        if ($outImage===FALSE) {
            error_log("Failed to create output image of size $outWidth x $outHeight");
            return FALSE;
        }

        if ($transparent && ($typeCode===IMAGETYPE_GIF ||$typeCode===IMAGETYPE_PNG)) {
            imagealphablending($outImage, false);
            imagefill($outImage, 0, 0, imagecolorallocatealpha($outImage, 0, 0, 0, 127));
            imagesavealpha($outImage, true);
        }

        imagecopyresampled($outImage, $inImage, 0, 0, $inLeft, $inTop, $outWidth, $outHeight, $outWidth, $outHeight);

        if ($typeCode===IMAGETYPE_GIF) {
            $result = imagegif($outImage, $outFile);
        } elseif ($typeCode===IMAGETYPE_PNG) {
            $result = imagepng($outImage, $outFile);
        } else {
            $result = imagejpeg($outImage, $outFile, 95);
        }

        return $result;
    }
