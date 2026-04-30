
import pandas as pd
import json

# Set path to the CSV file
file_path = r'c:\xampp\htdocs\matadata\raw\realisasi2025alldept.csv'

# Load the data
# Using low_memory=False to avoid DtypeWarning and handling possible encoding issues
try:
    df = pd.read_csv(file_path, encoding='utf-8')
except UnicodeDecodeError:
    df = pd.read_csv(file_path, encoding='latin-1')

# Data Cleaning
df['Total Nilai (Rp)'] = pd.to_numeric(df['Total Nilai (Rp)'], errors='coerce')
df['Nama Penyedia'] = df['Nama Penyedia'].str.upper().str.strip()
df['Nama Satuan Kerja'] = df['Nama Satuan Kerja'].str.strip()

# 1. Top Vendors by Frequency (Most active)
top_vendors_freq = df['Nama Penyedia'].value_counts().head(20).to_dict()

# 2. Top Vendors by Total Value (Richest)
top_vendors_value = df.groupby('Nama Penyedia')['Total Nilai (Rp)'].sum().sort_values(ascending=False).head(20).to_dict()

# 3. Top SKPD by Makanan & Minuman spending
makan_minum_df = df[df['Nama Paket'].str.contains('MAKAN|MINUM|SNACK|NASI', case=False, na=False)]
top_skpd_makan = makan_minum_df.groupby('Nama Satuan Kerja')['Total Nilai (Rp)'].sum().sort_values(ascending=False).head(15).to_dict()

# 4. Analyze "ALHAMDULILLAH" reaches
alhamdulillah_reach = df[df['Nama Penyedia'] == 'ALHAMDULILLAH'].groupby('Nama Satuan Kerja')['Total Nilai (Rp)'].sum().to_dict()

# 5. Price Variation for "NASI BOX" / "SNACK"
# Let's look for packages like "NASI BOX" and see the price distribution if available, 
# but since the values are total values, we might need a more granular look or just see total per packet.
nasi_box_packets = df[df['Nama Paket'].str.contains('NASI BOX|PAKET MAKAN', case=False, na=False)][['Nama Paket', 'Nama Satuan Kerja', 'Total Nilai (Rp)']].head(20).to_dict(orient='records')

# 6. Method Distribution
method_dist = df['Metode Pengadaan'].value_counts().head(10).to_dict()

# 7. Status Distribution
status_dist = df['Status Paket'].value_counts().head(10).to_dict()

# 8. Anomaly: E-Purchasing vs Payment Outside System for top vendors
outside_system_vendors = df[df['Status Paket'] == 'PAYMENT OUTSIDE SYSTEM']['Nama Penyedia'].value_counts().head(10).to_dict()

# Output the results
analysis_results = {
    "top_vendors_by_frequency": top_vendors_freq,
    "top_vendors_by_value": top_vendors_value,
    "top_skpd_spending_on_food": top_skpd_makan,
    "alhamdulillah_vendor_analysis": alhamdulillah_reach,
    "nasi_box_sample": nasi_box_packets,
    "procurement_methods": method_dist,
    "packet_statuses": status_dist,
    "top_vendors_outside_system": outside_system_vendors
}

with open(r'c:\xampp\htdocs\matadata\scratch\analysis_2025_deep.json', 'w') as f:
    json.dump(analysis_results, f, indent=4)

print("Deep Analysis Complete. Results saved to scratch/analysis_2025_deep.json")
