<?php

namespace DigitalFeonix;

/**
 * ImageManipulation is a GD based class to do common image tasks such as
 * resizing and scaling. It also has some more features for "compositing"
 * images.
 */
class ImageManipulation
{
    protected $img;
    protected $has_imagefilter;
    protected $has_convolution;
    protected $font_file;

    protected $img_width    = 0;
    protected $img_height   = 0;

    /**
     * __construct
     *
     * @return void
     */
    function __construct()
    {
        ## TODO: add some parameter passing for construct
        ## add ability to pass in file to open?
        $this->has_imagefilter = function_exists('imagefilter');
        $this->has_convolution = function_exists('imageconvolution');
    }

    /**
     * __destruct
     *
     * @return void
     */
    function __destruct()
    {
        $this->clear_img();
    }

    /**
     * clear_img
     *
     * @return void
     */
    private function clear_img()
    {
        if (is_resource($this->img))
        {
            imagedestroy($this->img);
        }
    }

    /**
     * reset_sizes
     *
     * @return void
     */
    private function reset_sizes()
    {
        $this->img_width  = imagesx($this->img);
        $this->img_height = imagesy($this->img);
    }

    ################################################################################
    # private Functions
    ################################################################################

    /**
     * private function to determine image type by filename extension
     *
     * @param  string $file
     *
     * @return string
     */
    protected function getFileType($file)
    {
        $type = strtolower(preg_replace('/^(.*)\./i', '', $file));
        if ($type == 'jpg') { $type = 'jpeg'; }
        return $type;
    }

    /**
     * averaging function, used primarly by the pixelate method
     *
     * @param  array $arr
     *
     * @return integer
     */
    protected function avg($arr)
    {
        return round(array_sum($arr) / count($arr));
    }

    ################################################################################
    # DEBUG help
    ################################################################################

    /**
     * setProperty
     *
     * @param  string $property
     * @param  mixed $value
     *
     * @return void
     */
    public function setProperty($property, $value)
    {
        $this->$property = $value;
    }

    ################################################################################
    # Image management
    ################################################################################

    /**
     * isLoaded
     *
     * @return boolean
     */
    public function isLoaded()
    {
        return is_resource($this->img);
    }

    /**
     * create blank image with optional background color
     *
     * @param  integer $width
     * @param  integer $height
     * @param  integer $bgcolor
     *
     * @return void
     */
    public function create($width, $height, $bgcolor = 0x7F000000)
    {
        $this->clear_img();

        $this->img_width  = (int)$width;
        $this->img_height = (int)$height;

        $this->img = imagecreatetruecolor((int)$width, (int)$height);

        imagesavealpha($this->img, TRUE);
        imagefill($this->img, 0, 0, $bgcolor);
    }

    /**
     * load image file from (binary) string
     *
     * @param  string  $string
     * @param  integer $bgcolor
     *
     * @return void
     */
    public function load($string, $bgcolor = 0x7F000000)
    {
        $img = imagecreatefromstring($string);

        $w = imagesx($img);
        $h = imagesy($img);

        // always ensure that we are in true color mode
        $this->create($w, $h, $bgcolor);
        $this->copyImage($img,0,0,0,0,$w,$h,$w,$h);

        imagedestroy($img);
    }

    /**
     * open image from filename/path
     *
     * @param  string $file
     * @param  integer $bgcolor
     *
     * @return void
     */
    public function open($file, $bgcolor = 0x7F000000)
    {
        $size = getimagesize($file);

        ## TODO: if greater than about 20 mega pixels we really shouldn't handle it

        // if the image is over a couple of megapixels it likely doesn't have transparency
        if ($size[0] * $size[1] > (8*1024*1024))
        {
            ## IDEA: call some sort of microservice to resize the original to something
            ## reasonable and use the result so we can apply any background color requested

            $img = imagecreatefromstring(file_get_contents($file));
            imagealphablending($img, TRUE);
            imagesavealpha($img, TRUE);

            $this->setResource($img);
        }
        else
        {
            // TODO: check to see if file exists before creating image from it
            $this->load(file_get_contents($file), $bgcolor);
        }

        ## TODO: deal with non-existing file
    }

    ## IDEA: rename setImage() ?
    /**
     * replace current instance with a COPY of an external image
     *
     * @param  resource $img
     *
     * @return void
     */
    public function importImage($img)
    {
        $w = imagesx($img);
        $h = imagesy($img);

        // always ensure that we are in true color mode
        $this->create($w, $h);
        $this->copyImage($img,0,0,0,0,$w,$h,$w,$h);
    }

    ## IDEA: rename getImage() ?
    /**
     * send an image COPY out for use in another instance
     *
     * @return resource
     */
    public function exportImage()
    {
        ## XXX: could allow manipulation outside of the class, in case we want refImage()
        ## return $this->img;

        $w = $this->img_width;
        $h = $this->img_height;

        $img = imagecreatetruecolor($w, $h); // will generate a black image

        imagefill($img, 0, 0, 0x7F000000); // we want to keep any alpha
        imagecopyresampled($img, $this->img, 0, 0, 0, 0, $w, $h, $w, $h);

        return $img;
    }

    /**
     * set local image resource to outside image resource
     *
     * @param  resource $img
     *
     * @return void
     */
    public function setResource($img)
    {
        $this->clear_img();
        $this->img = $img;
        $this->reset_sizes();
    }

    /**
     * pass image reference out for direct manipulation elsewhere
     *
     * @return resource
     */
    public function getResource()
    {
        return $this->img;
    }

    /**
     * save to filesystem
     *
     * @param  string $filename
     * @param  string $type
     * @param  mixed $quality
     *
     * @return void
     */
    public function save($filename, $type = 'jpeg', $quality = 90)
    {
        $out = 'image' . $type;
        $out($this->img, $filename, $quality);
    }

