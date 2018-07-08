<?php

namespace DigitalFeonix;

class ImageTextManipulation extends ImageManipulation
{
    // font information (font file is set in parent)
    protected $kerning          = 1.02;
    protected $use_kerning      = FALSE;
    protected $linespacing      = 24;
    protected $font_size        = 15;
    protected $font_size_adj    = 0;
    protected $font_color       = 0x00000000;

    protected $available_fonts  = array();
    protected $engraving_text   = array();
    protected $row_specs        = array();

    protected $uppercase_only   = FALSE;

    /** LEGACY STUFF **/
    protected $top              = 50; // top pixel margin (measured down from top)
    protected $btm              = 0;  // bottom margin of text area(measured up from bottom)
    protected $rgt              = 40; // right boundary
    protected $lft              = 20; // left boundary
    protected $offset           = 10; // horizontal offset for text

    ################################################################################
    # private utility Functions
    ################################################################################

    protected function getArcDegFromWidth($width, $radius)
    {
        return (($width / (pi() * 2 * $radius)) * 360);
    }

    protected function getBoundingWidth($size, $str)
    {
        $ret = 0;

        // add width of each character instead of whole
        for ($l = 0; $l < strlen($str); $l++)
        {
            $bbox = $this->getTextBox($str[$l], $size);
            $ret += abs($bbox[2] - $bbox[0]);
        }

        // adjust width based on kerning
        return $ret * $this->kerning;
    }

    ################################################################################
    # text Functions
    ################################################################################

    protected function imagettftextarc($cx,$cy,$r,$deg,$size,$color,$text)
    {
        // if radius is negative (so text is normal on the bottom of the curve),adjust for font height
        $adj = ($r < 0) ? $size : 0;
        $deg = ($r < 0) ? -($deg) : $deg;

        $txt_width = 0;
        $txt_width = $this->getBoundingWidth($size, $text);
        $txt_deg   = ($this->getArcDegFromWidth($txt_width, $r) / 2) - $deg; // if deg = 0, center along top going clockwise as deg increases

        for ($l = 0; $l < strlen($text); $l++)
        {
            // there is still an issue of single letter vs letter + kerning in words
            // which cause thin letters (i,l,etc) to get too close to their neighbors
            $ltr_width = max($this->getBoundingWidth($size, $text[$l]), $this->getBoundingWidth($size, '-'));

            $ltr_deg = $this->getArcDegFromWidth($ltr_width, $r);
            $out_deg = $txt_deg - ($ltr_deg / 2); // bisect the arc angle for a better text flow

            $tx = $cx - sin(deg2rad($txt_deg)) * ($r - $adj);
            $ty = $cy - cos(deg2rad($txt_deg)) * ($r - $adj);

            imagettftext($this->img, $size, $out_deg, $tx, $ty, $color, $this->font_file, $text[$l]);

            $txt_deg -= $ltr_deg;
        }
    }

    protected function imagettftext_kerned($size,$angle,$x,$y,$color,$text,$align=0)
    {
        switch ($align)
        {
            case 1: // align right
                // starts further left to stay at same endpoint
                $x = $x - ((strlen($text) - 1) * $this->kerning);
                break;
            case -1: // align left
                // starts at same point, will go further right
                break;
            case 0: // align center
                // starts further left, passes original endpoint
                $x = $x - (((strlen($text) - 1) * $this->kerning) / 2);
                break;
        }

        for ($s = 0; $s < strlen($text); $s++)
        {
            $kbox = imagettftext($this->img, $size, $angle, $x, $y, $color, $this->font_file, $text[$s]);

            $x = $kbox[2] + $this->kerning;
        }
    }

    public function addTextArc($text, $cx, $cy, $r, $angle = 0, $size = 12, $color = 0x000000)
    {
        if (is_null($this->font_file))
        {
            return FALSE;
        }

        $this->imagettftextarc( $cx, $cy, $r, $angle, $size, $color, $text );
    }

    ################################################################################
    # settings Functions
    ################################################################################

    public function addAvailableFonts($font_array, $override = FALSE)
    {
        if (is_array($font_array))
        {
            foreach ($font_array as $name => $file)
            {
                if (!is_numeric($name) && file_exists($file))
                {
                    if (!array_key_exists($name, $this->available_fonts) || ($override == TRUE))
                    {
                        $this->available_fonts[$name] = $file;
                    }
                }
            }
        }
    }

    public function addRowInfo($style, $align, $cx, $cy, $radius, $angle, $size)
    {
        $this->row_specs[] = array(
            'style' => $style,
            'align' => $align,
            'cx'    => $cx,
            'cy'    => $cy,
            'r'     => $radius,
            'a'     => $angle,
            's'     => $size
        );
    }

    public function adjustFontSize($adjustment)
    {
        $this->font_size_adj = floatval($adjustment);
    }

    // put in the same order as CSS
    public function setBoundaries($top, $right, $bottom, $left)
    {
        $this->top  = intval($top);
        $this->btm  = intval($bottom);
        $this->rgt  = intval($right);
        $this->lft  = intval($left);
    }

    public function setEngravingText($text_array)
    {
        if (is_array($text_array))
        {
            $this->engraving_text = $text_array;
        }
    }

    // overrides the parent version
    public function setFont($font)
    {
        if (!empty($this->available_fonts) && array_key_exists($font, $this->available_fonts))
        {
            $this->font_file = $this->available_fonts[$font];
        }
        else
        {
            parent::setFont($font);
        }
    }

    public function setFontColor($color)
    {
        $old_color = $this->font_color;
        ## TODO: add validation
        $this->font_color = $color;
        return $old_color;
    }

    public function getFontColor()
    {
        return $this->font_color;
    }

    public function setKerning($kerning)
    {
        # kerning needs improving so floats actually matter
        $this->kerning = floatval($kerning);
    }

    public function useKerning($use = TRUE)
    {
        $this->use_kerning = (bool)$use;
    }

    public function uppercaseOnly($uppercase = TRUE)
    {
        $this->uppercase_only = (bool)$uppercase;
    }


    ################################################################################
    # legacy Functions
    ################################################################################

    protected function scale_font_size($lines,$max_width=200)
    {
        foreach($lines as $string)
        {
            $bbox = imagettfbbox($this->font_size, 0, $this->font_file, $string);
            // make width of this text less than width of coin
            // but not more than the max font size
            while ($bbox[2] > ($max_width))
            {
                $this->font_size -= 1;
                $bbox = imagettfbbox($this->font_size, 0, $this->font_file, $string);
            }

        }
    }

    // this scales the font down if the height of the text blocks are too high
    // starts by reducing the line spacing and then starts reducing the font size
    protected function scale_font_size2($num_lines,$max_height,$current_height)
    {
        while ($current_height > ($max_height) && $this->font_size > 7)
        {
            if (($this->linespacing > ($this->font_size + ceil($this->font_size * 0.50))) && (($this->linespacing - $this->font_size) > 4))
            {
                $this->linespacing -= 1;
            }
            else
            {
                $this->font_size -= 1;
            }

            $current_height = ($this->linespacing * $num_lines) - ($this->linespacing - $this->font_size);
        }
    }
}
