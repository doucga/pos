<?php
/**
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @author  Helmut Tischer <htischer@weihenstephan.org>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace Dompdf\Adapter;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Helpers;
use Dompdf\Exception;
use Dompdf\FontMetrics;
use Dompdf\Image\Cache;
use Dompdf\PhpEvaluator;

/**
 * PDF rendering interface
 *
 * Dompdf\Adapter\PDFLib provides a simple, stateless interface to the one
 * provided by PDFLib.
 *
 * Unless otherwise mentioned, all dimensions are in points (1/72 in).
 * The coordinate origin is in the top left corner and y values
 * increase downwards.
 *
 * See {@link http://www.pdflib.com/} for more complete documentation
 * on the underlying PDFlib functions.
 *
 * @package dompdf
 */
class PDFLib implements Canvas
{

    /**
     * Dimensions of paper sizes in points
     *
     * @var array;
     */
    static public $PAPER_SIZES = array(); // Set to Dompdf\Adapter\CPDF::$PAPER_SIZES below.

    /**
     * Whether to create PDFs in memory or on disk
     *
     * @var bool
     */
    static $IN_MEMORY = true;

    /**
     * @var Dompdf
     */
    private $_dompdf;

    /**
     * Instance of PDFLib class
     *
     * @var \PDFlib
     */
    private $_pdf;

    /**
     * Name of temporary file used for PDFs created on disk
     *
     * @var string
     */
    private $_file;

    /**
     * PDF width, in points
     *
     * @var float
     */
    private $_width;

    /**
     * PDF height, in points
     *
     * @var float
     */
    private $_height;

    /**
     * Last fill color used
     *
     * @var array
     */
    private $_last_fill_color;

    /**
     * Last stroke color used
     *
     * @var array
     */
    private $_last_stroke_color;

    /**
     * Cache of image handles
     *
     * @var array
     */
    private $_imgs;

    /**
     * Cache of font handles
     *
     * @var array
     */
    private $_fonts;

    /**
     * List of objects (templates) to add to multiple pages
     *
     * @var array
     */
    private $_objs;

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
     * Class constructor
     *
     * @param mixed $paper The size of paper to use either a string (see {@link Dompdf\Adapter\CPDF::$PAPER_SIZES}) or
     *                            an array(xmin,ymin,xmax,ymax)
     * @param string $orientation The orientation of the document (either 'landscape' or 'portrait')
     * @param Dompdf $dompdf
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

        $this->_width = $size[2] - $size[0];
        $this->_height = $size[3] - $size[1];

        $this->_dompdf = $dompdf;

        $this->_pdf = new \PDFLib();
        
        $license = $dompdf->get_option('pdflibLicense');
        if (strlen($license) > 0)
            $this->_pdf->set_parameter("license", $license);

        $this->_pdf->set_parameter("textformat", "utf8");
        $this->_pdf->set_parameter("fontwarning", "false");

        // TODO: fetch PDFLib version information for the producer field
        $this->_pdf->set_info("Producer", sprintf("%s + PDFLib", $dompdf->version));

        // Silence pedantic warnings about missing TZ settings
        $tz = @date_default_timezone_get();
        date_default_timezone_set("UTC");
        $this->_pdf->set_info("Date", date("Y-m-d"));
        date_default_timezone_set($tz);

        if (self::$IN_MEMORY)
            $this->_pdf->begin_document("", "");
        else {
            $tmp_dir = $this->_dompdf->get_options("temp_dir");
            $tmp_name = tempnam($tmp_dir, "libdompdf_pdf_");
            @unlink($tmp_name);
            $this->_file = "$tmp_name.pdf";
            $this->_pdf->begin_document($this->_file, "");
        }

        $this->_pdf->begin_page_ext($this->_width, $this->_height, "");

        $this->_page_number = $this->_page_count = 1;
        $this->_page_text = array();

        $this->_imgs = array();
        $this->_fonts = array();
        $this->_objs = array();