    /**
     * output to browser
     *
     * @param  string $type
     * @param  mixed $quality
     *
     * @return void
     */
    public function output($type = 'jpeg', $quality = 90)
    {
        header('Content-type: image/'.$type);
        $out = 'image' . $type;
        $out($this->img, NULL, $quality);
    }

    /**
     * copy outside image onto local image
     *
     * @param  resource $img external image resource
     * @param  integer $dx  destination X
     * @param  integer $dy  destination Y
     * @param  integer $sx  source X
     * @param  integer $sy  source Y
     * @param  integer $dw  destination WIDTH
     * @param  integer $dh  destination HEIGHT
     * @param  integer $sw  source WIDTH
     * @param  integer $sh  source HEIGHT
     *
     * @return void
     */
    public function copyImage($img, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh)
    {
        imagecopyresampled($this->img, $img, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh);
    }

    /**
     * resize local image to the dimension, does not preserve image ratio
     *
     * @param  integer $w
     * @param  integer $h
     *
     * @return void
     */
    public function resize($w, $h)
    {
        $img = imagecreatetruecolor($w, $h);

        imagealphablending($img, TRUE);
        imagesavealpha($img, TRUE);
        imagefill($img, 0, 0, 0x7F000000);
        imagecopyresampled($img, $this->img, 0, 0, 0, 0, $w, $h, $this->img_width, $this->img_height);

        $this->setResource($img);
    }

    /**
     * fit local image inside of new size, keeping image ratio
     *
     * @param  integer $hdim
     * @param  integer $vdim
     * @param  integer $bgcolor
     *
     * @return void
     */
    public function fitImage($hdim, $vdim, $bgcolor = 0xFFFFFF)
    {
        $size = array($this->getWidth(), $this->getHeight());

        if (($size[0] / $size[1]) < ($hdim / $vdim))
        {
            $width = floor(($vdim * $size[0])/$size[1]); // width
            $height = $vdim; // height
        }
        else
        {
            $width = $hdim; // width
            $height = floor(($hdim * $size[1])/$size[0]); // height
        }

        // offsets to center
        $left   = ($hdim - $width)/2;
        $top    = ($vdim - $height)/2;

        $img = imagecreatetruecolor($hdim, $vdim);

        imagealphablending($img, TRUE);
        imagesavealpha($img, TRUE);
        imagefill($img, 0, 0, $bgcolor);
        imagecopyresampled($img, $this->img, $left, $top, 0, 0, $width, $height, $this->img_width, $this->img_height);

        $this->setResource($img);
    }

    /**
     * resize local image to fill the dimension, keeping image ratio (overflow hidden)
     *
     * @param  integer $hdim
     * @param  integer $vdim
     *
     * @return void
     */
    public function fillImage($hdim, $vdim)
    {
        $size = array($this->img_width, $this->img_height);

        if (($size[0] / $size[1]) < ($hdim / $vdim))
        {
            // the image is taller than the target
            $width  = $hdim; // width
            $height = floor(($hdim * $size[1])/$size[0]); // height
        }
        else
        {
            // the image is wider than the target
            $width  = floor(($vdim * $size[0])/$size[1]); // width
            $height = $vdim; // height
        }

        // offsets to center
        $left   = ($hdim - $width)/2;
        $top    = ($vdim - $height)/2;

        $img = imagecreatetruecolor($hdim, $vdim);

        imagealphablending($img, TRUE);
        imagesavealpha($img, TRUE);
        imagecopyresampled($img, $this->img, $left, $top, 0, 0, $width, $height, $this->img_width, $this->img_height);

        $this->setResource($img);
    }

    /**
     * scale local image to fill the dimensions, keeping image ratio (overflow visible)
     *
     * @param  integer $hdim
     * @param  integer $vdim
     * @param  integer $bgcolor
     *
     * @return void
     */
    public function scaleImage($hdim, $vdim, $bgcolor = 0xFFFFFF)
    {
        $size = array($this->img_width, $this->img_height);

        if (($size[0] / $size[1]) < ($hdim / $vdim))
        {
            // the image is taller than the target
            $width  = $hdim; // width
            $height = floor(($hdim * $size[1])/$size[0]); // height
        }
        else
        {
            // the image is wider than the target
            $width  = floor(($vdim * $size[0])/$size[1]); // width
            $height = $vdim; // height
        }

        $img = imagecreatetruecolor($width, $height);

        imagealphablending($img, TRUE);
        imagesavealpha($img, TRUE);
        imagefill($img, 0, 0, $bgcolor);
        imagecopyresampled($img, $this->img, 0, 0, 0, 0, $width, $height, $this->img_width, $this->img_height);

        $this->setResource($img);
    }

    ################################################################################
    # Image Info
    ################################################################################

    /**
     * getWidth
     *
     * @return integer
     */
    public function getWidth()
    {
        if (($this->img_width == 0) && is_resource($this->img))
        {
            $this->img_width = imagesx($this->img);
        }

        return $this->img_width;
    }

    /**
     * getHeight
     *
     * @return integer
     */
    public function getHeight()
    {
        if (($this->img_height == 0) && is_resource($this->img))
        {
            $this->img_height = imagesy($this->img);
        }

        return $this->img_height;
    }

    /**
     * getFont
     *
     * @return string
     */
    public function getFont()
    {
        return $this->font_file;
    }

    ################################################################################
    # Basic Text Manipulation
    ################################################################################

    /**
     * setFont
     *
     * @param  string $font_file
     *
     * @return void
     */
    public function setFont($font_file)
    {
        if (file_exists($font_file))
        {
            $this->font_file = $font_file;
        }
        else
        {
            ## TODO: add better error reporting
            #error_log('font not set');
        }
    }

