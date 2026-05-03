const fs = require('fs');
const db = require('./db');

const centers = JSON.parse(fs.readFileSync('scratch/kecamatan_centers.json', 'utf8'));
const defaultCenter = [-6.837, 108.227]; // Majalengka Central

const KECAMATANS = [
    'Bantarujeg', 'Banjaran', 'Talaga', 'Palasah', 'Malausma', 'Argapura', 'Maja', 'Jatiwangi',
    'Sumberjaya', 'Ligung', 'Majalengka', 'Dawuan', 'Kertajati', 'Leuwimunding', 'Sukahaji',
    'Sindangwangi', 'Kadipaten', 'Rajagaluh', 'Jatitujuh', 'Panyingkiran', 'Sindang', 'Kasokandel',
    'Cigasong', 'Lemahsugih', 'Cikijing', 'Cingambul'
];

const importData = async () => {
    console.log("Reading data/realisasi20222024alldept.json...");
    const rawData = fs.readFileSync('data/realisasi20222024alldept.json', 'utf8');
    const jsonData = JSON.parse(rawData);
    const data = jsonData.data;
    
    console.log(`Starting ingestion of ${data.length} records into MySQL...`);
    
    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();
        let count = 0;
        for (const row of data) {
            const satker = row.nama_satuan_kerja || "";
            const kodePacket = row.kode_paket || "";
            const rup = row.kode_rup || "";
            const sourceDana = row.sumber_dana || "";
            const vendor = row.nama_penyedia || "";
            const method = row.metode_pengadaan || "";
            const type = row.jenis_pengadaan || "";
            const packageName = row.nama_paket || "";
            const status = row.status_paket || "";
            const total = parseFloat(row.total_nilai_rp) || 0;
            const pdn = parseFloat(row.nilai_pdn_rp) || 0;
            const tahun = parseInt(row.tahun_anggaran) || 0;

            let kecamatan = "KAB. MAJALENGKA";
            let lat = defaultCenter[0];
            let lng = defaultCenter[1];

            const sortedKecamatans = [...KECAMATANS].sort((a, b) => b.length - a.length);
            for (const kec of sortedKecamatans) {
                const regex = new RegExp('\\b' + kec + '\\b', 'i');
                if (regex.test(satker) || regex.test(packageName)) {
                    kecamatan = kec;
                    if (centers[kec]) {
                        lat = centers[kec][0];
                        lng = centers[kec][1];
                    }
                    break;
                }
            }

            await connection.query(`INSERT INTO realizations 
                (satker, kode_paket, rup, sumber_dana, vendor, metode, jenis, nama_paket, status, total_nilai, pdn, kecamatan, lat, lng, tahun) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
                [satker, kodePacket, rup, sourceDana, vendor, method, type, packageName, status, total, pdn, kecamatan, lat, lng, tahun]
            );
            
            count++;
            if (count % 1000 === 0) console.log(`Ingested ${count} records...`);
        }
        
        await connection.commit();
        console.log(`Successfully ingested ${count} realization records into MySQL.`);
    } catch (e) {
        await connection.rollback();
        console.error("Error during historical import:", e.message);
    } finally {
        connection.release();
    }
};

importData().catch(err => {
    console.error(err);
    process.exit(1);
});

