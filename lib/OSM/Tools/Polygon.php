<?php

/* ------------------------------------------------------------------------------
 * * File:		polygon.php
 * * Description:	PHP class for a polygon. 
 * * Version:		1.6
 * * Author:		Brenor Brophy
 * * Email:		brenor dot brophy at gmail dot com
 * * Homepage:	www.brenorbrophy.com 
 * *------------------------------------------------------------------------------
 * * COPYRIGHT (c) 2005-2010 BRENOR BROPHY
 * *
 * * The source code included in this package is free software; you can
 * * redistribute it and/or modify it under the terms of the GNU General Public
 * * License as published by the Free Software Foundation. This license can be
 * * read at:
 * *
 * * http://www.opensource.org/licenses/gpl-license.php
 * *
 * * This program is distributed in the hope that it will be useful, but WITHOUT 
 * * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
 * * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. 
 * *------------------------------------------------------------------------------
 * *
 * * Based on the paper "Efficient Clipping of Arbitary Polygons" by Gunther
 * * Greiner (greiner at informatik dot uni-erlangen dot de) and Kai Hormann
 * * (hormann at informatik dot tu-clausthal dot de), ACM Transactions on Graphics
 * * 1998;17(2):71-83.
 * *
 * * Available at:
 * *
 * *      http://www2.in.tu-clausthal.de/~hormann/papers/Greiner.1998.ECO.pdf
 * *
 * * Another useful site describing the algorithm and with some example
 * * C code by Ionel Daniel Stroe is at:
 * *
 * *		http://davis.wpi.edu/~matt/courses/clipping/
 * *
 * * The algorithm is extended by Brenor Brophy to allow polygons with
 * * arcs between vertices.
 * *
 * * Rev History
 * * -----------------------------------------------------------------------------
 * * 1.0	08/25/2005	Initial Release
 * * 1.1	09/04/2005	Added Move(), Rotate(), isPolyInside() and bRect() methods.
 * *                	Added software license language to header comments
 * * 1.2	09/07/2005	Fixed a divide by zero error when an attempt is made to
 * *					find an intersection between two arcs with the same center
 * *					point. Fixed an undefined variable warning for $last in
 * *					boolean() method. Added protection against divide by zero
 * *					warning in angle() method.
 * * 1.3  04/16/2006  Fixed a bug in the ints() function. The perturb() function
 * *					was being called with uninitialized parameters. This caused
 * *					incorrect clipping in cases where a vertex on one polygon
 * *					exactly fell on a line segment of the other polygon. Thanks
 * *					to Allan Wright who found the bug.
 * * 1.4  03/19/2009  Added isPolyOutside() and isPolyIntersect() methods.
 * *                  Created a new perturb function, the old one was simply
 * *                  wrong.
 * * 1.5  07/16/2009  Added isPolySelfIntersect() method.
 * * 1.6  15/05/2010  Added scale() & translate() methods. Modified move(), rotate(),
 * *                  bRect() methods to correctly handle Polygon lists.
 * *                  Fixed a bug in how the perturb function is called. It was being
 * *                  incorrectly called for intersections between lines when the
 * *                  intersection occurred outside the line segments.
 */

namespace OSM\Tools;

define("infinity", 100000000); // for places that are far far away

require_once(__DIR__ . '/Vertex.php'); // A polygon consists of vertices. So the polygon
// class is just a reference to a linked list of vertices

class Polygon {
	/* ------------------------------------------------------------------------------
	 * * This class manages a doubly linked list of vertex objects that represents
	 * * a polygon. The class consists of basic methods to manage the list
	 * * and methods to implement boolean operations between polygon objects.
	 */

	/**
	 *
	 * @var Vertex 
	 */
	var $first; // Reference to first vertex in the linked list
	// Polygons are always closed so the last vertex will point back
	// to the first, hence there is no need to store a reference to the
	// last vertex (it is just the one before the first)
	var $cnt; // Tracks number of vertices in the polygon
	var $x_max, $x_min;
	var $y_max, $y_min;

	/*
	 * * Construct a new shiny polygon
	 */

	function __construct($first = NULL) {
		$this->first = $first;
		$this->cnt = 0;
	}

	public function getXMin() {
		return $this->x_min;
	}

	public function getXMax() {
		return $this->x_max;
	}

	public function getYMin() {
		return $this->y_min;
	}

	public function getYMax() {
		return $this->y_max;
	}

	/*
	 * * Get the first vertex
	 */

	function getFirst() {
		return $this->first;
	}

	/*
	 * * Return the next polygon
	 */

	function NextPoly() {
		return $this->first->NextPoly();
	}

	/*
	 * * Print out main variables of the polygon for debugging
	 */

	function print_poly() {
		print("Polygon:<br>");
		$c = $this->first;
		do
		{
			$c->print_vertex();
			$c = $c->Next();
		}
		while ($c->id() != $this->first->id());
		if ($this->first->nextPoly)
		{
			print("Next Polygon:<br>");
			$this->first->nextPoly->print_poly();
		}
	}

	/*
	 * * Add a vertex object to the polygon (vertex is added at the "end" of the list)
	 * * Which because polygons are closed lists means it is added just before the first
	 * * vertex.
	 */

	function add(Vertex &$nv) {

		$this->x_min = min($this->x_min, $nv->X());
		$this->x_max = max($this->x_max, $nv->X());

		$this->y_min = min($this->y_min, $nv->Y());
		$this->y_max = max($this->y_max, $nv->Y());

		if ($this->cnt == 0) // If this is the first vertex in the polygon
		{
			$this->first = $nv; // Save a reference to it in the polygon
			$this->first->setNext($nv); // Set its pointer to point to itself
			$this->first->setPrev($nv); // because it is the only vertex in the list
			$ps = $this->first->Nseg(); // Get ref to the Next segment object
			$this->first->setPseg($ps); // and save it as Prev segment as well
		}
		else // At least one other vertex already exists
		{
			// $p <-> $nv <-> $n
			//    $ps     $ns
			$n = $this->first; // Get a ref to the first vertex in the list
			$p = $n->Prev(); // Get ref to previous vertex
			$n->setPrev($nv); // Add at end of list (just before first)
			$nv->setNext($n); // link the new Vertex to it
			$nv->setPrev($p); // link to the pervious EOL vertex
			$p->setNext($nv); // And finally link the previous EOL vertex
			// Segments
			$ns = $nv->Nseg(); // Get ref to the new next segment
			$ps = $p->Nseg(); // Get ref to the previous segment
			$n->setPseg($ns); // Set new previous seg for $this->first
			$nv->setPseg($ps); // Set previous seg of the new Vertex
		}
		$this->cnt++; // Increment the count of vertices
	}

