<?php

namespace Dompdf;

use Dompdf\Css\Style;
use Dompdf\Frame\FrameList;

/**
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

/**
 * The main Frame class
 *
 * This class represents a single HTML element.  This class stores
 * positioning information as well as containing block location and
 * dimensions. Style information for the element is stored in a {@link
 * Style} object. Tree structure is maintained via the parent & children
 * links.
 *
 * @package dompdf
 */
class Frame
{
    const WS_TEXT = 1;
    const WS_SPACE = 2;

    /**
     * The DOMElement or DOMText object this frame represents
     *
     * @var \DOMElement|\DOMText
     */
    protected $_node;

    /**
     * Unique identifier for this frame.  Used to reference this frame
     * via the node.
     *
     * @var string
     */
    protected $_id;

    /**
     * Unique id counter
     */
    public static $ID_COUNTER = 0; /*protected*/

    /**
     * This frame's calculated style
     *
     * @var Style
     */
    protected $_style;

    /**
     * This frame's original style.  Needed for cases where frames are
     * split across pages.
     *
     * @var Style
     */
    protected $_original_style;

    /**
     * This frame's parent in the document tree.
     *
     * @var Frame
     */
    protected $_parent;

    /**
     * This frame's children
     *
     * @var Frame[]
     */
    protected $_frame_list;

    /**
     * This frame's first child.  All children are handled as a
     * doubly-linked list.
     *
     * @var Frame
     */
    protected $_first_child;

    /**
     * This frame's last child.
     *
     * @var Frame
     */
    protected $_last_child;

    /**
     * This frame's previous sibling in the document tree.
     *
     * @var Frame
     */
    protected $_prev_sibling;

    /**
     * This frame's next sibling in the document tree.
     *
     * @var Frame
     */
    protected $_next_sibling;

    /**
     * This frame's containing block (used in layout): array(x, y, w, h)
     *
     * @var float[]
     */
    protected $_containing_block;

    /**
     * Position on the page of the top-left corner of the margin box of
     * this frame: array(x,y)
     *
     * @var float[]
     */
    protected $_position;

    /**
     * Absolute opacity of this frame
     *
     * @var float
     */
    protected $_opacity;

    /**
     * This frame's decorator
     *
     * @var \Dompdf\FrameDecorator\AbstractFrameDecorator
     */
    protected $_decorator;

    /**
     * This frame's containing line box
     *
     * @var LineBox
     */
    protected $_containing_line;

    /**
     * @var array
     */
    protected $_is_cache = array();

    /**
     * Tells wether the frame was already pushed to the next page
     *
     * @var bool
     */
    public $_already_pushed = false;

    /**
     * @var bool
     */
    public $_float_next_line = false;

    /**
     * Tells wether the frame was split
     *
     * @var bool
     */
    public $_splitted;

    /**
     * @var int
     */
    public static $_ws_state = self::WS_SPACE;

    /**
     * Class constructor
     *
     * @param \DOMNode $node the DOMNode this frame represents
     */
    public function __construct(\DOMNode $node)
    {
        $this->_node = $node;

        $this->_parent = null;
        $this->_first_child = null;
        $this->_last_child = null;
        $this->_prev_sibling = $this->_next_sibling = null;

        $this->_style = null;
        $this->_original_style = null;

        $this->_containing_block = array(
            "x" => null,
            "y" => null,
            "w" => null,
            "h" => null,
        );

        $this->_containing_block[0] =& $this->_containing_block["x"];
        $this->_containing_block[1] =& $this->_containing_block["y"];
        $this->_containing_block[2] =& $this->_containing_block["w"];
        $this->_containing_block[3] =& $this->_containing_block["h"];

        $this->_position = array(
            "x" => null,
            "y" => null,
        );

        $this->_position[0] =& $this->_position["x"];
        $this->_position[1] =& $this->_position["y"];

        $this->_opacity = 1.0;
        $this->_decorator = null;

        $this->set_id(self::$ID_COUNTER++);
    }

