const fs = require('fs');
let c = fs.readFileSync('index.php', 'utf8');
c = c.replace('<div id="map"></div>', '<div id="map" style="height: 100vh !important; width: 100vw !important; background: #020617; position: absolute; inset: 0; z-index: 1;"></div>');
fs.writeFileSync('index.php', c);

let m = fs.readFileSync('assets/js/main.js', 'utf8');
// Globalize functions
m = m.replace('function selectModeFromDock(mode) {', 'window.selectModeFromDock = function(mode) {');
m = m.replace('function switchMode(mode) {', 'window.switchMode = function(mode) {');
m = m.replace('function toggleLayerPopover() {', 'window.toggleLayerPopover = function() {');
m = m.replace('function toggleSidebar() {', 'window.toggleSidebar = function() {');

fs.writeFileSync('assets/js/main.js', m);
console.log('Fixed index.php and globalized JS functions.');
