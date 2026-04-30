const fs = require('fs');

/**
 * Reverse-Geocode: Perbaiki label kecamatan di GeoJSON
 * berdasarkan koordinat sebenarnya menggunakan polygon resmi.
 */

// --- Point-in-Polygon (Ray Casting) ---
function pip(lat, lng, ring) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const xi = ring[i][0], yi = ring[i][1];
        const xj = ring[j][0], yj = ring[j][1];
        if (((yi > lat) !== (yj > lat)) && (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi))
            inside = !inside;
    }
    return inside;
}

function findKecamatan(lat, lng, boundaries) {
    for (const f of boundaries.features) {
        const coords = f.geometry.coordinates;
        const type = f.geometry.type;
        let found = false;

        if (type === 'MultiPolygon') {
            for (const part of coords) {
                if (pip(lat, lng, part[0])) { found = true; break; }
            }
        } else if (type === 'Polygon') {
            found = pip(lat, lng, coords[0]);
        }

        if (found) {
            return {
                kecamatan: f.properties.district,
                desa: f.properties.village
            };
        }
    }
    return null; // Tidak ditemukan (di luar Majalengka)
}

// --- Load boundaries ---
console.log("Loading polygon boundaries...");
const boundaries = JSON.parse(fs.readFileSync('majalengka_kecamatan.json', 'utf8'));
console.log(`  ${boundaries.features.length} polygons loaded.\n`);

// --- Fix function ---
function fixLabels(inputFile, label) {
    console.log(`=== ${label}: ${inputFile} ===`);
    if (!fs.existsSync(inputFile)) {
        console.log(`  File tidak ditemukan, skip.\n`);
        return;
    }

    const data = JSON.parse(fs.readFileSync(inputFile, 'utf8'));
    let fixed = 0;
    let notFound = 0;
    const corrections = [];

    for (const feature of data.features) {
        const [lng, lat] = feature.geometry.coordinates;
        const oldKec = feature.properties.kecamatan || feature.properties.district || '(kosong)';
        
        const result = findKecamatan(lat, lng, boundaries);
        
        if (result) {
            const newKec = result.kecamatan;
            if (oldKec.toLowerCase().trim() !== newKec.toLowerCase().trim()) {
                corrections.push({
                    nama: feature.properties.nama || feature.properties.name || '?',
                    dari: oldKec,
                    ke: newKec,
                    desa: result.desa
                });
                fixed++;
            }
            // Update properti
            feature.properties.kecamatan = result.kecamatan;
            feature.properties.desa_geo = result.desa; // Tambah info desa berdasarkan koordinat
        } else {
            notFound++;
            console.log(`  ⚠ LUAR MAJALENGKA: ${feature.properties.nama || '?'} [${lng}, ${lat}]`);
        }
    }

    // Tampilkan koreksi
    if (corrections.length > 0) {
        console.log(`\n  Koreksi yang dilakukan:`);
        corrections.forEach(c => {
            console.log(`    📌 "${c.nama}": ${c.dari} → ${c.ke} (Desa ${c.desa})`);
        });
    }

    console.log(`\n  Total fitur: ${data.features.length}`);
    console.log(`  Label diperbaiki: ${fixed}`);
    console.log(`  Di luar Majalengka: ${notFound}`);

    // Save
    fs.writeFileSync(inputFile, JSON.stringify(data, null, 2));
    console.log(`  File tersimpan ✅\n`);
}

// --- EKSEKUSI ---
fixLabels('data/jembatan_kabupaten.geojson', 'Jembatan (Anggaran)');
fixLabels('data/jembatan_deep.geojson', 'Jembatan (Scraped AI)');
fixLabels('data/sarana_pendidikan.geojson', 'Sarana Pendidikan');

console.log("=== SELESAI: Semua label kecamatan sudah disesuaikan dengan koordinat ===");