	/*
	 * * Create a vertex and then add it to the polygon
	 */

	function addv($x, $y, $xc=0, $yc=0, $d=0) {
		$nv = new Vertex($x, $y, $xc, $yc, $d);
		$this->add($nv);
	}

	/*
	 * * Delete a vertex object from the polygon. This is not used by the main algorithm
	 * * but instead is used to clean-up a polygon so that a second boolean operation can
	 * * be performed.
	 */

	function &del(Vertex &$v) {
		// $p <-> $v <-> $n				   Will delete $v and $ns
		//    $ps    $ns
		$p = $v->Prev(); // Get ref to previous vertex
		$n = $v->Next(); // Get ref to next vertex
		$p->setNext($n); // Link previous forward to next 
		$n->setPrev($p); // Link next back to previous
		// Segments
		$ps = $p->Nseg(); // Get ref to previous segment
		$ns = $v->Nseg(); // Get ref to next segment
		$n->setPseg($ps); // Link next back to previous segment
		$ns = NULL;
		$v = NULL; // Free the memory
		$this->cnt--; // One less vertex
		return $n; // Return a ref to the next valid vertex
	}

	/*
	 * * Reset Polygon - Deletes all intersection vertices. This is used to
	 * * restore a polygon that has been processed by the boolean method
	 * * so that it can be processed again.
	 */

	function res() {
		$v = $this->getFirst(); // Get the first vertex
		do
		{
			$v = $v->Next(); // Get the next vertex in the polygon
			while ($v->isIntersect()) // Delete all intersection vertices
				$v = $this->del($v);
		}
		while ($v->id() != $this->first->id());
	}

	/*
	 * * Copy Polygon - Returns a reference to a new copy of the poly object
	 * * including all its vertices & their segments
	 */

	function &copy_poly() {
		$this_class = get_class($this); // Findout the class I'm in
		$n = new $this_class;
		; // Create a new instance of this class
		$v = $this->getFirst();
		do
		{
			$n->addv($v->X(), $v->Y(), $v->Xc(), $v->Yc(), $v->d());
			$v = $v->Next();
		}
		while ($v->id() != $this->first->id());
		return $n;
	}

	/*
	 * * Insert and Sort a vertex between a specified pair of vertices (start and end)
	 * *
	 * * This function inserts a vertex (most likely an intersection point) between two
	 * * other vertices. These other vertices cannot be intersections (that is they must
	 * * be actual vertices of the original polygon). If there are multiple intersection
	 * * points between the two vertices then the new Vertex is inserted based on its
	 * * alpha value.
	 */

	function insertSort(&$nv, &$s, &$e) {
		$c = $s; // Set current to the sarting vertex
		while ($c->id() != $e->id() && $c->Alpha() < $nv->Alpha())
			$c = $c->Next(); // Move current past any intersections





			
// whose alpha is lower but don't go past
		// the end vertex
		// $p <-> $nv <-> $c
		$nv->setNext($c); // Link new Vertex forward to curent one
		$p = $c->Prev(); // Get a link to the previous vertex
		$nv->setPrev($p); // Link the new Vertex back to the previous one
		$p->setNext($nv); // Link previous vertex forward to new Vertex
		$c->setPrev($nv); // Link current vertex back to the new Vertex
		// Segments
		$ps = $p->Nseg();
		$nv->setPseg($ps);
		$ns = $nv->Nseg();
		$c->setPseg($ns);
		$this->cnt++; // Just added a new Vertex
	}

	/*
	 * * return the next non intersecting vertex after the one specified
	 */

	function &nxt(&$v) {
		$c = $v; // Initialize current vertex
		while ($c && $c->isIntersect()) // Move until a non-intersection
			$c = $c->Next(); // vertex if found		
		return $c; // return that vertex
	}

	/*
	 * * Check if any unchecked intersections remain in the polygon. The boolean
	 * * method is complete when all intersections have been checked.
	 */

	function unckd_remain() {
		$remain = FALSE;
		$v = $this->first;
		do
		{
			if ($v->isIntersect() && !$v->isChecked())
				$remain = TRUE; // Set if an unchecked intersection is found
			$v = $v->Next();
		}
		while ($v->id() != $this->first->id());
		return $remain;
	}

	/*
	 * * Return a ref to the first unchecked intersection point in the polygon.
	 * * If none are found then just the first vertex is returned.
	 */

	function &first_unckd_intersect() {
		$v = $this->first;
		do // Do-While
		{ // Not yet reached end of the polygon
			$v = $v->Next(); // AND the vertex if NOT an intersection
		} // OR it IS an intersection, but has been checked already
		while ($v->id() != $this->first->id() && (!$v->isIntersect() || ( $v->isIntersect() && $v->isChecked() ) ));
		return $v;
	}

	/*
	 * * Return the distance between two points
	 */

	function dist($x1, $y1, $x2, $y2) {
		return sqrt(($x1 - $x2) * ($x1 - $x2) + ($y1 - $y2) * ($y1 - $y2));
	}

	/*
	 * * Calculate the angle between 2 points, where Xc,Yc is the center of a circle
	 * * and x,y is a point on its circumference. All angles are relative to
	 * * the 3 O'Clock position. Result returned in radians
	 */

	function angle($xc, $yc, $x1, $y1) {
		$d = $this->dist($xc, $yc, $x1, $y1); // calc distance between two points
		if ($d != 0)
			if (asin(($y1 - $yc) / $d) >= 0)
				$a1 = acos(($x1 - $xc) / $d);
			else
				$a1 = 2 * pi() - acos(($x1 - $xc) / $d);
		else
			$a1 = 0;
		return $a1;
	}

