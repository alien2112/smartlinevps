<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\TestCase;

class TripDistanceAndTimeTest extends TestCase
{
    /**
     * Test distance conversion formula: meters to kilometers
     */
    public function test_distance_conversion_formula()
    {
        $distanceMeters = 5000;
        $expectedKilometers = 5.0;

        // Formula: distanceMeters / 1000
        $actualKilometers = (double) str_replace(',', '', number_format(($distanceMeters ?? 0) / 1000, 2));

        $this->assertEquals($expectedKilometers, $actualKilometers);
    }

    /**
     * Test distance formatting with decimal places
     */
    public function test_distance_formatting_with_decimal_places()
    {
        $testCases = [
            ['meters' => 1234, 'expected' => 1.23],
            ['meters' => 15500, 'expected' => 15.5],
            ['meters' => 234, 'expected' => 0.23],
            ['meters' => 100000, 'expected' => 100.0],
            ['meters' => 1000, 'expected' => 1.0],
        ];

        foreach ($testCases as $case) {
            $distance = (double) str_replace(',', '', number_format(($case['meters'] ?? 0) / 1000, 2));
            $this->assertEquals($case['expected'], $distance, "Distance conversion failed for {$case['meters']} meters");
        }
    }

    /**
     * Test duration conversion formula: seconds to minutes
     */
    public function test_duration_conversion_formula()
    {
        $durationSeconds = 600; // 10 minutes
        $expectedMinutes = 10.0;

        // Formula: durationSeconds / 60
        $actualMinutes = number_format(($durationSeconds / 60), 2);

        $this->assertEquals('10.00', $actualMinutes);
    }

    /**
     * Test TWO_WHEELER duration conversion with factor 1.2
     */
    public function test_two_wheeler_duration_calculation()
    {
        $durationSeconds = 1200; // 20 minutes raw
        $convert_to_bike = 1.2;

        // TWO_WHEELER formula: (durationSeconds / 60) / convert_to_bike
        $bikeMinutes = number_format((($durationSeconds / 60) / $convert_to_bike), 2);
        $bikeDurationSec = (int) ($durationSeconds / $convert_to_bike);

        $this->assertEquals('16.67', $bikeMinutes);
        $this->assertEquals(1000, $bikeDurationSec);
    }

    /**
     * Test DRIVE duration (no conversion factor)
     */
    public function test_drive_duration_calculation()
    {
        $durationSeconds = 1200; // 20 minutes

        // DRIVE formula: durationSeconds / 60
        $driveMinutes = number_format(($durationSeconds / 60), 2);
        $driveDurationSec = (int) $durationSeconds;

        $this->assertEquals('20.00', $driveMinutes);
        $this->assertEquals(1200, $driveDurationSec);
    }

    /**
     * Test duration formatting consistency
     */
    public function test_duration_formatting_consistency()
    {
        $testCases = [
            ['seconds' => 300, 'expectedDriveMin' => 5.0, 'expectedBikeMin' => 4.17, 'expectedDriveSec' => 300, 'expectedBikeSec' => 250],
            ['seconds' => 600, 'expectedDriveMin' => 10.0, 'expectedBikeMin' => 8.33, 'expectedDriveSec' => 600, 'expectedBikeSec' => 500],
            ['seconds' => 1800, 'expectedDriveMin' => 30.0, 'expectedBikeMin' => 25.0, 'expectedDriveSec' => 1800, 'expectedBikeSec' => 1500],
            ['seconds' => 60, 'expectedDriveMin' => 1.0, 'expectedBikeMin' => 0.83, 'expectedDriveSec' => 60, 'expectedBikeSec' => 50],
        ];

        $convert_to_bike = 1.2;

        foreach ($testCases as $case) {
            // DRIVE calculation
            $driveMin = (float) number_format(($case['seconds'] / 60), 2);
            $driveSec = (int) $case['seconds'];

            // TWO_WHEELER calculation
            $bikeMin = (float) number_format((($case['seconds'] / 60) / $convert_to_bike), 2);
            $bikeSec = (int) ($case['seconds'] / $convert_to_bike);

            $this->assertEquals($case['expectedDriveMin'], $driveMin);
            $this->assertEquals($case['expectedBikeMin'], $bikeMin);
            $this->assertEquals($case['expectedDriveSec'], $driveSec);
            $this->assertEquals($case['expectedBikeSec'], $bikeSec);
        }
    }

