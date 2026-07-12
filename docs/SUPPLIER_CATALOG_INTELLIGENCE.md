# Supplier Catalog Intelligence

Supplier catalogs are generic and can contain food, seafood, organic products, furniture, office supplies, engineering materials, retail goods, or other procurement categories.

## Supported input

- CSV/TXT, XLSX/XLS, and text-based PDF up to 20 MB.
- Indonesian and English column aliases for SKU, product name, price, unit/pack, brand, category, MOQ, and stock.
- PDF image scans are marked as requiring OCR/review when no usable text is found.

## Workflow

1. Select an existing company supplier and upload a private catalog file.
2. Scanner detects the header and extracts up to 5,000 rows.
3. Quantities are normalized to KG, L, PCS, SET, PACK, BOX, or ROLL.
4. Rows remain staged for human review. Users can correct names, SKU, price, unit, and MOQ.
5. Only published rows are available to AI comparison.
6. Comparison applies requested quantity, normalized unit price, budget variance, supplier risk, confidence, and catalog validity.

The cheapest result is never treated as automatically equivalent. Grade, brand, dimensions, freshness, certification, tax, delivery, MOQ, and commercial terms still require procurement review before a PO is created.

## VPS requirements

PHP must include ZIP, XML, GD, fileinfo, iconv, mbstring, and zlib extensions. Uploaded files must use persistent private storage and must not be served directly from the public web root.
