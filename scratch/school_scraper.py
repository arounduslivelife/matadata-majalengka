import asyncio
import pandas as pd
import random
import re
import os
from playwright.async_api import async_playwright

# DAFTAR KECAMATAN MAJALENGKA
KECAMATAN_MAJALENGKA = [
    "Argapura", "Banjaran", "Bantarujeg", "Cigasong", "Cikijing", 
    "Cingambul", "Dawuan", "Jatitujuh", "Jatiwangi", "Kadipaten", 
    "Kasokandel", "Kertajati", "Lemahsugih", "Leuwimunding", "Ligung", 
    "Maja", "Majalengka", "Malausma", "Panyingkiran", "Palasah", 
    "Rajagaluh", "Sindang", "Sindangwangi", "Sukahaji", "Sumberjaya", "Talaga"
]

KEYWORD = "Sekolah"
OUTPUT_FILE = "data/scraped_schools_majalengka.csv"

async def scrape_google_maps():
    async with async_playwright() as p:
        print("Launching Intelligence Scraper...")
        # Gunakan headless=False jika ingin melihat prosesnya (lebih aman dari blokir)
        browser = await p.chromium.launch(headless=False) 
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        
        all_results = []

        # Buat folder data jika belum ada
        if not os.path.exists('data'):
            os.makedirs('data')

        for kecamatan in KECAMATAN_MAJALENGKA:
            search_query = f"{KEYWORD} di {kecamatan}, Majalengka"
            print(f"\n[SCANNING] {search_query}...")
            
            try:
                await page.goto(f"https://www.google.com/maps/search/{search_query}", timeout=60000)
                await page.wait_for_timeout(5000)

                # ADVANCED SCROLLING: Menarik list ke bawah sampai mentok
                last_height = 0
                for _ in range(15): # Coba scroll 15 kali
                    # Arahkan mouse ke panel list (biasanya di sisi kiri)
                    await page.mouse.wheel(0, 3000)
                    await page.wait_for_timeout(1500)
                    
                # Cari semua elemen yang merupakan link ke lokasi
                listings = await page.locator('//a[contains(@href, "maps/place")]').all()
                print(f"   -> Ditemukan {len(listings)} potensi lokasi.")

                for index, listing in enumerate(listings):
                    try:
                        # Ambil Nama sebelum klik
                        name = await listing.get_attribute("aria-label")
                        if not name: continue
                        
                        # Klik untuk memicu update URL dengan koordinat
                        await listing.click()
                        await page.wait_for_timeout(2000) # Tunggu URL berubah

                        current_url = page.url
                        match = re.search(r'@(-?\d+\.\d+),(-?\d+\.\d+)', current_url)
                        
                        if match:
                            lat = match.group(1)
                            lng = match.group(2)
                            
                            data = {
                                "nama_sekolah": name,
                                "kecamatan": kecamatan,
                                "latitude": lat,
                                "longitude": lng,
                                "url": current_url
                            }
                            all_results.append(data)
                            print(f"      [OK] {name}")
                        
                    except Exception as e:
                        continue

                # Simpan Dashboard secara Real-time agar data tidak hilang jika crash
                df_temp = pd.DataFrame(all_results)
                df_temp.to_csv(OUTPUT_FILE, index=False)
                
            except Exception as e:
                print(f"   [FATAL] Gagal akses kecamatan {kecamatan}: {e}")
            
            # Delay antar kecamatan (Anti-Spam)
            await asyncio.sleep(random.uniform(2, 5))

        await browser.close()
        print(f"\n--- MISSION SUCCESS ---")
        print(f"Total {len(all_results)} data sekolah berhasil dikumpulkan.")
        print(f"Hasil disimpan di: {OUTPUT_FILE}")

if __name__ == "__main__":
    asyncio.run(scrape_google_maps())