	/*
	 * * Return Alpha value for an Arc
	 * *
	 * * X1/Y1 & X2/Y2 are the end points of the arc, Xc/Yc is the center & Xi/Yi
	 * * the intersection point on the arc. $d is the direction of the arc
	 */

	function aAlpha($x1, $y1, $x2, $y2, $xc, $yc, $xi, $yi, $d) {
		$sa = $this->angle($xc, $yc, $x1, $y1); // Start Angle
		$ea = $this->angle($xc, $yc, $x2, $y2); // End Angle
		$ia = $this->angle($xc, $yc, $xi, $yi); // Intersection Angle
		if ($d == 1) // Anti-Clockwise
		{
			$arc = $ea - $sa;
			$int = $ia - $sa;
		}
		else // Clockwise
		{
			$arc = $sa - $ea;
			$int = $sa - $ia;
		}
		if ($arc < 0)
			$arc += 2 * pi();
		if ($int < 0)
			$int += 2 * pi();
		$a = $int / $arc;
		return $a;
	}

	/*
	 * * This function handles the degenerate case where a vertex of one
	 * * polygon lies directly on an edge of the other. This case can
	 * * also occur during the isInside() function, where the search
	 * * line exactly intersects with a vertex. The function works
	 * * by shortening the line by a tiny amount.
	 * *
	 * * Revision 1.4 Completely new perturb function. The old version was
	 * * simply wrong, I'm amazed it took so long to show up as a problem.
	 */

	function perturb(&$p1, &$p2, &$q1, &$q2, $aP, $aQ) {
		$PT = 0.00001; // Perturbation factor
		if ($aP == 0) // q1,q2 intersects p1 exactly, move vertex p1 closer to p2
		{
			$h = $this->dist($p1->X(), $p1->Y(), $p2->X(), $p2->Y());
			$a = ($PT * $this->dist($p1->X(), $p1->Y(), $p2->X(), $p1->Y())) / $h;
			$b = ($PT * $this->dist($p2->X(), $p2->Y(), $p2->X(), $p1->Y())) / $h;
			$p1->setX($p1->X() + $a);
			$p1->setY($p1->Y() + $b);
		}
		elseif ($aP == 1) // q1,q2 intersects p2 exactly, move vertex p2 closer to p1
		{
			$h = $this->dist($p1->X(), $p1->Y(), $p2->X(), $p2->Y());
			$a = ($PT * $this->dist($p1->X(), $p1->Y(), $p2->X(), $p1->Y())) / $h;
			$b = ($PT * $this->dist($p2->X(), $p2->Y(), $p2->X(), $p1->Y())) / $h;
			$p2->setX($p2->X() - $a);
			$p2->setY($p2->Y() - $b);
		}
		elseif ($aQ == 0) // p1,p2 intersects q1 exactly, move vertex q1 closer to q2
		{
			$h = $this->dist($q1->X(), $q1->Y(), $q2->X(), $q2->Y());
			$a = ($PT * $this->dist($q1->X(), $q1->Y(), $q2->X(), $q1->Y())) / $h;
			$b = ($PT * $this->dist($q2->X(), $q2->Y(), $q2->X(), $q1->Y())) / $h;
			$q1->setX($q1->X() + $a);
			$q1->setY($q1->Y() + $b);
		}
		elseif ($aQ == 1) // p1,p2 intersects q2 exactly, move vertex q2 closer to q1
		{
			$h = $this->dist($q1->X(), $q1->Y(), $q2->X(), $q2->Y());
			$a = ($PT * $this->dist($q1->X(), $q1->Y(), $q2->X(), $q1->Y())) / $h;
			$b = ($PT * $this->dist($q2->X(), $q2->Y(), $q2->X(), $q1->Y())) / $h;
			$q2->setX($q2->X() - $a);
			$q2->setY($q2->Y() - $b);
		}
	}

	/*
	 * * Determine the intersection between two pairs of vertices p1/p2, q1/q2
	 * *
	 * * Either or both of the segments passed to this function could be arcs.
	 * * Thus we must first determine if the intersection is line/line, arc/line
	 * * or arc/arc. Then apply the correct math to calculate the intersection(s).
	 * *
	 * * Line/Line can have 0 (no intersection) or 1 intersection
	 * * Line/Arc and Arc/Arc can have 0, 1 or 2 intersections
	 * *
	 * * The function returns TRUE is any intersections are found
	 * * The number found is returned in $n
	 * * The arrays $ix[], $iy[], $alphaP[] & $alphaQ[] return the intersection points
	 * * and their associated alpha values.
	 */

