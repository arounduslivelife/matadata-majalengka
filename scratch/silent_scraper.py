import asyncio
import pandas as pd
import random
import re
import os
import json
from playwright.async_api import async_playwright

# LIST 26 KECAMATAN MAJALENGKA
KECAMATAN_MAJALENGKA = [
    "Argapura", "Banjaran", "Bantarujeg", "Cigasong", "Cikijing", 
    "Cingambul", "Dawuan", "Jatitujuh", "Jatiwangi", "Kadipaten", 
    "Kasokandel", "Kertajati", "Lemahsugih", "Leuwimunding", "Ligung", 
    "Maja", "Majalengka", "Malausma", "Panyingkiran", "Palasah", 
    "Rajagaluh", "Sindang", "Sindangwangi", "Sukahaji", "Sumberjaya", "Talaga"
]

OBJEK = "Sekolah"
OUTPUT_CSV = "data/data_majalengka_silent.csv"
OUTPUT_JSON = "data/listsekolahmajalengka.json"

async def silent_background_scraper():
    async with async_playwright() as p:
        # STRICT HEADLESS MODE
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        
        all_results = []
        
        # Ensure data directory exists
        if not os.path.exists('data'):
            os.makedirs('data')

        print(f"--- STARTING SILENT SCRAPER: {OBJEK} MAJALENGKA ---")

        for kecamatan in KECAMATAN_MAJALENGKA:
            print(f"Processing Kecamatan {kecamatan}...")
            search_url = f"https://www.google.com/maps/search/{OBJEK}+di+{kecamatan}+Majalengka"
            
            try:
                await page.goto(search_url, timeout=60000)
                await page.wait_for_timeout(3000)

                # FAST SCROLLING
                for _ in range(5):
                    await page.mouse.wheel(0, 4000)
                    await page.wait_for_timeout(700)

                # NO-CLICK EXTRACTION (REGEX FROM HREF)
                listings = await page.locator('//a[contains(@href, "maps/place")]').all()
                
                count = 0
                for item in listings:
                    try:
                        name = await item.get_attribute("aria-label")
                        href = await item.get_attribute("href")
                        
                        if not name or not href: continue

                        # COORDINATE EXTRACTION VIA REGEX (!3d=Lat, !4d=Lng)
                        match = re.search(r'!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)', href)
                        if match:
                            lat = match.group(1)
                            lng = match.group(2)
                            
                            res = {
                                "nama": name,
                                "kecamatan": kecamatan,
                                "latitude": float(lat),
                                "longitude": float(lng)
                            }
                            all_results.append(res)
                            count += 1
                    except:
                        continue
                
                print(f"   -> Found {count} items.")

                # Incremental Save (CSV)
                pd.DataFrame(all_results).to_csv(OUTPUT_CSV, index=False)
                
            except Exception as e:
                print(f"   [Error] {kecamatan}: {e}")

            # Random sleep (Anti-Detection)
            await asyncio.sleep(random.uniform(3, 5))

        # Final Export to JSON
        with open(OUTPUT_JSON, 'w') as f:
            json.dump(all_results, f, indent=2)
        
        await browser.close()
        print(f"\nCOMPLETED! Total {len(all_results)} schools saved to {OUTPUT_JSON}")

if __name__ == "__main__":
    asyncio.run(silent_background_scraper())
