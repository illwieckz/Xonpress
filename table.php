<?php
/**
 * \file
 * \author Mattia Basaglia
 * \copyright Copyright 2015-2016 Mattia Basaglia
 * \section License
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class HTML_Element
{
    public $element_name;
    public $simple = false;
    public $attributes = array();

    function __construct($element_name, $simple = false, $attributes = array() )
    {
        $this->element_name = $element_name;
        $this->simple = $simple;
        $this->attributes = $attributes;
    }

    function contents()
    {
        return "";
    }

    function __toString()
    {
        $html = "<{$this->element_name}";
        foreach ( $this->attributes as $name => $value )
            if ( $value !== "" )
                $html .= ' '.htmlentities($name, ENT_QUOTES).'="'.
                    htmlentities($value, ENT_QUOTES).'"';
        if ( $this->simple )
            $html .= "/>";
        else
            $html .= ">".$this->contents()."</{$this->element_name}>";
        return $html;
    }
}

class HTML_TableCell extends HTML_Element
{
    public $contents;

    function __construct($contents, $header=false, $attributes = array())
    {
        parent::__construct($header ? "th" : "td", false, $attributes);
        $this->contents = $contents;
    }

    function contents()
    {
        return $this->contents;
    }
}

class HTML_TableRow extends HTML_Element
{
    public $cells;

    function __construct($cells = array(), $attributes = array())
    {
        parent::__construct("tr", false, $attributes);

        if ( !is_array($cells) )
        {
            $cells = array($cells);
        }

        $this->cells = $cells;
    }

    function contents()
    {
        return implode("", $this->cells);
    }
}

/**
 * \brief Simple HTML table builder
 */
class HTML_Table extends HTML_Element
{
    public $rows = array();

    function __construct($css_class="")
    {
        parent::__construct("table");
        $this->attributes["class"] = $css_class;
    }

    function simple_header($data, $escape=true)
    {
        if ( $escape )
            $data = htmlentities($data);

        $this->rows[] = new HTML_TableRow(
            new HTML_TableCell($data, true, array('colspan'=>2))
        );
    }


    function simple_row($header, $data, $escape=true)
    {
        if ( $escape )
        {
            $header = htmlentities($header);
            $data = htmlentities($data);
        }
        $this->rows[] = new HTML_TableRow(array(
            new HTML_TableCell($header, true),
            new HTML_TableCell($data)
        ));
    }

    private function generic_row($cell_contents, $escape, $header, $row_attributes)
    {
        if ( !is_array($cell_contents) )
            $cell_contents = array($cell_contents);
        $row = array();
        foreach ( $cell_contents as $data )
        {
            if ($data instanceof HTML_TableCell)
                $row []= $data;
            else
                $row []= new HTML_TableCell($escape ? htmlentities($data) : $data,  $header);
        }
        $this->rows []= new HTML_TableRow($row, $row_attributes);
    }

    function header_row($cell_contents, $escape=true, $row_attributes=array())
    {
        $this->generic_row($cell_contents, $escape, true, $row_attributes);
    }

    function data_row($cell_contents, $escape=true, $row_attributes=array())
    {
        $this->generic_row($cell_contents, $escape, false, $row_attributes);
    }

    function contents()
    {
        return implode("\n", $this->rows);
    }
}

class HTML_Link extends HTML_Element
{
    public $text;
    public $destination;

    function __construct($text, $destination=null, $escape_text=true, $attributes=array() )
    {
        $attributes["href"] = $destination == null ? $text : $destination;
        parent::__construct("a", false, $attributes);

        if ( $escape_text )
            $this->text = htmlentities($text);
        else
            $this->text = $text;
    }

    function contents()
    {
        return $this->text;
    }
}
