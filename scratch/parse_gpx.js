const fs = require('fs');
const gpxFile = 'raw/osmtitikjembatanseluruhmajalengka.gpx';

try {
    const data = fs.readFileSync(gpxFile, 'utf8');
    const wptRegex = /<wpt lat="([^"]+)" lon="([^"]+)">[\s\S]*?<name>([^<]+)<\/name>[\s\S]*?<\/wpt>/g;
    
    let match;
    const bridges = [];
    while ((match = wptRegex.exec(data)) !== null) {
        bridges.push({
            lat: parseFloat(match[1]),
            lon: parseFloat(match[2]),
            osm_name: match[3]
        });
    }

    console.log(`TOTAL_WPTS:${bridges.length}`);
    fs.writeFileSync('scratch/bridge_list.json', JSON.stringify(bridges, null, 2));
    console.log("Data jembatan disimpan di scratch/bridge_list.json");
} catch (err) {
    console.error("Gagal memproses file:", err);
}
