const axios = require('axios');
const fs = require('fs');

async function bulkScrape() {
    const villages = JSON.parse(fs.readFileSync('scratch/village_codes.json', 'utf8'));
    const vnk = 'a5c9dece'; // Current valid VNK
    const cookie = '_gid=GA1.2.786938787.1777772710; _clck=14ugt2x%5E2%5Eg5q%5E0%5E2314; _gcl_au=1.1.449694260.1777772714; _fbp=fb.1.1777772715905.679880209810106675; _ga=GA1.1.1016779279.1777772710; _ga_8NF83QQ38P=GS2.1.s1777772715$o1$g1$t1777775926$j8$l0$h0; _clsk=1hyxofc%5E1777775926602%5E57%5E1%5El.clarity.ms%2Fcollect';
    
    console.log(`Starting bulk scrape for ${villages.length} villages...`);
    
    const results = [];
    const year = 2024;
    
    for (let i = 0; i < villages.length; i++) {
        const v = villages[i];
        const url = `https://jaga.id/api/v5/desa/${v.code}/penyaluran?year=${year}&vnk=${vnk}`;
        
        console.log(`[${i+1}/${villages.length}] Fetching ${v.name} (${v.code})...`);
        
        try {
            const response = await axios.get(url, {
                headers: {
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept': 'application/json, text/plain, */*',
                    'Referer': `https://jaga.id/pelayanan-publik/desa/${v.code}/${encodeURIComponent(v.name)}?year=${year}`,
                    'Origin': 'https://jaga.id',
                    'Cookie': cookie
                }
            });
            
            if (i === 0) {
                console.log('DEBUG First Response:', JSON.stringify(response.data, null, 2));
            }

            if (response.data && response.data.data) {

                const data = response.data.data;
                results.push({
                    id: v.id,
                    name: v.name,
                    code: v.code,
                    pagu: data.total_pagu || 0,
                    realisasi: data.total_realisasi || 0,
                    status: data.status_desa || 'N/A',
                    last_update: data.tgl_update || null
                });
            } else {
                console.log(`  No data for ${v.name}`);
                results.push({ id: v.id, name: v.name, code: v.code, error: 'Empty data' });
            }
        } catch (err) {
            console.error(`  Error for ${v.name}:`, err.message);
            results.push({ id: v.id, name: v.name, code: v.code, error: err.message });
        }
        
        // Break after 10 for testing first
        if (i === 9) {
            console.log('--- TEST BATCH COMPLETED ---');
            break;
        }

        // Delay 500ms
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    
    fs.writeFileSync('dana_desa_majalengka_2024_test.json', JSON.stringify(results, null, 2));
    console.log('Results saved to dana_desa_majalengka_2024_test.json');
}

bulkScrape();
