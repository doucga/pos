<?php
/**
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @author  Orion Richardson <orionr@yahoo.com>
 * @author  Helmut Tischer <htischer@weihenstephan.org>
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

// FIXME: Need to sanity check inputs to this class
namespace Dompdf\Adapter;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Helpers;
use Dompdf\Exception;
use Dompdf\Image\Cache;
use Dompdf\PhpEvaluator;

/**
 * PDF rendering interface
 *
 * Dompdf\Adapter\CPDF provides a simple stateless interface to the stateful one
 * provided by the Cpdf class.
 *
 * Unless otherwise mentioned, all dimensions are in points (1/72 in).  The
 * coordinate origin is in the top left corner, and y values increase
 * downwards.
 *
 * See {@link http://www.ros.co.nz/pdf/} for more complete documentation
 * on the underlying {@link Cpdf} class.
 *
 * @package dompdf
 */
class CPDF implements Canvas
{

    /**
     * Dimensions of paper sizes in points
     *
     * @var array;
     */
    static $PAPER_SIZES = array(
        "4a0" => array(0, 0, 4767.87, 6740.79),
        "2a0" => array(0, 0, 3370.39, 4767.87),
        "a0" => array(0, 0, 2383.94, 3370.39),
        "a1" => array(0, 0, 1683.78, 2383.94),
        "a2" => array(0, 0, 1190.55, 1683.78),
        "a3" => array(0, 0, 841.89, 1190.55),
        "a4" => array(0, 0, 595.28, 841.89),
        "a5" => array(0, 0, 419.53, 595.28),
        "a6" => array(0, 0, 297.64, 419.53),
        "a7" => array(0, 0, 209.76, 297.64),
        "a8" => array(0, 0, 147.40, 209.76),
        "a9" => array(0, 0, 104.88, 147.40),
        "a10" => array(0, 0, 73.70, 104.88),
        "b0" => array(0, 0, 2834.65, 4008.19),
        "b1" => array(0, 0, 2004.09, 2834.65),
        "b2" => array(0, 0, 1417.32, 2004.09),
        "b3" => array(0, 0, 1000.63, 1417.32),
        "b4" => array(0, 0, 708.66, 1000.63),
        "b5" => array(0, 0, 498.90, 708.66),
        "b6" => array(0, 0, 354.33, 498.90),
        "b7" => array(0, 0, 249.45, 354.33),
        "b8" => array(0, 0, 175.75, 249.45),
        "b9" => array(0, 0, 124.72, 175.75),
        "b10" => array(0, 0, 87.87, 124.72),
        "c0" => array(0, 0, 2599.37, 3676.54),
        "c1" => array(0, 0, 1836.85, 2599.37),
        "c2" => array(0, 0, 1298.27, 1836.85),
        "c3" => array(0, 0, 918.43, 1298.27),
        "c4" => array(0, 0, 649.13, 918.43),
        "c5" => array(0, 0, 459.21, 649.13),
        "c6" => array(0, 0, 323.15, 459.21),
        "c7" => array(0, 0, 229.61, 323.15),
        "c8" => array(0, 0, 161.57, 229.61),
        "c9" => array(0, 0, 113.39, 161.57),
        "c10" => array(0, 0, 79.37, 113.39),
        "ra0" => array(0, 0, 2437.80, 3458.27),
        "ra1" => array(0, 0, 1729.13, 2437.80),
        "ra2" => array(0, 0, 1218.90, 1729.13),
        "ra3" => array(0, 0, 864.57, 1218.90),
        "ra4" => array(0, 0, 609.45, 864.57),
        "sra0" => array(0, 0, 2551.18, 3628.35),
        "sra1" => array(0, 0, 1814.17, 2551.18),
        "sra2" => array(0, 0, 1275.59, 1814.17),
        "sra3" => array(0, 0, 907.09, 1275.59),
        "sra4" => array(0, 0, 637.80, 907.09),
        "letter" => array(0, 0, 612.00, 792.00),
        "legal" => array(0, 0, 612.00, 1008.00),
        "ledger" => array(0, 0, 1224.00, 792.00),
        "tabloid" => array(0, 0, 792.00, 1224.00),
        "executive" => array(0, 0, 521.86, 756.00),
        "folio" => array(0, 0, 612.00, 936.00),
        "commercial #10 envelope" => array(0, 0, 684, 297),
        "catalog #10 1/2 envelope" => array(0, 0, 648, 864),
        "8.5x11" => array(0, 0, 612.00, 792.00),
        "8.5x14" => array(0, 0, 612.00, 1008.0),
        "11x17" => array(0, 0, 792.00, 1224.00),
    );

