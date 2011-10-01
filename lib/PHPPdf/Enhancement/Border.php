<?php

/*
 * Copyright 2011 Piotr Śliwa <peter.pl7@gmail.com>
 *
 * License information is in LICENSE file
 */

namespace PHPPdf\Enhancement;

use PHPPdf\Node\Page,
    PHPPdf\Node\Node,
    PHPPdf\Util\Boundary,
    PHPPdf\Util\UnitConverter,
    PHPPdf\Engine\GraphicsContext,
    PHPPdf\Document;

/**
 * Enhance node by drawing border
 *
 * Border can be drawed in specific edges by passing type parameter. Size, radius and
 * line style also may by customized.
 *
 * Allowed styles:
 * * solid - solid line
 * * dotted - dotted line
 * * array as pattern - define your own line dashing pattern
 *
 * @author Piotr Śliwa <peter.pl7@gmail.com>
 */
class Border extends Enhancement
{
    const TYPE_NONE = 0;
    const TYPE_TOP = 1;
    const TYPE_RIGHT = 2;
    const TYPE_BOTTOM = 4;
    const TYPE_LEFT = 8;
    const TYPE_ALL = 15;

    const STYLE_SOLID = GraphicsContext::DASHING_PATTERN_SOLID;
    const STYLE_DOTTED = GraphicsContext::DASHING_PATTERN_DOTTED;

    private $type = self::TYPE_ALL;
    private $size;
    private $style = self::STYLE_SOLID;
    private $position = 0;

    public function __construct($color = null, $type = self::TYPE_ALL, $size = 1, $radius = null, $style = self::STYLE_SOLID, $position = 0)
    {
        parent::__construct($color, $radius);

        $this->setType($type);
        $this->setStyle($style);
        $this->setSize($size);
        $this->setPosition($position);
    }

    private function setType($type)
    {
        if(!is_numeric($type))
        {
            $types = explode('+', $type);

            $this->type = 0;
            foreach($types as $type)
            {
                $this->type |= $this->getConstantValue('TYPE', $type);
            }
        }
        else
        {
            $this->type = $type;
        }
    }

    private function setStyle($style)
    {
        if(!is_numeric($style) && !is_array($style))
        {
            if(strpos($style, ' ') !== false)
            {
                $style = explode(' ', $style);
            }
            else
            {
                $style = $this->getConstantValue('STYLE', $style);
            }
        }

        $this->style = $style;
    }

    private function setSize($size)
    {
        $this->size = $size;
    }

    private function setPosition($position)
    {
        $this->position = $position;
    }

    protected function doEnhance($graphicsContext, Node $node, Document $document)
    {
        $graphicsContext->setLineDashingPattern($this->style);
        $size = $document->convertUnit($this->size);
        $graphicsContext->setLineWidth($size);
        $boundary = $node->getBoundary();

        $points = $this->getPointsWithPositionCorrection($document, $boundary);

        if($this->getRadius() !== null)
        {
            $firstPoint = $points[3];
            $diagonalPoint = $points[1];

            $this->drawRoundedBoundary($graphicsContext, $firstPoint[0], $firstPoint[1], $diagonalPoint[0], $diagonalPoint[1], GraphicsContext::SHAPE_DRAW_STROKE);
        }
        elseif($this->type == self::TYPE_ALL)
        {
            $this->drawBoundary($graphicsContext, $points, GraphicsContext::SHAPE_DRAW_STROKE, $size/2);
        }
        else
        {
            $halfSize = $size/2;
            foreach(array(self::TYPE_TOP, self::TYPE_RIGHT, self::TYPE_BOTTOM, self::TYPE_LEFT) as $type)
            {
                if($this->type & $type)
                {
                    $log = 1;
                    for($index=0; $log < $type; $log = $log << 1)
                    {
                        $index++;
                    }

                    list($x1, $y1, $x2, $y2) = $this->getPointsForFixedLine($points, $index, $halfSize);

                    $graphicsContext->drawLine($x1, $y1, $x2, $y2);
                }
            }
        }
    }

    private function getPointsWithPositionCorrection(UnitConverter $converter, Boundary $boundary)
    {
        $points = array();

        $xSignMatrix = array(-1, 1, 1, -1, -1);
        $ySignMatrix = array(1, 1, -1, -1, 1);
        
        $position = $converter->convertUnit($this->position);

        foreach($boundary->getPoints() as $index => $point)
        {
            $xSign = isset($xSignMatrix[$index]) ? $xSignMatrix[$index] : 1;
            $ySign = isset($ySignMatrix[$index]) ? $ySignMatrix[$index] : 1;

            $points[$index] = array($point->getX() + $position*$xSign, $point->getY() + $position*$ySign);
        }

        return $points;
    }

    private function getPointsForFixedLine($points, $firstPointIndex, $halfSize)
    {
        $x1 = $points[$firstPointIndex][0];
        $y1 = $points[$firstPointIndex][1];
        $x2 = $points[$firstPointIndex+1][0];
        $y2 = $points[$firstPointIndex+1][1];

        if($x1 == $x2)
        {
            if($y1 >= $y2)
            {
                $y1 += $halfSize;
                $y2 -= $halfSize;
            }
            else
            {
                $y1 -= $halfSize;
                $y2 += $halfSize;
            }
        }

        if($y1 == $y2)
        {
            if($x1 <= $x2)
            {
                $x1 -= $halfSize;
                $x2 += $halfSize;
            }
            else
            {
                $x1 += $halfSize;
                $x2 -= $halfSize;
            }
        }

        return array($x1, $y1, $x2, $y2);
    }

    public function getPriority()
    {
        return Document::DRAWING_PRIORITY_BACKGROUND3;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getStyle()
    {
        return $this->style;
    }
}