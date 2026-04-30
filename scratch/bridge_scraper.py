import asyncio
import random
import re
import os
import pandas as pd
from playwright.async_api import async_playwright
from playwright_stealth import stealth_async

# CONFIGURATION
OUTPUT_FILE = "data_jembatan_deep.csv"
ZOOM_LEVEL = "17z"
OFFSET = 0.01  # Approximately 1.1km

# DATA KECAMATAN MAJALENGKA (Estimated Center Coordinates)
KECAMATAN_DATA = {
    "Argapura": {"lat": -6.915, "lng": 108.315},
    "Banjaran": {"lat": -6.925, "lng": 108.265},
    "Bantarujeg": {"lat": -6.995, "lng": 108.155},
    "Cigasong": {"lat": -6.815, "lng": 108.215},
    "Cikijing": {"lat": -7.025, "lng": 108.345},
    "Cingambul": {"lat": -7.045, "lng": 108.285},
    "Dawuan": {"lat": -6.775, "lng": 108.225},
    "Jatitujuh": {"lat": -6.645, "lng": 108.255},
    "Jatiwangi": {"lat": -6.745, "lng": 108.265},
    "Kadipaten": {"lat": -6.755, "lng": 108.185},
    "Kasokandel": {"lat": -6.765, "lng": 108.215},
    "Kertajati": {"lat": -6.675, "lng": 108.185},
    "Lemahsugih": {"lat": -7.045, "lng": 108.155},
    "Leuwimunding": {"lat": -6.715, "lng": 108.295},
    "Ligung": {"lat": -6.695, "lng": 108.265},
    "Maja": {"lat": -6.875, "lng": 108.265},
    "Majalengka": {"lat": -6.836, "lng": 108.227},
    "Malausma": {"lat": -7.035, "lng": 108.235},
    "Palasah": {"lat": -6.775, "lng": 108.285},
    "Panyingkiran": {"lat": -6.795, "lng": 108.205},
    "Rajagaluh": {"lat": -6.815, "lng": 108.325},
    "Sindang": {"lat": -6.845, "lng": 108.295},
    "Sindangwangi": {"lat": -6.825, "lng": 108.385},
    "Sukahaji": {"lat": -6.805, "lng": 108.255},
    "Sumberjaya": {"lat": -6.735, "lng": 108.325},
    "Talaga": {"lat": -6.955, "lng": 108.305},
}

async def scroll_down(page):
    """Scroll through the side panel to load more results."""
    selector = 'div[role="feed"]'
    if await page.query_selector(selector):
        last_height = await page.evaluate(f'document.querySelector("{selector}").scrollHeight')
        while True:
            await page.evaluate(f'document.querySelector("{selector}").scrollTo(0, document.querySelector("{selector}").scrollHeight)')
            await asyncio.sleep(2)
            new_height = await page.evaluate(f'document.querySelector("{selector}").scrollHeight')
            if new_height == last_height:
                break
            last_height = new_height

async def scrape_grid_point(page, lat, lng, kec_name, grid_index):
    search_url = f"https://www.google.com/maps/search/Jembatan/@{lat},{lng},{ZOOM_LEVEL}"
    print(f"Navigating to: {search_url}")
    
    try:
        await page.goto(search_url, wait_until="networkidle", timeout=60000)
    except Exception as e:
        print(f"Timeout or Error: {e}")
        return []

    # Wait for results or empty state
    await asyncio.sleep(random.uniform(2, 4))
    
    # Optional: Scroll down to get all results in that grid point
    await scroll_down(page)

    # Extract all links that look like place links
    # These often have the class "hfpxzc" or are inside <a> tags with specific href patterns
    links = await page.query_selector_all('a[href*="/maps/place/"]')
    
    results = []
    for link in links:
        title = await link.get_attribute("aria-label")
        href = await link.get_attribute("href")
        
        if not title or not href:
            continue
            
        # Regex for Lat/Lng in URL
        lat_match = re.search(r'!3d([-+]?\d+\.\d+)', href)
        lng_match = re.search(r'!4d([-+]?\d+\.\d+)', href)
        
        if lat_match and lng_match:
            results.append({
                "Nama": title,
                "Latitude": lat_match.group(1),
                "Longitude": lng_match.group(1),
                "Kecamatan": kec_name,
                "Source_URL": href
            })
            
    print(f"Kecamatan [{kec_name}]: Grid [{grid_index}/5] done, [{len(results)}] items found...")
    return results

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()
        await stealth_async(page)

        all_results = []
        
        # Check if file exists to load existing data (incremental)
        if os.path.exists(OUTPUT_FILE):
             print(f"Output file {OUTPUT_FILE} already exists. Appending results.")

        for kec, coord in KECAMATAN_DATA.items():
            # Define 5 Grid Points (Center, North, South, East, West)
            grid_points = [
                (coord['lat'], coord['lng']),             # Center
                (coord['lat'] + OFFSET, coord['lng']),    # North
                (coord['lat'] - OFFSET, coord['lng']),    # South
                (coord['lat'], coord['lng'] + OFFSET),    # East
                (coord['lat'], coord['lng'] - OFFSET),    # West
            ]
            
            kec_results = []
            for i, (g_lat, g_lng) in enumerate(grid_points, 1):
                point_results = await scrape_grid_point(page, g_lat, g_lng, kec, i)
                kec_results.extend(point_results)
                
                # Randomized delay between points
                await asyncio.sleep(random.uniform(2, 5))
            
            # Save incrementally after each kecamatan
            if kec_results:
                df_new = pd.DataFrame(kec_results)
                if not os.path.isfile(OUTPUT_FILE):
                    df_new.to_csv(OUTPUT_FILE, index=False)
                else:
                    df_new.to_csv(OUTPUT_FILE, mode='a', header=False, index=False)
                
                # Cleanup duplicates in file after each kecamatan save
                df_main = pd.read_csv(OUTPUT_FILE)
                df_main = df_main.drop_duplicates(subset=['Latitude', 'Longitude'])
                df_main.to_csv(OUTPUT_FILE, index=False)

        await browser.close()
        print("Scraping completed successfully!")

if __name__ == "__main__":
    asyncio.run(main())
