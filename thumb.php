<?php
    require_once("util.php");

	/**
	 * Image thumbnail generator
	 * Based on the work from http://sniptools.com/vault/generating-jpggifpng-thumbnails-in-php-using-imagegif-imagejpeg-imagepng
     * Based on the work from http://ria-coder.com/blog/php-thumbnail-generator
	 */
	class ThumbnailGenerator
	{
		public $sourceFile;
		public $destinationFile;
		public $width;
		public $height;
		public $format;
		public $scale;
 
		/**
		 * ThumbnailGenerator::__construct()
		 *
		 * @param mixed $sourceFile Path to source file
		 * @param mixed $destinationFile Path to destination file
		 * @param mixed $width Thumbnail file width
		 * @param mixed $height Thumbnail file height
		 * @param string $format jpeg, png or gif
		 * @param bool $scale Scale thumbnail
		 * @return
		 */
		public function __construct($sourceFile, $destinationFile, $width, $height, $format="png", $quality=9, $transparent=true, $useResample=true) {
			$this->sourceFile = $sourceFile;
			$this->destinationFile = $destinationFile;
			$this->width = $width;
			$this->height = $height;
            $this->format = $format;
            $this->outputFormat = $format;
            $this->quality = $quality;
            $this->transparent = $transparent;
            $this->useResample = $useResample;
        }

        /**
         * @param bool $crop - true=scale one dimention and crop the other
         * @return bool
         */
        public function _generate2($crop=false) {
            $fromFormat = "imagecreatefrom".$this->format;
            $sourceImage = $fromFormat($this->sourceFile);
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);

            if ($this->width===0 || $this->height===0 || $sourceWidth===0 || $sourceHeight===0) return false;

            $wRatio = $sourceWidth/$this->width;
            $hRatio = $sourceHeight/$this->height;

            if ($crop ? $wRatio<$hRatio : $wRatio>$hRatio) {
                $destWidth = $this->width;
                $destHeight = $sourceHeight/$wRatio;
                $destY = ($this->height-$destHeight)/2;
                $destX = 0;
            } else {
                $destHeight = $this->height;
                $destWidth = $sourceWidth/$hRatio;
                $destX = ($this->width-$destWidth)/2;
                $destY = 0;
            }


            $targetImage = imagecreatetruecolor($this->width,$this->height);
            // preserve transparency
            if($this->transparent && ($this->format == "gif" or $this->format == "png")){
                imagealphablending($targetImage, false);
                $colorTransparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
                imagefill($targetImage, 0, 0, $colorTransparent);
                imagesavealpha($targetImage, true);
            }

            $copyFunction = $this->useResample ? "imagecopyresampled" : "imagecopyresized";
            $copyFunction($targetImage,$sourceImage,$destX,$destY,0,0,$destWidth,
                    $destHeight,imagesx($sourceImage),imagesy($sourceImage));

            $imageFunction = "image".$this->outputFormat;
            $return = $this->format=='gif' ?
                $imageFunction($targetImage, $this->destinationFile) :
                $imageFunction($targetImage, $this->destinationFile, $this->getQuality());
            imagedestroy($targetImage);
            return $return;
        }

        public function setOutputFormat($format) { $this->outputFormat = $format; }
        public function setQuality($quality) { $this->quality = $quality; }
        public function setTransparent($transparent) { $this->transparent = $transparent; }
        public function setUseResample($useResample) { $this->useResample = $useResample; }

        public function getQuality() {
            $quality = $this->quality;
            if ($this->outputFormat==='png') {
                return $quality<0 ? 0 : $quality>9 ? 9 : $quality;
            } else {
                return $quality<0 ? 0 : $quality>100 ? 100 : $quality;
            }
        }

        /**
         * @param null $hangOver - true=hang over,  false=fit in,  null=scale down to fit within both
         * @return bool
         */
        public function _generate($hangOver=null) {
			$fromFormat = "imagecreatefrom".$this->format;
			$sourceImage = $fromFormat($this->sourceFile);
			$sourceWidth = imagesx($sourceImage);
			$sourceHeight = imagesy($sourceImage);

            if ($this->width===0 || $this->height===0 || $sourceWidth===0 || $sourceHeight===0) return false;

            $wRatio = $sourceWidth/$this->width;
            $hRatio = $sourceHeight/$this->height;

            if($hangOver!==null) {
                $ratio = ($hangOver ? $wRatio<$hRatio : $wRatio>$hRatio) ? $wRatio : $hRatio;
				$this->width = $sourceWidth / $ratio;
				$this->height = $sourceHeight / $ratio;
			}

            $targetImage = imagecreatetruecolor($this->width,$this->height);
            // preserve transparency
            if($this->transparent && ($this->format == "gif" or $this->format == "png")){
                imagealphablending($targetImage, false);
                $colorTransparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
                imagefill($targetImage, 0, 0, $colorTransparent);
                imagesavealpha($targetImage, true);
            }

            $copyFunction = $this->useResample ? "imagecopyresampled" : "imagecopyresized";
            $copyFunction($targetImage,$sourceImage,0,0,0,0,$this->width,
                $this->height,imagesx($sourceImage),imagesy($sourceImage));

			$imageFunction = "image".$this->outputFormat;
			$return = $this->format=='gif' ?
				$imageFunction($targetImage, $this->destinationFile) :
				$imageFunction($targetImage, $this->destinationFile, $this->getQuality());
			imagedestroy($targetImage);
			return $return;
		}

        /**
         *  squish to fit in both dimensions exactly
         */
        public function squish() {
            $this->_generate();
        }

        /**
         *   proportionally scale to fit within both dimensions
         */
        public function fitIn() {
            $this->_generate(false);
        }

        /**
         *   size to fit in one target dimensions and then hang over in the other
         */
        public function hangOver() {
            $this->_generate(true);
        }

        /**
         *   letter-box to the target dimensions
         */
        public function letterBox() {
            $this->_generate2(false);
        }

        /**
         *   scale to fit in one dimensions and crop to fit the other
         */
        public function crop() {
            $this->_generate2(true);
        }
    }


	//========================================


    $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : false;
    $thumb = "$WORK/thumb/$name";
    $file = "$WORK/data/$name";

    if ($name===false || !is_file($file) || !preg_match('/^(\d+\/)?[^\/]+\.(jpg|jpeg|png|gif)$/i',$name,$r)) {
	    header('Content-type: image/png');
    	readfile('error.png');
    	exit;
    } 
    
    $type = strtolower($r[2])==='jpg' ? 'jpeg' : strtolower($r[2]);
    header('Content-type: image/'.$type);
    
    if (is_file($thumb) && filemtime($thumb)>filemtime($file)) {
    	readfile($thumb);
    	exit;		    	
	}

    if (!is_dir(dirname($thumb))) {
        mkdir(dirname($thumb),0777, true);
    }

	$tng = new ThumbnailGenerator($file,$thumb,1440/4,900/4,$type,60);
	$result = $tng->letterBox();

   	readfile($thumb);
?>