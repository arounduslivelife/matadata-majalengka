const fs = require('fs');

console.log('Patching index.php for character integrity...');

// Read as buffer to handle bad bytes
const buf = fs.readFileSync('index.php');

// Convert to string, but we'll use regex to find the problematic areas
let content = buf.toString('utf8');

// Replace the problematic lines with clean ASCII-safe versions
// This fixes the JS parsing issue
content = content.replace(/const statusIcon = p\.status === 'Rusak' \? '[^']*' : \(p\.status === 'Perbaikan' \? '[^']*' : '[^']*'\);/g, "const statusIcon = p.status === 'Rusak' ? '(X)' : (p.status === 'Perbaikan' ? '(!)' : '(V)');");
content = content.replace(/const realisasiIcon = \(p\.status_realisasi === 'Realisasi'\) \? '[^']*' : '[^']*';/g, "const realisasiIcon = (p.status_realisasi === 'Realisasi') ? '[OK]' : '[PLAN]';");

// Fix HTML entities if they are mangled
content = content.replace(/🏗️/g, '[ICON]');
content = content.replace(/🔍/g, '[SEARCH]');
content = content.replace(/✕/g, 'X');

// Fix the "DATA PENGGARAP" header mangling
content = content.replace(/uppercase; letter-spacing:1px; color:#06b6d4; opacity:0.8; margin-bottom:6px;\">[^<]* DATA PENGGARAP<\/div>/g, 'uppercase; letter-spacing:1px; color:#06b6d4; opacity:0.8; margin-bottom:6px;">DATA PENGGARAP</div>');

// Fix the "Location" mangling
content = content.replace(/padding:6px; background:rgba\(6,182,212,0.15\); border-radius:8px; color:#22d3ee; text-decoration:none; font-size:0.72rem; font-weight:600;\">[^<]* Buka di Google Maps [^<]*<\/a>/g, 'padding:6px; background:rgba(6,182,212,0.15); border-radius:8px; color:#22d3ee; text-decoration:none; font-size:0.72rem; font-weight:600;">Buka di Google Maps</a>');

fs.writeFileSync('index.php', content, 'utf8');
console.log('Patching complete. JS should be valid now.');