    /**
     * The Dompdf object
     *
     * @var Dompdf
     */
    private $_dompdf;

    /**
     * Instance of Cpdf class
     *
     * @var Cpdf
     */
    private $_pdf;

    /**
     * PDF width, in points
     *
     * @var float
     */
    private $_width;

    /**
     * PDF height, in points
     *
     * @var float;
     */
    private $_height;

    /**
     * Current page number
     *
     * @var int
     */
    private $_page_number;

    /**
     * Total number of pages
     *
     * @var int
     */
    private $_page_count;

    /**
     * Text to display on every page
     *
     * @var array
     */
    private $_page_text;

    /**
     * Array of pages for accesing after rendering is initially complete
     *
     * @var array
     */
    private $_pages;

    /**
     * Array of temporary cached images to be deleted when processing is complete
     *
     * @var array
     */
    private $_image_cache;

    /**
     * Class constructor
     *
     * @param mixed $paper The size of paper to use in this PDF ({@link CPDF::$PAPER_SIZES})
     * @param string $orientation The orientation of the document (either 'landscape' or 'portrait')
     * @param Dompdf $dompdf The Dompdf instance
     */
    function __construct($paper = "letter", $orientation = "portrait", Dompdf $dompdf)
    {
        if (is_array($paper)) {
            $size = $paper;
        } else if (isset(self::$PAPER_SIZES[mb_strtolower($paper)])) {
            $size = self::$PAPER_SIZES[mb_strtolower($paper)];
        } else {
            $size = self::$PAPER_SIZES["letter"];
        }

        if (mb_strtolower($orientation) === "landscape") {
            list($size[2], $size[3]) = array($size[3], $size[2]);
        }

        $this->_dompdf = $dompdf;

        $this->_pdf = new \Cpdf(
            $size,
            true,
            $dompdf->get_option("font_cache"),
            $dompdf->get_option("temp_dir")
        );

        $this->_pdf->addInfo("Producer", sprintf("%s + CPDF", $dompdf->version));
        $time = substr_replace(date('YmdHisO'), '\'', -2, 0) . '\'';
        $this->_pdf->addInfo("CreationDate", "D:$time");
        $this->_pdf->addInfo("ModDate", "D:$time");

        $this->_width = $size[2] - $size[0];
        $this->_height = $size[3] - $size[1];

        $this->_page_number = $this->_page_count = 1;
        $this->_page_text = array();

        $this->_pages = array($this->_pdf->getFirstPageId());

        $this->_image_cache = array();
    }

    function get_dompdf()
    {
        return $this->_dompdf;
    }

    /**
     * Class destructor
     *
     * Deletes all temporary image files
     */
    function __destruct()
    {
        foreach ($this->_image_cache as $img) {
            // The file might be already deleted by 3rd party tmp cleaner,
            // the file might not have been created at all
            // (if image outputting commands failed)
            // or because the destructor was called twice accidentally.
            if (!file_exists($img)) {
                continue;
            }

            if ($this->_dompdf->get_option("debugPng")) print '[__destruct unlink ' . $img . ']';
            if (!$this->_dompdf->get_option("debugKeepTemp")) unlink($img);
        }
    }

    /**
     * Returns the Cpdf instance
     *
     * @return \Cpdf
     */
    function get_cpdf()
    {
        return $this->_pdf;
    }

    /**
     * Add meta information to the PDF
     *
     * @param string $label label of the value (Creator, Producer, etc.)
     * @param string $value the text to set
     */
    function add_info($label, $value)
    {
        $this->_pdf->addInfo($label, $value);
    }

    /**
     * Opens a new 'object'
     *
     * While an object is open, all drawing actions are recored in the object,
     * as opposed to being drawn on the current page.  Objects can be added
     * later to a specific page or to several pages.
     *
     * The return value is an integer ID for the new object.
     *
     * @see CPDF_Adapter::close_object()
     * @see CPDF_Adapter::add_object()
     *
     * @return int
     */
    function open_object()
    {
        $ret = $this->_pdf->openObject();
        $this->_pdf->saveState();
        return $ret;
    }

    /**
     * Reopens an existing 'object'
     *
     * @see CPDF_Adapter::open_object()
     * @param int $object the ID of a previously opened object
     */
    function reopen_object($object)
    {
        $this->_pdf->reopenObject($object);
        $this->_pdf->saveState();
    }

