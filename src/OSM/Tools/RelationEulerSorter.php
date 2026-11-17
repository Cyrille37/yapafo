<?php
namespace Cyrille37\OSM\Yapafo\Tools ;

use Cyrille37\OSM\Yapafo\Objects\Member ;
use Cyrille37\OSM\Yapafo\Objects\Node ;
use Cyrille37\OSM\Yapafo\Objects\OSM_Object ;
use Cyrille37\OSM\Yapafo\Objects\Relation ;
use Cyrille37\OSM\Yapafo\Objects\Way ;
use Cyrille37\OSM\Yapafo\Exceptions\Exception as OSM_Exception ;
use Cyrille37\OSM\Yapafo\Exceptions\SortException ;

/**
 * Sort the way members of a route relation by Eulerian tour
 * (Hierholzer algorithm): "without lifting the pencil".
 *
 * A duplicated way member (aller/retour) appears twice in the relation,
 * producing 2 edges: that is what makes every junction even.
 *
 * Automatic closure: when exactly 2 nodes have an odd degree, the tour is
 * open A→B; it is closed by doubling the BFS shortest path between the 2
 * odd nodes (the "queue": parking access, viewpoint...) so that a closed
 * Eulerian circuit exists. Non-way members (nodes: signs, bollards...)
 * are ignored by the sort and kept in their original relative order.
 */
class RelationEulerSorter
{

  /**
   * Sort Relation's members using Euler/Hierholzer algorithm.
   *
   * If sort success, the way members are reordered (doubled queue members
   * included) and the Relation is set as "dirty". Non-way members are kept,
   * in their original relative order, after the sorted way members.
   *
   * If sort failed, a SortException is thrown and the Relation is not modified.
   * SortException codes:
   * - SortException::ERR_MISSING_WAYS hole in the loop (way(s) missing)
   * - SortException::ERR_DUPLICATE_WAYS way(s) duplicated
   *
   * @param Relation $relation
   * @param array $ways Array<int wayId, Way>
   * @param array $nodes Optional. Array<int nodeId, Node>. Used to pick a
   * parking as start node and to log distances.
   * @return void
   * @throws OSM_Exception
   * @throws SortException
   */
  public static function sort(Relation $relation, array $ways, array $nodes = [])
  {
    $logger = Logger::getInstance();
    [$edges, $adj, $extraMembers] = self::buildGraph($relation, $ways);
    $logger->debug('{_m} relation:{id} way occurrences:{count}', ['_m' => __METHOD__, 'id' => $relation->getId(), 'count' => count($edges)]);

    $oddNodes = self::oddNodes($adj);

    // Automatic closure: exactly 2 odd nodes → double the BFS path between them
    if (count($oddNodes) === 2) {
      $path = self::findOddPairPath($adj, $edges, $oddNodes);
      if ($path === [])
        throw new SortException('Missing ways: no path links the 2 odd nodes (' . implode(', ', $oddNodes) . ') in relation "' . $relation->getId() . '"', SortException::ERR_MISSING_WAYS);
      $queueKm = 0.0;
      foreach ($path as $eid) {
        $queueKm += self::wayLengthMeters($ways[$edges[$eid]['way']], $nodes) / 1000.0;
        $logger->notice('{_m} relation:{id} queue way:{way} doubled', ['_m' => __METHOD__, 'id' => $relation->getId(), 'way' => $edges[$eid]['way']]);
        self::doubleEdge($edges, $adj, $eid);
      }
      $logger->notice('{_m} relation:{id} queue doubled to close the circuit ({km} km)', ['_m' => __METHOD__, 'id' => $relation->getId(), 'km' => number_format($queueKm, 2)]);
      $oddNodes = self::oddNodes($adj);
    }

    if (count($oddNodes) !== 0)
      throw self::sortException($oddNodes, $edges, $ways, $relation);

    $start = self::pickStartNode($adj, $edges, $nodes);
    $logger->debug('{_m} relation:{id} start node:{node}', ['_m' => __METHOD__, 'id' => $relation->getId(), 'node' => $start]);

    [$tourNodes, $tourEdges] = self::hierholzer($adj, $edges, $start);

    if (count($tourEdges) !== count($edges))
      throw new SortException('Missing ways: ' . (count($edges) - count($tourEdges)) . ' way occurrence(s) of relation "' . $relation->getId() . '" not connected to the main tour', SortException::ERR_MISSING_WAYS);

    if ($nodes !== []) {
      $totalKm = 0.0;
      foreach ($edges as $e)
        $totalKm += self::wayLengthMeters($ways[$e['way']], $nodes) / 1000.0;
      $logger->info('{_m} relation:{id} tour length:{km} km', ['_m' => __METHOD__, 'id' => $relation->getId(), 'km' => number_format($totalKm, 2)]);
    }

    $newMembers = [];
    foreach ($tourEdges as $eid)
      $newMembers[] = $edges[$eid]['member'];
    foreach ($extraMembers as $member)
      $newMembers[] = $member;
    $relation->replaceMembers($newMembers);
  }