    /**
     * addText
     *
     * @param  string $text
     * @param  integer $x
     * @param  integer $y
     * @param  float $angle
     * @param  float $size
     * @param  integer $color
     *
     * @return void
     */
    public function addText($text, $x, $y, $angle = 0, $size = 12, $color = 0x000000)
    {
        if (is_null($this->font_file))
        {
            ## TODO: add better error reporting
            #error_log('font not loaded');
            return FALSE;
        }

        imagettftext(
            $this->img,
            $size,
            $angle,
            $x,
            $y,
            $color,
            $this->font_file,
            $text
        );
    }

    /**
     * getTextBox
     *
     * @param  string $string
     * @param  float $font_size
     * @param  float $angle
     *
     * @return array
     */
    public function getTextBox($string, $font_size = 12, $angle = 0)
    {
        $tbox = imagettfbbox($font_size, $angle, $this->font_file, $string);

        return $tbox;

        // transform into useful data
        #return array('width' => abs($tbox[4] - $tbox[0]), 'height' => abs($tbox[5] - $tbox[1]));
    }

    ### NOTE: should this go into ImageTextManipulation?
    /**
     * fitTextHeight
     *
     * @param  integer $height
     *
     * @return array
     */
    public function fitTextHeight($height)
    {
        $n = $height;

        do {
            // Can we come up with a better text to give largest range between ascenders and descenders?
            $b = $this->getTextBox('The quick brown fox jumps over the lazy dog', --$n);
            $t = $b[1] - $b[7];
        } while ($t > $height);

        return array('font-size' => $n, 'baseline-offset' => $b[1]);
    }

    ################################################################################
    # basic draw functions
    ################################################################################

    /**
     * drawLine
     *
     * @param  integer $x1
     * @param  integer $y1
     * @param  integer $x2
     * @param  integer $y2
     * @param  integer $color
     *
     * @return boolean
     */
    public function drawLine($x1, $y1, $x2, $y2, $color = 0x000000)
    {
        return imageline($this->img, $x1, $y1, $x2, $y2, $color);
    }

    /**
     * drawDashedLine
     *
     * @param  integer $x1
     * @param  integer $y1
     * @param  integer $x2
     * @param  integer $y2
     * @param  integer $color
     *
     * @return boolean
     */
    public function drawDashedLine($x1, $y1, $x2, $y2, $color = 0x000000)
    {
        imagesetstyle($this->img, array($color, $color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT));
        return imageline($this->img, $x1, $y1, $x2, $y2, IMG_COLOR_STYLED);
    }

    /**
     * drawRect
     *
     * @param  integer $x1
     * @param  integer $y1
     * @param  integer $x2
     * @param  integer $y2
     * @param  integer $color
     *
     * @return boolean
     */
    public function drawRect($x1, $y1, $x2, $y2, $color = 0x000000)
    {
        return imagefilledrectangle($this->img, $x1, $y1, $x2, $y2, $color);
    }

    /**
     * drawPoly
     *
     * @param  array $points
     * @param  integer $num_points
     * @param  integer $color
     *
     * @return boolean
     */
    public function drawPoly($points, $num_points, $color)
    {
        return imagefilledpolygon($this->img, $points, $num_points, $color);
    }

    ################################################################################
    # Filters and image manips
    ################################################################################

    /**
     * gd_to_rgba
     *
     * @param  integer $gd_color
     *
     * @return array
     */
    private function gd_to_rgba($gd_color)
    {
        $a = ($gd_color >> 24) & 0xFF;
        $r = ($gd_color >> 16) & 0xFF;
        $g = ($gd_color >> 8) & 0xFF;
        $b = $gd_color & 0xFF;

        return array('red' => $r, 'green' => $g, 'blue' => $b, 'alpha' => $a);
    }

    /**
     * fill
     *
     * @param  integer $color
     *
     * @return void
     */
    public function fill($color)
    {
        imagefill($this->img, 0, 0, $color);
    }

    /**
     * convertToBW
     *
     * @return void
     */
    public function convertToBW()
    {
        if ($this->has_imagefilter)
        {
            // image filter makes this dirt simple
            imagefilter($this->img, IMG_FILTER_GRAYSCALE);
        }
        else
        {
            // get width/height once
            $w = imagesx($this->img);
            $h = imagesy($this->img);

            // loop through the pixels
            for ($x = 0; $x < $w; $x++)
            {
                for ($y = 0; $y < $h; $y++)
                {
                    // get the color components at x,y
                    $color = imagecolorat($this->img, $x, $y);

                    $a = ($color >> 24) & 0xFF;
                    $r = ($color >> 16) & 0xFF;
                    $g = ($color >> 8) & 0xFF;
                    $b =  $color & 0xFF;

                    // convert to B/W
                    $k = (($r * 0.299) + ($g * 0.587) + ($b * 0.114)) & 0xFF;

                    // combine into a single value to be put back into the image
                    $color = (($a << 24) + ($k << 16) + ($k << 8) + $k);

                    // now that we have a valid value, set the pixel to that color.
                    imagesetpixel($this->img, $x, $y, $color);
                }
            }
        }
    }

