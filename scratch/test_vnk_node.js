const axios = require('axios');
const fs = require('fs');

async function testVnk() {
    const code = '3210222002'; // Sunia
    const year = 2024;
    const vnk = '8e1cafa7'; // From user's browser
    
    const url = `https://jaga.id/api/v5/desa/${code}/rincian-dana-desa?year=${year}&vnk=${vnk}`;
    
    console.log(`Testing with VNK: ${vnk}`);
    console.log(`URL: ${url}`);
    
    try {
        const response = await axios.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'application/json, text/plain, */*',
                'Referer': 'https://jaga.id/',
                'Origin': 'https://jaga.id'
            }
        });
        console.log('Success!');
        console.log(JSON.stringify(response.data, null, 2).substring(0, 500));
    } catch (error) {
        console.error('Error:', error.message);
        if (error.response) {
            console.error('Status:', error.response.status);
            console.error('Data:', JSON.stringify(error.response.data));
        }
    }
}

testVnk();