        // Set up font paths
        $families = $dompdf->getFontMetrics->getFontFamilies();
        foreach ($families as $files) {
            foreach ($files as $file) {
                $face = basename($file);
                $afm = null;

                // Prefer ttfs to afms
                if (file_exists("$file.ttf")) {
                    $outline = "$file.ttf";

                } else if (file_exists("$file.TTF")) {
                    $outline = "$file.TTF";

                } else if (file_exists("$file.pfb")) {
                    $outline = "$file.pfb";

                    if (file_exists("$file.afm")) {
                        $afm = "$file.afm";
                    }

                } else if (file_exists("$file.PFB")) {
                    $outline = "$file.PFB";
                    if (file_exists("$file.AFM")) {
                        $afm = "$file.AFM";
                    }
                } else {
                    continue;
                }

                $this->_pdf->set_parameter("FontOutline", "\{$face\}=\{$outline\}");

                if (!is_null($afm)) {
                    $this->_pdf->set_parameter("FontAFM", "\{$face\}=\{$afm\}");
                }
            }
        }
    }

    function get_dompdf()
    {
        return $this->_dompdf;
    }

    /**
     * Close the pdf
     */
    protected function _close()
    {
        $this->_place_objects();

        // Close all pages
        $this->_pdf->suspend_page("");
        for ($p = 1; $p <= $this->_page_count; $p++) {
            $this->_pdf->resume_page("pagenumber=$p");
            $this->_pdf->end_page_ext("");
        }

        $this->_pdf->end_document("");
    }


    /**
     * Returns the PDFLib instance
     *
     * @return PDFLib
     */
    function get_pdflib()
    {
        return $this->_pdf;
    }

    /**
     * Add meta information to the PDF
     *
     * @param string $label label of the value (Creator, Producter, etc.)
     * @param string $value the text to set
     */
    function add_info($label, $value)
    {
        $this->_pdf->set_info($label, $value);
    }

    /**
     * Opens a new 'object' (template in PDFLib-speak)
     *
     * While an object is open, all drawing actions are recorded to the
     * object instead of being drawn on the current page.  Objects can
     * be added later to a specific page or to several pages.
     *
     * The return value is an integer ID for the new object.
     *
     * @see PDFLib_Adapter::close_object()
     * @see PDFLib_Adapter::add_object()
     *
     * @return int
     */
    function open_object()
    {
        $this->_pdf->suspend_page("");
        $ret = $this->_pdf->begin_template($this->_width, $this->_height);
        $this->_pdf->save();
        $this->_objs[$ret] = array("start_page" => $this->_page_number);
        return $ret;
    }

    /**
     * Reopen an existing object (NOT IMPLEMENTED)
     * PDFLib does not seem to support reopening templates.
     *
     * @param int $object the ID of a previously opened object
     *
     * @throws Exception
     * @return void
     */
    function reopen_object($object)
    {
        throw new Exception("PDFLib does not support reopening objects.");
    }

    /**
     * Close the current template
     *
     * @see PDFLib_Adapter::open_object()
     */
    function close_object()
    {
        $this->_pdf->restore();
        $this->_pdf->end_template();
        $this->_pdf->resume_page("pagenumber=" . $this->_page_number);
    }

    /**
     * Adds the specified object to the document
     *
     * $where can be one of:
     * - 'add' add to current page only
     * - 'all' add to every page from the current one onwards
     * - 'odd' add to all odd numbered pages from now on
     * - 'even' add to all even numbered pages from now on
     * - 'next' add the object to the next page only
     * - 'nextodd' add to all odd numbered pages from the next one
     * - 'nexteven' add to all even numbered pages from the next one
     *
     * @param int $object the object handle returned by open_object()
     * @param string $where
     */
    function add_object($object, $where = 'all')
    {

        if (mb_strpos($where, "next") !== false) {
            $this->_objs[$object]["start_page"]++;
            $where = str_replace("next", "", $where);
            if ($where == "")
                $where = "add";
        }

        $this->_objs[$object]["where"] = $where;
    }

    /**
     * Stops the specified template from appearing in the document.
     *
     * The object will stop being displayed on the page following the
     * current one.
     *
     * @param int $object
     */
    function stop_object($object)
    {

        if (!isset($this->_objs[$object]))
            return;

        $start = $this->_objs[$object]["start_page"];
        $where = $this->_objs[$object]["where"];

        // Place the object on this page if required
        if ($this->_page_number >= $start &&
            (($this->_page_number % 2 == 0 && $where === "even") ||
                ($this->_page_number % 2 == 1 && $where === "odd") ||
                ($where === "all"))
        ) {
            $this->_pdf->fit_image($object, 0, 0, "");
        }

        $this->_objs[$object] = null;
        unset($this->_objs[$object]);
    }

    /**
     * Add all active objects to the current page
     */
    protected function _place_objects()
    {

        foreach ($this->_objs as $obj => $props) {
            $start = $props["start_page"];
            $where = $props["where"];

            // Place the object on this page if required
            if ($this->_page_number >= $start &&
                (($this->_page_number % 2 == 0 && $where === "even") ||
                    ($this->_page_number % 2 == 1 && $where === "odd") ||
                    ($where === "all"))
            ) {
                $this->_pdf->fit_image($obj, 0, 0, "");
            }
        }

    }

    function get_width()
    {
        return $this->_width;
    }

    function get_height()
    {
        return $this->_height;
    }

    function get_page_number()
    {
        return $this->_page_number;
    }

    function get_page_count()
    {
        return $this->_page_count;
    }

    function set_page_number($num)
    {
        $this->_page_number = (int)$num;
    }

    function set_page_count($count)
    {
        $this->_page_count = (int)$count;
    }


    /**
     * Sets the line style
     *
     * @param float $width
     * @param        $cap
     * @param string $join
     * @param array $dash
     *
     * @return void
     */
    protected function _set_line_style($width, $cap, $join, $dash)
    {

        if (count($dash) == 1)
            $dash[] = $dash[0];

        if (count($dash) > 1)
            $this->_pdf->setdashpattern("dasharray={" . implode(" ", $dash) . "}");
        else
            $this->_pdf->setdash(0, 0);

        switch ($join) {
            case "miter":
                $this->_pdf->setlinejoin(0);
                break;

            case "round":
                $this->_pdf->setlinejoin(1);
                break;

            case "bevel":
                $this->_pdf->setlinejoin(2);
                break;

            default:
                break;
        }

        switch ($cap) {
            case "butt":
                $this->_pdf->setlinecap(0);
                break;

            case "round":
                $this->_pdf->setlinecap(1);
                break;

            case "square":
                $this->_pdf->setlinecap(2);
                break;

            default:
                break;
        }

        $this->_pdf->setlinewidth($width);

    }

    /**
     * Sets the line color
     *
     * @param array $color array(r,g,b)
     */
    protected function _set_stroke_color($color)
    {
        if ($this->_last_stroke_color == $color)
            return;

        $this->_last_stroke_color = $color;

        if (isset($color[3])) {
            $type = "cmyk";
            list($c1, $c2, $c3, $c4) = array($color[0], $color[1], $color[2], $color[3]);
        } elseif (isset($color[2])) {
            $type = "rgb";
            list($c1, $c2, $c3, $c4) = array($color[0], $color[1], $color[2], null);
        } else {
            $type = "gray";
            list($c1, $c2, $c3, $c4) = array($color[0], $color[1], null, null);
        }

        $this->_pdf->setcolor("stroke", $type, $c1, $c2, $c3, $c4);
    }

    /**
     * Sets the fill color
     *
     * @param array $color array(r,g,b)
     */
    protected function _set_fill_color($color)
    {
        if ($this->_last_fill_color == $color)
            return;

        $this->_last_fill_color = $color;

        if (isset($color[3])) {
            $type = "cmyk";
            list($c1, $c2, $c3, $c4) = array($color[0], $color[1], $color[2], $color[3]);
        } elseif (isset($color[2])) {
            $type = "rgb";
            list($c1, $c2, $c3, $c4) = array($color[0], $color[1], $color[2], null);
        } else {
            $type = "gray";
            list($c1, $c2, $c3, $c4) = array($color[0], $color[1], null, null);
        }

        $this->_pdf->setcolor("fill", $type, $c1, $c2, $c3, $c4);
    }

    /**
     * Sets the opacity
     *
     * @param $opacity
     * @param $mode
     */
    function set_opacity($opacity, $mode = "Normal")
    {
        if ($mode === "Normal") {
            $gstate = $this->_pdf->create_gstate("opacityfill=$opacity opacitystroke=$opacity");
            $this->_pdf->set_gstate($gstate);
        }
    }

    function set_default_view($view, $options = array())
    {
        // TODO
        // http://www.pdflib.com/fileadmin/pdflib/pdf/manuals/PDFlib-8.0.2-API-reference.pdf
        /**
         * fitheight Fit the page height to the window, with the x coordinate left at the left edge of the window.
         * fitrect Fit the rectangle specified by left, bottom, right, and top to the window.
         * fitvisible Fit the visible contents of the page (the ArtBox) to the window.
         * fitvisibleheight Fit the visible contents of the page to the window with the x coordinate left at the left edge of the window.
         * fitvisiblewidth Fit the visible contents of the page to the window with the y coordinate top at the top edge of the window.
         * fitwidth Fit the page width to the window, with the y coordinate top at the top edge of the window.
         * fitwindow Fit the complete page to the window.
         * fixed
         */
        //$this->_pdf->set_parameter("openaction", $view);
    }

    /**
     * Loads a specific font and stores the corresponding descriptor.
     *
     * @param string $font
     * @param string $encoding
     * @param string $options
     *
     * @return int the font descriptor for the font
     */
    protected function _load_font($font, $encoding = null, $options = "")
    {

        // Check if the font is a native PDF font
        // Embed non-native fonts
        $test = strtolower(basename($font));
        if (in_array($test, DOMPDF::$native_fonts)) {
            $font = basename($font);

        } else {
            // Embed non-native fonts
            $options .= " embedding=true";
        }

        if (is_null($encoding)) {

            // Unicode encoding is only available for the commerical
            // version of PDFlib and not PDFlib-Lite
            if (strlen($dompdf->get_option('pdflibLicense')) > 0)
                $encoding = "unicode";
            else
                $encoding = "auto";

        }

        $key = "$font:$encoding:$options";

        if (isset($this->_fonts[$key]))
            return $this->_fonts[$key];

        else {

            $this->_fonts[$key] = $this->_pdf->load_font($font, $encoding, $options);
            return $this->_fonts[$key];

        }

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

    //........................................................................

    /**
     * @param float $x1
     * @param float $y1
     * @param float $x2
     * @param float $y2
     * @param array $color
     * @param float $width
     * @param array $style
     */
    function line($x1, $y1, $x2, $y2, $color, $width, $style = null)
    {
        $this->_set_line_style($width, "butt", "", $style);
        $this->_set_stroke_color($color);

        $y1 = $this->y($y1);
        $y2 = $this-