    /**
     * sepia
     * 
     * based on function from http://hanswestman.se/web-development/some-image-filters-for-php-gd/
     *
     * @return void
     */
    public function sepia()
    {
        if ($this->has_imagefilter)
        {
            // image filter makes this dirt simple
            imagefilter($this->img, IMG_FILTER_GRAYSCALE);
            imagefilter($this->img, IMG_FILTER_COLORIZE, 36, -10, -42);
        }
        else
        {
            $w = imagesx($this->img);
            $h = imagesy($this->img);

            for($x = 0; $x < $w; $x++)
            {
                for($y = 0; $y < $h; $y++)
                {
                    $color = imagecolorat($this->img, $x, $y);

                    $a = ($color >> 24) & 0xFF;
                    $r = ($color >> 16) & 0xFF;
                    $g = ($color >> 8) & 0xFF;
                    $b =  $color & 0xFF;

                    // convert to B/W
                    $k = (($r * 0.299) + ($g * 0.587) + ($b * 0.114)) & 0xFF;

                    $r = ($k + 36);
                    $g = ($k - 10);
                    $b = ($k - 42);

                    if($r<0||$r>0xFF){$r=($r<0)?0:0xFF;}
                    if($g<0||$g>0xFF){$g=($g<0)?0:0xFF;}
                    if($b<0||$b>0xFF){$b=($b<0)?0:0xFF;}
                    if($a<0||$a>0x7F){$a=($a<0)?0:0x7F;}

                    // combine into a single value to be put back into the image
                    $color = (($a << 24) + ($r << 16) + ($g << 8) + $b);

                    // now that we have a valid value, set the pixel to that color.
                    imagesetpixel($this->img, $x, $y, $color);
                }
            }
        }
    }

    /**
     * colorize
     *
     * @param  integer $red
     * @param  integer $green
     * @param  integer $blue
     * @param  integer $alpha
     *
     * @return void
     */
    public function colorize($red, $green, $blue, $alpha = 0)
    {
        if ($this->has_imagefilter)
        {
            // image filter makes this dirt simple
            imagefilter($this->img, IMG_FILTER_COLORIZE, $red, $green, $blue, $alpha);
        }
        else
        {
            // get width/height once
            $w = imagesx($this->img);
            $h = imagesy($this->img);

            // loop through the pixels
            for ($x = 0; $x < $w; $x++)
            {
                for ($y = 0; $y < $h; $y++)
                {
                    $color = imagecolorat($this->img, $x, $y);

                    $a = ($color >> 24) & 0xFF;
                    $r = ($color >> 16) & 0xFF;
                    $g = ($color >> 8) & 0xFF;
                    $b =  $color & 0xFF;

                    // add/remove color to/from image, clamping to 0-255 range
                    $a = ($a + $alpha);
                    $r = ($r + $red);
                    $g = ($g + $green);
                    $b = ($b + $blue);

                    if($r<0||$r>0xFF){$r=($r<0)?0:0xFF;}
                    if($g<0||$g>0xFF){$g=($g<0)?0:0xFF;}
                    if($b<0||$b>0xFF){$b=($b<0)?0:0xFF;}
                    if($a<0||$a>0x7F){$a=($a<0)?0:0x7F;}

                    // combine into a single value to be put back into the image
                    $color = (($a << 24) + ($r << 16) + ($g << 8) + $b);

                    // now that we have a valid value, set the pixel to that color.
                    imagesetpixel($this->img, $x, $y, $color);
                }
            }
        }
    }

    /**
     * grades the image
     *
     * @param  integer $grading_color
     *
     * @return void
     */
    public function grade($grading_color)
    {
        // get width/height once
        $w = imagesx($this->img);
        $h = imagesy($this->img);

        $grade = $this->gd_to_rgba($grading_color);

        // loop through the pixels
        for ($x = 0; $x < $w; $x++)
        {
            for ($y = 0; $y < $h; $y++)
            {
                // get the color components at x,y
                $color = imagecolorat($this->img, $x, $y);

                $a = ($color >> 24) & 0xFF;
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b =  $color & 0xFF;

                $r = (($r * $grade['red']) / 255) & 0xFF;
                $g = (($g * $grade['green']) / 255) & 0xFF;
                $b = (($b * $grade['blue']) / 255) & 0xFF;

                // combine into a single value to be put back into the image
                $color = (($a << 24) + ($r << 16) + ($g << 8) + $b);

                // now that we have a valid value, set the pixel to that color.
                imagesetpixel($this->img, $x, $y, $color);
            }
        }
    }


    /**
     * tints the image, replacing the whites with a color
     *
     * @param  integer $tinting_color
     *
     * @return void
     */
    public function tinting($tinting_color)
    {
        // get width/height once
        $w = imagesx($this->img);
        $h = imagesy($this->img);

        $tint = $this->gd_to_rgba($tinting_color);

        // loop through the pixels
        for ($x = 0; $x < $w; $x++)
        {
            for ($y = 0; $y < $h; $y++)
            {
                // get the color components at x,y
                $color = imagecolorat($this->img, $x, $y);

                $a = ($color >> 24) & 0xFF;
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b =  $color & 0xFF;

                // convert to B/W
                $k = (($r * 0.299) + ($g * 0.587) + ($b * 0.114)) & 0xFF;

                $i = $k / 255;

                $r = ($i * $tint['red']);
                $g = ($i * $tint['green']);
                $b = ($i * $tint['blue']);

                // combine into a single value to be put back into the image
                $color = (($a << 24) + ($r << 16) + ($g << 8) + $b);

                // now that we have a valid value, set the pixel to that color.
                imagesetpixel($this->img, $x, $y, $color);
            }
        }
    }

    /**
     * tones the image, replacing the blacks with a color
     *
     * @param  mixed $toning_color
     *
     * @return void
     */
    public function toning($toning_color)
    {
        // get width/height once
        $w = imagesx($this->img);
        $h = imagesy($this->img);

        $tone = $this->gd_to_rgba($toning_color);

        // loop through the pixels
        for ($x = 0; $x < $w; $x++)
        {
            for ($y = 0; $y < $h; $y++)
            {
                // get the color components at x,y
                $color = imagecolorat($this->img, $x, $y);

                $a = ($color >> 24) & 0xFF;
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b =  $color & 0xFF;

                // convert to B/W
                $luma = (($r * 0.299) + ($g * 0.587) + ($b * 0.114)) & 0xFF;

                $k = (~$luma & 0xFF) / 255;

                $r = ($luma + ($k * $tone['red']));
                $g = ($luma + ($k * $tone['green']));
                $b = ($luma + ($k * $tone['blue']));

                // combine into a single value to be put back into the image
                $color = (($a << 24) + ($r << 16) + ($g << 8) + $b);

                // now that we have a valid value, set the pixel to that color.
                imagesetpixel($this->img, $x, $y, $color);
            }
        }
    }