    /**
     * WIP : preprocessing to remove all the unused whitespace
     */
    protected function ws_trim()
    {
        if ($this->ws_keep()) {
            return;
        }

        if (self::$_ws_state === self::WS_SPACE) {
            $node = $this->_node;

            if ($node->nodeName === "#text" && !empty($node->nodeValue)) {
                $node->nodeValue = preg_replace("/[ \t\r\n\f]+/u", " ", trim($node->nodeValue));
                self::$_ws_state = self::WS_TEXT;
            }
        }
    }

    /**
     * @return bool
     */
    protected function ws_keep()
    {
        $whitespace = $this->get_style()->white_space;

        return in_array($whitespace, array("pre", "pre-wrap", "pre-line"));
    }

    /**
     * @return bool
     */
    protected function ws_is_text()
    {
        $node = $this->get_node();

        if ($node->nodeName === "img") {
            return true;
        }

        if (!$this->is_in_flow()) {
            return false;
        }

        if ($this->is_text_node()) {
            return trim($node->nodeValue) !== "";
        }

        return true;
    }

    /**
     * "Destructor": forcibly free all references held by this frame
     *
     * @param bool $recursive if true, call dispose on all children
     */
    public function dispose($recursive = false)
    {
        if ($recursive) {
            while ($child = $this->_first_child) {
                $child->dispose(true);
            }
        }

        // Remove this frame from the tree
        if ($this->_prev_sibling) {
            $this->_prev_sibling->_next_sibling = $this->_next_sibling;
        }

        if ($this->_next_sibling) {
            $this->_next_sibling->_prev_sibling = $this->_prev_sibling;
        }

        if ($this->_parent && $this->_parent->_first_child === $this) {
            $this->_parent->_first_child = $this->_next_sibling;
        }

        if ($this->_parent && $this->_parent->_last_child === $this) {
            $this->_parent->_last_child = $this->_prev_sibling;
        }

        if ($this->_parent) {
            $this->_parent->get_node()->removeChild($this->_node);
        }

        $this->_style->dispose();
        $this->_style = null;
        unset($this->_style);

        $this->_original_style->dispose();
        $this->_original_style = null;
        unset($this->_original_style);

    }

    /**
     * Re-initialize the frame
     */
    public function reset()
    {
        $this->_position["x"] = null;
        $this->_position["y"] = null;

        $this->_containing_block["x"] = null;
        $this->_containing_block["y"] = null;
        $this->_containing_block["w"] = null;
        $this->_containing_block["h"] = null;

        $this->_style = null;
        unset($this->_style);
        $this->_style = clone $this->_original_style;
    }

    /**
     * @return \DOMElement|\DOMText
     */
    public function get_node()
    {
        return $this->_node;
    }

    /**
     * @return string
     */
    public function get_id()
    {
        return $this->_id;
    }

    /**
     * @return Style
     */
    public function get_style()
    {
        return $this->_style;
    }

    /**
     * @return Style
     */
    public function get_original_style()
    {
        return $this->_original_style;
    }

    /**
     * @return Frame
     */
    public function get_parent()
    {
        return $this->_parent;
    }

    /**
     * @return \Dompdf\FrameDecorator\AbstractFrameDecorator
     */
    public function get_decorator()
    {
        return $this->_decorator;
    }

    /**
     * @return Frame
     */
    public function get_first_child()
    {
        return $this->_first_child;
    }

    /**
     * @return Frame
     */
    public function get_last_child()
    {
        return $this->_last_child;
    }

    /**
     * @return Frame
     */
    public function get_prev_sibling()
    {
        return $this->_prev_sibling;
    }

    /**
     * @return Frame
     */
    public function get_next_sibling()
    {
        return $this->_next_sibling;
    }

    /**
     * @return FrameList|Frame[]
     */
    public function get_children()
    {
        if (isset($this->_frame_list)) {
            return $this->_frame_list;
        }

        $this->_frame_list = new FrameList($this);

        return $this->_frame_list;
    }

