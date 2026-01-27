import sys
import os

file_path = "PEDIDOS-PIZARRA/Cliente-5475.xlsx"

if not os.path.exists(file_path):
    print(f"File not found: {file_path}")
    sys.exit(1)

try:
    import pandas as pd
    df = pd.read_excel(file_path)
    print("HEADERS_DETECTED:")
    # Print headers separated by pipe | to avoid confusion with commas in names
    print("|".join([str(h).strip() for h in df.columns.tolist()]))
except ImportError:
    try:
        import openpyxl
        wb = openpyxl.load_workbook(file_path, read_only=True)
        sheet = wb.active
        headers = []
        for cell in next(sheet.iter_rows(min_row=1, max_row=1)):
             if cell.value:
                headers.append(str(cell.value).strip())
        print("HEADERS_DETECTED:")
        print("|".join(headers))
    except ImportError:
        print("ERROR: No suitable library found (pandas or openpyxl)")
    except Exception as e:
        print(f"ERROR: {e}")
except Exception as e:
    print(f"ERROR: {e}")
