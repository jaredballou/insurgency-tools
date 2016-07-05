<?php
class PolygonMaker
{

    private $image;
    private $width;
    private $height;
    private $vImage;

    public function __construct($width, $height)
    {
        // create a GD image to display results and debug
        $this->width = $width;
        $this->height = $height;
        $this->image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($this->image, 0xFF, 0xFF, 0xFF);
        imagefill($this->image, 0, 0, $white);
        imagesetthickness($this->image, 3);
    }

    public function __destruct()
    {
        imagedestroy($this->image);
    }

    public function display()
    {
        // Display gd image as png
        header("Content-type: image/png");
        imagepng($this->image);
    }

    public function drawRectangles(array $rectangles, $r, $g, $b)
    {
        // Draw rectangles as they are inside the gd image
        foreach ($rectangles as $rectangle)
        {
            list($tx, $ty) = $rectangle[0];
            list($bx, $by) = $rectangle[1];
            $color = imagecolorallocate($this->image, $r, $g, $b);
            imagerectangle($this->image, $tx, $ty, $bx, $by, $color);
        }
    }

    public function findPolygonsPoints(array $rectangles)
    {
        // Create a virtual image where rectangles will be "drawn"
        $this->_createVirtualImage($rectangles);

        $polygons = array ();

        // Searches for all polygons inside the virtual image
        while (!is_null($beginPoint = $this->_findPolygon()))
        {
            $polygon = array ();

            // Push the first point
            $polygon[] = $this->_cleanAndReturnPolygonPoint($beginPoint);
            $point = $beginPoint;

            // Try to go up, down, left, right until there is no more point
            while ($point = $this->_getNextPolygonPoint($point))
            {
                // Push the found point
                $polygon[] = $this->_cleanAndReturnPolygonPoint($point);
            }

            // Push the first point at the end to close polygon
            $polygon[] = $beginPoint;

            // Add the polygon to the list, in case of several polygons in the image
            $polygons[] = $polygon;
        }

        $this->vImage = null;
        return $polygons;
    }

    private function _createVirtualImage(array $rectangles)
    {
        // Create a 0-filled grid where will be stored rectangles
        $this->vImage = array_fill(0, $this->height, array_fill(0, $this->width, 0));

        // Draw each rectangle to that grid (each pixel increments the corresponding value of the grid of 1)
        foreach ($rectangles as $rectangle)
        {
            list($x1, $y1, $x2, $y2) = array ($rectangle[0][0], $rectangle[0][1], $rectangle[1][0], $rectangle[1][1]);
            $this->_drawVirtualLine($x1, $y1, $x1, $y2); // top-left, bottom-left
            $this->_drawVirtualLine($x2, $y1, $x2, $y2); // top-right, bottom-right
            $this->_drawVirtualLine($x1, $y1, $x2, $y1); // top-left, top-right
            $this->_drawVirtualLine($x1, $y2, $x2, $y2); // bottom-left, bottom-right
        }

        // Remove all pixels that are scored > 1 (that's our logic!)
        for ($y = 0; ($y < $this->height); $y++)
        {
            for ($x = 0; ($x < $this->width); $x++)
            {
                $value = &$this->vImage[$y][$x];
                $value = $value > 1 ? 0 : $value;
            }
        }
    }

    private function _drawVirtualLine($x1, $y1, $x2, $y2)
    {
        // Draw a vertial line in the virtual image
        if ($x1 == $x2)
        {
            if ($y1 > $y2)
            {
                list($x1, $y1, $x2, $y2) = array ($x2, $y2, $x1, $y1);
            }
            for ($y = $y1; ($y <= $y2); $y++)
            {
                $this->vImage[$y][$x1]++;
            }
        }

        // Draw an horizontal line in the virtual image
        if ($y1 == $y2)
        {
            if ($x1 > $x2)
            {
                list($x1, $y1, $x2, $y2) = array ($x2, $y2, $x1, $y1);
            }
            for ($x = $x1; ($x <= $x2); $x++)
            {
                $this->vImage[$y1][$x]++;
            }
        }

        // Force corners to be 1 (because one corner is at least used 2 times but we don't want to remove them)
        $this->vImage[$y1][$x1] = 1;
        $this->vImage[$y1][$x2] = 1;
        $this->vImage[$y2][$x1] = 1;
        $this->vImage[$y2][$x2] = 1;
    }

