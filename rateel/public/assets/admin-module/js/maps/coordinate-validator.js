/**
 * Coordinate Validator Utility
 *
 * Standalone utility for validating and normalizing geographic coordinates
 * Specifically designed for Egypt bounding box validation
 *
 * Usage:
 *   const validator = new CoordinateValidator();
 *   const result = validator.validate(lat, lng);
 *   if (result.valid) {
 *     console.log('Valid:', result.lat, result.lng);
 *   }
 */

class CoordinateValidator {
    constructor(options = {}) {
        // Default to Egypt bounding box
        this.bounds = options.bounds || {
            minLat: 22,
            maxLat: 32,
            minLng: 25,
            maxLng: 36
        };

        this.fallbackCenter = options.fallbackCenter || {
            lat: 30.0444, // Cairo
            lng: 31.2357
        };

        this.autoSwap = options.autoSwap !== false; // Default true
        this.verbose = options.verbose || false;
    }

    /**
     * Check if coordinates are within bounds
     */
    isWithinBounds(lat, lng) {
        return lat >= this.bounds.minLat &&
               lat <= this.bounds.maxLat &&
               lng >= this.bounds.minLng &&
               lng <= this.bounds.maxLng;
    }

    /**
     * Validate and normalize a single coordinate pair
     *
     * @param {number|string} lat - Latitude
     * @param {number|string} lng - Longitude
     * @returns {object} - {valid: boolean, lat: number, lng: number, swapped: boolean, error: string}
     */
    validate(lat, lng) {
        // Convert to float
        lat = parseFloat(lat);
        lng = parseFloat(lng);

        // Check if numeric
        if (isNaN(lat) || isNaN(lng)) {
            return {
                valid: false,
                lat: null,
                lng: null,
                swapped: false,
                error: 'Coordinates are not numeric'
            };
        }

        // Check if within bounds
        if (this.isWithinBounds(lat, lng)) {
            if (this.verbose) {
                console.log('✅ Valid coordinates:', {lat, lng});
            }
            return {
                valid: true,
                lat: lat,
                lng: lng,
                swapped: false,
                error: null
            };
        }

        // Try swapping if autoSwap is enabled
        if (this.autoSwap && this.isWithinBounds(lng, lat)) {
            if (this.verbose) {
                console.warn('🔄 Swapped coordinates:', {
                    original: {lat, lng},
                    fixed: {lat: lng, lng: lat}
                });
            }
            return {
                valid: true,
                lat: lng,
                lng: lat,
                swapped: true,
                error: null
            };
        }

        // Invalid
        if (this.verbose) {
            console.error('❌ Invalid coordinates:', {lat, lng}, 'Bounds:', this.bounds);
        }
        return {
            valid: false,
            lat: null,
            lng: null,
            swapped: false,
            error: `Coordinates outside bounds (lat: ${this.bounds.minLat}-${this.bounds.maxLat}, lng: ${this.bounds.minLng}-${this.bounds.maxLng})`
        };
    }

    /**
     * Validate an array of coordinates
     *
     * @param {Array} coordinates - Array of {lat, lng} objects
     * @param {boolean} removeInvalid - Remove invalid coordinates instead of failing
     * @returns {object} - {valid: boolean, coordinates: Array, errors: Array}
     */
    validateArray(coordinates, removeInvalid = true) {
        if (!Array.isArray(coordinates)) {
            return {
                valid: false,
                coordinates: [],
                errors: ['Input is not an array']
            };
        }

        const validated = [];
        const errors = [];

        coordinates.forEach((coord, index) => {
            if (!coord || typeof coord !== 'object') {
                errors.push(`Index ${index}: Not an object`);
                return;
            }

            const lat = coord.lat || coord.latitude;
            const lng = coord.lng || coord.lng || coord.longitude;

            const result = this.validate(lat, lng);

            if (result.valid) {
                validated.push({
                    lat: result.lat,
                    lng: result.lng,
                    swapped: result.swapped
                });
            } else {
                errors.push(`Index ${index}: ${result.error}`);
            }
        });

        return {
            valid: removeInvalid ? validated.length > 0 : errors.length === 0,
            coordinates: validated,
            errors: errors,
            stats: {
                total: coordinates.length,
                valid: validated.length,
                invalid: errors.length,
                swapped: validated.filter(c => c.swapped).length
            }
        };
    }

    /**
     * Validate polygon (must have at least 3 points)
     *
     * @param {Array} polygon - Array of coordinate objects
     * @param {boolean} autoClose - Automatically close polygon by adding first point at end
     * @returns {object} - {valid: boolean, polygon: Array, errors: Array}
     */
    validatePolygon(polygon, autoClose = true) {
        const result = this.validateArray(polygon, true);

        if (result.coordinates.length < 3) {
            return {
                valid: false,
                polygon: [],
                errors: result.errors.concat(['Polygon must have at least 3 valid points'])
            };
        }

        const validatedPolygon = result.coordinates;

        // Auto-close polygon if needed
        if (autoClose) {
            const first = validatedPolygon[0];
            const last = validatedPolygon[validatedPolygon.length - 1];

            if (first.lat !== last.lat || first.lng !== last.lng) {
                validatedPolygon.push({
                    lat: first.lat,
                    lng: first.lng,
                    swapped: false
                });
            }
        }

        return {
            valid: true,
            polygon: validatedPolygon,
            errors: result.errors,
            stats: result.stats
        };
    }

    /**
     * Calculate center from array of coordinates
     *
     * @param {Array} coordinates - Array of {lat, lng} objects
     * @returns {object|null} - {lat, lng} or null if no valid coordinates
     */
    calculateCenter(coordinates) {
        const result = this.validateArray(coordinates, true);

        if (result.coordinates.length === 0) {
            if (this.verbose) {
                console.warn('⚠️ No valid coordinates, using fallback center');
            }
            return this.fallbackCenter;
        }

        let latSum = 0;
        let lngSum = 0;

        result.coordinates.forEach(coord => {
            latSum += coord.lat;
            lngSum += coord.lng;
        });

        return {
            lat: latSum / result.coordinates.length,
            lng: lngSum / result.coordinates.length
        };
    }

    /**
     * Get fallback center (Cairo by default)
     */
    getFallbackCenter() {
        return {...this.fallbackCenter};
    }

    /**
     * Test if coordinates need swapping
     */
    static needsSwapping(lat, lng, bounds) {
        return lat < bounds.minLat || lat > bounds.maxLat ||
               lng < bounds.minLng || lng > bounds.maxLng;
    }

    /**
     * Bulk validate and report
     */
    validateAndReport(data, name = 'Dataset') {
        console.group(`📊 Validation Report: ${name}`);

        if (Array.isArray(data)) {
            const result = this.validateArray(data, true);
            console.log('Total coordinates:', result.stats.total);
            console.log('✅ Valid:', result.stats.valid);
            console.log('❌ Invalid:', result.stats.invalid);
            console.log('🔄 Swapped:', result.stats.swapped);

            if (result.errors.length > 0) {
                console.warn('Errors:', result.errors);
            }

            console.groupEnd();
            return result;
        }

        console.error('Invalid input type');
        console.groupEnd();
        return null;
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CoordinateValidator;
}

// Example usage
if (typeof window !== 'undefined') {
    window.CoordinateValidator = CoordinateValidator;

    // Create global instance with Egypt defaults
    window.egyptValidator = new CoordinateValidator({
        verbose: true
    });

    console.log('✅ CoordinateValidator loaded. Usage:');
    console.log('  const result = egyptValidator.validate(lat, lng);');
    console.log('  const polygonResult = egyptValidator.validatePolygon(polygonArray);');
}
