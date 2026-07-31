# M040 Smart Document Center — Interactive Prototype

This is a safe, dependency-free browser prototype. It does not connect to production, Dolibarr, databases, or customer files.

## Test
1. Open `index.html` in Chrome, Edge, or Safari.
2. Click **تحميل بيانات تجريبية**.
3. Switch between Arabic, English, and Chinese.
4. Switch document type.
5. Edit seller, buyer, shipping, product, quantity, and price data.
6. Upload product images.
7. Click **طباعة / PDF** and choose **Save as PDF**.

## Included
- Quotation, Proforma Invoice, Commercial Invoice, Packing List, and Sales Contract titles.
- Arabic RTL, English LTR, and Simplified Chinese LTR.
- Dynamic lines, product image upload, live totals, live preview, A4 print layout.
- Vision Trading / China Planet sample branding.

## Prototype limitations
- Data exists only in the browser session.
- All five document types currently share one commercial layout; production renderers will differ by type.
- Packing calculations, taxes, freight, discounts, versioning, approval, portal sharing, and Dolibarr source mapping are not connected yet.
- The stamp is a visual placeholder and must be replaced by the approved company asset.

## Next implementation slice
After repository discovery, connect the builder first to one verified Dolibarr source object, preserving an immutable snapshot and generated document version. No production deployment is authorized.