	function ints(&$p1, &$p2, &$q1, &$q2, &$n, &$ix, &$iy, &$alphaP, &$alphaQ) {

		$found = FALSE;
		$n = 0; // No intersections found yet
		$pt = $p1->d();
		$qt = $q1->d(); // Do we have Arcs or Lines?

		if ($pt == 0 && $qt == 0) // Is it line/Line ?
		{
			/* LINE/LINE
			 * * Algorithm from: http://astronomy.swin.edu.au/~pbourke/geometry/lineline2d/
			 */
			$x1 = $p1->X();
			$y1 = $p1->Y();
			$x2 = $p2->X();
			$y2 = $p2->Y();
			$x3 = $q1->X();
			$y3 = $q1->Y();
			$x4 = $q2->X();
			$y4 = $q2->Y();
			$d = (($y4 - $y3) * ($x2 - $x1) - ($x4 - $x3) * ($y2 - $y1));
			if ($d != 0)
			{ // The lines intersect at a point somewhere
				$ua = (($x4 - $x3) * ($y1 - $y3) - ($y4 - $y3) * ($x1 - $x3)) / $d;
				$ub = (($x2 - $x1) * ($y1 - $y3) - ($y2 - $y1) * ($x1 - $x3)) / $d;
				// The values of $ua and $ub tell us where the intersection occurred.
				// A value between 0 and 1 means the intersection occurred within the
				// line segment.
				// A value less tha 0 or greater than 1 means the intersection occurred
				// outside the line segment
				// A value of exactly 0 or 1 means the intersection occurred right at the
				// start or end of the line segment. For our purposes we will consider this
				// NOT to be an intersection and we will move the vertex a tiny distance
				// away from the intersecting line. 
				if ((($ua == 0 || $ua == 1 ) && ($ub >= 0 && $ub <= 1)) || (($ub == 0 || $ub == 1) && ($ua >= 0 && $ua <= 1)))
				{ // Degenerate case - vertex exactly touches a line
//					print("Perturb: P(".$p1->X().",".$p1->Y().")(".$p2->X().",".$p2->Y().") Q(".$q1->X().",".$q1->Y().")(".$q2->X().",".$q2->Y().") UA(".$ua.") UB(".$ub.")<br>");
					$this->perturb($p1, $p2, $q1, $q2, $ua, $ub);
					$found = FALSE;
				}
				elseif (($ua > 0 && $ua < 1) && ($ub > 0 && $ub < 1))
				{ // Intersection occurs on both line segments
					$x = $x1 + $ua * ($x2 - $x1);
					$y = $y1 + $ua * ($y2 - $y1);
					$iy[0] = $y;
					$ix[0] = $x;
					$alphaP[0] = $ua;
					$alphaQ[0] = $ub;
					$n = 1;
					$found = TRUE;
				}
				else
				{ // The lines do not intersect within the line segments
					$found = FALSE;
				}
			}
			else
			{ // The lines do not intersect
				$found = FALSE;
			}
		} // End of find Line/Line intersection
		elseif ($pt != 0 && $qt != 0) // Is  it Arc/Arc?
		{
			/* ARC/ARC
			 * * Algorithm from: http://astronomy.swin.edu.au/~pbourke/geometry/2circle/
			 */
			$x0 = $p1->Xc();
			$y0 = $p1->Yc(); // Center of first Arc
			$r0 = $this->dist($x0, $y0, $p1->X(), $p1->Y()); // Calc the radius
			$x1 = $q1->Xc();
			$y1 = $q1->Yc(); // Center of second Arc
			$r1 = $this->dist($x1, $y1, $q1->X(), $q1->Y()); // Calc the radius

			$dx = $x1 - $x0; // dx and dy are the vertical and horizontal 
			$dy = $y1 - $y0; // distances between the circle centers.
			$d = sqrt(($dy * $dy) + ($dx * $dx)); // Distance between the centers.

			if ($d == 0) // Don't try an find intersection if centers are the same.
			{ // Added in Rev 1.2
				$found = FALSE;
			}
			elseif ($d > ($r0 + $r1)) // Check for solvability.
			{ // no solution. circles do not intersect.
				$found = FALSE;
			}
			elseif ($d < abs($r0 - $r1))
			{ // no solution. one circle inside the other
				$found = FALSE;
			}
			else
			{
				/*
				 * * 'xy2' is the point where the line through the circle intersection
				 * * points crosses the line between the circle centers.  
				 */
				$a = (($r0 * $r0) - ($r1 * $r1) + ($d * $d)) / (2.0 * $d); // Calc the distance from xy0 to xy2.
				$x2 = $x0 + ($dx * $a / $d); // Determine the coordinates of xy2.
				$y2 = $y0 + ($dy * $a / $d);
				if ($d == ($r0 + $r1)) // Arcs touch at xy2 exactly (unlikely)
				{
					$alphaP[0] = $this->aAlpha($p1->X(), $p1->Y(), $p2->X(), $p2->Y(), $x0, $y0, $x2, $y2, $pt);
					$alphaQ[0] = $this->aAlpha($q1->X(), $q1->Y(), $q2->X(), $q2->Y(), $x1, $y1, $x2, $y2, $qt);
					if (($alphaP[0] > 0 && $alphaP[0] < 1) && ($alphaQ[0] > 0 && $alphaQ[0] < 1))
					{
						$ix[0] = $x2;
						$iy[0] = $y2;
						$n = 1;
						$found = TRUE;
					}
				}
				else // Arcs intersect at two points
				{
					$h = sqrt(($r0 * $r0) - ($a * $a)); // Calc the distance from xy2 to either
					// of the intersection points.
					$rx = -$dy * ($h / $d); // Now determine the offsets of the 
					$ry = $dx * ($h / $d); // intersection points from xy2
					$x[0] = $x2 + $rx;
					$x[1] = $x2 - $rx; // Calc the absolute intersection points.
					$y[0] = $y2 + $ry;
					$y[1] = $y2 - $ry;
					$alP[0] = $this->aAlpha($p1->X(), $p1->Y(), $p2->X(), $p2->Y(), $x0, $y0, $x[0], $y[0], $pt);
					$alQ[0] = $this->aAlpha($q1->X(), $q1->Y(), $q2->X(), $q2->Y(), $x1, $y1, $x[0], $y[0], $qt);
					$alP[1] = $this->aAlpha($p1->X(), $p1->Y(), $p2->X(), $p2->Y(), $x0, $y0, $x[1], $y[1], $pt);
					$alQ[1] = $this->aAlpha($q1->X(), $q1->Y(), $q2->X(), $q2->Y(), $x1, $y1, $x[1], $y[1], $qt);
					for ($i = 0; $i <= 1; $i++)
						if (($alP[$i] > 0 && $alP[$i] < 1) && ($alQ[$i] > 0 && $alQ[$i] < 1))
						{
							$ix[$n] = $x[$i];
							$iy[$n] = $y[$i];
							$alphaP[$n] = $alP[$i];
							$alphaQ[$n] = $alQ[$i];
							$n++;
							$found = TRUE;
						}
				}
			}
		} // End of find Arc/Arc intersection
		else // It must be Arc/Line
		{
			/* ARC/LINE
			 * * Algorithm from: http://astronomy.swin.edu.au/~pbourke/geometry/sphereline/
			 */
			if ($pt == 0) // Segment p1,p2 is the line
			{ // Segment q1,q2 is the arc
				$x1 = $p1->X();
				$y1 = $p1->Y();
				$x2 = $p2->X();
				$y2 = $p2->Y();
				$xc = $q1->Xc();
				$yc = $q1->Yc();
				$xs = $q1->X();
				$ys = $q1->Y();
				$xe = $q2->X();
				$ye = $q2->Y();
				$d = $qt;
			}
			else // Segment q1,q2 is the line
			{ // Segment p1,p2 is the arc
				$x1 = $q1->X();
				$y1 = $q1->Y();
				$x2 = $q2->X();
				$y2 = $q2->Y();
				$xc = $p1->Xc();
				$yc = $p1->Yc();
				$xs = $p1->X();
				$ys = $p1->Y();
				$xe = $p2->X();
				$ye = $p2->Y();
				$d = $pt;
			}
			$r = $this->dist($xc, $yc, $xs, $ys);
			$a = pow(($x2 - $x1), 2) + pow(($y2 - $y1), 2);
			$b = 2 * ( ($x2 - $x1) * ($x1 - $xc)
				+ ($y2 - $y1) * ($y1 - $yc) );
			$c = pow($xc, 2) + pow($yc, 2) +
				pow($x1, 2) + pow($y1, 2) -
				2 * ( $xc * $x1 + $yc * $y1) - pow($r, 2);
			$i = $b * $b - 4 * $a * $c;
			if ($i < 0.0) // no intersection
			{
				$found = FALSE;
			}
			elseif ($i == 0.0) // one intersection
			{
				if ($a != 0)
					$mu = -$b / (2 * $a);
				$x = $x1 + $mu * ($x2 - $x1);
				$y = $y1 + $mu * ($y2 - $y1);
				$al = $mu; // Line Alpha
				$aa = $this->aAlpha($xs, $ys, $xe, $ye, $xc, $yc, $x, $y, $d); // Arc Alpha
				if (($al > 0 && $al < 1) && ($aa > 0 && $aa < 1))
				{
					$ix[0] = $x;
					$iy[0] = $y;
					$n = 1;
					$found = TRUE;
					if ($pt == 0)
					{
						$alphaP[0] = $al;
						$alphaQ[0] = $aa;
					}
					else
					{
						$alphaP[0] = $aa;
						$alphaQ[0] = $al;
					}
				}
			}
			elseif ($i > 0.0) // two intersections
			{
				if ($a != 0)
					$mu[0] = (-$b + sqrt(pow($b, 2) - 4 * $a * $c)) / (2 * $a); // first intersection
				$x[0] = $x1 + $mu[0] * ($x2 - $x1);
				$y[0] = $y1 + $mu[0] * ($y2 - $y1);
				if ($a != 0)
					$mu[1] = (-$b - sqrt(pow($b, 2) - 4 * $a * $c)) / (2 * $a); // second intersection
				$x[1] = $x1 + $mu[1] * ($x2 - $x1);
				$y[1] = $y1 + $mu[1] * ($y2 - $y1);
				$al[0] = $mu[0];
				$aa[0] = $this->aAlpha($xs, $ys, $xe, $ye, $xc, $yc, $x[0], $y[0], $d);
				$al[1] = $mu[1];
				$aa[1] = $this->aAlpha($xs, $ys, $xe, $ye, $xc, $yc, $x[1], $y[1], $d);
				for ($i = 0; $i <= 1; $i++)
					if (($al[$i] > 0 && $al[$i] < 1) && ($aa[$i] > 0 && $aa[$i] < 1))
					{
						$ix[$n] = $x[$i];
						$iy[$n] = $y[$i];
						if ($pt == 0)
						{
							$alphaP[$n] = $al[$i];
							$alphaQ[$n] = $aa[$i];
						}
						else
						{
							$alphaP[$n] = $aa[$i];
							$alphaQ[$n] = $al[$i];
						}
						$n++;
						$found = TRUE;
					}
			}
		} // End of find Arc/Line intersection
		return $found;
	}

// end of intersect function
	/*
	 * * Test if a vertex lies inside the polygon
	 * *
	 * * This function calculates the "winding" number for the point. This number
	 * * represents the number of times a ray emitted from the point to infinity
	 * * intersects any edge of the polygon. An even winding number means the point
	 * * lies OUTSIDE the polygon, an odd number means it lies INSIDE it.
	 * *
	 * * Right now infinity is set to -10000000, some people might argue that infinity
	 * * actually is a bit bigger. Those people have no lives.
	 */

