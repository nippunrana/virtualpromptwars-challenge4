<?php
declare(strict_types=1);

/**
 * ArenaNexus 2026 Router Core
 * Dijkstra implementation separated for unit testing.
 */

/**
 * Calculate the optimal route between two zones using Dijkstra's algorithm.
 *
 * @param string  $start    Starting zone ID.
 * @param string  $end      Destination zone ID.
 * @param bool    $stepFree Whether to enforce step-free (elevator) routing.
 * @param array   $graph    Adjacency list: [ zone_id => [ neighbor_id => base_weight ] ].
 * @param array   $zoneData Zone metadata keyed by zone ID.
 * @return array<string>|null Ordered list of zone IDs forming the path, or null if unreachable.
 */
function calculateRoute(string $start, string $end, bool $stepFree, array $graph, array $zoneData): ?array {
    $distances = [];
    $previous = [];
    $queue = new class extends SplPriorityQueue {
        // Override compare to make it a min-heap
        public function compare($priority1, $priority2): int {
            if ($priority1 === $priority2) return 0;
            return $priority1 < $priority2 ? 1 : -1;
        }
    };

    foreach ($graph as $node => $adj) {
        $distances[$node] = INF;
        $previous[$node] = null;
    }

    $distances[$start] = 0;
    $queue->insert($start, 0);

    while (!$queue->isEmpty()) {
        $u = $queue->extract();

        if ($u === $end) {
            break; // Found shortest path
        }

        if ($distances[$u] === INF) {
            break; // Rest of nodes are unreachable
        }

        if (!isset($graph[$u])) continue;

        foreach ($graph[$u] as $v => $baseWeight) {
            // Apply congestion and step-free penalties
            $congestion = isset($zoneData[$v]) ? $zoneData[$v]['congestion_density'] : 0;
            $hasElevator = isset($zoneData[$v]) ? (bool)$zoneData[$v]['elevator_access'] : false;

            // Congestion penalty: weight multipliers
            $congestionWeight = $baseWeight * (1 + ($congestion / 100));

            // Step-free penalty: if step-free is requested and target zone lacks elevator access
            $accessibilityWeight = 0;
            if ($stepFree && !$hasElevator) {
                // If it is a zone requiring stairs, add a huge penalty
                $accessibilityWeight = 500; 
            }

            $alt = $distances[$u] + $congestionWeight + $accessibilityWeight;

            if ($alt < $distances[$v]) {
                $distances[$v] = $alt;
                $previous[$v] = $u;
                $queue->insert($v, $alt);
            }
        }
    }

    // Build path list
    $path = [];
    $curr = $end;
    while ($curr !== null) {
        array_unshift($path, $curr);
        $curr = $previous[$curr];
    }

    if (count($path) <= 1 && $path[0] !== $start) {
        return null;
    }

    return $path;
}
