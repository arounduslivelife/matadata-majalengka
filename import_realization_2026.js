const fs = require('fs');
const db = require('./db');
const readline = require('readline');

const centers = JSON.parse(fs.readFileSync('scratch/kecamatan_centers.json', 'utf8'));
const defaultCenter = [-6.837, 108.227]; // Majalengka Central

const KECAMATANS = [
    'Bantarujeg', 'Banjaran', 'Talaga', 'Palasah', 'Malausma', 'Argapura', 'Maja', 'Jatiwangi',
    'Sumberjaya', 'Ligung', 'Majalengka', 'Dawuan', 'Kertajati', 'Leuwimunding', 'Sukahaji',
    'Sindangwangi', 'Kadipaten', 'Rajagaluh', 'Jatitujuh', 'Panyingkiran', 'Sindang', 'Kasokandel',
    'Cigasong', 'Lemahsugih', 'Cikijing', 'Cingambul'
];

const importData = async () => {
    // Re-create table for MySQL
    await db.query("DROP TABLE IF EXISTS realization_2026");
    await db.query(`CREATE TABLE realization_2026 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        satker TEXT,
        kode_paket TEXT,
        rup TEXT,
        sumber_dana TEXT,
        vendor TEXT,
        metode TEXT,
        jenis TEXT,
        nama_paket TEXT,
        status TEXT,
        total_nilai DOUBLE,
        pdn DOUBLE,
        kecamatan TEXT,
        lat DOUBLE,
        lng DOUBLE
    )`);

    const fileStream = fs.createReadStream('raw/realisasi2026alldept.csv');
    const rl = readline.createInterface({ input: fileStream, crlfDelay: Infinity });

    let header = null;
    let count = 0;

    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();

        for await (const line of rl) {
            if (!header) {
                header = line.split(',');
                continue;
            }

            const parts = line.split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/);
            if (parts.length < 14) continue;

            const satker = parts[1].replace(/"/g, '');
            const kodePacket = parts[2];
            const rup = parts[3];
            const sourceDana = parts[6];
            const vendor = parts[7];
            const method = parts[8];
            const type = parts[9];
            const packageName = parts[10].replace(/"/g, '');
            const status = parts[11];
            const total = parseFloat(parts[12]) || 0;
            const pdn = parseFloat(parts[13]) || 0;

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

            await connection.query(`INSERT INTO realization_2026 
                (satker, kode_paket, rup, sumber_dana, vendor, metode, jenis, nama_paket, status, total_nilai, pdn, kecamatan, lat, lng) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
                [satker, kodePacket, rup, sourceDana, vendor, method, type, packageName, status, total, pdn, kecamatan, lat, lng]
            );
            count++;
        }

        await connection.commit();
        console.log(`Ingested ${count} realization records into MySQL.`);
    } catch (e) {
        await connection.rollback();
        console.error("Error during import:", e.message);
    } finally {
        connection.release();
    }
};

importData().catch(err => {
    console.error(err);
    process.exit(1);
});