	function isInside(&$v) {
		$winding_number = 0;
		$point_at_infinity = new Vertex(-10000000, $v->Y()); // Create point at infinity
		$q = $this->first; // End vertex of a line segment in polygon
		do
		{
			if (!$q->isIntersect())
			{
				if ($this->ints($point_at_infinity, $v, $q, $this->nxt($q->Next()), $n, $x, $y, $aP, $aQ))
					$winding_number += $n; // Add number of intersections found
			}
			$q = $q->Next();
		}
		while ($q->id() != $this->first->id());
		$point_at_infinity = NULL; // Free the memory for neatness
		if ($winding_number % 2 == 0) // Check even or odd
			return FALSE; // even == outside
		else
			return TRUE; // odd == inside
	}

	/**
	 * http://math.15873.pagesperso-orange.fr/page9.htm
	 * 
	 * @return array
	 */
	function getGravityCenter() {

		$p = $this->first;
		$sA = 0.0;
		$sX = 0.0;
		$sY = 0.0;
		$np = 0.0;
		do
		{
			$p1 = $p->Next();

			// v = (Xi * Yi+1) - (Xi+1 * Yi)
			$v = ($p->X() * $p1->Y()) - ($p1->X() * $p->Y());
			$sA += $v;
			// vX = v * (Xi + Xi+1)
			$vX = $v * ($p->X() + $p1->X());
			$sX += $vX;
			// vY = v * (Yi + Yi+1)
			$vY = $v * ($p->Y() + $p1->Y());
			$sY += $vY;
			$p = $p1;
			$np++;

			//echo $sA.', '.$sX.','.$sY. "\n";
		}
		while ($p->id() != $this->first->id());

		$a = $sA / 2.0;
		$gX = (1 / (6*$a)) * $sX ;
		$gY = (1 / (6*$a)) * $sY ;

		//echo $gX.', '.$gY ."\n";

		return array($gX, $gY);
	}

	/*
	 * *	Execute a Boolean operation on a polygon
	 * *
	 * * This is the key method. It allows you to AND/OR this polygon with another one
	 * * (equvalent to a UNION or INTERSECT operation. You may also subtract one from
	 * * the other (same as DIFFERENCE). Given two polygons A, B the following operations
	 * * may be performed:
	 * *
	 * * A|B ... A OR  B (Union of A and B)
	 * * A&B ... A AND B (Intersection of A and B)
	 * * A\B ... A - B
	 * * B\A ... B - A
	 * *
	 * * A is the object and B is the polygon passed to the method.
	 */