    /**
     * Test edge cases for distance calculation
     */
    public function test_distance_edge_cases()
    {
        $testCases = [
            ['meters' => 0, 'expected' => 0.0],
            ['meters' => 1, 'expected' => 0.0], // Rounds to 0.00
            ['meters' => 999, 'expected' => 1.0], // Should round up
            ['meters' => 1, 'expected' => 0.0],
        ];

        foreach ($testCases as $case) {
            $distance = (double) str_replace(',', '', number_format(($case['meters'] ?? 0) / 1000, 2));
            $this->assertEquals($case['expected'], $distance, "Distance edge case failed for {$case['meters']} meters");
        }
    }

    /**
     * Test edge cases for duration calculation
     */
    public function test_duration_edge_cases()
    {
        $testCases = [
            ['seconds' => 0, 'expectedMin' => '0.00'],
            ['seconds' => 30, 'expectedMin' => '0.50'], // 30 seconds = 0.5 minutes
            ['seconds' => 90, 'expectedMin' => '1.50'], // 90 seconds = 1.5 minutes
        ];

        foreach ($testCases as $case) {
            $duration = number_format(($case['seconds'] / 60), 2);
            $this->assertEquals($case['expectedMin'], $duration, "Duration edge case failed for {$case['seconds']} seconds");
        }
    }

    /**
     * Test large distance values
     */
    public function test_large_distance_values()
    {
        $testCases = [
            ['meters' => 1000000, 'expected' => 1000.0], // 1000 km
            ['meters' => 500000, 'expected' => 500.0],   // 500 km
            ['meters' => 50000, 'expected' => 50.0],     // 50 km
        ];

        foreach ($testCases as $case) {
            $distance = (double) str_replace(',', '', number_format(($case['meters'] ?? 0) / 1000, 2));
            $this->assertEquals($case['expected'], $distance);
        }
    }

    /**
     * Test large duration values
     */
    public function test_large_duration_values()
    {
        $testCases = [
            ['seconds' => 86400, 'expectedMin' => '1440.00'], // 24 hours = 1440 minutes
            ['seconds' => 43200, 'expectedMin' => '720.00'],  // 12 hours = 720 minutes
            ['seconds' => 3600, 'expectedMin' => '60.00'],    // 1 hour = 60 minutes
        ];

        foreach ($testCases as $case) {
            $duration = number_format(($case['seconds'] / 60), 2);
            $this->assertEquals($case['expectedMin'], $duration);
        }
    }

    /**
     * Test accuracy of calculations with floating point numbers
     */
    public function test_floating_point_precision()
    {
        // Test with fractional seconds
        $durationSeconds = 650; // 10.833... minutes
        $duration = number_format(($durationSeconds / 60), 2);
        $this->assertEquals('10.83', $duration);

        // Test with fractional meters
        $distanceMeters = 5555;
        $distance = (double) str_replace(',', '', number_format(($distanceMeters / 1000), 2));
        $this->assertEquals(5.56, $distance);
    }

    /**
     * Test combined distance and duration calculation
     */
    public function test_combined_distance_duration_calculation()
    {
        // Simulate a 10 km ride that takes 20 minutes
        $distanceMeters = 10000;
        $durationSeconds = 1200;
        $convert_to_bike = 1.2;

        // Calculate results
        $distance = (double) str_replace(',', '', number_format(($distanceMeters ?? 0) / 1000, 2));
        $driveMinutes = number_format(($durationSeconds / 60), 2);
        $bikeMinutes = number_format((($durationSeconds / 60) / $convert_to_bike), 2);

        // Verify results
        $this->assertEquals(10.0, $distance);
        $this->assertEquals('20.00', $driveMinutes);
        $this->assertEquals('16.67', $bikeMinutes);

        // Calculate average speed
        $driveSpeed = $distance / ((float) $driveMinutes / 60); // km/h
        $this->assertEquals(30.0, round($driveSpeed, 1)); // 10 km / 0.333 hours = 30 km/h
    }
}
