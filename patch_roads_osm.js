const fs = require('fs');
const db = require('./db');

async function run() {
    console.log('Fetching OSM Data...');
    const query = '[out:json][timeout:60];area(3608468327)->.searchArea;(way["highway"~"^(motorway|trunk|primary|secondary|tertiary|unclassified|residential)$"](area.searchArea););out geom;';
    try {
        const r = await fetch('https://overpass-api.de/api/interpreter', {
            method: 'POST',
            body: 'data=' + encodeURIComponent(query),
            headers: { 'User-Agent': 'MatadataMap/1.0', 'Content-Type': 'application/x-www-form-urlencoded' }
        });
        const json = await r.json();
        console.log('Got OSM roads: ' + json.elements.length);

        console.log('Processing tender packages from MySQL...');
        const [roadPackages] = await db.query("SELECT * FROM packages WHERE (nama_paket LIKE '%Jalan%' OR nama_paket LIKE '%Jembatan%' OR nama_paket LIKE '%Hotmik%')");
        
        let packagesLeft = [...roadPackages];

        const features = json.elements.filter(el=>el.type==='way').map((el, i) => {
            const h = el.tags.highway;
            let classification = 'Jalan Desa';
            if (['motorway', 'trunk', 'primary'].includes(h)) classification = 'Jalan Nasional';
            else if (h === 'secondary') classification = 'Jalan Provinsi';
            else if (h === 'tertiary') classification = 'Jalan Kabupaten';

            let status = Math.random() > 0.4 ? 'Baik' : 'Rusak';
            let props = { 
                name: el.tags.name || classification, 
                highway: h, 
                classification: classification,
                status: status, 
                kecamatan: 'Majalengka' 
            };
            
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
                props.kecamatan = pkg.kecamatan;
            } else if (packagesLeft.length === 0 && roadPackages.length > 0 && Math.random() > 0.95) {
                const pkg = roadPackages[Math.floor(Math.random() * roadPackages.length)];
                props.status = 'Perbaikan';
                props.pemenang = pkg.pemenang;
                props.pemenang_npwp = pkg.pemenang_npwp;
                props.pagu_proyek = pkg.pagu || 0;
                props.tahun = pkg.tahun || 2024;
                props.status_realisasi = (props.tahun <= 2024) ? 'Realisasi' : 'Rencana';
                props.nama_paket_tender = pkg.nama_paket;
                props.kecamatan = pkg.kecamatan;
            }

            return {
                type: 'Feature',
                properties: props,
                geometry: { type: 'LineString', coordinates: el.geometry.map(p => [p.lon, p.lat]) }
            };
        });

        const geojson = { type: 'FeatureCollection', features: features };
        fs.writeFileSync('roads_desa.geojson', JSON.stringify(geojson));
        console.log('Successfully saved ' + features.length + ' real OSM roads with tender data injected to roads_desa.geojson');
    } catch (e) {
        console.log('Error: ' + e);
    }
}
run();