	function &boolean(&$polyB, $oper) {
		$last = NULL;
		$s = $this->first; // First vertex of the subject polygon
		$c = $polyB->getFirst(); // First vertex of the "clip" polygon
		/*
		 * * Phase 1 of the algoritm is to find all intersection points between the two
		 * * polygons. A new Vertex is created for each intersection and it is added to
		 * * the linked lists for both polygons. The "neighbor" reference in each vertex
		 * * stores the link between the same intersection point in each polygon. 
		 */
		do
		{
			if (!$s->isIntersect())
			{
				do
				{
					if (!$c->isIntersect())
					{
						if ($this->ints($s, $this->nxt($s->Next()), $c, $polyB->nxt($c->Next()), $n, $ix, $iy, $alphaS, $alphaC))
						{
							for ($i = 0; $i < $n; $i++)
							{
								$is = new Vertex($ix[$i], $iy[$i], $s->Xc(), $s->Yc(), $s->d(), NULL, NULL, NULL, TRUE, NULL, $alphaS[$i], FALSE, FALSE);
								$ic = new Vertex($ix[$i], $iy[$i], $c->Xc(), $c->Yc(), $c->d(), NULL, NULL, NULL, TRUE, NULL, $alphaC[$i], FALSE, FALSE);
								$is->setNeighbor($ic);
								$ic->setNeighbor($is);
								$this->insertSort($is, $s, $this->nxt($s->Next()));
								$polyB->insertSort($ic, $c, $polyB->nxt($c->Next()));
							}
						}
					} // end if $c is not an intersect point
					$c = $c->Next();
				}
				while ($c->id() != $polyB->first->id());
			} // end if $s not an intersect point
			$s = $s->Next();
		}
		while ($s->id() != $this->first->id());
		/*
		 * * Phase 2 of the algorithm is to identify every intersection point as an
		 * * entry or exit point to the other polygon. This will set the entry bits
		 * * in each vertex object.
		 * *
		 * * What is really stored in the entry record for each intersection is the
		 * * direction the algorithm should take when it arrives at that entry point.
		 * * Depending in the operation requested (A&B, A|B, A/B, B/A) the direction is
		 * * set as follows for entry points (f=foreward, b=Back), exit poits are always set
		 * * to the opposite:
		 * *       Enter       Exit
		 * *       A    B     A    B
		 * * A|B   b    b     f    f
		 * * A&B   f    f     b    b
		 * * A\B   b    f     f    b
		 * * B\A   f    b     b    f
		 * *
		 * * f = TRUE, b = FALSE when stored in the entry record
		 */
		switch ($oper)
		{
			case "A|B": $A = FALSE;
				$B = FALSE;
				break;
			case "A&B": $A = TRUE;
				$B = TRUE;
				break;
			case "A\B": $A = FALSE;
				$B = TRUE;
				break;
			case "B\A": $A = TRUE;
				$B = FALSE;
				break;
			default: $A = TRUE;
				$B = TRUE;
				break;
		}
		$s = $this->first;
		if ($polyB->isInside($s)) // if we are already inside
			$entry = !$A; // next intersection must be an exit
		else // otherwise
			$entry = $A; // next intersection must be an entry
		do
		{
			if ($s->isIntersect())
			{
				$s->setEntry($entry);
				$entry = !$entry;
			}
			$s = $s->Next();
		}
		while ($s->id() != $this->first->id());
		/*
		 * * Repeat for other polygon
		 */
		$c = $polyB->first;
		if ($this->isInside($c)) // if we are already inside
			$entry = !$B; // next intersection must be an exit
		else // otherwise
			$entry = $B; // next intersection must be an entry
		do
		{
			if ($c->isIntersect())
			{
				$c->setEntry($entry);
				$entry = !$entry;
			}
			$c = $c->Next();
		}
		while ($c->id() != $polyB->first->id());
		/*
		 * * Phase 3 of the algorithm is to scan the linked lists of the
		 * * two input polygons an construct a linked list of result
		 * * polygons. We start at the first intersection then depending
		 * * on whether it is an entry or exit point we continue building
		 * * our result polygon by following the source or clip polygon
		 * * either forwards or backwards.
		 */
		while ($this->unckd_remain()) // Loop while unchecked intersections remain
		{
			$v = $this->first_unckd_intersect(); // Get the first unchecked intersect point
			$this_class = get_class($this); // Findout the class I'm in
			$r = new $this_class; // Create a new instance of that class
			do
			{
				$v->setChecked(); // Set checked flag true for this intersection
				if ($v->isEntry())
				{
					do
					{
						$v = $v->Next();
						$nv = new Vertex($v->X(), $v->Y(), $v->Xc(), $v->Yc(), $v->d());
						$r->add($nv);
					}
					while (!$v->isIntersect());
				}
				else
				{
					do
					{
						$v = $v->Prev();
						$nv = new Vertex($v->X(), $v->Y(), $v->Xc(FALSE), $v->Yc(FALSE), $v->d(FALSE));
						$r->add($nv);
					}
					while (!$v->isIntersect());
				}
				$v = $v->Neighbor();
			}
			while (!$v->isChecked()); // until polygon closed
			if ($last) // Check in case first time thru the loop
				$r->first->setNextPoly($last); // Save ref to the last poly in the first vertex





				
// of this poly
			$last = $r; // Save this polygon
		} // end of while there is another intersection to check
		/*
		 * * Clean up the input polygons by deleting the intersection points
		 */
		$this->res();
		$polyB->res();
		/*
		 * * It is possible that no intersection between the polygons was found and
		 * * there is no result to return. In this case we make function fail
		 * * gracefully as follows (depending on the requested operation):
		 * *
		 * * A|B : Return $this with $polyB in $this->first->nextPoly
		 * * A&B : Return $this
		 * * A\B : Return $this
		 * * B\A : return $polyB
		 */
		if (!$last)
		{
			switch ($oper)
			{
				case "A|B": $last = $this->copy_poly();
					$p = $polyB->copy_poly();
					$last->first->setNextPoly($p);
					break;
				case "A&B": $last = $this->copy_poly();
					break;
				case "A\B": $last = $this->copy_poly();
					break;
				case "B\A": $last = $polyB->copy_poly();
					break;
				default: $last = $this->copy_poly();
					break;
			}
		}
		elseif ($this->first->nextPoly)
		{
			$last->first->nextPoly = $this->first->NextPoly();
		}
		return $last;
	}

// end of boolean function
	/*
	 * * Test if a polygon lies entirly inside this polygon
	 * *
	 * * First every point in the polygon is tested to determine if it is
	 * * inside this polygon. If all points are inside, then the second
	 * * test is performed that looks for any intersections between the
	 * * two polygons. If no intersections are found then the polygon
	 * * must be completely enclosed by this polygon.
	 */

