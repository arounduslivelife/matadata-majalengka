const axios = require('axios');

async function discover() {
    const url = 'https://sirup.inaproc.id/sirup/home/rekapitulasi/paket/penyedia/K109/2025';
    try {
        const response = await axios.get(url);
        console.log("Success fetching rekap page");
        // We'll look for DT (DataTable) initialization in the HTML
        const html = response.data;
        if (html.includes('DataTable')) {
            console.log("Found DataTable reference");
        }
    } catch (e) {
        console.error("Error fetching:", e.message);
    }
}

discover();
