const d = require('../data/found_schools_ultra.json');

console.log('=== ANALISIS found_schools_ultra.json ===\n');
console.log('Total records:', d.data.length);
console.log('Kecamatan processed:', d.processed.length);
console.log('Processed list:', d.processed.join(', '));

// Per Kecamatan
const byKec = {};
d.data.forEach(s => { byKec[s.kecamatan] = (byKec[s.kecamatan] || 0) + 1; });
console.log('\n--- Distribusi per Kecamatan ---');
Object.entries(byKec).sort((a, b) => b[1] - a[1]).forEach(([k, v]) => console.log('  ' + k + ': ' + v));

// Fields
console.log('\nFields per entry:', Object.keys(d.data[0]));

// Missing coords
const noLat = d.data.filter(s => !s.lat || !s.lng);
console.log('\nMissing coordinates:', noLat.length);

// Duplicate names
const nameCounts = {};
d.data.forEach(s => { 
  const key = s.nama + '|' + s.kecamatan;
  nameCounts[key] = (nameCounts[key] || 0) + 1; 
});
const dupes = Object.entries(nameCounts).filter(([k, v]) => v > 1);
console.log('\nDuplicate entries (same name + kecamatan):', dupes.length);
dupes.slice(0, 20).forEach(([k, v]) => console.log('  [' + v + 'x] ' + k));

// Coordinate duplicates (exact same lat/lng)
const coordCounts = {};
d.data.forEach(s => {
  const key = s.lat + ',' + s.lng;
  coordCounts[key] = (coordCounts[key] || []);
  coordCounts[key].push(s.nama);
});
const coordDupes = Object.entries(coordCounts).filter(([k, v]) => v.length > 1);
console.log('\nDuplicate coordinates (different names, same location):', coordDupes.length);
coordDupes.slice(0, 10).forEach(([k, v]) => console.log('  [' + k + '] -> ' + v.join(', ')));

// Non-school entries detection
const schoolKeywords = ['sd', 'smp', 'sma', 'smk', 'tk', 'paud', 'mi ', 'mis ', 'mts', 'ma ', 
  'madrasah', 'sekolah', 'pesantren', 'pondok', 'raudhatul', 'kober', 'kelompok bermain',
  'dta', 'tpq', 'sps', 'sdit', 'ra ', 'sltpn', 'college', 'lab ipa', 'perpustakaan', 
  'toilet', 'lapangan', 'tkit', 'mda ', 'sekretariat himpaudi', 'gedung kober', 'nhic',
  'smp terbuka', 'tkb'];

const nonSchool = d.data.filter(s => {
  const n = s.nama.toLowerCase();
  return !schoolKeywords.some(kw => n.includes(kw));
});
console.log('\n--- Entri yang kemungkinan BUKAN sekolah ---');
console.log('Total:', nonSchool.length);
nonSchool.forEach(s => console.log('  [' + s.kecamatan + '] ' + s.nama + ' (' + s.lat + ', ' + s.lng + ')'));

// Geofence check - Majalengka approximate bounds
// lat: -7.1 to -6.7, lng: 108.1 to 108.5
const outOfBounds = d.data.filter(s => {
  return s.lat < -7.15 || s.lat > -6.65 || s.lng < 108.05 || s.lng > 108.55;
});
console.log('\n--- Entri di LUAR batas Majalengka ---');
console.log('Total:', outOfBounds.length);
outOfBounds.forEach(s => console.log('  [' + s.kecamatan + '] ' + s.nama + ' (' + s.lat + ', ' + s.lng + ')'));

// Check existing data
try {
  const existing = require('../data/sarana_pendidikan.geojson');
  console.log('\n--- Perbandingan dengan sarana_pendidikan.geojson ---');
  console.log('Existing schools in geojson:', existing.features.length);
} catch(e) {}

try {
  const existing2 = require('../data/realisasi_sekolah.geojson');
  console.log('Existing realisasi_sekolah:', existing2.features.length);
} catch(e) {}

try {
  const existing3 = require('../data/listsekolahmajalengka.json');
  console.log('List sekolah majalengka:', Array.isArray(existing3) ? existing3.length : 'not array');
} catch(e) {}
