import sys
import pandas as pd

file_path = "PEDIDOS-PIZARRA/Cliente-5475.xlsx"

try:
    # Read without header assumption to see raw layout
    df = pd.read_excel(file_path, header=None, nrows=20)
    print("ROWS_PREVIEW:")
    for index, row in df.iterrows():
        # Clean row values to string, handling NaNs
        row_str = "|".join([str(val).strip() if pd.notna(val) else "" for val in row])
        print(f"ROW_{index+1}: {row_str}")

except Exception as e:
    print(f"ERROR: {e}")
