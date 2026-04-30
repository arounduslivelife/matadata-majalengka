const fs = require('fs');
const { execSync } = require('child_process');

function normalize(str) {
    if (!str) return '';
    return str.toLowerCase()
        .replace(/sdn/g, 'sd negeri')
        .replace(/smpn/g, 'smp negeri')
        .replace(/sman/g, 'sma negeri')
        .replace(/smkn/g, 'smk negeri')
        .replace(/ i$/g, ' 1').replace(/ ii$/g, ' 2').replace(/ iii$/g, ' 3')
        .replace(/ iv$/g, ' 4').replace(/ v$/g, ' 5').replace(/ vi$/g, ' 6')
        .replace(/[^a-z0-9]/g, '');
}

const geo = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));
const geoMap = new Set();
geo.features.forEach(f => {
    geoMap.add(normalize(f.properties.nama));
});

const output = execSync(`sqlite3 database.sqlite "SELECT DISTINCT nama_paket FROM packages WHERE nama_paket LIKE '%SD %' OR nama_paket LIKE '%SMP %' OR nama_paket LIKE '%SMA %' OR nama_paket LIKE '%SMK %'"`).toString();
const projects = output.split('\n').filter(l => l.trim() !== '');

const missing = [];
projects.forEach(pkt => {
    const match = pkt.match(/(SDN|SMPN|SMAN|SMKN|SD|SMP|SMA|SMK|MTs|MA)\s+[A-Z0-9][a-zA-Z0-9\s-]+/i);
    if (match) {
        let rawName = match[0].split(/Kecamatan|Kabupaten|,/i)[0].trim();
        if (rawName.length > 5 && !geoMap.has(normalize(rawName))) {
            missing.push({ name: rawName, project: pkt });
        }
    }
});

console.log(`Physically Missing Schools (Not related to Roman Numerals): ${missing.length}`);
missing.slice(0, 15).forEach(m => console.log(`- ${m.name}`));
