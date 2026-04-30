import asyncio
import pandas as pd
import random
import re
import os
from playwright.async_api import async_playwright

# KONFIGURASI MASTER
OBJECT_NAME = "Sekolah"
KECAMATAN_LIST = [
    "Argapura", "Banjaran", "Bantarujeg", "Cigasong", "Cikijing", 
    "Cingambul", "Dawuan", "Jatitujuh", "Jatiwangi", "Kadipaten", 
    "Kasokandel", "Kertajati", "Lemahsugih", "Leuwimunding", "Ligung", 
    "Maja", "Majalengka", "Malausma", "Panyingkiran", "Palasah", 
    "Rajagaluh", "Sindang", "Sindangwangi", "Sukahaji", "Sumberjaya", "Talaga"
]
OUTPUT_FILE = "data/hasil_scraping_majalengka.csv"

async def master_scraper():
    async with async_playwright() as p:
        print(f"--- STARTING MASTER SCRAPER: {OBJECT_NAME} ---")
        browser = await p.chromium.launch(headless=True) # SILENT MODE
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        
        final_results = []

        if not os.path.exists('data'):
            os.makedirs('data')

        for kecamatan in KECAMATAN_LIST:
            query = f"{OBJECT_NAME} di {kecamatan}, Majalengka"
            print(f"Processing {kecamatan}...")
            
            try:
                # Buka pencarian
                await page.goto(f"https://www.google.com/maps/search/{query}")
                await page.wait_for_timeout(4000)

                # FAST SCROLLING
                for _ in range(8): 
                    await page.mouse.wheel(0, 4000)
                    await page.wait_for_timeout(800)

                # EXTRACTION WITHOUT CLICKING (FAST REGEX)
                # Mencari elemen <a> yang memiliki href maps/place
                items = await page.locator('//a[contains(@href, "maps/place")]').all()
                
                count_in_kec = 0
                for item in items:
                    try:
                        name = await item.get_attribute("aria-label")
                        href = await item.get_attribute("href")
                        
                        if not name or not href: continue

                        # REGEX TARGET: !3d (Latitude) dan !4d (Longitude)
                        # Pola: !3d(-?\d+\.\d+)!4d(-?\d+\.\d+)
                        coord_match = re.search(r'!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)', href)
                        
                        if coord_match:
                            lat = coord_match.group(1)
                            lng = coord_match.group(2)
                            
                            final_results.append({
                                "nama": name,
                                "kecamatan": kecamatan,
                                "latitude": lat,
                                "longitude": lng,
                                "sumber": "Google Maps (Regex)"
                            })
                            count_in_kec += 1
                    except:
                        continue
                
                print(f"   Done! Found {count_in_kec} items.")
                
                # Incremental Save
                pd.DataFrame(final_results).to_csv(OUTPUT_FILE, index=False)

            except Exception as e:
                print(f"   Error in {kecamatan}: {e}")
            
            # Anti-Detection Delay
            await asyncio.sleep(random.uniform(3, 6))

        await browser.close()
        print(f"\n--- SUCCESS! TOTAL {len(final_results)} ITEMS SCRAPED ---")

if __name__ == "__main__":
    asyncio.run(master_scraper())
