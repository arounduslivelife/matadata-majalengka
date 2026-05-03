const axios = require('axios');

async function testApi() {
    const code = '3210222002'; // Sunia
    const year = 2024;
    const url = `https://jaga.id/api/v5/desa/${code}/rincian-pagu?year=${year}`;
    
    try {
        const res = await axios.get(url, {
            headers: { 'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json' }
        });
        console.log('URL:', url);
        console.log('Response Status:', res.status);
        console.log('Response Data:', JSON.stringify(res.data, null, 2));
    } catch (err) {
        console.error('Error:', err.message);
    }
}

testApi();
