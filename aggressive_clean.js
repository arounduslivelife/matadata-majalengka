const fs = require('fs');

// Read as buffer
const buf = fs.readFileSync('index.php');

// Helper to check if a byte is ASCII
function isAscii(b) {
    return b >= 0 && b <= 127;
}

// Create a new buffer with only ASCII characters
// Note: This is aggressive, but will guarantee JS validity
let cleanBuf = Buffer.alloc(buf.length);
let j = 0;
for (let i = 0; i < buf.length; i++) {
    const b = buf[i];
    if (isAscii(b)) {
        cleanBuf[j++] = b;
    } else {
        // Replace non-ASCII with space to keep logic intact without breaking strings
        cleanBuf[j++] = 32; // space
    }
}

fs.writeFileSync('index.php', cleanBuf.slice(0, j), 'utf8');
console.log('Aggressive ASCII sanitization complete.');
