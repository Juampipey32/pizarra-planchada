import sys
import pandas as pd

file_path = "PEDIDOS-PIZARRA/Cliente-5475.xlsx"

try:
    # Read specific rows
    df = pd.read_excel(file_path, header=None, skiprows=5, nrows=10)
    print("ROWS 6-15:")
    for index, row in df.iterrows():
        # Clean row
        row_str = "|".join([str(val).strip() if pd.notna(val) else "" for val in row])
        print(f"ROW_{index+6}: {row_str}")

except Exception as e:
    print(f"ERROR: {e}")