    private function _findPolygon()
    {
        // We're looking for the first point in the virtual image
        foreach ($this->vImage as $y => $row)
        {
            foreach ($row as $x => $value)
            {
                if ($value == 1)
                {
                    // Removes alone points ( every corner have been set to 1, but some corners are alone (eg: middle  of 4 rectangles)
                    if ((!$this->_hasPixelAtBottom($x, $y)) && (!$this->_hasPixelAtTop($x, $y))
                       && (!$this->_hasPixelAtRight($x, $y)) && (!$this->_hasPixelAtLeft($x, $y)))
                    {
                        $this->vImage[$y][$x] = 0;
                        continue;
                    }
                    return array ($x, $y);
                }
            }
        }
        return null;
    }

    private function _hasPixelAtBottom($x, $y)
    {
        // The closest bottom point is a point positionned at (x, y + 1)
        return $this->_hasPixelAt($x, $y + 1);
    }

    private function _hasPixelAtTop($x, $y)
    {
        // The closest top point is a point positionned at (x, y - 1)
        return $this->_hasPixelAt($x, $y - 1);
    }

    private function _hasPixelAtLeft($x, $y)
    {
        // The closest left point is a point positionned at (x - 1, y)
        return $this->_hasPixelAt($x - 1, $y);
    }

    private function _hasPixelAtRight($x, $y)
    {
        // The closest right point is a point positionned at (x + 1, y)
        return $this->_hasPixelAt($x + 1, $y);
    }

    private function _hasPixelAt($x, $y)
    {
        // Check if the pixel (x, y) exists
        return ((isset($this->vImage[$y])) && (isset($this->vImage[$y][$x])) && ($this->vImage[$y][$x] > 0));
    }

    private function _cleanAndReturnPolygonPoint(array $point)
    {
        // Remove a point from the virtual image
        list($x, $y) = $point;
        $this->vImage[$y][$x] = 0;
        return $point;
    }

    private function _getNextPolygonPoint(array $point)
    {
        list($x, $y) = $point;

        // Initialize modifiers, to move to the right, bottom, left or top.
        $directions = array(
                array(1, 0), // right
                array(0, 1), // bottom
                array(-1, 0), // left
                array(0, -1), // top
        );

        // Try to get to one direction, if we can go ahead, there is a following corner
        $return = null;
        foreach ($directions as $direction)
        {
            list($xModifier, $yModifier) = $direction;
            if (($return = $this->_iterateDirection($x, $y, $xModifier, $yModifier)) !== null)
            {
                return $return;
            }
        }

        // the point is alone : we are at the end of the polygon
        return $return;
    }

    private function _iterateDirection($x, $y, $xModifier, $yModifier)
    {
        // This method follows points in a direction until the last point
        $return = null;
        while ($this->_hasPixelAt($x + $xModifier, $y + $yModifier))
        {
            $x = $x + $xModifier;
            $y = $y + $yModifier;

            // Important : we remove the point so we'll not get back when moving
            $return = $this->_cleanAndReturnPolygonPoint(array ($x, $y));
        }

        // The last point is a corner of the polygon because if it has no following point, we change direction
        return $return;
    }

    /**
     * This method draws a polygon with the given points. That's to check if
     * our calculations are valid.
     *
     * @param array $points An array of points that define the polygon
     */
    public function drawPolygon(array $points, $r, $g, $b)
    {
        $count = count($points);
        for ($i = 0; ($i < $count); $i++)
        {
            // Draws a line between the current and the next point until the last point is reached
            if (array_key_exists($i + 1, $points))
            {
                list($x1, $y1) = $points[$i];
                list($x2, $y2) = $points[$i + 1];
                $black = imagecolorallocate($this->image, $r, $g, $b);
                imageline($this->image, $x1, $y1, $x2, $y2, $black);
            }
        }
    }

}
?>
