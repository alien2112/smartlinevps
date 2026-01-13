<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripCreationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear any cached routes
        Cache::flush();
    }

    /**
     * Test that trip creation controller initializes correctly
     */
    public function test_trip_request_controller_can_be_instantiated()
    {
        $this->assertTrue(class_exists('\Modules\TripManagement\Http\Controllers\Api\Customer\TripRequestController'));
    }

    /**
     * Test that TripRequest model has required fields
     */
    public function test_trip_request_model_has_required_fillable_fields()
    {
        $tripRequestClass = '\Modules\TripManagement\Entities\TripRequest';
        $this->assertTrue(class_exists($tripRequestClass));

        $trip = new $tripRequestClass();
        $fillable = $trip->getFillable();

        // Check essential fields exist
        $requiredFields = [
            'customer_id',
            'driver_id',
            'estimated_fare',
            'actual_fare',
            'estimated_distance',
            'actual_distance',
            'payment_method',
            'payment_status',
            'current_status',
        ];

        foreach ($requiredFields as $field) {
            $this->assertContains($field, $fillable, "Field '{$field}' should be fillable");
        }
    }

    /**
     * Test that distance calculation via getRoutes is working
     */
    public function test_get_routes_function_returns_distance_and_duration()
    {
        // Mock the HTTP response for GeoLink API
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'routes' => [
                        [
                            'distance' => [
                                'meters' => 5000 // 5 km
                            ],
                            'duration' => [
                                'seconds' => 600 // 10 minutes
                            ],
                            'polyline' => 'encoded_polyline_data',
                            'waypoints' => []
                        ]
                    ]
                ]
            ], 200)
        ]);

        // Test the getRoutes function
        $originCoordinates = [28.6139, 77.2090]; // Delhi coordinates
        $destinationCoordinates = [28.6245, 77.2211]; // Different Delhi location

        $result = getRoutes($originCoordinates, $destinationCoordinates);

        // Verify we got results
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Should have TWO_WHEELER and DRIVE modes

        // Check TWO_WHEELER response
        $this->assertEquals('OK', $result[0]['status']);
        $this->assertEquals(5.0, $result[0]['distance']);
        $this->assertStringContainsString('km', $result[0]['distance_text']);
        $this->assertStringContainsString('min', $result[0]['duration']);
        $this->assertEquals('TWO_WHEELER', $result[0]['drive_mode']);

        // Check DRIVE response
        $this->assertEquals('OK', $result[1]['status']);
        $this->assertEquals(5.0, $result[1]['distance']);
        $this->assertEquals('DRIVE', $result[1]['drive_mode']);
    }

    /**
     * Test distance conversion from meters to kilometers
     */
    public function test_distance_conversion_from_meters_to_kilometers()
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'routes' => [
                        [
                            'distance' => [
                                'meters' => 15500 // 15.5 km
                            ],
                            'duration' => [
                                'seconds' => 900 // 15 minutes
                            ],
                            'polyline' => 'test_polyline',
                            'waypoints' => []
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = getRoutes([28.6139, 77.2090], [28.6245, 77.2211]);

        // Should be converted to kilometers
        $this->assertEquals(15.5, $result[0]['distance']);
        $this->assertEquals(15.5, $result[1]['distance']);
    }

    /**
     * Test duration calculation for TWO_WHEELER with conversion factor
     */
    public function test_duration_calculation_with_two_wheeler_conversion()
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'routes' => [
                        [
                            'distance' => [
                                'meters' => 10000
                            ],
                            'duration' => [
                                'seconds' => 1200 // 20 minutes
                            ],
                            'polyline' => 'test_polyline',
                            'waypoints' => []
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = getRoutes([28.6139, 77.2090], [28.6245, 77.2211]);

        // TWO_WHEELER duration should be 1200 / 1.2 / 60 = 16.67 minutes
        $this->assertStringContainsString('16.67', $result[0]['duration']);
        $this->assertEquals(1000, $result[0]['duration_sec']); // 1200 / 1.2

        // DRIVE duration should be 1200 / 60 = 20 minutes
        $this->assertStringContainsString('20', $result[1]['duration']);
        $this->assertEquals(1200, $result[1]['duration_sec']);
    }

    /**
     * Test caching of routes
     */
    public function test_routes_are_cached_after_first_request()
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'routes' => [
                        [
                            'distance' => ['meters' => 5000],
                            'duration' => ['seconds' => 600],
                            'polyline' => 'test_polyline',
                            'waypoints' => []
                        ]
                    ]
                ]
            ], 200)
        ]);

        $origin = [28.6139, 77.2090];
        $destination = [28.6245, 77.2211];

        // First call should hit the API
        $result1 = getRoutes($origin, $destination);

        // Verify first result
        $this->assertEquals('OK', $result1[0]['status']);

        // Second call should use cache (HTTP call count should still be 1)
        $result2 = getRoutes($origin, $destination);

        // Results should be identical
        $this->assertEquals($result1, $result2);

        // Verify only one HTTP call was made
        Http::assertSentCount(1);
    }

    /**
     * Test route error handling
     */
    public function test_route_error_handling_when_api_fails()
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'Route not found'
            ], 400)
        ]);

        $result = getRoutes([28.6139, 77.2090], [28.6245, 77.2211]);

        // Should return error responses
        $this->assertEquals('ERROR', $result[0]['status']);
        $this->assertEquals('ERROR', $result[1]['status']);
        $this->assertStringContainsString('failed', strtolower($result[0]['error_detail']));
    }

    /**
     * Test intermediate coordinates (waypoints)
     */
    public function test_getRoutes_with_intermediate_coordinates()
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'routes' => [
                        [
                            'distance' => ['meters' => 20000],
                            'duration' => ['seconds' => 1200],
                            'polyline' => 'test_polyline',
                            'waypoints' => []
                        ]
                    ]
                ]
            ], 200)
        ]);

        $origin = [28.6139, 77.2090];
        $destination = [28.6245, 77.2211];
        $waypoint = [28.6180, 77.2150];

        $result = getRoutes($origin, $destination, [$waypoint]);

        // Should successfully handle waypoints
        $this->assertEquals('OK', $result[0]['status']);
        $this->assertEquals(20.0, $result[0]['distance']);
    }

    /**
     * Test coordinate format variations
     */
    public function test_getRoutes_accepts_different_coordinate_formats()
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'routes' => [
                        [
                            'distance' => ['meters' => 5000],
                            'duration' => ['seconds' => 600],
                            'polyline' => 'test_polyline',
                            'waypoints' => []
                        ]
                    ]
                ]
            ], 200)
        ]);

        // Test with array format [latitude, longitude]
        $result = getRoutes([28.6139, 77.2090], [28.6245, 77.2211]);
        $this->assertEquals('OK', $result[0]['status']);
        $this->assertEquals(5.0, $result[0]['distance']);
    }

    /**
     * Test polyline encoding is preserved
     */
    public function test_encoded_polyline_is_returned()
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'routes' => [
                        [
                            'distance' => ['meters' => 5000],
                            'duration' => ['seconds' => 600],
                            'polyline' => 'encoded_polyline_abc123',
                            'waypoints' => []
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = getRoutes([28.6139, 77.2090], [28.6245, 77.2211]);

        $this->assertEquals('encoded_polyline_abc123', $result[0]['encoded_polyline']);
        $this->assertEquals('encoded_polyline_abc123', $result[1]['encoded_polyline']);
    }
}
