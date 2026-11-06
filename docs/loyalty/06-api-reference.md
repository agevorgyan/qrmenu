# QR Menu SaaS – Loyalty & Digital Wallet API Reference

This document provides complete, production-ready REST API specifications for the Loyalty & Digital Wallet domain.

---

## 1. Storefront Customer Loyalty APIs

### 1.1 Send OTP Verification Code
- **Method:** `POST`
- **Route:** `/api/m/{vendor_slug}/loyalty/otp/send`
- **Rate Limit:** 1 request per 60 seconds per phone/email identifier
- **Headers:**
  - `Accept: application/json`
  - `X-CSRF-TOKEN: <csrf-token>`

#### Request Payload
```json
{
  "identifier": "+37491000001"
}
```

#### Successful Response (200 OK)
```json
{
  "success": true,
  "message": "Verification code dispatched successfully.",
  "cooldown_seconds": 60
}
```

#### Rate Limited Response (422 Unprocessable Entity)
```json
{
  "message": "Please wait 45 seconds before requesting a new code.",
  "errors": {
    "identifier": ["Please wait 45 seconds before requesting a new code."]
  }
}
```

---

### 1.2 Verify OTP Code & Session Authentication
- **Method:** `POST`
- **Route:** `/api/m/{vendor_slug}/loyalty/otp/verify`
- **Headers:** `Accept: application/json`, `X-CSRF-TOKEN`

#### Request Payload
```json
{
  "identifier": "+37491000001",
  "code": "849201",
  "name": "Jane Doe"
}
```

#### Successful Response (200 OK)
```json
{
  "success": true,
  "redirect_url": "https://qrmenu.test/m/bistro-alpha/loyalty"
}
```

---

### 1.3 Claim Reward Voucher
- **Method:** `POST`
- **Route:** `/api/m/{vendor_slug}/loyalty/rewards/{reward}/claim`
- **Authentication:** Customer Session (`loyalty_customer_id`)

#### Successful Response (200 OK)
```json
{
  "success": true,
  "message": "Reward redeemed successfully! Present the code to staff.",
  "code": "VOUCH-7B42D9",
  "expires_at": "2026-12-06T20:00:00+04:00",
  "new_balance": 350
}
```

#### Insufficient Balance (422 Unprocessable Entity)
```json
{
  "error": "Insufficient loyalty points. Balance: 100, Required: 250"
}
```

---

## 2. Staff Scanner & Operations APIs

All staff endpoints require an authenticated staff user belonging to the restaurant tenant with permission `loyalty.operate` or `loyalty.manage`.

### 2.1 Customer Scanner & Code Lookup
- **Method:** `POST`
- **Route:** `/admin/loyalty/staff/lookup`

#### Request Payload (Supports Ephemeral QR Token, Member Code, or Phone)
```json
{
  "query": "lq_9fa8b7c6d5e4f3a2b1c0d9e8f7a6b5c4"
}
```

#### Successful Response (200 OK)
```json
{
  "success": true,
  "overview": {
    "customer": {
      "id": 142,
      "name": "Alex Smith",
      "phone": "+37491223344",
      "email": "alex@example.com"
    },
    "points": {
      "balance": 1450,
      "lifetime_earned": 2800,
      "lifetime_redeemed": 1350
    },
    "cashback": {
      "balance": 18.50,
      "currency": "AMD"
    },
    "tier": {
      "name": "Gold",
      "level": 3,
      "multiplier": 1.5,
      "color": "#eab308"
    },
    "active_stamp_cards": [
      {
        "id": 8,
        "program_name": "Coffee Club Card",
        "stamps_collected": 7,
        "stamps_required": 10
      }
    ]
  },
  "recent_transactions": [...]
}
```

---

### 2.2 Staff Points Issuance
- **Method:** `POST`
- **Route:** `/admin/loyalty/staff/points/issue`

#### Request Payload
```json
{
  "customer_id": 142,
  "points": 100,
  "notes": "Offline catering spend compensation"
}
```

#### Successful Response (200 OK)
```json
{
  "success": true,
  "message": "100 points successfully credited.",
  "new_balance": 1550
}
```

---

### 2.3 Staff Points Redemption
- **Method:** `POST`
- **Route:** `/admin/loyalty/staff/points/redeem`

#### Request Payload
```json
{
  "customer_id": 142,
  "points": 200,
  "notes": "Redeemed for $10 Bill Discount"
}
```

#### Successful Response (200 OK)
```json
{
  "success": true,
  "message": "200 points successfully redeemed.",
  "new_balance": 1350
}
```

---

### 2.4 Staff Digital Stamp Punching
- **Method:** `POST`
- **Route:** `/admin/loyalty/staff/stamp/issue`

#### Request Payload
```json
{
  "customer_id": 142,
  "program_id": 3,
  "stamps": 1
}
```

#### Successful Response (200 OK)
```json
{
  "success": true,
  "message": "1 stamp(s) awarded successfully.",
  "current_stamps": 8,
  "stamps_required": 10,
  "is_completed": false
}
```

---

## 3. Apple PassKit Web Service API (Official Apple Specification)

Official endpoints called by Apple iOS devices to register, unregister, receive push notification updates, and download updated passes.

### 3.1 Device Registration for APNs Push Updates
- **Method:** `POST`
- **Route:** `/api/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`
- **Headers:** `Authorization: ApplePass <authenticationToken>`

#### Request Payload
```json
{
  "pushToken": "35e4d2a1b9c8...apns_device_token"
}
```

#### Responses
- `201 Created`: Device successfully registered.
- `200 OK`: Device already registered; push token updated.
- `401 Unauthorized`: Token mismatch or invalid signature.

---

### 3.2 Fetch Serial Numbers Updated Since Tag
- **Method:** `GET`
- **Route:** `/api/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}?passesUpdatedSince={tag}`

#### Successful Response (200 OK)
```json
{
  "lastUpdated": "1730928000",
  "serialNumbers": [
    "PASS-7B42D9E8F1A0"
  ]
}
```

---

### 3.3 Deliver Latest `.pkpass` Binary
- **Method:** `GET`
- **Route:** `/api/v1/passes/{passTypeIdentifier}/{serialNumber}`
- **Headers:**
  - `Authorization: ApplePass <authenticationToken>`
  - `If-Modified-Since: <timestamp>` (Optional)

#### Responses
- `200 OK`: Returns signed `.pkpass` ZIP archive (`Content-Type: application/vnd.apple.pkpass`).
- `304 Not Modified`: Pass has not changed since `If-Modified-Since`.
- `401 Unauthorized`: Authentication token mismatch.

---

### 3.4 Device Unregistration
- **Method:** `DELETE`
- **Route:** `/api/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}`
- **Headers:** `Authorization: ApplePass <authenticationToken>`

#### Responses
- `200 OK`: Device successfully unregistered from receiving APNs push notifications for this pass.

---

### 3.5 PassKit Diagnostic Logs
- **Method:** `POST`
- **Route:** `/api/v1/log`

#### Request Payload
```json
{
  "logs": [
    "Device failed to render strip image due to dimension constraints"
  ]
}
```

#### Responses
- `200 OK`: Log entry accepted.

