<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../api/router_core.php';

class RouteTest extends TestCase {
    private function getMockGraph() {
        return [
            'A' => ['B' => 10, 'C' => 15],
            'B' => ['D' => 12],
            'C' => ['D' => 10],
            'D' => []
        ];
    }

    private function getMockZoneData() {
        return [
            'A' => ['congestion_density' => 0, 'elevator_access' => true],
            'B' => ['congestion_density' => 80, 'elevator_access' => true],
            'C' => ['congestion_density' => 0, 'elevator_access' => false],
            'D' => ['congestion_density' => 0, 'elevator_access' => true]
        ];
    }

    public function testRouteWithoutStepFree() {
        $graph = $this->getMockGraph();
        $zoneData = $this->getMockZoneData();

        $path = calculateRoute('A', 'D', false, $graph, $zoneData);
        $this->assertEquals(['A', 'C', 'D'], $path);
    }

    public function testRouteWithStepFree() {
        $graph = $this->getMockGraph();
        $zoneData = $this->getMockZoneData();

        $path = calculateRoute('A', 'D', true, $graph, $zoneData);
        $this->assertEquals(['A', 'B', 'D'], $path);
    }

    public function testUnreachableRoute() {
        $graph = $this->getMockGraph();
        $zoneData = $this->getMockZoneData();

        $path = calculateRoute('D', 'A', false, $graph, $zoneData);
        $this->assertNull($path);
    }
}
