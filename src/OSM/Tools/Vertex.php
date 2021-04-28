<?php
/*------------------------------------------------------------------------------
** File:        vertex.php
** Description: PHP class for a polygon vertex. Used as the base object to
**              build a class of polygons. 
** Version:     1.6
** Author:      Brenor Brophy
** Email:       brenor dot brophy at gmail dot com
** Homepage:    www.brenorbrophy.com 
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2005-2010 BRENOR BROPHY
**
** The source code included in this package is free software; you can
** redistribute it and/or modify it under the terms of the GNU General Public
** License as published by the Free Software Foundation. This license can be
** read at:
**
** http://www.opensource.org/licenses/gpl-license.php
**
** This program is distributed in the hope that it will be useful, but WITHOUT 
** ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
** FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. 
**------------------------------------------------------------------------------
**
** Based on the paper "Efficient Clipping of Arbitary Polygons" by Gunther
** Greiner (greiner at informatik dot uni-erlangen dot de) and Kai Hormann
** (hormann at informatik dot tu-clausthal dot de), ACM Transactions on Graphics
** 1998;17(2):71-83.
**
** Available at:
**
**      http://www2.in.tu-clausthal.de/~hormann/papers/Greiner.1998.ECO.pdf
**
** Another useful site describing the algorithm and with some example
** C code by Ionel Daniel Stroe is at:
**
**              http://davis.wpi.edu/~matt/courses/clipping/
**
** The algorithm is extended by Brenor Brophy to allow polygons with
** arcs between vertices.
**
** Rev History
** -----------------------------------------------------------------------------
** 1.0  08/25/2005      Initial Release
** 1.1  09/04/2005      Added software license language to header comments
** 1.2  09/07/2005      Minor fix to polygon.php - no change to this file
** 1.3  04/16/2006      Minor fix to polygon.php - no change to this file
** 1.4  03/19/2009      Minor change to comments in this file. Significant
**                      change to polygon.php
** 1.5  07/16/2009      No change to this file
** 1.6  15/05/2010      No change to this file
*/

namespace OSM\Tools;

class Segment
{
/*------------------------------------------------------------------------------
** This class contains the information about the segments between vetrices. In
** the original algorithm these were just lines. In this extended form they
** may also be arcs. By creating a separate object for the segment and then
** referencing to it forward & backward from the two vertices it links it is
** easy to track in various directions through the polygon linked list.
*/
        var     $xc, $yc;               // Coordinates of the center of the arc
        var $d;                         // Direction of the arc, -1 = clockwise, +1 = anti-clockwise,
                                        // A 0 indicates this is a line
        /*
        ** Construct a segment
        */
        function __construct ($xc=0, $yc=0, $d=0)
        {
                $this->xc = $xc; $this->yc = $yc; $this->d = $d;
        }       
        /*
        ** Return the contents of a segment
        */
        function Xc () { return $this->xc ;}
        function Yc () { return $this->yc ;}
        function d () { return $this->d ;}
        /*
        ** Set Xc/Yc
        */
        function setXc ($xc) { $this->xc = $xc; }
        function setYc ($yc) { $this->yc = $yc; }
} // end of class segment

class Vertex
{
/*------------------------------------------------------------------------------
** This class is almost exactly as described in the paper by Gunter/Greiner
** with some minor additions for segments. Basically it is a node in a doubly
** linked list with a few extra control variables used by the algorithm
** for boolean operations. The only methods in the class are used to encapsulate
** the properties.
*/
        var $x, $y;                 // Coordinates of the vertex
        var $nextV, $prevV;         // References to the next and previous vetices in the polygon
        var $nSeg, $pSeg;           // References to next & previous segments
        var $nextPoly;              // Reference to another polygon in a list
        var $intersect;             // TRUE if vertex is an intersection (with another polgon)
        var $neighbor;              // Ref to the corresponding intersection vertex in another polygon 
        var $alpha;                 // Intersection points relative distance from previous vertex
        var $entry;                 // TRUE if intersection is an entry point to another polygon
                                    // FALSE if it is an exit point
        var $checked;               // Boolean - TRUE if vertex has been checked
        var $id;                    // A random ID assigned to make the vertex unique

