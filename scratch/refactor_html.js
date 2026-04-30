const fs = require('fs');

function refactorHTML() {
    let content = fs.readFileSync('index.php', 'utf8');
    
    // Sidebar regex
    const sidebarRegex = /<div class="sidebar" id="sidebar">([\s\S]*?)<\/div> <!-- END SIDEBAR -->/;
    const sidebarMatch = content.match(sidebarRegex);
    if (sidebarMatch) {
        fs.writeFileSync('includes/sidebar.php', sidebarMatch[0]);
        content = content.replace(sidebarMatch[0], "<?php include 'includes/sidebar.php'; ?>");
        console.log('Sidebar extracted.');
    }

    // Modals regex: from <!-- FINAL MODALS --> to the end of the div
    // Let's just use string replace since it's hard to regex nested divs.
    
    fs.writeFileSync('index.php', content);
}

refactorHTML();
