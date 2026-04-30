const fs = require('fs');

const geojson = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

const preciseCoords = {
    "SDN 1 Dawuan": [-6.7656323, 108.1721641],
    "SMPN 1 Majalengka": [-6.8351073, 108.2336324],
    "SMPN 2 Lemahsugih": [-6.9844221, 108.2058725],
    "SDN 1 Palasah": [-6.7219957, 108.2972744],
    "SDN 2 Argapura": [-6.9388301, 108.3150036],
    "TK Pembina Majalengka": [-6.8258850, 108.2500503],
    "SDN 4 Talaga": [-6.9810899, 108.3110963],
    "SMPN 1 Cigasong": [-6.8318884, 108.2598070],
    "SDN 3 Kadipaten": [-6.7865645, 108.1728752],
    "SMPN 1 Jatitujuh": [-6.6421024, 108.2303449]
};

geojson.features = geojson.features.map(f => {
    const schoolName = f.properties.nama_sekolah;
    if (preciseCoords[schoolName]) {
        f.geometry.coordinates = [preciseCoords[schoolName][1], preciseCoords[schoolName][0]];
        f.properties.catatan = "Koordinat Presisi (Google Maps)";
    }
    return f;
});

fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(geojson, null, 2));
console.log("Update Koordinat Sekolah Selesai!");
