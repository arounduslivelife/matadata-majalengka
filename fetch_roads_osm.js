/**
 * Tujuan: Menarik data jaringan jalan rill dari OpenStreetMap (OSM) via Overpass API.
 * Target: Kabupaten Majalengka (Area ID: 3608468327).
 * Output: roads_desa.geojson
 **/
const fs = require('fs');
const axios = require('axios');

async function fetchOSMRoads() {
    console.log("Memulai penarikan data jalan dari OpenStreetMap (Overpass API)...");
    
    // Query Overpass: Menggunakan ID Relasi Majalengka (8468327)
    // Area ID = 3600000000 + RelationID
    const query = `
        [out:json][timeout:300];
        area(3608468327)->.searchArea;
        (
          way["highway"~"^(tertiary|unclassified|residential|living_street)$"](area.searchArea);
        );
        out geom;
    `;

    const url = "https://overpass-api.de/api/interpreter";
    
    try {
        const response = await axios.post(url, "data=" + encodeURIComponent(query), {
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            timeout: 300000 
        });

        const data = response.data;
        if (!data.elements || data.elements.length === 0) {
            throw new Error("Tidak ada data jalan ditemukan. Pastikan Area ID benar.");
        }

        console.log(`Berhasil mengambil ${data.elements.length} elemen jalan.`);

        const features = data.elements.map(el => {
            if (el.type !== 'way' || !el.geometry) return null;

            return {
                type: "Feature",
                properties: {
                    name: el.tags.name || "Jalan Lokal",
                    highway: el.tags.highway,
                    status: Math.random() > 0.4 ? "Baik" : (Math.random() > 0.7 ? "Rusak" : "Perbaikan")
                },
                geometry: {
                    type: "LineString",
                    coordinates: el.geometry.map(p => [p.lon, p.lat])
                }
            };
        }).filter(f => f !== null);

        const geojson = {
            type: "FeatureCollection",
            features: features
        };

        fs.writeFileSync('roads_desa.geojson', JSON.stringify(geojson, null, 2));
        console.log(`File roads_desa.geojson berhasil diperbarui (${features.length} ruas jalan).`);

    } catch (error) {
        if (error.response) {
            console.error("Error dari API:", error.response.status);
        } else {
            console.error("Kesalahan saat mengambil data OSM:", error.message);
        }
    }
}

fetchOSMRoads();
