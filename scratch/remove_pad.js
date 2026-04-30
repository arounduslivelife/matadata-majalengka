const fs = require('fs');
let c = fs.readFileSync('index.php', 'utf8');
// Remove the PAD layer item with a more robust regex to handle whitespace
c = c.replace(/<div class="layer-item" onclick="selectModeFromDock\('pad'\)">Analisis PAD<\/div>/, '');
fs.writeFileSync('index.php', c);
console.log('PAD layer removed from index.php');
