const fs = require('fs');

function extractGPS() {
    let mainJS = fs.readFileSync('assets/js/main.js', 'utf8');
    
    // Regex to match requestGPS and skipGPS functions
    const gpsRegex = /\/\/ GPS Capture Logic[\s\S]*?function skipGPS\(\) \{[\s\S]*?\}\n/g;
    const match = mainJS.match(gpsRegex);
    
    if (match) {
        // Create gps.js
        const gpsCode = match[0];
        fs.writeFileSync('assets/js/gps.js', gpsCode);
        
        // Remove from main.js
        mainJS = mainJS.replace(gpsRegex, '');
        fs.writeFileSync('assets/js/main.js', mainJS);
        
        // Add gps.js to index.php
        let indexPHP = fs.readFileSync('index.php', 'utf8');
        indexPHP = indexPHP.replace('<script src="assets/js/main.js"></script>', '<script src="assets/js/gps.js"></script>\n<script src="assets/js/main.js"></script>');
        
        // Also extract the HTML modal
        const htmlRegex = /<!-- GPS Blur Overlay -->[\s\S]*?<!-- Pull Indicator Tab -->/;
        const htmlMatch = indexPHP.match(htmlRegex);
        
        if (htmlMatch) {
            let htmlCode = htmlMatch[0].replace('<!-- Pull Indicator Tab -->', '').trim();
            fs.writeFileSync('includes/gps_modal.php', htmlCode);
            indexPHP = indexPHP.replace(htmlMatch[0], "<?php include 'includes/gps_modal.php'; ?>\n\n<!-- Pull Indicator Tab -->");
        }
        
        fs.writeFileSync('index.php', indexPHP);
        console.log('GPS logic and HTML extracted successfully.');
    } else {
        console.log('Could not find GPS logic in main.js');
    }
}

extractGPS();
