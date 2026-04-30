const fs = require('fs');
let content = fs.readFileSync('index.php', 'utf8');

// 1. Ensure the top part of the body is clean
content = content.replace(/<body>[\s\S]*?<div class="share-modal"/, '<body>\n\n<!-- Pull Indicator Tab -->\n<div class="pull-indicator" id="pullIndicator" onclick="toggleSidebar()">\n    <span class="text">MENU</span>\n    <span class="icon">›</span>\n</div>\n\n<div class="share-modal"');

// 2. Fix potential broken divs after GPS removal
// We need to make sure the sidebar and map are at the top level
content = content.replace(/<\/div>\s*<\?php include 'includes\/sidebar\.php'; \?>/, '</div>\n\n<?php include \'includes/sidebar.php\'; ?>');

fs.writeFileSync('index.php', content);
console.log('Final structure fix applied.');