    /**
     * applies a find edges type filter to the image
     *
     * @return void
     */
    public function findEdges()
    {
        if ($this->has_convolution)
        {
            imageconvolution($this->img, array(array(-1,-1,-1),array(-1,8,-1),array(-1,-1,-1)), 1, 0); // find edges
        }
        elseif ($this->has_imagefilter)
        {
            imagefilter($this->img, IMG_FILTER_EDGEDETECT); // find edges
        }
        else
        {
            ## shouldn't have to use this, an imageconvolution function is available in lib.image-functions.php
            // get width/height once
            $w = imagesx($this->img);
            $h = imagesy($this->img);

            $timg = imagecreatetruecolor((int)$w, (int)$h);

            // loop through the pixels, except the edge ones
            for ($x = 1; $x < $w - 1; $x++)
            {
                for ($y = 1; $y < $h - 1; $y++)
                {
                    // get the color components surrounding x,y
                    $px = array(
                        'tl' => imagecolorat($this->img, $x-1, $y-1),
                        'tc' => imagecolorat($this->img, $x,   $y-1),
                        'tr' => imagecolorat($this->img, $x+1, $y-1),
                        'cl' => imagecolorat($this->img, $x-1, $y),
                        'cc' => imagecolorat($this->img, $x,   $y),
                        'cr' => imagecolorat($this->img, $x+1, $y),
                        'bl' => imagecolorat($this->img, $x-1, $y+1),
                        'bc' => imagecolorat($this->img, $x,   $y+1),
                        'br' => imagecolorat($this->img, $x+1, $y+1)
                    );

                    // convert each to b/w luma value
                    foreach ($px as $k => $color)
                    {
                        $r = ($color >> 16) & 0xFF;
                        $g = ($color >> 8) & 0xFF;
                        $b =  $color & 0xFF;

                        // convert to B/W
                        $px[$k] = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
                    }

                    ## different Matrix(es)?
                    /*
                    // appliying convolution mask
                    $conv_x = ($px['tr']+($px['cr']*2)+$px['br'])-($px['tl']+($px['cl']*2)+$px['bl']);
                    $conv_y = ($px['bl']+($px['bc']*2)+$px['br'])-($px['tl']+($px['tc']*2)+$px['tr']);

                    // calculating the distance
                    $gray = sqrt($conv_x * $conv_x + $conv_y + $conv_y);
                    */

                    $gray = ($px['cc']*8)-($px['tl']+$px['tc']+$px['tr']+$px['cl']+$px['cr']+$px['bl']+$px['bc']+$px['br']);

                    ## CLAMP TO 0-255
                    $gray = $gray & 0xFF;

                    // combine into a single value to be put back into the image
                    $color = (($gray << 16) + ($gray << 8) + $gray);

                    // now that we have a valid value, set the pixel to that color.
                    imagesetpixel($timg, $x, $y, $color);
                }
            }

            $this->copyImage($timg,0,0,0,0,$w,$h,$w,$h);

            imagedestroy($timg);
        }
    }

    /**
     * invert the image colors
     *
     * @return void
     */
    public function invert()
    {
        if ($this->has_imagefilter)
        {
            imagefilter($this->img, IMG_FILTER_NEGATE);
        }
        else
        {
            // get width/height once
            $w = imagesx($this->img);
            $h = imagesy($this->img);

            // loop through the pixels
            for ($x = 0; $x < $w; $x++)
            {
                for ($y = 0; $y < $h; $y++)
                {
                    // get the color components at x,y
                    $color = imagecolorat($this->img, $x, $y);

                    $a = ($color >> 24) & 0xFF;
                    $r = ($color >> 16) & 0xFF;
                    $g = ($color >> 8) & 0xFF;
                    $b =  $color & 0xFF;

                    $r = 255 - $r;
                    $g = 255 - $g;
                    $b = 255 - $b;

                    // combine into a single value to be put back into the image
                    $color = (($a << 24) + ($r << 16) + ($g << 8) + $b);

                    // now that we have a valid value, set the pixel to that color.
                    imagesetpixel($this->img, $x, $y, $color);
                }
            }
        }
    }

    /**
     * adjusts the contrast of the image
     * 
     * negative increases contrast, postive decreases it (100 is complete gray)
     *
     * @param  integer $val
     *
     * @return void
     */
    public function adjustContrast($val = 0)
    {
        if ($this->has_imagefilter)
        {
            imagefilter($this->img, IMG_FILTER_CONTRAST, $val);
        }
        else
        {
            $val *= -1; // invert so forumulas work as expected

            // http://thecryptmag.com/Online/56/imgproc_5.html
            $f = (259 * ($val + 100)) / (255 * (100 - $val));
            ## this will work if staying within the -100 to 100 range that the filter is supposed to support
            ## but breaks outside that

            // get width/height once
            $w = imagesx($this->img);
            $h = imagesy($this->img);

            $color_arr = array();

            // loop through the pixels
            for ($x = 0; $x < $w; $x++)
            {
                for ($y = 0; $y < $h; $y++)
                {
                    // get the color components at x,y
                    $color = imagecolorat($this->img, $x, $y);

                    $color_arr['alpha'] = ($color >> 24) & 0xFF;
                    $color_arr['red']   = ($color >> 16) & 0xFF;
                    $color_arr['green'] = ($color >> 8) & 0xFF;
                    $color_arr['blue']  =  $color & 0xFF;

                    foreach ($color_arr as $color_key => $value)
                    {
                        if ($color_key == 'alpha') continue;

                        // http://thecryptmag.com/Online/56/imgproc_5.html
                        $v = (($f * ($value - 128)) + 128);

                        // dirty clamp
                        if ($v<0||$v>0xFF){$v=($v<0)?0:0xFF;}

                        // back in the array as int
                        $color_arr[$color_key] = $v & 0xFF;
                    }

                    // combine into a single value to be put back into the image
                    $color = (($color_arr['alpha'] << 24) + ($color_arr['red'] << 16) + ($color_arr['green'] << 8) + $color_arr['blue']);

                    // now that we have a valid value, set the pixel to that color.
                    imagesetpixel($this->img, $x, $y, $color);
                }
            }
        }
    }