  /**
   * Build the multigraph: one way member occurrence = one edge.
   *
   * NB: all node ids are cast to int here. PHP pitfall: a numeric array key
   * ("3669118192") is stored as int, while (string)$ref stays a string, so
   * strict comparisons (===) between the two would silently fail. One single
   * type (int) everywhere makes every === reliable.
   *
   * @param Relation $relation
   * @param array $ways Array<int wayId, Way>
   * @return array [ array edges, array adj, array extraMembers ]
   * @throws OSM_Exception
   */
  private static function buildGraph(Relation $relation, array $ways)
  {
    $edges = [];      // eid => ['way'=>wayId, 'member'=>Member, 'a'=>nodeId, 'b'=>nodeId]
    $adj = [];        // nodeId => [eid, ...] (incident edges, with duplicates)
    $extraMembers = [];

    foreach ($relation->getMembers() as $member) {
      if ($member->getType() !== OSM_Object::OBJTYPE_WAY) {
        $extraMembers[] = $member; // signs, bollards... do not take part in the tour
        continue;
      }
      $wayId = (int) $member->getRef();
      if (! isset($ways[$wayId]))
        throw new OSM_Exception('way "' . $wayId . '" is a member of relation "' . $relation->getId() . '" but was not provided');
      $nodeRefs = $ways[$wayId]->getNodesRefs();
      if (count($nodeRefs) < 2)
        throw new OSM_Exception('way "' . $wayId . '" of relation "' . $relation->getId() . '" has fewer than 2 nodes');
      $a = (int) reset($nodeRefs);   // first node of the way (its OSM drawing direction)
      $b = (int) end($nodeRefs);     // last node
      $eid = count($edges);
      $edges[] = ['way' => $wayId, 'member' => $member, 'a' => $a, 'b' => $b];
      $adj[$a][] = $eid;
      $adj[$b][] = $eid;
    }

    if ($edges === [])
      throw new OSM_Exception('no way member to sort in relation "' . $relation->getId() . '"');

    return [$edges, $adj, $extraMembers];
  }

  /**
   * @param array $adj
   * @return array Node ids with an odd degree
   */
  private static function oddNodes(array $adj)
  {
    return array_keys(array_filter($adj, fn($incidents) => count($incidents) % 2 === 1));
  }

  /**
   * Dead end: a node whose 2 incident edges are the same way (aller/retour).
   *
   * @param array $adj
   * @param array $edges
   * @return array Node ids
   */
  private static function findDeadEnds(array $adj, array $edges)
  {
    $deadEnds = [];
    foreach ($adj as $node => $incidents) {
      if (
        count($incidents) === 2
        && $edges[$incidents[0]]['way'] === $edges[$incidents[1]]['way']
      )
        $deadEnds[] = $node;
    }
    return $deadEnds;
  }

  /**
   * BFS shortest edge path linking the 2 odd nodes.
   *
   * This path always exists: the sum of the degrees of a connected component
   * being even, the 2 odd nodes necessarily belong to the same component.
   *
   * @param array $adj
   * @param array $edges
   * @param array $oddNodes Exactly 2 node ids
   * @return array Edge ids from the 1st odd node to the 2nd, or [] if not found.
   */
  private static function findOddPairPath(array $adj, array $edges, array $oddNodes)
  {
    $start = $oddNodes[0];
    $target = $oddNodes[1];

    $cameFromEdge = [$start => null]; // node already seen => edge that led to it
    $queue = [$start];

    while ($queue !== [] && ! array_key_exists($target, $cameFromEdge)) {
      $v = array_shift($queue);
      foreach ($adj[$v] ?? [] as $eid) {
        $u = ($edges[$eid]['a'] === $v) ? $edges[$eid]['b'] : $edges[$eid]['a'];
        if (! array_key_exists($u, $cameFromEdge)) {
          $cameFromEdge[$u] = $eid;
          $queue[] = $u;
        }
      }
    }

    if (! array_key_exists($target, $cameFromEdge))
      return [];

    // walk the path back: target → start, then reverse
    $path = [];
    $cur = $target;
    while ($cur !== $start) {
      $eid = $cameFromEdge[$cur];
      $path[] = $eid;
      $cur = ($edges[$eid]['a'] === $cur) ? $edges[$eid]['b'] : $edges[$eid]['a'];
    }
    return array_reverse($path);
  }

  /**
   * Double an edge: a 2nd occurrence of the same way (aller/retour).
   * The copy keeps the role of the original member (usually empty).
   *
   * @param array $edges By reference
   * @param array $adj By reference
   * @param int $eid
   * @return void
   */
  private static function doubleEdge(array &$edges, array &$adj, int $eid)
  {
    $copy = $edges[$eid];
    $copy['member'] = new Member(
      $edges[$eid]['member']->getType(),
      $edges[$eid]['member']->getRef(),
      $edges[$eid]['member']->getRole()
    );
    $newId = count($edges);
    $edges[] = $copy;
    $adj[$copy['a']][] = $newId;
    $adj[$copy['b']][] = $newId;
  }

