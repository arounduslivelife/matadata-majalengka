const fs = require('fs');
const path = require('path');

// Paths
const currentGeoJsonPath = 'data/sarana_pendidikan.geojson';
const ultraJsonPath = 'data/found_schools_ultra.json';
const outputPath = 'data/sarana_pendidikan.geojson'; // Overwrite

// Load current data
const currentGeoJson = JSON.parse(fs.readFileSync(currentGeoJsonPath, 'utf8'));
const ultraData = JSON.parse(fs.readFileSync(ultraJsonPath, 'utf8'));

// Helper to normalize names for matching
function normalize(str) {
    if (!str) return '';
    return str.toLowerCase()
        .replace(/sdn/g, 'sd negeri')
        .replace(/smpn/g, 'smp negeri')
        .replace(/sman/g, 'sma negeri')
        .replace(/smkn/g, 'smk negeri')
        .replace(/[^a-z0-9]/g, '');
}

// Map existing schools by name+kecamatan
const existingMap = new Map();
currentGeoJson.features.forEach(f => {
    const key = normalize(f.properties.nama) + '|' + normalize(f.properties.kecamatan);
    existingMap.set(key, f);
});

console.log(`Current Schools: ${currentGeoJson.features.length}`);
console.log(`Ultra Schools: ${ultraData.data.length}`);

const newFeatures = [];
let updatedCount = 0;
let addedCount = 0;

// Track processed keys to avoid duplicates from ultra
const processedKeys = new Set();

ultraData.data.forEach(school => {
    const key = normalize(school.nama) + '|' + normalize(school.kecamatan);
    
    if (processedKeys.has(key)) return;
    processedKeys.add(key);

    const existing = existingMap.get(key);
    
    if (existing) {
        // Update coordinates and basic info, but keep packages
        existing.geometry.coordinates = [school.lng, school.lat];
        existing.properties.nama = school.nama; // Use ultra name as it might be cleaner
        existing.properties.kecamatan = school.kecamatan;
        newFeatures.push(existing);
        updatedCount++;
    } else {
        // Add as new school
        newFeatures.push({
            type: "Feature",
            geometry: {
                type: "Point",
                coordinates: [school.lng, school.lat]
            },
            properties: {
                nama: school.nama,
                kecamatan: school.kecamatan,
                paket: [],
                is_project: false,
                catatan: "Lokasi Terverifikasi (Ultra Deep Scraper)",
                desa_geo: "" // Village name not provided in ultra data
            }
        });
        addedCount++;
    }
});

// Check if any existing schools were NOT in ultra
// (We should keep them unless they are definitively wrong)
existingMap.forEach((feature, key) => {
    if (!processedKeys.has(key)) {
        newFeatures.push(feature);
        console.log(`Keeping existing school not found in ultra: ${feature.properties.nama}`);
    }
});

const finalGeoJson = {
    type: "FeatureCollection",
    features: newFeatures
};

fs.writeFileSync(outputPath, JSON.stringify(finalGeoJson, null, 2));

console.log(`--- SUMMARY ---`);
console.log(`Updated existing schools: ${updatedCount}`);
console.log(`Added new schools: ${addedCount}`);
console.log(`Total schools now: ${newFeatures.length}`);
