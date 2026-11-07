# B2B Wholesale POS & Distribution Subsystem
## 06 — Armenian Tax Rules & Regulatory Requirements
### Architecture Version 1.0 — QR Menu SaaS Platform

---

## 1. Overview of the Armenian Tax Framework for Wholesale

In the Republic of Armenia, commercial sales between legal entities and individual entrepreneurs (ԱՁ / Sole Proprietors) are governed by the RA Tax Code (ՀՀ Հարկային Օրենսգիրք).

### Key Tax Regimes:
1. **Value Added Tax (ԱԱՀ - 20.0%)**:
   - Entities registered as general VAT payers must issue **Tax Invoices (Հարկային հաշիվ)**.
   - Standard VAT rate is 20.0% (`Price * 0.20 = VAT`).
   - Prices can be quoted either net of VAT with VAT added, or gross. In wholesale B2B, standard practice is displaying Net Price, VAT Amount, and Total Price.
2. **Turnover Tax (Շրջանառության հարկ)**:
   - Small and medium businesses under the statutory turnover threshold.
   - Non-VAT payers must NOT issue Tax Invoices with VAT.
   - Instead, they issue **Accounting Documents (Հաշիվ վավերագիր)** with deal type `SPECIAL_TAX_SYSTEM`.
3. **Micro-Enterprise (Միկրոձեռնարկատիրություն)**:
   - Entities exempt from business profit taxes up to the legal threshold.
   - Issue **Accounting Documents (Հաշիվ վավերագիր)** without VAT.

---

## 2. Tax Document Determination Engine

The system strictly determines document type by tax rules, NEVER arbitrary UI selection:

```
[Seller Tax Regime?]
     |
     +---> If VAT_PAYER:
     |        |
     |        +---> Generate "Tax Invoice" (Հարկային հաշիվ)
     |              - Series: "A"
     |              - XML: <SignableData xsi:type="Invoice">
     |              - Explicit VATRate (20.0%) and VAT amount
     |
     +---> If TURNOVER_TAX or MICRO_BUSINESS:
              |
              +---> Generate "Accounting Document" (Հաշիվ վավերագիր)
                    - Series: "B"
                    - XML: <SignableData xsi:type="AccountingDocument">
                    - DealType: "SPECIAL_TAX_SYSTEM"
                    - VAT explicitly omitted
                    - Seller Bank Account included
```

---

## 3. Product Classification (ԱՏԳԱԱ / FEACN)

- Every wholesale product must be categorized by its official Classifier Code (ԱՏԳ ԱԱ - Արտաքին տնտեսական գործունեության ապրանքային անվանացանկ).
- Minimum 4 digits (e.g. `1602` Meat preparations, `1902` Pasta and stuffed dough/pelmeni, `2106` Food preparations) up to 10 digits (e.g. `2106.90.98.00`).
- **Strict Prohibition**: Never invent arbitrary ATGAA codes; codes must be selected from the standardized classifier directory.

---

## 4. Adjustment Documents (Ճշգրտող հաշիվներ)

When goods are returned, delivered short, or prices are renegotiated after issuance:
- An **Adjustment Document (Ճշգրտող հարկային հաշիվ / վավերագիր)** is generated.
- Must reference:
  - `ParentInvoiceNumber`: e.g. `A3992153793`
  - `ParentInvoiceSubmitDate`: e.g. `2026-06-02+04:00`
  - `AdjustmentDiffFlag`: `-1` for reductions (returns/rebates), `+1` for additions.
  - `Procedure`: `2` (Adjustment).
  - `GoodParentId`: UUID matching the original invoice line item.

