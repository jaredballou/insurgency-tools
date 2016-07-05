<?php 

class RectUnion {
    private $x, $y;
    private $sides;
    private $points;

    function __construct() {
        $this->x = array();
        $this->y = array();
        $this->sides = array();
        $this->points = array();
    }

    function addRect($r) {
        extract($r);
        $this->x[] = $x1;
        $this->x[] = $x2;
        $this->y[] = $y1;
        $this->y[] = $y2;
        if ($x1 > $x2) { $tmp = $x1; $x1 = $x2; $x2 = $tmp; }
        if ($y1 > $y2) { $tmp = $y1; $y1 = $y2; $y2 = $tmp; }
        $this->sides[] = array($x1, $y1, $x2, $y1);
        $this->sides[] = array($x2, $y1, $x2, $y2);
        $this->sides[] = array($x1, $y2, $x2, $y2);
        $this->sides[] = array($x1, $y1, $x1, $y2);
    }

    function splitSides() {
       $result = array();
       $this->x = array_unique($this->x);
       $this->y = array_unique($this->y);
       sort($this->x);
       sort($this->y);
       foreach ($this->sides as $i => $s) {
           if ($s[0] - $s[2]) {     // Horizontal
               foreach ($this->x as $xx) {
                   if (($xx > $s[0]) && ($xx < $s[2])) {
                       $result[] = array($s[0], $s[1], $xx, $s[3]);
                       $s[0] = $xx;
                   }
               }
           } else {                 // Vertical
               foreach ($this->y as $yy) {
                   if (($yy > $s[1]) && ($yy < $s[3])) {
                       $result[] = array($s[0], $s[1], $s[2], $yy);
                       $s[1] = $yy;
                   }
               }
           }
           $result[] = $s;
       }
       return($result);
    }

    function removeDuplicates($sides) {
        $x = array();
        foreach ($sides as $i => $s) {
            @$x[$s[0].','.$s[1].','.$s[2].','.$s[3]]++;
        }
        foreach ($x as $s => $n) {
            if ($n > 1) {
              unset($x[$s]);
            } else {
              $this->points[] = explode(",", $s);
            }
        }
        return($x);
    }

    function drawPoints($points, $outfile = null) {
        $xs = $this->x[count($this->x) - 1] + $this->x[0];
        $ys = $this->y[count($this->y) - 1] + $this->y[0];
        $img = imagecreate($xs, $ys);
        if ($img !== FALSE) {
            $wht = imagecolorallocate($img, 255, 255, 255);
            $blk = imagecolorallocate($img, 0, 0, 0);
            $red = imagecolorallocate($img, 255, 0, 0);
            imagerectangle($img, 0, 0, $xs - 1, $ys - 1, $red);
            $oldp = $points[0];
            for ($i = 1; $i < count($points); $i++) {
                $p = $points[$i];
                imageline($img, $oldp['x'], $oldp['y'], $p['x'], $p['y'], $blk);
                $oldp = $p;
            }
            imageline($img, $oldp['x'], $oldp['y'], $points[0]['x'], $points[0]['y'], $blk);
            if ($outfile == null) header("content-type: image/png");
            imagepng($img, $outfile);
            imagedestroy($img);
        }
    }
    function drawSides($sides, $outfile = null) {
        $xs = $this->x[count($this->x) - 1] + $this->x[0];
        $ys = $this->y[count($this->y) - 1] + $this->y[0];
        $img = imagecreate($xs, $ys);
        if ($img !== FALSE) {
            $wht = imagecolorallocate($img, 255, 255, 255);
            $blk = imagecolorallocate($img, 0, 0, 0);
            $red = imagecolorallocate($img, 255, 0, 0);
            imagerectangle($img, 0, 0, $xs - 1, $ys - 1, $red);
            foreach ($sides as $s => $n) {
                if (is_array($n)) {
                    $r = $n;
                } else {
                    $r = explode(",", $s);
                }
                imageline($img, $r['x1'], $r['y1'], $r['x2'], $r['y2'], $blk);
            }
            if ($outfile == null) header("content-type: image/png");
            imagepng($img, $outfile);
            imagedestroy($img);
        }
    }

    function getSides($sides = FALSE) {
        if ($sides === FALSE) {
            foreach ($this->sides as $r) {
                $result[] = array('x1' => $r[0], 'y1' => $r[1], 'x2' => $r[2], 'y2' => $r[3]);
            }
        } else {
            $result = array();
            foreach ($sides as $s => $n) {
                $r = explode(",", $s);
                $result[] = array('x1' => $r[0], 'y1' => $r[1], 'x2' => $r[2], 'y2' => $r[3]);
            }
        }
        return($result);
    }

    private function _nextPoint(&$points, $lastpt) {
        @extract($lastpt);
        foreach ($points as $i => $p) {
            if (($p[0] == $x) && ($p[1] == $y)) {
                unset($points[$i]);
                return(array('x' => $p[2], 'y' => $p[3]));
            } else if (($p[2] == $x) && ($p[3] == $y)) {
                unset($points[$i]);
                return(array('x' => $p[0], 'y' => $p[1]));
            }
        }
        return false;
    }

    function getPoints($points = FALSE) {
        if ($points === FALSE) $points = $this->points;
        $result = array(
            array('x' => $points[0][0], 'y' => $points[0][1])
        );
        $lastpt = array('x' => $points[0][2], 'y' => $points[0][3]);
        unset($points[0]);
        do {
            $result[] = $lastpt;
        } while ($lastpt = $this->_nextPoint($points, $lastpt));
        return($result);
    }
}

?>
