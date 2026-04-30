const fs = require('fs');

const bridges = JSON.parse(fs.readFileSync('scratch/bridge_with_districts.json', 'utf8'));

const finalData = bridges.map(b => ({
    nama: (b.osm_name && b.osm_name !== '11' && !b.osm_name.startsWith('way/')) ? b.osm_name : `Jembatan ${b.kecamatan}`,
    korrdinat: `${b.lat}, ${b.lon}`,
    kecamatan: b.kecamatan
}));

fs.writeFileSync('data/jembatan_seluruh_majalengka.json', JSON.stringify(finalData, null, 2));
console.log("JSON Seluruh Majalengka Berhasil Dibuat!");
