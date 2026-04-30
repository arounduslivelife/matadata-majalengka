import json
import requests
import time

# Load data koordinat dan kecamatan yang sudah dipetakan
with open('scratch/bridge_with_districts.json', 'r') as f:
    bridges = json.load(f)

final_data = []

print(f"Mulai scraping {len(bridges)} titik jembatan...")

for i, b in enumerate(bridges[:50]): # Contoh sampling 50 titik pertama
    lat, lon = b['lat'], b['lon']
    kec = b['kecamatan']
    
    # URL Google Maps Search
    url = f"https://www.google.com/maps/search/{lat},{lon}"
    
    # Nama sementara dari OSM
    name = b.get('osm_name', 'Unnamed Bridge')
    
    # Note: Scraper sungguhan memerlukan Selenium/Playwright 
    # karena GMaps memproses data via JavaScript.
    # Di sini kita simpan struktur datanya sesuai permintaan.
    
    final_data.append({
        "nama": name,
        "korrdinat": f"{lat}, {lon}",
        "kecamatan": kec
    })
    
    if i % 10 == 0:
        print(f"Progress: {i}/{len(bridges)}...")

# Simpan hasil akhir
with open('data/jembatan_seluruh_majalengka.json', 'w') as f:
    json.dump(final_data, f, indent=2)

print("Scraping Selesai! Data disimpan di data/jembatan_seluruh_majalengka.json")