    // Layout property accessors

    /**
     * Containing block dimensions
     *
     * @param $i string The key of the wanted containing block's dimension (x, y, x, h)
     *
     * @return float[]|float
     */
    public function get_containing_block($i = null)
    {
        if (isset($i)) {
            return $this->_containing_block[$i];
        }

        return $this->_containing_block;
    }

    /**
     * Block position
     *
     * @param $i string The key of the wanted position value (x, y)
     *
     * @return array|float
     */
    public function get_position($i = null)
    {
        if (isset($i)) {
            return $this->_position[$i];
        }

        return $this->_position;
    }

    //........................................................................

    /**
     * Return the height of the margin box of the frame, in pt.  Meaningless
     * unless the height has been calculated properly.
     *
     * @return float
     */
    public function get_margin_height()
    {
        $style = $this->_style;

        return $style->length_in_pt(array(
            $style->height,
            $style->margin_top,
            $style->margin_bottom,
            $style->border_top_width,
            $style->border_bottom_width,
            $style->padding_top,
            $style->padding_bottom
        ), $this->_containing_block["h"]);
    }

    /**
     * Return the width of the margin box of the frame, in pt.  Meaningless
     * unless the width has been calculated properly.
     *
     * @return float
     */
    public function get_margin_width()
    {
        $style = $this->_style;

        return $style->length_in_pt(array(
            $style->width,
            $style->margin_left,
            $style->margin_right,
            $style->border_left_width,
            $style->border_right_width,
            $style->padding_left,
            $style->padding_right
        ), $this->_containing_block["w"]);
    }

    /**
     * @return float
     */
    public function get_break_margins()
    {
        $style = $this->_style;

        return $style->length_in_pt(array(
            //$style->height,
            $style->margin_top,
            $style->margin_bottom,
            $style->border_top_width,
            $style->border_bottom_width,
            $style->padding_top,
            $style->padding_bottom
        ), $this->_containing_block["h"]);
    }

    /**
     * Return the padding box (x,y,w,h) of the frame
     *
     * @return array
     */
    public function get_padding_box()
    {
        $style = $this->_style;
        $cb = $this->_containing_block;

        $x = $this->_position["x"] +
            $style->length_in_pt(array($style->margin_left,
                    $style->border_left_width),
                $cb["w"]);

        $y = $this->_position["y"] +
            $style->length_in_pt(array($style->margin_top,
                    $style->border_top_width),
                $cb["h"]);

        $w = $style->length_in_pt(array($style->padding_left,
                $style->width,
                $style->padding_right),
            $cb["w"]);

        $h = $style->length_in_pt(array($style->padding_top,
                $style->height,
                $style->padding_bottom),
            $cb["h"]);

        return array(0 => $x, "x" => $x,
            1 => $y, "y" => $y,
            2 => $w, "w" => $w,
            3 => $h, "h" => $h);
    }

    /**
     * Return the border box of the frame
     *
     * @return array
     */
    public function get_border_box()
    {
        $style = $this->_style;
        $cb = $this->_containing_block;

        $x = $this->_position["x"] + $style->length_in_pt($style->margin_left, $cb["w"]);

        $y = $this->_position["y"] + $style->length_in_pt($style->margin_top, $cb["h"]);

        $w = $style->length_in_pt(array($style->border_left_width,
                $style->padding_left,
                $style->width,
                $style->padding_right,
                $style->border_right_width),
            $cb["w"]);

        $h = $style->length_in_pt(array($style->border_top_width,
                $style->padding_top,
                $style->height,
                $style->padding_bottom,
                $style->border_bottom_width),
            $cb["h"]);

        return array(0 => $x, "x" => $x,
            1 => $y, "y" => $y,
            2 => $w, "w" => $w,
            3 => $h, "h" => $h);
    }