	function isPolyInside(&$p) {
		$inside = TRUE;
		$c = $p->getFirst(); // Get the first vertex in polygon $p
		do
		{
			if (!$this->isInside($c)) // If vertex is NOT inside this polygon
				$inside = FALSE; // then set flag to false
			$c = $c->Next(); // Get the next vertex in polygon $p
		}
		while ($c->id() != $p->first->id());
		if ($inside)
		{
			$c = $p->getFirst(); // Get the first vertex in polygon $p
			$s = $this->getFirst(); // Get the first vertex in this polygon
			do
			{
				do
				{
					if ($this->ints($s, $s->Next(), $c, $c->Next(), $n, $x, $y, $aS, $aC))
						$inside = FALSE;
					$c = $c->Next();
				}
				while ($c->id() != $p->first->id());
				$s = $s->Next();
			}
			while ($s->id() != $this->first->id());
		}
		return $inside;
	}

// end of isPolyInside
	/*
	 * * Test if a polygon lies completely outside this polygon
	 * *
	 * * First every point in the polygon is tested to determine if it is
	 * * outside this polygon. If all points are outside, then the second
	 * * test is performed that looks for any intersections between the
	 * * two polygons. If no intersections are found then the polygon
	 * * must be completely outside this polygon.
	 */

	function isPolyOutside(&$p) {
		$outside = TRUE;
		$c = $p->getFirst(); // Get the first vertex in polygon $p
		do
		{
			if ($this->isInside($c)) // If vertex is inside this polygon
				$outside = FALSE; // then set flag to false
			$c = $c->Next(); // Get the next vertex in polygon $p
		}
		while ($c->id() != $p->first->id());
		if ($outside)
		{
			$c = $p->getFirst(); // Get the first vertex in polygon $p
			$s = $this->getFirst(); // Get the first vertex in this polygon
			do
			{
				do
				{
					if ($this->ints($s, $s->Next(), $c, $c->Next(), $n, $x, $y, $aS, $aC))
						$outside = FALSE;
					$c = $c->Next();
				}
				while ($c->id() != $p->first->id());
				$s = $s->Next();
			}
			while ($s->id() != $this->first->id());
		}
		return $outside;
	}

// end of isPolyOutside
	/*
	 * * Test if a polygon intersects anywhere with this polygon
	 * * looks for any intersections between the two polygons.
	 * * If no intersections between any segments are found then
	 * * the polygons do not intersect. However, one could be
	 * * completely inside the other.
	 */

	function isPolyIntersect(&$p) {
		$intersect = FALSE;
		$c = $p->getFirst(); // Get the first vertex in polygon $p
		$s = $this->getFirst(); // Get the first vertex in this polygon
		do
		{
			do
			{
				if ($this->ints($s, $s->Next(), $c, $c->Next(), $n, $x, $y, $aS, $aC))
					$intersect = TRUE;
				$c = $c->Next();
			}
			while ($c->id() != $p->first->id());
			$s = $s->Next();
		}
		while ($s->id() != $this->first->id());
		return $intersect;
	}

// end of isPolyIntersect
	/*
	 * * Test if this polygon intersects anywhere with itself
	 * * looks for any self intersections within the polygon.
	 * * If no intersections between any segments are found then
	 * * the polygon does not self intersect.
	 */

	function isPolySelfIntersect() {
		$intersect = FALSE;
		$s = $this->getFirst(); // Get the first vertex in this polygon
		$c = $s->Next(); // Get the next vertex
		do
		{
			do
			{
				if ($this->ints($s, $s->Next(), $c, $c->Next(), $n, $x, $y, $aS, $aC)) // If the segments intersect
					for ($i = 0; $i <= $n; $i++) // then for each intersection point
						if ((isset($aS[$i]) && $aS[$i] <> 0) || (isset($aC[$i]) && $aC[$i] <> 0)) // check that it NOT at the end of the segment
							$intersect = TRUE; // Because sequential segments always intersect at their ends
							$c = $c->Next();
			}
			while ($c->id() != $this->first->id());
			$s = $s->Next();
		}
		while ($s->id() != $this->first->id());
		return $intersect;
	}

// end of isPolySelfIntersect
	/*
	 * * Move Polygon
	 * *
	 * * Translates polygon by delta X and delta Y
	 */

	function move($dx, $dy) {
		$p = $this;
		if ($p) // For a valid polygon
			do
			{
				$v = $p->getFirst();
				do
				{
					$v->setX($v->X() + $dx);
					$v->setY($v->Y() + $dy);
					if ($v->d() != 0)
					{
						$v->setXc($v->Xc() + $dx);
						$v->setYc($v->Yc() + $dy);
					}
					$v = $v->Next();
				}
				while ($v->id() != $p->first->id());
				$p = $p->NextPoly(); // Get the next polygon in the list
			}
			while ($p); // Keep checking polygons as long as they exist
	}

// end of move polygon	
	/*
	 * * Rotate Polygon
	 * *
	 * * Rotates a polgon about point $xr/$yr by $a radians
	 */

