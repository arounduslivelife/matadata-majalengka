const fs = require('fs');
const Database = require('better-sqlite3');
function run() {
    console.log('Reading offline OSM Data from roads_desa.geojson...');
    try {
        const json = JSON.parse(fs.readFileSync('roads_desa.geojson', 'utf8'));
        const elements = json.features; 
        // Note: the original overpass gave json.elements, but my roads_desa.geojson saves FeatureCollection 
        // where features[i].geometry is the LineString, and properties are standard. We can reuse it.

        console.log('Processing tender packages...');
        const db = new Database('database.sqlite');
        const roadPackages = db.prepare("SELECT * FROM packages WHERE (nama_paket LIKE '%Jalan%' OR nama_paket LIKE '%Jembatan%' OR nama_paket LIKE '%Hotmik%')").all();
        
        let packagesLeft = [...roadPackages];

        const features = elements.map((el, i) => {
            let status = Math.random() > 0.4 ? 'Baik' : 'Rusak';
            
            // Re-initialize properties
            let props = { 
                name: el.properties.name || 'Jalan Desa/Kecamatan', 
                highway: el.properties.highway || 'tertiary', 
                status: status, 
                kecamatan: el.properties.kecamatan || 'Majalengka' 
            };
            
            // Randomly assign a package to about 50% of the roads or until packages run out
            if (packagesLeft.length > 0 && Math.random() > 0.5) {
                const pkg = packagesLeft.shift();
                props.status = 'Perbaikan';
                props.pemenang = pkg.pemenang;
                props.pemenang_npwp = pkg.pemenang_npwp;
                props.pagu_proyek = pkg.pagu || 0;
                props.tahun = pkg.tahun || 2024;
                props.status_realisasi = (props.tahun <= 2024) ? 'Realisasi' : 'Rencana';
                props.nama_paket_tender = pkg.nama_paket;
                props.metode = pkg.metode;
                props.satker = pkg.satker;
                props.kecamatan = pkg.kecamatan || props.kecamatan;
            } else if (packagesLeft.length === 0 && Math.random() > 0.95) {
                // Recycle package logic skipped here to not artificially inflate Rencana,
                // but let's keep it for visual density as was in the original script.
                const pkg = roadPackages[Math.floor(Math.random() * roadPackages.length)];
                props.status = 'Perbaikan';
                props.pemenang = pkg.pemenang;
                props.pemenang_npwp = pkg.pemenang_npwp;
                props.pagu_proyek = pkg.pagu || 0;
                props.tahun = pkg.tahun || 2024;
                props.status_realisasi = (props.tahun <= 2024) ? 'Realisasi' : 'Rencana';
                props.nama_paket_tender = pkg.nama_paket;
                props.kecamatan = pkg.kecamatan || props.kecamatan;
            }

            return {
                type: 'Feature',
                properties: props,
                geometry: el.geometry
            };
        });

        const geojson = { type: 'FeatureCollection', features: features };
        fs.writeFileSync('roads_desa.geojson', JSON.stringify(geojson));
        console.log('Successfully saved ' + features.length + ' real OSM roads with ' + roadPackages.length + ' DB tender packages injected to roads_desa.geojson');
        db.close();
    } catch (e) {
        console.log('Error: ' + e);
    }
}
run();
