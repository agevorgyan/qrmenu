# QR Menu SaaS – Apple Wallet & Google Wallet Onboarding Guide

This guide details the step-by-step operational setup for Apple Wallet PassKit and Google Wallet integrations.

---

## 1. Architecture Overview: Platform Mode vs. Custom Mode

QR Menu SaaS supports dual issuance modes configured under **Admin Panel → Loyalty & Wallet → Wallet Settings**:

| Capability | Platform Managed (Default) | Custom Enterprise Account |
| :--- | :--- | :--- |
| **Pass Certificate** | Signed by QR Menu SaaS Platform Apple Cert | Signed by Restaurant's Apple Developer Cert |
| **Pass Type Identifier** | `pass.am.elab.menu.loyalty` | `pass.com.yourrestaurant.loyalty` |
| **Google Issuer ID** | Shared Platform Google Pay Issuer | Dedicated Restaurant Google Pay Issuer |
| **Setup Time** | Instant (Zero configuration required) | 1-2 Business Days (Requires Dev accounts) |
| **Lock Screen Alerts** | Displays restaurant name & live balance | Fully branded lock screen notifications |

---

## 2. Apple Wallet (PassKit) Enterprise Onboarding

### Step 1: Create a Pass Type ID
1. Log into your [Apple Developer Account](https://developer.apple.com/account).
2. Navigate to **Certificates, Identifiers & Profiles → Identifiers**.
3. Click the **+** button, select **Pass Type IDs**, and click **Continue**.
4. Enter:
   - **Description:** `My Restaurant Loyalty Pass`
   - **Identifier:** `pass.com.myrestaurant.loyalty` (must start with `pass.`)
5. Click **Register**.

### Step 2: Generate Pass Signing Certificate
1. On your Mac, open **Keychain Access**.
2. Select **Keychain Access → Certificate Assistant → Request a Certificate from a Certificate Authority**.
3. Enter your email and name, select **Saved to disk**, and save the `.certSigningRequest` (CSR) file.
4. Back in the Apple Developer portal, click your new Pass Type ID, click **Create Certificate**, and upload the CSR file.
5. Download the resulting `pass.cer` certificate.

### Step 3: Export PEM Files
1. Double-click `pass.cer` to install it into your Keychain.
2. In Keychain Access, locate the certificate under **My Certificates**.
3. Right-click the certificate and export it as `pass_cert.pem`.
4. Right-click the associated private key and export it as `pass_key.pem`.
5. Open **Admin Panel → Loyalty & Wallet → Wallet Settings**.
6. Switch Mode to **Custom Enterprise Accounts**.
7. Enter your **Pass Type Identifier** and **Apple Team ID** (found at top right of developer portal).
8. Upload `pass_cert.pem` and `pass_key.pem`. The system automatically parses the certificate, verifies OpenSSL compatibility, records the certificate expiration date, and AES-256 encrypts the private key at rest.

### Step 4: Apple Worldwide Developer Relations (WWDR) Certificate
Apple passes require the Apple WWDR G4/G6 Intermediate CA certificate. The platform bundles this intermediate certificate automatically at `storage/certs/wwdr.pem`.

---

## 3. Google Wallet (Google Pay Business Console) Onboarding

### Step 1: Access Google Pay & Wallet Console
1. Navigate to the [Google Pay & Wallet Console](https://pay.google.com/business/console).
2. Complete business registration and accept the Google Pay API Terms of Service.
3. Note your **Google Pay Issuer ID** (e.g. `3388000000022345678`).

### Step 2: Create a Service Account in Google Cloud
1. Go to [Google Cloud Console](https://console.cloud.google.com/).
2. Create or select your project.
3. Enable the **Google Wallet API** (`walletobjects.googleapis.com`).
4. Navigate to **IAM & Admin → Service Accounts → Create Service Account**.
5. Name: `google-wallet-loyalty-signer`.
6. Click **Done**.
7. Under **Keys**, click **Add Key → Create new key → JSON**.
8. Save the downloaded JSON credentials file.

### Step 3: Authorize Service Account in Wallet Console
1. In the Google Pay & Wallet Console, go to **Google Wallet API → Users**.
2. Click **Invite User** and paste the Service Account email (`google-wallet-loyalty-signer@your-project.iam.gserviceaccount.com`).
3. Set role to **Developer** or **Admin**.

### Step 4: Configure in QR Menu Admin
1. Open **Admin Panel → Loyalty & Wallet → Wallet Settings**.
2. Enter your **Google Pay Issuer ID**.
3. Upload the downloaded Service Account JSON file.
4. Click **Save Credentials & Settings**.

---

## 4. Visual Card Customization Best Practices

Navigate to **Admin Panel → Loyalty & Wallet → Card Designer** to customize visual assets:

1. **Logo Image:** 320 × 100 px PNG with transparent background.
2. **Strip Banner:** 1125 × 432 px PNG without transparent areas.
3. **Card Colors:** Choose high contrast foreground vs. background colors (e.g., `#1e293b` background with `#ffffff` foreground) for optimal readability under bright sunlight.
4. **Barcode Standard:** Default is `PKBarcodeFormatQR` (QR Code), which is compatible with iOS Camera, Android Google Lens, and optical 2D laser checkout scanners.

