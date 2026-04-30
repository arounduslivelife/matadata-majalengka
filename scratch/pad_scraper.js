const axios = require('axios');
const fs = require('fs');
const path = require('path');

const DATA_FILE = path.join(__dirname, '..', 'data', 'pad_majalengka.json');
const API_URL = 'https://opendata.majalengkakab.go.id/api/bigdata/bpd/jumlah_pendapatan_asli_daerah_pad_di_kabupaten_maja_1';

async function updatePAD() {
    console.log('🔄 Checking for PAD updates from Open Data Majalengka...');
    try {
        const response = await axios.get(API_URL);
        if (response.data && response.data.data) {
            const rawData = response.data.data;
            console.log('Sample Data:', rawData[0]);
            
            // Map the API response to our local format
            // Assuming API returns an array of objects with year and value
            const mappedData = rawData.map(item => ({
                tahun: parseInt(item.tahun),
                nilai: parseFloat(item.jumlah_pad),
                satuan: "Rupiah",
                status: parseInt(item.tahun) <= 2025 ? "Realisasi" : "Target"
            }));

            // Load existing file to keep manual entries (like 2026 target if not in API)
            let existing = JSON.parse(fs.readFileSync(DATA_FILE, 'utf8'));
            
            // Merge or update
            mappedData.forEach(newItem => {
                const idx = existing.data.findIndex(d => d.tahun === newItem.tahun);
                if (idx !== -1) {
                    existing.data[idx] = { ...existing.data[idx], ...newItem };
                } else {
                    existing.data.push(newItem);
                }
            });

            // Sort by year
            existing.data.sort((a, b) => a.tahun - b.tahun);
            existing.last_updated_scraper = new Date().toISOString();

            fs.writeFileSync(DATA_FILE, JSON.stringify(existing, null, 2));
            console.log('✅ PAD data updated successfully.');
        } else {
            console.log('⚠️ API returned no data or invalid format.');
        }
    } catch (error) {
        console.error('❌ Error fetching data:', error.message);
        console.log('💡 Note: If API is blocked, consider using Playwright scraper instead.');
    }
}

updatePAD();
