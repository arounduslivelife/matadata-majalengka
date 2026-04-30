const fs = require('fs');
const content = fs.readFileSync('index.php', 'utf8');
const scriptMatch = content.match(/<script>([\s\S]*?)<\/script>/g);
if (scriptMatch) {
    scriptMatch.forEach((script, idx) => {
        const open = (script.match(/{/g) || []).length;
        const close = (script.match(/}/g) || []).length;
        console.log(`Script block ${idx}: { count = ${open}, } count = ${close}`);
        if (open !== close) {
            console.log("UNBALANCED BRACES in script block " + idx);
        }
    });
}