    ## BRIGHTNESS ?

    ## RESIZING

    ## ROTATION

    /**
     * Uses mean removal to achieve a "sketchy" effect
     *
     * @return void
     */
    public function meanRemoval()
    {
        if ($this->has_imagefilter)
        {
            imagefilter($this->img, IMG_FILTER_MEAN_REMOVAL);
        }

        ## need no filter version
    }

    /**
     * pixelate the image
     *
     * @param  integer $block_size
     * @param  boolean $advanced
     *
     * @return void
     */
    public function pixelate($block_size = 4, $advanced = FALSE)
    {
        if ($this->has_imagefilter)
        {
            // default = first pixel is value for block
            // advanced = average across pixels
            imagefilter($this->img, IMG_FILTER_PIXELATE, $block_size, (bool)$advanced);
        }
        else
        {
            // get width/height once
            $w = imagesx($this->img);
            $h = imagesy($this->img);
            $s = (bool)$advanced ? $block_size : 1;

            // loop through the pixels
            for ($x = 0; $x < $w; $x += $block_size)
            {
                for ($y = 0; $y < $h; $y += $block_size)
                {
                    $r = array();
                    $g = array();
                    $b = array();
                    $a = array();

                    for ($x0 = 0; $x0 < $s; $x0++)
                    {
                        for ($y0 = 0; $y0 < $s; $y0++)
                        {
                            if (($x+$x0 < $w) && ($y+$y0 < $h))
                            {
                                // get the color at x,y
                                $color = imagecolorat($this->img, $x+$x0, $y+$y0);

                                $a[] = ($color >> 24) & 0xFF;
                                $r[] = ($color >> 16) & 0xFF;
                                $g[] = ($color >> 8) & 0xFF;
                                $b[] =  $color & 0xFF;
                            }
                        }
                    }

                    // combine into a single value to be put back into the image
                    $color = ($this->avg($a)<< 24) + ($this->avg($r)<< 16) + ($this->avg($g) << 8) + ($this->avg($b));

                    // now that we have a valid value, set the pixels to that color.
                    imagefilledrectangle($this->img, $x, $y, ($x + $block_size - 1), ($y + $block_size - 1), $color);
                }
            }
        }
    }

    /**
     * overlay an external image onto this image
     *
     * @param  resource $image
     * @param  integer $destX
     * @param  integer $destY
     *
     * @return void
     */
    public function applyOverlay($image, $destX, $destY)
    {
        // get width/height once
        $w = imagesx($this->img);
        $h = imagesy($this->img);
        $dw = imagesx($image);
        $dh = imagesy($image);

        for ($x = 0; $x < $dw; $x++)
        {
            for ($y = 0; $y < $dh; $y++)
            {
                ## CHECK TO SEE IF THE LOCATION IS WITHIN THE TARGET IMAGE
                if ($x + $destX < 0) { continue; }
                if ($y + $destY < 0) { continue; }
                if ($x + $destX >= $w) { continue; }
                if ($y + $destY >= $h) { continue; }

                // First get the colors for the base and top pixels.
                $color = imagecolorat($this->img, $x + $destX, $y + $destY);

                $baseColor = array(
                  'alpha' => ($color >> 24) & 0x7F,
                  'red'   => ($color >> 16) & 0xFF,
                  'green' => ($color >> 8) & 0xFF,
                  'blue'  => $color & 0xFF
                );

                $color = imagecolorat($image, $x, $y);

                $topColor = array(
                  'alpha' => ($color >> 24) & 0x7F,
                  'red'   => ($color >> 16) & 0xFF,
                  'green' => ($color >> 8) & 0xFF,
                  'blue'  => $color & 0xFF
                );

                // Now perform the multiply algorithm.
                $destColor = array(
                    'red'   => min(intval($baseColor['red']   * ($topColor['red']   / 0xFF)), 0xFF),
                    'green' => min(intval($baseColor['green'] * ($topColor['green'] / 0xFF)), 0xFF),
                    'blue'  => min(intval($baseColor['blue']  * ($topColor['blue']  / 0xFF)), 0xFF),
                    'alpha' => min(($baseColor['alpha'] + $topColor['alpha']), 0x7F)
                );

                // Now set the destination pixel.
                #$newColor = imagecolorallocatealpha($this->img, $destColor['red'], $destColor['green'], $destColor['blue'], $destColor['alpha']);
                $newColor = ($destColor['alpha'] << 24) + ($destColor['red'] << 16) + ($destColor['green'] << 8) + ($destColor['blue']);

                // this really is drawing the color on top of existing pixel
                imagesetpixel($this->img, $x + $destX, $y + $destY, $newColor);
            }
        }
    }

