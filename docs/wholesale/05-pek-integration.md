# B2B Wholesale POS & Distribution Subsystem
## 05 — Armenian PEK XML Export & Integration Specification
### Architecture Version 1.0 — QR Menu SaaS Platform

---

## 1. Regulatory Context & Scope

The State Revenue Committee of the Republic of Armenia (ՀՀ ՊԵԿ) mandates electronic tax document submission via the national e-Invoicing portal (`e-invoicing.taxservice.am`).

### Approved Integration Architectural Boundary:
- **XML Export Only**: The application generates compliant XML files for manual download.
- **Manual Import by User**: The authorized accountant/operator uploads the exported XML file directly into the official PEK e-invoicing portal and signs it using their authorized e-signature (e-token / CryptoCard).
- **Zero Automated API Submission**: No automated third-party API communication, remote signing, or direct submission is performed in this version.
- **Strict Anti-Fabrication Rule**: Document numbers, series, tax IDs, and timestamps are generated according to official format rules, and document statuses accurately reflect internal export state rather than fabricated governmental acceptance.

---

## 2. Document Generators Architecture

The subsystem implements two strictly isolated XML generators:

```
                                  +---------------------------------------+
                                  |     Wholesale Tax Document Model      |
                                  +---------------------------------------+
                                                      |
                         +----------------------------+----------------------------+
                         |                                                         |
                         v                                                         v
         +-------------------------------+                         +-------------------------------+
         |    PekInvoiceXmlGenerator     |                         |     PekAccDocXmlGenerator     |
         |    (Հարկային հաշիվ)           |                         |     (Հաշիվ վավերագիր)         |
         +-------------------------------+                         +-------------------------------+
                         |                                                         |
                         v                                                         v
             ExportedData (XML Root)                                   ExportedAccDocData (XML Root)
             SignedData                                                SignedAccDocData
             Data -> SignableData (type: Invoice)                      Data -> SignableData (type: AccountingDocument)
                         |                                                         |
                         +----------------------------+----------------------------+
                                                      |
                                                      v
                                      +-------------------------------+
                                      |   PekXsdValidationService     |
                                      |   (W3C Schema Validation)     |
                                      +-------------------------------+
                                                      |
                                     Pass             |             Fail
                         +----------------------------+----------------------------+
                         |                                                         |
                         v                                                         v
             [Ready for Export / Download]                             [Export Blocked + Error Log]
```

---

## 3. Detailed XML Schema Analysis

### 3.1 Invoice Generator (Հարկային հաշիվ)
- **Target Audience**: VAT-registered Sellers issuing tax invoices to buyers.
- **Root Element**: `<ExportedData xmlns="http://www.taxservice.am/tp3/invoice/definitions">`
- **Envelope**: `<SignedData><Data><SignableData xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="1.0" xsi:type="Invoice">`
- **Structure**:
  - `<Type>1</Type>`
  - `<Traceable>false</Traceable>`
  - `<User>{pek_username}</User>`
  - `<GeneralInfo>`:
    - `<ParentInvoiceNumber>` (optional, required if adjustment)
    - `<ParentInvoiceSubmitDate>` (optional, e.g. `YYYY-MM-DD+04:00`)
    - `<InvoiceNumber><Number>6629988041</Number><Series>A</Series></InvoiceNumber>`
    - `<AdjustmentDiffFlag>-1</AdjustmentDiffFlag>` (optional)
    - `<SupplyDate>2026-11-06+04:00</SupplyDate>`
    - `<Procedure>1</Procedure>` (or 2 for adjustments)
    - `<AdjustmentAccount>false</AdjustmentAccount>`
  - `<SupplierInfo>`:
    - `<VATNumber>08293072/1</VATNumber>`
    - `<Taxpayer><TIN>08293072</TIN><Name>«...» ՍՊԸ</Name><Address>...</Address></Taxpayer>`
    - `<SupplyLocation>...</SupplyLocation>`
    - `<SourceIsCar>false</SourceIsCar>`
  - `<OnBehalfOfSupplierInfo><Taxpayer><PrincipalTinNotRequired>false</PrincipalTinNotRequired></Taxpayer></OnBehalfOfSupplierInfo>`
  - `<BuyerInfo>`:
    - `<VATNumber>03576728/1</VATNumber>` (if buyer has VAT registration)
    - `<Taxpayer><TIN>03576728</TIN><Name>«...» ՍՊԸ</Name><Address>...</Address><TinNotRequired>false</TinNotRequired><IsNatural>false</IsNatural></Taxpayer>`
    - `<DeliveryLocation>...</DeliveryLocation>`
  - `<GoodsInfo>`:
    - `<Good>`:
      - `<GoodParentId>` (UUID if adjustment)
      - `<Description>- Պելմենի Ֆիրմային (450գր)</Description>`
      - `<ClassifierCode>1602</ClassifierCode>` (Valid ATGAA / FEACN code)
      - `<Unit>հատ</Unit>`
      - `<Amount>3</Amount>`
      - `<PricePerUnit>1208.33333</PricePerUnit>`
      - `<Price>3625</Price>`
      - `<VATRate>20.0</VATRate>`
      - `<VAT>725</VAT>`
      - `<TotalPrice>4350</TotalPrice>`
    - `<Total>`:
      - `<Price>6208.33</Price>`
      - `<VAT>1241.67</VAT>`
      - `<TotalPrice>7450</TotalPrice>`
  - `<Signature><SignatureType>PKCS7</SignatureType></Signature>`
  - `<InvoiceMetadata><SubmissionDate>2026-11-06T13:46:37.59082+04:00</SubmissionDate></InvoiceMetadata>`

