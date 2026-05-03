const axios = require('axios');

async function testApi() {
    const id = '3210162008'; // Ampel
    const year = '2024'; // Using 2024 as it's more likely to be filled
    const url = `https://jaga.id/api/v5/desa/dana-desa/penyaluran/${id}?year=${year}`;
    
    console.log(`Testing URL: ${url}`);
    
    try {
        const response = await axios.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'application/json'
            }
        });
        console.log('Success!');
        console.log(JSON.stringify(response.data, null, 2).substring(0, 500));
    } catch (error) {
        console.error('Error fetching API:', error.message);
        if (error.response) {
            console.error('Status:', error.response.status);
            console.error('Data:', error.response.data);
        }
    }
}

testApi();