    /**
     * overlay an external image onto this image with a bolder result
     *
     * @param  resource $image
     * @param  integer $destX
     * @param  integer $destY
     *
     * @return void
     */
    public function applyDoubleOverlay($image, $destX, $destY)
    {
        // get width/height once
        $w = imagesx($this->img);
        $h = imagesy($this->img);
        $dw = imagesx($image);
        $dh = imagesy($image);

        for ($x = 0; $x < $dw; $x++)
        {
            for ($y = 0; $y < $dh; $y++)
            {
                ## CHECK TO SEE IF THE LOCATION IS WITHIN THE TARGET IMAGE
                if ($x + $destX < 0) { continue; }
                if ($y + $destY < 0) { continue; }
                if ($x + $destX >= $w) { continue; }
                if ($y + $destY >= $h) { continue; }

                // First get the colors for the base and top pixels.
                $color = imagecolorat($this->img, $x + $destX, $y + $destY);

                $baseColor = array(
                  'alpha' => ($color >> 24) & 0x7F,
                  'red'   => ($color >> 16) & 0xFF,
                  'green' => ($color >> 8) & 0xFF,
                  'blue'  => $color & 0xFF
                );

                $color = imagecolorat($image, $x, $y);

                $topColor = array(
                  'alpha' => ($color >> 24) & 0x7F,
                  'red'   => ($color >> 16) & 0xFF,
                  'green' => ($color >> 8) & 0xFF,
                  'blue'  => $color & 0xFF
                );

                // Now perform the multiply algorithm.
                $destColor = array(
                    'red'   => min(intval($baseColor['red']   * pow(($topColor['red']   / 0xFF),2)), 0xFF),
                    'green' => min(intval($baseColor['green'] * pow(($topColor['green'] / 0xFF),2)), 0xFF),
                    'blue'  => min(intval($baseColor['blue']  * pow(($topColor['blue']  / 0xFF),2)), 0xFF),
                    'alpha' => min(($baseColor['alpha'] + $topColor['alpha']), 0x7F)
                );

                // Now set the destination pixel.
                #$newColor = imagecolorallocatealpha($this->img, $destColor['red'], $destColor['green'], $destColor['blue'], $destColor['alpha']);
                $newColor = ($destColor['alpha'] << 24) + ($destColor['red'] << 16) + ($destColor['green'] << 8) + ($destColor['blue']);

                // this really is drawing the color on top of existing pixel
                imagesetpixel($this->img, $x + $destX, $y + $destY, $newColor);
            }
        }
    }

    ## BLUR ?

    ################################################################################
    # alpha/masking functions
    ################################################################################

    /**
     * uses an images alpha channel for transparency
     *
     * @param  resource $mask
     *
     * @return void
     */
    public function applyAlpha($mask)
    {
        $orig = $this->exportImage();

        $this->create($this->img_width, $this->img_height);

        $mask_width  = imagesx($mask);
        $mask_height = imagesy($mask);

        // Resize mask as necessary
        if (($this->img_width != $mask_width) || ($this->img_height != $mask_height))
        {
            $temp_pic = imagecreatetruecolor($this->img_width, $this->img_height);
            imagealphablending($temp_pic, FALSE);
            imagecopyresampled($temp_pic, $mask, 0, 0, 0, 0, $this->img_width, $this->img_height, $mask_width, $mask_height);
            imagedestroy($mask);
            $mask = $temp_pic;
        }

        // Perform pixel-based alpha map application
        for ($x = 0; $x < $this->img_width; $x++)
        {
            for ($y = 0; $y < $this->img_height; $y++)
            {
                $alpha = imagecolorsforindex($mask, imagecolorat($mask, $x, $y));
                $color = imagecolorsforindex($orig, imagecolorat($orig, $x, $y));

                imagesetpixel(
                    $this->img, $x, $y,
                    imagecolorallocatealpha(
                        $this->img, $color['red'], $color['green'], $color['blue'], max($alpha['alpha'], $color['alpha'])
                    )
                );
            }
        }

        imagedestroy($orig);
    }

    /**
     * applies a mask using a grayscale image with white being opaque
     *
     * @param  mixed $mask
     *
     * @return void
     */
    public function applyMask($mask)
    {
        $orig = $this->exportImage();

        $this->create($this->img_width, $this->img_height);

        $mask_width  = imagesx($mask);
        $mask_height = imagesy($mask);

        // Resize mask as necessary
        if (($this->img_width != $mask_width) || ($this->img_height != $mask_height))
        {
            $temp_pic = imagecreatetruecolor($this->img_width, $this->img_height);
            imagecopyresampled($temp_pic, $mask, 0, 0, 0, 0, $this->img_width, $this->img_height, $mask_width, $mask_height);
            imagedestroy($mask);
            $mask = $temp_pic;
        }

        // Perform pixel-based alpha map application
        for ($x = 0; $x < $this->img_width; $x++)
        {
            for ($y = 0; $y < $this->img_height; $y++)
            {
                $alpha = imagecolorsforindex($mask, imagecolorat($mask, $x, $y));
                $color = imagecolorsforindex($orig, imagecolorat($orig, $x, $y));

                // get accurate grayscale value of the masking image (color or B/W)
                $gray  = (($alpha['red'] * 0.299) + ($alpha['green'] * 0.587) + ($alpha['blue'] * 0.114)) & 0xFF;

                // higher of the calculated alpha or the source alpha channel (white opaque, black transparent)
                $new_alpha = max((127 - floor($gray / 2)), $color['alpha']);

                // draw pixel on image
                imagesetpixel(
                    $this->img, $x, $y,
                    imagecolorallocatealpha(
                        $this->img, $color['red'], $color['green'], $color['blue'], $new_alpha
                    )
                );
            }
        }

        imagedestroy($orig);
    }

    ################################################################################
    # 3rd party manip code
    # formated and integrated into the class
    ################################################################################

