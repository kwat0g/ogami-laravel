<?php

declare(strict_types=1);

use App\Domains\Attendance\Services\GeoFenceService;

it('computes correct distance between two coordinates', function () {
    $service = new GeoFenceService();

    // Makati (14.5547, 121.0244) to BGC (14.5503, 121.0494) ~2.7km
    $distance = $service->distanceMeters(14.5547, 121.0244, 14.5503, 121.0494);

    expect($distance)->toBeGreaterThan(2500);
    expect($distance)->toBeLessThan(3000);
});

it('computes zero distance for same coordinates', function () {
    $service = new GeoFenceService();

    $distance = $service->distanceMeters(14.5547, 121.0244, 14.5547, 121.0244);

    expect($distance)->toBe(0.0);
});

it('computes correct distance for nearby coordinates within 100m', function () {
    $service = new GeoFenceService();

    // Two points ~50m apart
    $distance = $service->distanceMeters(14.55470, 121.02440, 14.55475, 121.02480);

    expect($distance)->toBeGreaterThan(30);
    expect($distance)->toBeLessThan(100);
});

it('haversine formula is symmetric', function () {
    $service = new GeoFenceService();

    $d1 = $service->distanceMeters(14.5547, 121.0244, 14.6000, 121.0800);
    $d2 = $service->distanceMeters(14.6000, 121.0800, 14.5547, 121.0244);

    expect(round($d1, 2))->toBe(round($d2, 2));
});
