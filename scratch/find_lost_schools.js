const fs = require('fs');

function normalize(str) {
    if (!str) return '';
    return str.toLowerCase().trim()
        .replace(/sdn/g, 'sd negeri').replace(/smpn/g, 'smp negeri').replace(/sman/g, 'sma negeri').replace(/smkn/g, 'smk negeri')
        .replace(/ i$/g, ' 1').replace(/ ii$/g, ' 2').replace(/ iii$/g, ' 3').replace(/ iv$/g, ' 4').replace(/ v$/g, ' 5')
        .replace(/[^a-z0-9]/g, '');
}

const geo = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));
const geoMap = new Set();
geo.features.forEach(f => {
    geoMap.add(normalize(f.properties.nama));
});

const realisasi = JSON.parse(fs.readFileSync('data/realisasi_sekolah.geojson', 'utf8'));
const lostSchools = new Map();

realisasi.data.forEach(p => {
    const pkg = p.nama_paket;
    // Look for patterns like "Lokasi : [NAME]" or just a school name at the end
    let name = null;
    const match1 = pkg.match(/Lokasi\s*:\s*([^,Kecamatan|^,]+)/i);
    if (match1) {
        name = match1[1].trim();
    } else {
        const match2 = pkg.match(/(SDN|SMPN|SMAN|SMKN|SD|SMP|SMA|SMK|MTs|MA)\s+[A-Z0-9][a-zA-Z0-9\s-]+/i);
        if (match2) name = match2[0].trim().split(/Kecamatan|Kabupaten|,/i)[0].trim();
    }

    if (name) {
        const nName = normalize(name);
        if (nName.length > 5 && !geoMap.has(nName)) {
            lostSchools.set(nName, { name: name, kec: extractKec(p.nama_paket) });
        }
    }
});

function extractKec(str) {
    const m = str.match(/Kecamatan\s+([A-Za-z]+)/i);
    return m ? m[1] : 'Unknown';
}

console.log(`Lost Schools with Projects:`);
lostSchools.forEach(s => console.log(`- ${s.name} (${s.kec})`));
