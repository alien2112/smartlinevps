<div class="map-container h-100" style="position: relative; min-height: 500px;">
    <input class="form-control map-search-input" type="text"
           placeholder="{{ translate('Search for a location') }}">
    <div id="map" class="heat-map rounded map h-100" style="min-height: 500px; width: 100%;" 
         data-lat="{{ $centerLat ?? 30.0444 }}" 
         data-lng="{{ $centerLng ?? 31.2357 }}"
         data-title="Heat Map"
         data-markers='{{ $markers ?? "[]" }}'
         data-polygon='{{ $polygons ?? "[]" }}'
    >
        <div class="d-flex align-items-center justify-content-center h-100">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">{{ translate('Loading map...') }}</span>
            </div>
        </div>
    </div>
</div>