        /*
        ** Construct a vertex
        */
        function __construct ($x, $y, $xc=0, $yc=0, $d=0,
                         $nextV=NULL, $prevV=NULL, $nextPoly=NULL,
                         $intersect = FALSE, $neighbor=NULL, $alpha=0, $entry=TRUE, $checked=FALSE)
        {
                $this->x = $x; $this->y = $y;
                $this->nextV = $nextV; $this->prevV = $prevV; $this->nextPoly = $nextPoly;
                $this->intersect = $intersect; $this->neighbor = $neighbor; $this->alpha = $alpha;
                $this->entry = $entry; $this->checked = $checked;
                $this->id = mt_rand(0,1000000);
                /*
                ** Create a new Segment and set a reference to it. Segments are always
                ** placed after the vertex
                */
                $this->nSeg = new Segment ($xc, $yc, $d);
                $this->pSeg = NULL;
        }
        /*
        ** Get id
        */
        function id() { return $this->id; }
        /*
        ** Get/Set x/y
        */
        function X() { return $this->x; }
        function setX($x) { $this->x = $x; }
        function Y() { return $this->y; }
        function setY($y) { $this->y = $y; }
        /*
        ** Return contents of a segment. Default is to always return the next
        ** segment, unless previous is specified. The special case is where
        ** the vertex is an intersection, in that case the contents of the
        ** neighbor vertex's next or prev segment is returned. Whether next
        ** or previous is returned depends upon the entry value of the vertex
        ** This method ensures that the correct segment data is returned when
        ** a result polygon is being constructed.
        **
        ** For $g Next == TRUE and Prev == FALSE
        */
        function Xc ($g = TRUE)
        {
                if ($this->isIntersect())
                {
                        if ($this->neighbor->isEntry())
                                return $this->neighbor->nSeg->Xc();
                        else
                                return $this->neighbor->pSeg->Xc();
                }
                else
                        if ($g) return $this->nSeg->Xc(); else return $this->pSeg->Xc();
        }
        function Yc ($g = TRUE)
        {
                if ($this->isIntersect())
                {
                        if ($this->neighbor->isEntry())
                                return $this->neighbor->nSeg->Yc();
                        else
                                return $this->neighbor->pSeg->Yc();
                }
                else
                        if ($g) return $this->nSeg->Yc(); else return $this->pSeg->Yc();
        }
				
        function d ($g = TRUE)
        {
                if ($this->isIntersect())
                {
                        if ($this->neighbor->isEntry())
                                return $this->neighbor->nSeg->d();
                        else
                                return (-1*$this->neighbor->pSeg->d());
                }
                else
                        if ($g) return $this->nSeg->d(); else return (-1*$this->pSeg->d());
        }
        /*
        ** Set Xc/Yc (Only for segment pointed to by Nseg)
        */
        function setXc ($xc) { $this->nSeg->setXc($xc); }
        function setYc ($yc) { $this->nSeg->setYc($yc); }
        /*
        ** Set/Get the reference to the next vertex
        */
        function setNext ($nextV){ $this->nextV = $nextV; }
        function &Next (){ return $this->nextV; }
        /*
        ** Set/Get the reference to the previous vertex
        */
        function setPrev ($prevV){ $this->prevV = $prevV; }
        function &Prev (){ return $this->prevV; }
        /*
        ** Set/Get the reference to the next segment
        */
        function setNseg ($nSeg){ $this->nSeg = $nSeg; }
        function &Nseg (){ return $this->nSeg; }
        /*
        ** Set/Get the reference to the previous segment
        */
        function setPseg ($pSeg){ $this->pSeg = $pSeg; }
        function &Pseg (){ return $this->pSeg; }
        /*
        ** Set/Get reference to the next Polygon
        */
        function setNextPoly ($nextPoly){ $this->nextPoly = $nextPoly; }
        function &NextPoly (){ return $this->nextPoly; }
        /*
        ** Set/Get reference to neighbor polygon
        */
        function setNeighbor ($neighbor){ $this->neighbor = $neighbor; }
        function &Neighbor (){ return $this->neighbor; }
        /*
        ** Get alpha
        */
        function Alpha (){ return $this->alpha; }
        /*
        ** Test for intersection
        */
        function isIntersect (){ return $this->intersect; }
        /*
        ** Set/Test for checked flag
        */      
        function setChecked($check = TRUE)
        {
                $this->checked = $check;
                if ($this->neighbor && !$this->neighbor->isChecked())
                        $this->neighbor->setChecked();
        }
        function isChecked () { return $this->checked; }
        /*
        ** Set/Test entry
        */
        function setEntry ($entry = TRUE){ $this->entry = $entry; }
        function isEntry (){ return $this->entry; }
        /*
        ** Print Vertex used for debugging
        */
        function print_vertex()
        {
                print("(".$this->x.")(".$this->y.") ");
                if ($this->nSeg->d() != 0)
                        print(" c(".$this->nSeg->Xc().")(".$this->nSeg->Yc().")(".$this->nSeg->d().") ");
                if ($this->intersect) {
                        print("Intersection with alpha=".$this->alpha." ");
                        if ($this->entry)
                                print(" Entry");
                        else
                                print(" Exit");}
                if ($this->checked)
                        print(" Checked");
                else
                        print(" Unchecked");
                print("<br>");
        }
} //end of class vertex
