const fs = require('fs');
let content = fs.readFileSync('index.php', 'utf8');

// Remove GPS Blur Overlay
content = content.replace(/<!-- GPS Blur Overlay -->[\s\S]*?<div class="gps-blur-msg">[\s\S]*?<\/div>\s*<\/div>/, '');

// Remove Floating GPS Button
content = content.replace(/<button class="gps-float-btn"[\s\S]*?<\/button>/, '');

fs.writeFileSync('index.php', content);
console.log('GPS elements removed successfully.');