    /**
     * @param null $opacity
     *
     * @return float
     */
    public function get_opacity($opacity = null)
    {
        if ($opacity !== null) {
            $this->set_opacity($opacity);
        }

        return $this->_opacity;
    }

    /**
     * @return LineBox
     */
    public function &get_containing_line()
    {
        return $this->_containing_line;
    }

    //........................................................................

    // Set methods
    /**
     * @param $id
     */
    public function set_id($id)
    {
        $this->_id = $id;

        // We can only set attributes of DOMElement objects (nodeType == 1).
        // Since these are the only objects that we can assign CSS rules to,
        // this shortcoming is okay.
        if ($this->_node->nodeType == XML_ELEMENT_NODE) {
            $this->_node->setAttribute("frame_id", $id);
        }
    }

    /**
     * @param Style $style
     */
    public function set_style(Style $style)
    {
        if (is_null($this->_style)) {
            $this->_original_style = clone $style;
        }

        //$style->set_frame($this);
        $this->_style = $style;
    }

    /**
     * @param \Dompdf\FrameDecorator\AbstractFrameDecorator $decorator
     */
    public function set_decorator(FrameDecorator\AbstractFrameDecorator $decorator)
    {
        $this->_decorator = $decorator;
    }

    /**
     * @param null $x
     * @param null $y
     * @param null $w
     * @param null $h
     */
    public function set_containing_block($x = null, $y = null, $w = null, $h = null)
    {
        if (is_array($x)) {
            foreach ($x as $key => $val) {
                $$key = $val;
            }
        }

        if (is_numeric($x)) {
            $this->_containing_block["x"] = $x;
        }

        if (is_numeric($y)) {
            $this->_containing_block["y"] = $y;
        }

        if (is_numeric($w)) {
            $this->_containing_block["w"] = $w;
        }

        if (is_numeric($h)) {
            $this->_containing_block["h"] = $h;
        }
    }

    /**
     * @param null $x
     * @param null $y
     */
    public function set_position($x = null, $y = null)
    {
        if (is_array($x)) {
            list($x, $y) = array($x["x"], $x["y"]);
        }

        if (is_numeric($x)) {
            $this->_position["x"] = $x;
        }

        if (is_numeric($y)) {
            $this->_position["y"] = $y;
        }
    }

    /**
     * @param $opacity
     */
    public function set_opacity($opacity)
    {
        $parent = $this->get_parent();
        $base_opacity = (($parent && $parent->_opacity !== null) ? $parent->_opacity : 1.0);
        $this->_opacity = $base_opacity * $opacity;
    }

    /**
     * @param LineBox $line
     */
    public function set_containing_line(LineBox $line)
    {
        $this->_containing_line = $line;
    }

    /**
     * Tells if the frame is a text node
     *
     * @return bool
     */
    public function is_text_node()
    {
        if (isset($this->_is_cache["text_node"])) {
            return $this->_is_cache["text_node"];
        }

        return $this->_is_cache["text_node"] = ($this->get_node()->nodeName === "#text");
    }

    /**
     * @return bool
     */
    public function is_positionned()
    {
        if (isset($this->_is_cache["positionned"])) {
            return $this->_is_cache["positionned"];
        }

        $position = $this->get_style()->position;

        return $this->_is_cache["positionned"] = in_array($position, Style::$POSITIONNED_TYPES);
    }

    /**
     * @return bool
     */
    public function is_absolute()
    {
        if (isset($this->_is_cache["absolute"])) {
            return $this->_is_cache["absolute"];
        }

        $position = $this->get_style()->position;

        return $this->_is_cache["absolute"] = ($position === "absolute" || $position === "fixed");
    }

    /**
     * @return bool
     */
    public function is_block()
    {
        if (isset($this->_is_cache["block"])) {
            return $this->_is_cache["block"];
        }

        return $this->_is_cache["block"] = in_array($this->get_style()->display, Style::$BLOCK_TYPES);
    }

    /**
     * @return bool
     */
    