    /**
     * Closes the current 'object'
     *
     * @see CPDF_Adapter::open_object()
     */
    function close_object()
    {
        $this->_pdf->restoreState();
        $this->_pdf->closeObject();
    }

    /**
     * Adds a specified 'object' to the document
     *
     * $object int specifying an object created with {@link
     * CPDF::open_object()}.  $where can be one of:
     * - 'add' add to current page only
     * - 'all' add to every page from the current one onwards
     * - 'odd' add to all odd numbered pages from now on
     * - 'even' add to all even numbered pages from now on
     * - 'next' add the object to the next page only
     * - 'nextodd' add to all odd numbered pages from the next one
     * - 'nexteven' add to all even numbered pages from the next one
     *
     * @see Cpdf::addObject()
     *
     * @param int $object
     * @param string $where
     */
    function add_object($object, $where = 'all')
    {
        $this->_pdf->addObject($object, $where);
    }

    /**
     * Stops the specified 'object' from appearing in the document.
     *
     * The object will stop being displayed on the page following the current
     * one.
     *
     * @param int $object
     */
    function stop_object($object)
    {
        $this->_pdf->stopObject($object);
    }

    /**
     * @access private
     */
    function serialize_object($id)
    {
        // Serialize the pdf object's current state for retrieval later
        return $this->_pdf->serializeObject($id);
    }

    /**
     * @access private
     */
    function reopen_serialized_object($obj)
    {
        return $this->_pdf->restoreSerializedObject($obj);
    }

    //........................................................................

    /**
     * Returns the PDF's width in points
     * @return float
     */
    function get_width()
    {
        return $this->_width;
    }

    /**
     * Returns the PDF's height in points
     * @return float
     */
    function get_height()
    {
        return $this->_height;
    }

    /**
     * Returns the current page number
     * @return int
     */
    function get_page_number()
    {
        return $this->_page_number;
    }

    /**
     * Returns the total number of pages in the document
     * @return int
     */
    function get_page_count()
    {
        return $this->_page_count;
    }

    /**
     * Sets the current page number
     *
     * @param int $num
     */
    function set_page_number($num)
    {
        $this->_page_number = $num;
    }

    /**
     * Sets the page count
     *
     * @param int $count
     */
    function set_page_count($count)
    {
        $this->_page_count = $count;
    }

    /**
     * Sets the stroke color
     *
     * See {@link Style::set_color()} for the format of the color array.
     * @param array $color
     */
    protected function _set_stroke_color($color)
    {
        $this->_pdf->setStrokeColor($color);
    }

    /**
     * Sets the fill colour
     *
     * See {@link Style::set_color()} for the format of the colour array.
     * @param array $color
     */
    protected function _set_fill_color($color)
    {
        $this->_pdf->setColor($color);
    }

    /**
     * Sets line transparency
     * @see Cpdf::setLineTransparency()
     *
     * Valid blend modes are (case-sensitive):
     *
     * Normal, Multiply, Screen, Overlay, Darken, Lighten,
     * ColorDodge, ColorBurn, HardLight, SoftLight, Difference,
     * Exclusion
     *
     * @param string $mode the blending mode to use
     * @param float $opacity 0.0 fully transparent, 1.0 fully opaque
     */
    protected function _set_line_transparency($mode, $opacity)
    {
        $this->_pdf->setLineTransparency($mode, $opacity);
    }

    /**
     * Sets fill transparency
     * @see Cpdf::setFillTransparency()
     *
     * Valid blend modes are (case-sensitive):
     *
     * Normal, Multiply, Screen, Overlay, Darken, Lighten,
     * ColorDogde, ColorBurn, HardLight, SoftLight, Difference,
     * Exclusion
     *
     * @param string $mode the blending mode to use
     * @param float $opacity 0.0 fully transparent, 1.0 fully opaque
     */
    protected function _set_fill_transparency($mode, $opacity)
    {
        $this->_pdf->setFillTransparency($mode, $opacity);
    }

    /**
     * Sets the line style
     *
     * @see Cpdf::setLineStyle()
     *
     * @param float $width
     * @param string $cap
     * @param string $join
     * @param array $dash
     */
    protected function _set_line_style($width, $cap, $join, $dash)
    {
        $this->_pdf->setLineStyle($width, $cap, $join, $dash);
    }

    /**
     * Sets the opacity
     *
     * @param $opacity
     * @param $mode
     */
    function set_opacity($opacity, $mode = "Normal")
    {
        $this->_set_line_transparency($mode, $opacity);
        $this->_set_fill_transparency($mode, $opacity);
    }