---

### 3.2 Accounting Document Generator (Հաշիվ վավերագիր)
- **Target Audience**: Non-VAT Sellers (Turnover Tax / Micro-Business) or Special Tax Regimes.
- **Root Element**: `<ExportedAccDocData xmlns="http://www.taxservice.am/tp3/invoice/definitions">`
- **Envelope**: `<SignedAccDocData><Data><SignableData xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="1.0" xsi:type="AccountingDocument">`
- **Structure**:
  - `<User>{pek_username}</User>`
  - `<Type>1</Type>`
  - `<Traceable>false</Traceable>`
  - `<GeneralInfo>`:
    - `<InvoiceNumber><Number>9771705511</Number><Series>B</Series></InvoiceNumber>`
    - `<DeliveryDate>2026-11-06+04:00</DeliveryDate>`
    - `<Procedure>1</Procedure>`
    - `<AdjustmentAccount>false</AdjustmentAccount>`
  - `<SupplierInfo>`:
    - `<Taxpayer>`:
      - `<TIN>08285927</TIN>`
      - `<Name>«...» ՍՊԸ</Name>`
      - `<Address>...</Address>`
      - `<BankAccount><BankName>«ԱՄԵՐԻԱԲԱՆԿ» ՓԲԸ</BankName><BankAccountNumber>1570077654886500</BankAccountNumber></BankAccount>`
    - `<SupplyLocation>...</SupplyLocation>`
    - `<SourceIsCar>false</SourceIsCar>`
  - `<BuyerInfo>`:
    - `<VATNumber>08293072/1</VATNumber>` (if buyer has VAT registration)
    - `<Taxpayer><TIN>08293072</TIN><Name>...</Name><Address>...</Address><TinNotRequired>false</TinNotRequired><IsNatural>false</IsNatural></Taxpayer>`
    - `<DeliveryLocation>...</DeliveryLocation>`
  - `<GoodsInfo>`:
    - `<Good>`:
      - `<Description>- Պելմենի Սիբիրյան (450գր)</Description>`
      - `<ClassifierCode>1902</ClassifierCode>`
      - `<Unit>հատ</Unit>`
      - `<Amount>5</Amount>`
      - `<PricePerUnit>1600</PricePerUnit>`
      - `<Price>6400</Price>`
      - `<Discount>20</Discount>`
      - `<DealType>SPECIAL_TAX_SYSTEM</DealType>`
      - `<TotalPrice>6400</TotalPrice>`
    - `<Total>`:
      - `<TotalPrice>25600</TotalPrice>`
  - `<Signature><SignatureType>PKCS7</SignatureType></Signature>`
  - `<AccDocMetadata><SubmissionDate>2026-11-06T13:06:19.18964+04:00</SubmissionDate></AccDocMetadata>`

---

## 4. W3C Schema Validation Engine

The `PekXsdValidationService` performs pre-flight validation against compiled XSD definitions:
1. XML syntactical correctness and well-formedness.
2. Namespace adherence to `http://www.taxservice.am/tp3/invoice/definitions`.
3. Validation of all mandatory tags: TIN (must match `^\d{8}$`), dates (must include timezone offset `+04:00`), positive numbers, unit codes, classifier codes.
4. Mathematical Invariants:
   - Invoice: `ROUND(Price + VAT, 2) === TotalPrice`
   - Invoice Total: `SUM(Price) === Total.Price && SUM(VAT) === Total.VAT`
   - AccDoc: `ROUND(Amount * PricePerUnit * (1 - Discount/100), 2) === TotalPrice`
5. On failure: Detailed error listing rendered in the tax document dashboard with explicit remediation advice.

