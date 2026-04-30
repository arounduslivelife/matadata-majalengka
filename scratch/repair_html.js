const fs = require('fs');
let content = fs.readFileSync('index.php', 'utf8');

// Replace everything between <body> and the share-modal with a clean structure
content = content.replace(/<body>[\s\S]*?<div class="share-modal"/, '<body>\n\n<!-- Pull Indicator Tab -->\n<div class="pull-indicator" id="pullIndicator" onclick="toggleSidebar()">\n    <span class="text">MENU</span>\n    <span class="icon">›</span>\n</div>\n\n<div class="share-modal"');

// Also remove the aggressive anti-inspect script to be safe
content = content.replace(/<script>\s*\/\/ ANTI-INSPECT PROTECTION LAYER[\s\S]*?<\/script>/, '');

fs.writeFileSync('index.php', content);
console.log('HTML structure repaired and Anti-Inspect removed.');
