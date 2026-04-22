const fs = require('fs');
const code = fs.readFileSync('index.php', 'utf8');

// Try to find the script tags and validate the content
const scriptMatch = code.match(/<script>([\s\S]*?)<\/script>/g);
if (scriptMatch) {
    scriptMatch.forEach((script, i) => {
        try {
            // Remove the <script> tags for testing
            const js = script.replace(/<script>|<\/script>/g, '');
            // We can't easily run the JS because it contains PHP tags like <?= ... ?>,
            // but we can check for obvious unclosed strings or corrupted chars.
            console.log(`Checking script block ${i}...`);
            if (js.includes('')) {
                console.error(`Block ${i} contains replacement character (REPLACEMENT CHARACTER).`);
            }
        } catch (e) {
            console.error(`Syntax error in script block ${i}: ${e.message}`);
        }
    });
}