	function rotate($xr, $yr, $a) {
		$this->move(-$xr, -$yr); // Move the polygon so that the point of
		// rotation is at the origin (0,0)
		if ($a < 0) // We might be passed a negitive angle
			$a += 2 * pi(); // make it positive

		$p = $this;
		if ($p) // For a valid polygon
			do
			{
				$v = $p->first;
				do
				{
					$x = $v->X();
					$y = $v->Y();
					$v->setX($x * cos($a) - $y * sin($a)); // x' = xCos(a)-ySin(a)
					$v->setY($x * sin($a) + $y * cos($a)); // y' = xSin(a)+yCos(a)
					if ($v->d() != 0)
					{
						$x = $v->Xc();
						$y = $v->Yc();
						$v->setXc($x * cos($a) - $y * sin($a));
						$v->setYc($x * sin($a) + $y * cos($a));
					}
					$v = $v->Next();
				}
				while ($v->id() != $p->first->id());
				$p = $p->NextPoly(); // Get the next polygon in the list
			}
			while ($p); // Keep checking polygons as long as they exist	
		$this->move($xr, $yr); // Move the rotated polygon back 
	}

// end of rotate polygon
	/*
	 * * Return Bounding Rectangle for a Polygon
	 * *
	 * * returns a polygon object that represents the bounding rectangle
	 * * for this polygon. Arc segments are correctly handled.
	 * *
	 * * Remember the polygon object allows for a linked list of polygons.
	 * * If more than one polygon is linked through the NextPoly list
	 * * then the bounding rectangle will be for ALL polygons in the
	 * * list.
	 */

	function &bRect() {
		$minX = INF;
		$minY = INF;
		$maxX = -INF;
		$maxY = -INF;
		$p = $this;
		if ($p) // For a valid polygon
			do
			{
				$v = $p->first; // Get the first vertex
				do
				{
					if ($v->d() != 0) // Is it an arc segment
					{
						$vn = $v->Next(); // end vertex of the arc segment
						$v1 = new Vertex($v->Xc(), -infinity); // bottom point of vertical line thru arc center
						$v2 = new Vertex($v->Xc(), +infinity); // top point of vertical line thru arc center
						if ($p->ints($v, $vn, $v1, $v2, $n, $x, $y, $aS, $aC)) // Does line intersect the arc ?
						{
							for ($i = 0; $i < $n; $i++) // check y portion of all intersections
							{
								$minY = min($minY, $y[$i], $v->Y());
								$maxY = max($maxY, $y[$i], $v->Y());
							}
						}
						else // There was no intersection so bounding rect is determined
						{ // by the start point only, not the edge of the arc
							$minY = min($minY, $v->Y());
							$maxY = max($maxY, $v->Y());
						}
						$v1 = NULL;
						$v2 = NULL; // Free the memory used
						$h1 = new Vertex(-infinity, $v->Yc()); // left point of horozontal line thru arc center
						$h2 = new Vertex(+infinity, $v->Yc()); // right point of horozontal line thru arc center
						if ($p->ints($v, $vn, $h1, $h2, $n, $x, $y, $aS, $aC)) // Does line intersect the arc ?
						{
							for ($i = 0; $i < $n; $i++) // check x portion of all intersections
							{
								$minX = min($minX, $x[$i], $v->X());
								$maxX = max($maxX, $x[$i], $v->X());
							}
						}
						else
						{
							$minX = min($minX, $v->X());
							$maxX = max($maxX, $v->X());
						}
						$h1 = NULL;
						$h2 = NULL;
					}
					else // Straight segment so just check the vertex
					{
						$minX = min($minX, $v->X());
						$minY = min($minY, $v->Y());
						$maxX = max($maxX, $v->X());
						$maxY = max($maxY, $v->Y());
					}
					$v = $v->Next();
				}
				while ($v->id() != $p->first->id());
				$p = $p->NextPoly(); // Get the next polygon in the list
			}
			while ($p); // Keep checking polygons as long as they exist
			//
		// Now create an return a polygon with the bounding rectangle
		//
		$this_class = get_class($this); // Findout the class I'm in (might be an extension of polygon)
		$p = new $this_class; // Create a new instance of that class
		$p->addv($minX, $minY);
		$p->addv($minX, $maxY);
		$p->addv($maxX, $maxY);
		$p->addv($maxX, $minY);
		return $p;
	}

// end of bounding rectangle
	/*
	 * * Scale a Polygon
	 * *
	 * * Resize a polygon by scale X & scale Y
	 */

	function scale($sx, $sy) {
		$p = $this;
		if ($p) // For a valid polygon
			do
			{
				$v = $p->getFirst();
				do
				{
					$v->setX($v->X() * $sx);
					$v->setY($v->Y() * $sy);
					if ($v->d() != 0)
					{
						$v->setXc($v->Xc() * $sx);
						$v->setYc($v->Yc() * $sy);
					}
					$v = $v->Next();
				}
				while ($v->id() != $p->first->id());
				$p = $p->NextPoly(); // Get the next polygon in the list
			}
			while ($p); // Keep checking polygons as long as they exist
	}

// end of scale polygon	
	/*
	 * * translate a Polygon
	 * *
	 * * Resize & move a polygon so that its bounding rectangle becomes
	 * * the rectangle defined by the two points (xmin,ymin) and
	 * * (xmax,ymax).
	 */

	function translate($xmin, $ymin, $xmax, $ymax) {
		$nXsize = $xmax - $xmin;
		$nYsize = $ymax - $ymin;

		$o_br = $this->bRect(); // Get the min/max corners of the original polygon bounding rect
		$v = $o_br->getFirst(); // First vertex of bRect is xmin & ymin of the polygon
		$o_xmin = $v->X();
		$o_ymin = $v->Y();
		$v = $v->Next(); // Next vertex has ymax
		$o_ymax = $v->Y();
		$v = $v->Next(); // Next vertex has xmax
		$o_xmax = $v->X();

		$oXsize = $o_xmax - $o_xmin;
		$oYsize = $o_ymax - $o_ymin;

		$xScale = $nXsize / $oXsize; // Calculate the X axis scale factor
		$yScale = $nYsize / $oYsize; // Calculate the X axis scale factor

		$xMove = $xmin - ($o_xmin * $xScale);
		$yMove = $ymin - ($o_ymin * $yScale);

		$this->scale($xScale, $yScale);
		$this->move($xMove, $yMove);
	}

// end of translate polygon
}

//end of class polygon
