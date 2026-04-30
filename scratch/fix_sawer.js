const fs = require('fs');
let c = fs.readFileSync('index.php', 'utf8');

// 1. Add Auto-Close to Sawer Modal
c = c.replace('<div class="sawer-modal" id="sawerModal">', '<div class="sawer-modal" id="sawerModal" onclick="if(event.target == this) toggleSawer(false)">');

// 2. Add Auto-Close to Share Modal
c = c.replace('<div class="share-modal" id="shareModal">', '<div class="share-modal" id="shareModal" onclick="if(event.target == this) toggleShare(false)">');

fs.writeFileSync('index.php', c);

let m = fs.readFileSync('assets/js/main.js', 'utf8');
// Globalize remaining modal functions
m = m.replace('function toggleSawer(show) {', 'window.toggleSawer = function(show) {');
m = m.replace('function toggleShare(show) {', 'window.toggleShare = function(show) {');
m = m.replace('function toggleModal() {', 'window.toggleModal = function() {');
m = m.replace('function closeAuditDisclaimer() {', 'window.closeAuditDisclaimer = function() {');

fs.writeFileSync('assets/js/main.js', m);
console.log('Sawer popup fixed with Auto-Close and global functions.');