    /*

    Source: http://vikjavev.no/computing/ump.php

    New:
    - In version 2.1 (February 26 2007) Tom Bishop has done some important speed enhancements.
    - From version 2 (July 17 2006) the script uses the imageconvolution function in PHP
    version >= 5.1, which improves the performance considerably.


    Unsharp masking is a traditional darkroom technique that has proven very suitable for
    digital imaging. The principle of unsharp masking is to create a blurred copy of the image
    and compare it to the underlying original. The difference in colour values
    between the two images is greatest for the pixels near sharp edges. When this
    difference is subtracted from the original image, the edges will be
    accentuated.

    The Amount parameter simply says how much of the effect you want. 100 is 'normal'.
    Radius is the radius of the blurring circle of the mask. 'Threshold' is the least
    difference in colour values that is allowed between the original and the mask. In practice
    this means that low-contrast areas of the picture are left unrendered whereas edges
    are treated normally. This is good for pictures of e.g. skin or blue skies.

    Any suggenstions for improvement of the algorithm, expecially regarding the speed
    and the roundoff errors in the Gaussian blur process, are welcome.

    */

    /**
     * applies an unsharp mask to the image
     *
     * @param  float $amount
     * @param  float $radius
     * @param  float $threshold
     *
     * @return void
     */
    public function unsharpMask($amount, $radius, $threshold)
    {
        ////////////////////////////////////////////////////////////////////////////////////////////////
        ////
        ////                  Unsharp Mask for PHP - version 2.1.1
        ////
        ////    Unsharp mask algorithm by Torstein Hønsi 2003-07.
        ////             thoensi_at_netcom_dot_no.
        ////               Please leave this notice.
        ////
        ///////////////////////////////////////////////////////////////////////////////////////////////

        // Attempt to calibrate the parameters to Photoshop:
        if ($amount > 500)
        {
            $amount = 500;
        }

        $amount = $amount * 0.016;

        if ($radius > 50)
        {
            $radius = 50;
        }

        $radius = $radius * 2;

        if ($threshold > 255)
        {
            $threshold = 255;
        }

        $radius = abs(round($radius));     // Only integers make sense.

        if ($radius == 0)
        {
            return;
        }

        $w = imagesx($this->img);
        $h = imagesy($this->img);
        $imgCanvas = imagecreatetruecolor($w, $h);
        $imgBlur = imagecreatetruecolor($w, $h);

        // Gaussian blur matrix:
        //
        //    1    2    1
        //    2    4    2
        //    1    2    1
        //
        //////////////////////////////////////////////////

        // PHP >= 5.1
        if ($this->has_convolution)
        {
            $matrix = array(
                array( 1, 2, 1 ),
                array( 2, 4, 2 ),
                array( 1, 2, 1 )
            );
            imagecopy ($imgBlur, $this->img, 0, 0, 0, 0, $w, $h);
            imageconvolution($imgBlur, $matrix, 16, 0);
        }
        else
        {
            // Move copies of the image around one pixel at the time and merge them with weight
            // according to the matrix. The same matrix is simply repeated for higher radii.
            for ($i = 0; $i < $radius; $i++)
            {
                imagecopy($imgBlur, $this->img, 0, 0, 1, 0, $w - 1, $h); // left
                imagecopymerge($imgBlur, $this->img, 1, 0, 0, 0, $w, $h, 50); // right
                imagecopymerge($imgBlur, $this->img, 0, 0, 0, 0, $w, $h, 50); // center
                imagecopy($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);
                imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up
                imagecopymerge($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down
            }
        }

        if ($threshold > 0)
        {
            // Calculate the difference between the blurred pixels and the original
            // and set the pixels
            for ($x = 0; $x < $w-1; $x++)
            {
                for ($y = 0; $y < $h; $y++)
                {
                    $rgbOrig = imagecolorat($this->img, $x, $y);
                    $rOrig = (($rgbOrig >> 16) & 0xFF);
                    $gOrig = (($rgbOrig >> 8) & 0xFF);
                    $bOrig = ($rgbOrig & 0xFF);

                    $rgbBlur = imagecolorat($imgBlur, $x, $y);

                    $rBlur = (($rgbBlur >> 16) & 0xFF);
                    $gBlur = (($rgbBlur >> 8) & 0xFF);
                    $bBlur = ($rgbBlur & 0xFF);

                    // When the masked pixels differ less from the original
                    // than the threshold specifies, they are set to their original value.
                    $rNew = (abs($rOrig - $rBlur) >= $threshold)
                        ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))
                        : $rOrig;
                    $gNew = (abs($gOrig - $gBlur) >= $threshold)
                        ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))
                        : $gOrig;
                    $bNew = (abs($bOrig - $bBlur) >= $threshold)
                        ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))
                        : $bOrig;

                    if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {
                            #$pixCol = imagecolorallocate($this->img, $rNew, $gNew, $bNew);
                            $pixCol = ($rNew << 16) + ($gNew << 8) + ($bNew);
                            imagesetpixel($this->img, $x, $y, $pixCol);
                        }
                }
            }
        }
        else
        {
            for ($x = 0; $x < $w; $x++)
            {
                for ($y = 0; $y < $h; $y++)
                {
                    $rgbOrig = imagecolorat($this->img, $x, $y);
                    $rOrig = (($rgbOrig >> 16) & 0xFF);
                    $gOrig = (($rgbOrig >> 8) & 0xFF);
                    $bOrig = ($rgbOrig & 0xFF);

                    $rgbBlur = imagecolorat($imgBlur, $x, $y);

                    $rBlur = (($rgbBlur >> 16) & 0xFF);
                    $gBlur = (($rgbBlur >> 8) & 0xFF);
                    $bBlur = ($rgbBlur & 0xFF);

                    $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;
                        if($rNew>255){$rNew=255;}
                        elseif($rNew<0){$rNew=0;}
                    $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;
                        if($gNew>255){$gNew=255;}
                        elseif($gNew<0){$gNew=0;}
                    $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;
                        if($bNew>255){$bNew=255;}
                        elseif($bNew<0){$bNew=0;}
                    $rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew;
                        imagesetpixel($this->img, $x, $y, $rgbNew);
                }
            }
        }
        imagedestroy($imgCanvas);
        imagedestroy($imgBlur);
    }
}
