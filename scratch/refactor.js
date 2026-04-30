const fs = require('fs');

function refactorCSS() {
    let content = fs.readFileSync('index.php', 'utf8');
    const styleRegex = /<style>([\s\S]*?)<\/style>/;
    const match = content.match(styleRegex);

    if (match) {
        fs.writeFileSync('assets/css/style.css', match[1].trim());
        const newContent = content.replace(styleRegex, '<link rel="stylesheet" href="assets/css/style.css">');
        fs.writeFileSync('index.php', newContent);
        console.log('CSS extracted successfully.');
    } else {
        console.log('No style tag found.');
    }
}

refactorCSS();
