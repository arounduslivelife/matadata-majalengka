const fs = require('fs');
let content = fs.readFileSync('index.php', 'utf8');

// Sidebar/HTML fixes
content = content.replace(/color: #22d3ee;\">[^<]* Daftar Penggarap Aktif/, 'color: #22d3ee;\">🏗️ Daftar Penggarap Aktif');
content = content.replace(/id=\"filterActiveBanner\">\s*<span>[^<]*<\/span>/, 'id=\"filterActiveBanner\">\n                <span>🔍</span>');
content = content.replace(/onclick=\"resetContractorFilter\(\)\">[^<]* Reset/, 'onclick=\"resetContractorFilter()\">✕ Reset');

// Re-apply JS fixes just in case
const newStatusIcon = "const statusIcon = p.status === 'Rusak' ? '🔴' : (p.status === 'Perbaikan' ? '🟡' : '🟢');";
content = content.replace(/const statusIcon = p\.status === 'Rusak' \? '[^']*' : \(p\.status === 'Perbaikan' \? '[^']*' : '[^']*'\);/, newStatusIcon);
content = content.replace(/const realisasiIcon = \(p\.status_realisasi === 'Realisasi'\) \? '[^']*' : '[^']*';/, "const realisasiIcon = (p.status_realisasi === 'Realisasi') ? '✅' : '📋';");
content = content.replace(/margin-bottom:6px;\">[^<]* DATA PENGGARAP<\/div>/, 'margin-bottom:6px;\">🏗️ DATA PENGGARAP</div>');
content = content.replace(/div style=\"font-size:0.6rem; opacity:0.4;\">[^<]* \${lat}, \${lng}<\/div>/, 'div style=\"font-size:0.6rem; opacity:0.4;\">📍 ${lat}, ${lng}</div>');
content = content.replace(/'[^']* Buka di Google Maps [^']*'/, "'🗺️ Buka di Google Maps ↗'");

fs.writeFileSync('index.php', content, 'utf8');
console.log('Successfully fully patched index.php with correct emojis.');
