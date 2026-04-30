const fs = require('fs');
const { execSync } = require('child_process');

// Load Current Schools
const geo = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));
const geoNames = new Set(geo.features.map(f => f.properties.nama.toLowerCase().trim()
    .replace(/sdn/g, 'sd negeri')
    .replace(/[^a-z0-9]/g, '')
));

// Load Projects from SQLite
// We'll search for things that look like school names in nama_paket
const sqliteQuery = `sqlite3 database.sqlite "SELECT DISTINCT nama_paket FROM packages WHERE nama_paket LIKE '%SD %' OR nama_paket LIKE '%SMP %' OR nama_paket LIKE '%SMA %' OR nama_paket LIKE '%SMK %' OR nama_paket LIKE '%SDN %' OR nama_paket LIKE '%SMPN %'"`;
const output = execSync(sqliteQuery).toString();
const projects = output.split('\n').filter(line => line.trim() !== '');

function normalize(str) {
    return str.toLowerCase().trim()
        .replace(/sdn/g, 'sd negeri')
        .replace(/[^a-z0-9]/g, '');
}

const unmappedSchools = new Set();

projects.forEach(pkt => {
    // Extract school name candidate
    // Match something like "SD Negeri [Name]" or "SMK [Name]"
    const match = pkt.match(/(SDN|SMPN|SMAN|SMKN|SD|SMP|SMA|SMK|MTs|MA)\s+[A-Z0-9][a-zA-Z0-9\s-]+/i);
    if (match) {
        let name = match[0].trim();
        // Cut at "Kecamatan" or "Kabupaten"
        name = name.split(/Kecamatan|Kabupaten|,/i)[0].trim();
        
        const nName = normalize(name);
        if (nName.length > 5 && !geoNames.has(nName)) {
            unmappedSchools.add(`${name} (Found in: ${pkt.substring(0, 50)}...)`);
        }
    }
});

console.log(`Summary of Schools mentioned in budget but missing from Map:`);
console.log(`Total: ${unmappedSchools.size}`);
Array.from(unmappedSchools).slice(0, 20).forEach(s => console.log(`- ${s}`));
