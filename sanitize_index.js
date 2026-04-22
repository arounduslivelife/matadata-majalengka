const fs = require('fs');
let content = fs.readFileSync('index.php', 'utf8');

console.log('Sanitizing index.php from corrupted characters...');

// Function to replace corrupted emoji-like patterns with safe text/escapes
function sanitize(text) {
    // Replace specific known corrupted sequences (dY" etc)
    // and also general replacement chars
    return text
        .replace(/dY"[^'"`\s]*/g, ' [icon] ')
        .replace(/dYY/g, ' [icon] ')
        .replace(//g, '')
        .replace(//g, '')
        .replace(//g, '');
}

// Target the specific section that was mangled (road popup)
const startMarker = 'onEachFeature: function(f, layer) {';
const endMarker = 'layer._contractorName = p.pemenang || null;';

const startIndex = content.indexOf(startMarker, content.indexOf('roadLayer = L.geoJson'));
const endIndex = content.indexOf(endMarker, startIndex);

if (startIndex !== -1 && endIndex !== -1) {
    console.log('Found roadLayer block. Cleaning...');
    let block = content.substring(startIndex, endIndex + endMarker.length);
    
    // Manual clean for the specific bad lines observed in powershell
    block = block.replace(/statusIcon = p\.status === 'Rusak' \? '[^']*' : \(p\.status === 'Perbaikan' \? '[^']*' : '[^']*'\);/g, "statusIcon = p.status === 'Rusak' ? '(!) ' : (p.status === 'Perbaikan' ? '(?) ' : '(V) ');");
    block = block.replace(/realisasiIcon = \(p\.status_realisasi === 'Realisasi'\) \? '[^']*' : '[^']*';/g, "realisasiIcon = (p.status_realisasi === 'Realisasi') ? '[OK] ' : '[PLAN] ';");
    block = block.replace(/dY\?-\?\1, DATA PENGGARAP/g, 'DATA PENGGARAP');
    block = block.replace(/dY\"\? \${lat}, \${lng}/g, `Location: \${lat}, \${lng}`);
    block = block.replace(/dY-,? Buka di Google Maps \+-/g, 'Buka di Google Maps');
    
    // General cleanup for anything else in this block
    block = sanitize(block);
    
    content = content.substring(0, startIndex) + block + content.substring(endIndex + endMarker.length);
}

// Clean the sidebar/HTML part too
content = content.replace(/🏗️/g, '[icon]');
content = content.replace(/🔍/g, '[search]');
content = content.replace(/✕/g, 'X');
content = content.replace(/🏗️/g, '[icon]');

fs.writeFileSync('index.php', content, 'utf8');
console.log('Sanitization complete.');
