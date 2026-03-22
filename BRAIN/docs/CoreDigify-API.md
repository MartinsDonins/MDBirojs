# MDBirojs ↔ CoreDigify API Integration

## Overview

This document describes the REST API between **MDBirojs** (accounting system) and **CoreDigify** (invoice management system).

There are **two directions** of communication:

| Direction | Who calls | Endpoint | Purpose |
|-----------|-----------|----------|---------|
| **Outgoing** | MDBirojs → CoreDigify | Configured in Settings | Notify CoreDigify when a payment is received |
| **Incoming** | CoreDigify → MDBirojs | `/api/coredigify/*` | Query MDBirojs for matching transactions |

---

## 1. Outgoing: MDBirojs → CoreDigify

### When it fires
- **Automatically**: when a transaction is set to `COMPLETED` status AND its category is "Ienākumi no saimnieciskās darbības" (VID columns 4, 5, or 6)
- **Manually**: via "Sinhronizēt ar CoreDigify" button in the Income/Expense Journal or via the CoreDigify dashboard

### Configuration (MDBirojs Settings → CoreDigify savienojums)
- **CoreDigify API URL** — the endpoint on CoreDigify side that receives the POST
- **CoreDigify API atslēga** — API key MDBirojs sends in Authorization header

### Request
```
POST {COREDIGIFY_API_URL}
Authorization: Bearer {COREDIGIFY_API_KEY}
Content-Type: application/json
```

### Payload
```json
{
  "source": "MDBirojs",
  "transaction_id": 123,
  "occurred_at": "2024-03-15",
  "booked_at": "2024-03-16",
  "amount": 250.00,
  "amount_eur": 250.00,
  "currency": "EUR",
  "exchange_rate": 1.000000,
  "counterparty_name": "SIA Klients",
  "counterparty_account": "LV00BANK0000000000001",
  "description": "Apmaksa par pakalpojumiem",
  "reference": "RK-2024-001",
  "type": "INCOME",
  "account_name": "Uzņēmuma norēķinu konts",
  "category_name": "Ienākumi no saimnieciskās darbības",
  "vid_column": 5,
  "cash_order_number": "KIO-2024-0042"
}
```

### Expected response
- `2xx` — success; MDBirojs marks the transaction as synced
- `4xx` / `5xx` / timeout — failure; MDBirojs logs the error, transaction can be re-sent manually

### CoreDigify implementation notes
- Validate the `Authorization: Bearer` header against your stored MDBirojs outgoing key
- Match the payment to an invoice using: `reference`, `amount_eur`, `counterparty_name`, `occurred_at`
- The `transaction_id` should be stored on the invoice/payment record for future reference
- Return HTTP 200 on successful processing

---

## 2. Incoming: CoreDigify → MDBirojs

### Authentication
All requests must include the MDBirojs API key (found in Settings → CoreDigify savienojums → "MDBirojs API atslēga"):

```
Authorization: Bearer {MDBIROJS_INCOMING_API_KEY}
```

### Base URL
```
https://mdbirojs.donins.lv/api
```

---

### 2.1 Search Transactions

```
POST /api/coredigify/transactions/search
Authorization: Bearer {key}
Content-Type: application/json
```

Search for income transactions matching given criteria. All fields are optional — combine them for precise matching.

**Request body:**
```json
{
  "amount":       250.00,
  "amount_from":  200.00,
  "amount_to":    300.00,
  "date_from":    "2024-01-01",
  "date_to":      "2024-12-31",
  "counterparty": "SIA Klients",
  "reference":    "RK-2024-001",
  "description":  "pakalpojum",
  "limit":        20
}
```

| Field | Type | Description |
|-------|------|-------------|
| `amount` | float | Exact EUR amount match |
| `amount_from` | float | Minimum EUR amount |
| `amount_to` | float | Maximum EUR amount |
| `date_from` | string (YYYY-MM-DD) | Start date (occurred_at) |
| `date_to` | string (YYYY-MM-DD) | End date (occurred_at) |
| `counterparty` | string | Partial match on counterparty name (case-insensitive) |
| `reference` | string | Partial match on reference field |
| `description` | string | Partial match on description |
| `limit` | int | Max results, default 20, max 100 |

**Response:**
```json
{
  "count": 2,
  "data": [
    {
      "transaction_id": 123,
      "occurred_at": "2024-03-15",
      "booked_at": "2024-03-16",
      "amount": 250.00,
      "amount_eur": 250.00,
      "currency": "EUR",
      "exchange_rate": 1.0,
      "counterparty_name": "SIA Klients",
      "counterparty_account": "LV00BANK0000000000001",
      "description": "Apmaksa par pakalpojumiem",
      "reference": "RK-2024-001",
      "account_name": "Uzņēmuma norēķinu konts",
      "category_name": "Ienākumi no saimnieciskās darbības",
      "vid_column": 5,
      "cash_order_number": "KIO-2024-0042",
      "coredigify_sent_at": "2024-03-16T10:30:00+02:00"
    }
  ]
}
```

---

### 2.2 Get Transaction by ID

```
GET /api/coredigify/transactions/{id}
Authorization: Bearer {key}
```

Returns a single transaction. Only returns transactions that qualify as business income (INCOME, COMPLETED, VID col 4/5/6).

**Response:** same format as a single item in the search `data` array above.

**Error responses:**
- `404` — transaction not found or does not qualify

---

### Error responses (both endpoints)

| HTTP Code | Meaning |
|-----------|---------|
| `401` | Invalid or missing API key |
| `404` | Transaction not found |
| `422` | Validation error (invalid date format, etc.) |
| `503` | MDBirojs incoming API key not configured |

---

## 3. Suggested CoreDigify Implementation Workflow

```
When creating/updating an invoice in CoreDigify:

1. User triggers "Meklēt apmaksu MDBirojs" button on invoice
2. CoreDigify calls POST /api/coredigify/transactions/search with:
   - amount = invoice.total
   - date_from = invoice.due_date - 30 days
   - date_to = today
   - counterparty = client.name (optional)
   - reference = invoice.number (optional)
3. Display results to user — let them pick the matching transaction
4. On confirm:
   a. Store transaction_id on the invoice
   b. Mark invoice as PAID with payment_date = transaction.occurred_at
   c. Optionally: call MDBirojs back to mark the transaction as linked
      (via the outgoing flow or a dedicated endpoint added later)

Alternatively (automatic matching):
- When MDBirojs sends a payment (outgoing POST), try to auto-match
  against open invoices by amount + counterparty + reference
- If unique match found → auto-mark invoice as paid
- If ambiguous → flag for manual review
```

---

## 4. Security Notes

- MDBirojs uses `hash_equals()` for constant-time key comparison (timing attack safe)
- The incoming API key should be treated as a secret (do not log it)
- Regenerate the incoming key via Settings → CoreDigify savienojums → "Reģenerēt" if compromised
- API endpoints are CSRF-exempt (standard for Laravel API routes)
- All API traffic should use HTTPS in production