  /**
   * Choose the start node: a parking if any, else the 1st dead end
   * (viewpoint, belvedere...), else one end of the 1st edge (pure loop).
   *
   * @param array $adj
   * @param array $edges
   * @param array $nodes Array<int nodeId, Node>
   * @return int Node id
   */
  private static function pickStartNode(array $adj, array $edges, array $nodes)
  {
    $deadEnds = self::findDeadEnds($adj, $edges);
    foreach ($deadEnds as $node) {
      if (
        isset($nodes[$node])
        && $nodes[$node] instanceof Node
        && $nodes[$node]->getTag('amenity', 'parking')
      )
        return $node;
    }
    if ($deadEnds !== [])
      return $deadEnds[0];
    return $edges[0]['a']; // loop without dead end: arbitrary start
  }

  /**
   * Hierholzer: the "without lifting the pencil" tour.
   *
   * Returns [ $tourNodes, $tourEdges ] where $tourEdges[i] links
   * $tourNodes[i] to $tourNodes[i+1].
   *
   * Iterative stack-based version (no recursion): O(nodes + edges).
   *
   * Tour direction: the FIRST unused edge is taken (deterministic,
   * reproducible).
   *
   * @param array $adj
   * @param array $edges
   * @param int $start
   * @return array [ array tourNodes, array tourEdges ]
   */
  private static function hierholzer(array $adj, array $edges, int $start)
  {
    $used = array_fill(0, count($edges), false);

    $stackNodes = [$start];
    $stackEdges = [null];    // edge used to reach each node of the stack
    $tourNodes = [];
    $tourEdges = [];

    while (! empty($stackNodes)) {
      $v = end($stackNodes);

      // edges still available at the top of the stack
      $candidates = array_filter($adj[$v] ?? [], fn($eid) => ! $used[$eid]);

      if ($candidates !== []) {
        $eid = reset($candidates);
        $used[$eid] = true;
        $edge = $edges[$eid];
        $u = ($edge['a'] === $v) ? $edge['b'] : $edge['a'];
        $stackNodes[] = $u;
        $stackEdges[] = $eid;
      } else {
        // nothing left to visit from $v: fix it into the tour (backtrack)
        $tourNodes[] = array_pop($stackNodes);
        $tourEdges[] = array_pop($stackEdges);
      }
    }

    $tourNodes = array_reverse($tourNodes);  // the tour is read backwards of the pops
    $tourEdges = array_reverse($tourEdges);
    array_shift($tourEdges);                 // align: $tourEdges[i] = $tourNodes[i] → $tourNodes[i+1]

    return [$tourNodes, $tourEdges];
  }

  /**
   * Build the SortException for N odd nodes > 2: distinguishes "hole" from
   * "way in excess".
   *
   * - No edge between 2 odd nodes → HOLE (missing way)
   * - At least 1 edge between 2 odd nodes → WAY IN EXCESS (duplicated)
   *
   * @param array $oddNodes
   * @param array $edges
   * @param array $ways Array<int wayId, Way>
   * @param Relation $relation
   * @return SortException
   */
  private static function sortException(array $oddNodes, array $edges, array $ways, Relation $relation)
  {
    $oddSet = array_fill_keys($oddNodes, true);
    $oddList = implode(', ', $oddNodes);

    $oddEdges = []; // wayId => occurrence count
    foreach ($edges as $e) {
      if (isset($oddSet[$e['a']]) && isset($oddSet[$e['b']]))
        $oddEdges[$e['way']] = ($oddEdges[$e['way']] ?? 0) + 1;
    }

    if ($oddEdges === [])
      return new SortException(
        'Missing ways: hole in the loop, no way links two odd nodes (' . $oddList . ') in relation "' . $relation->getId() . '"',
        SortException::ERR_MISSING_WAYS
      );

    $duplicates = [];
    foreach ($oddEdges as $wayId => $n) {
      $name = $ways[$wayId]->getTag('name');
      $label = $name ? $name->getValue() : '';
      $duplicates[] = 'way ' . $wayId . ($label !== '' ? ' "' . $label . '"' : '') . ' x' . $n;
    }
    return new SortException(
      'Duplicate ways: ' . implode(', ', $duplicates) . ' link two odd nodes (' . $oddList . ') in relation "' . $relation->getId() . '"',
      SortException::ERR_DUPLICATE_WAYS
    );
  }

  /**
   * Length of a way in meters (haversine over the provided nodes).
   *
   * @param Way $way
   * @param array $nodes Array<int nodeId, Node>
   * @return float
   */
  private static function wayLengthMeters(Way $way, array $nodes)
  {
    $m = 0.0;
    $nodeRefs = $way->getNodesRefs();
    for ($i = 0, $n = count($nodeRefs) - 1; $i < $n; $i++) {
      $p = $nodes[(int) $nodeRefs[$i]] ?? null;
      $q = $nodes[(int) $nodeRefs[$i + 1]] ?? null;
      if ($p === null || $q === null)
        continue; // node not provided
      $m += Math::distanceOnEarth($p->getLat(), $p->getLon(), $q->getLat(), $q->getLon());
    }
    return $m;
  }
}