    function set_default_view($view, $options = array())
    {
        array_unshift($options, $view);
        call_user_func_array(array($this->_pdf, "openHere"), $options);
    }

    /**
     * Remaps y coords from 4th to 1st quadrant
     *
     * @param float $y
     * @return float
     */
    protected function y($y)
    {
        return $this->_height - $y;
    }

    // Canvas implementation
    function line($x1, $y1, $x2, $y2, $color, $width, $style = array())
    {
        $this->_set_stroke_color($color);
        $this->_set_line_style($width, "butt", "", $style);

        $this->_pdf->line($x1, $this->y($y1),
            $x2, $this->y($y2));
    }

    function arc($x, $y, $r1, $r2, $astart, $aend, $color, $width, $style = array())
    {
        $this->_set_stroke_color($color);
        $this->_set_line_style($width, "butt", "", $style);

        $this->_pdf->ellipse($x, $this->y($y), $r1, $r2, 0, 8, $astart, $aend, false, false, true, false);
    }

    //........................................................................

    /**
     * Convert a GIF or BMP image to a PNG image
     *
     * @param string $image_url
     * @param integer $type
     *
     * @throws Exception
     * @return string The url of the newly converted image
     */
    protected function _convert_gif_bmp_to_png($image_url, $type)
    {
        $func_name = "imagecreatefrom$type";

        if (!function_exists($func_name)) {
            if (!method_exists("Dompdf\Helpers", $func_name)) {
                throw new Exception("Function $func_name() not found.  Cannot convert $type image: $image_url.  Please install the image PHP extension.");
            }
            $func_name = "\\Dompdf\\Helpers::" . $func_name;
        }

        set_error_handler(array("\\Dompdf\\Helpers", "record_warnings"));
        $im = call_user_func($func_name, $image_url);

        if ($im) {
            imageinterlace($im, false);

            $tmp_dir = $this->_dompdf->get_option("temp_dir");
            $tmp_name = tempnam($tmp_dir, "{$type}dompdf_img_");
            @unlink($tmp_name);
            $filename = "$tmp_name.png";
            $this->_image_cache[] = $filename;

            imagepng($im, $filename);
            imagedestroy($im);
        } else {
            $filename = Cache::$broken_image;
        }

        restore_error_handler();

        return $filename;
    }

    function rectangle($x1, $y1, $w, $h, $color, $width, $style = array())
    {
        $this->_set_stroke_color($color);
        $this->_set_line_style($width, "butt", "", $style);
        $this->_pdf->rectangle($x1, $this->y($y1) - $h, $w, $h);
    }

    function filled_rectangle($x1, $y1, $w, $h, $color)
    {
        $this->_set_fill_color($color);
        $this->_pdf->filledRectangle($x1, $this->y($y1) - $h, $w, $h);
    }

    function clipping_rectangle($x1, $y1, $w, $h)
    {
        $this->_pdf->clippingRectangle($x1, $this->y($y1) - $h, $w, $h);
    }

    function clipping_roundrectangle($x1, $y1, $w, $h, $rTL, $rTR, $rBR, $rBL)
    {
        $this->_pdf->clippingRectangleRounded($x1, $this->y($y1) - $h, $w, $h, $rTL, $rTR, $rBR, $rBL);
    }

    function clipping_end()
    {
        $this->_pdf->clippingEnd();
    }

    function save()
    {
        $this->_pdf->saveState();
    }

    function restore()
    {
        $this->_pdf->restoreState();
    }

    function rotate($angle, $x, $y)
    {
        $this->_pdf->rotate($angle, $x, $y);
    }

    function skew($angle_x, $angle_y, $x, $y)
    {
        $this->_pdf->skew($angle_x, $angle_y, $x, $y);
    }

    function scale($s_x, $s_y, $x, $y)
    {
        $this->_pdf->scale($s_x, $s_y, $x, $y);
    }

    function translate($t_x, $t_y)
    {
        $this->_pdf->translate($t_x, $t_y);
    }

    function transform($a, $b, $c, $d, $e, $f)
    {
        $this->_pdf->transform(array($a, $b, $c, $d, $e, $f));
    }

    function polygon($points, $color, $width = null, $style = array(), $fill = false)
    {
        $this->_set_fill_color($color);
        $this->_set_stroke_color($color);

        // Adjust y values
        for ($i = 1; $i < count($points); $i += 2) {
            $points[$i] = $this->y($points[$i]);