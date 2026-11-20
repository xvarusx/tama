# Design Specification: Payment Service UI Extensions

**Date:** 2026-11-16  
**Feature:** Allocations & Reminder Details  
**Status:** DRAFT

## 1. Allocations List Page

**Route:** `/dashboard/allocations`

### Layout
- **Breadcrumb:** Dashboard / Allocations
- **Header:**
  - **Title:** Allocations
  - **Description:** View payment allocations to installments.
  - **Actions:** Refresh (icon button)

### Data Table
| Column | Type | Content |
|--------|------|---------|
| Payment | ID | `attributes.paymentId` (truncated to 8 chars) |
| Installment | ID | `attributes.installmentId` (truncated to 8 chars) |
| Invoice | ID | `attributes.invoiceId` (truncated to 8 chars) |
| Amount | Currency | `attributes.amount` (formatted USD) |
| Actions | Link | View (Link to `/dashboard/allocations/{id}`) |

### States
- **Loading:** 5 skeleton rows
- **Empty:** "No allocations found."
- **Error:** Card with error message and "Retry" button

## 2. Reminder Detail Page

**Route:** `/dashboard/reminders/{id}`

### Layout
- **Breadcrumb:** Dashboard / Reminders / {ID}
- **Header:**
  - **Back Button:** Back to reminders
  - **Title:** Reminder Detail
  - **Actions:** Refresh (icon button)

### Content Card
- **Grid Layout (2 columns):**
  - **Installment ID:** `attributes.installmentId` (truncated)
  - **Kind:** Badge (UPCOMING_DUE = warning, OVERDUE = destructive)
  - **Scheduled For:** `attributes.scheduledFor` (localized date)
  - **Status:** Badge (secondary)
  - **Sent At:** `attributes.sentAt` (localized date or "—")

### Actions
- Back button navigates to `/dashboard/reminders`
- Refresh button reloads data from `/api/reminder/{id}`

## 3. API Contract Requirements

### Allocation List
- **Endpoint:** `GET /api/allocation`
- **Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "uuid",
      "type": "payment-allocation",
      "attributes": {
        "paymentId": "uuid",
        "installmentId": "uuid",
        "invoiceId": "uuid",
        "amount": 1000
      }
    }
  ]
}
```

### Reminder Detail
- **Endpoint:** `GET /api/reminder/{id}`
- **Response:**
```json
{
  "status": "success",
  "data": {
    "id": "uuid",
    "type": "reminder",
    "attributes": {
      "installmentId": "uuid",
      "kind": "OVERDUE",
      "scheduledFor": "2026-11-16T10:00:00Z",
      "status": "SENT",
      "sentAt": "2026-11-16T10:05:00Z"
    }
  }
}
```

## 4. Accessibility
- All table headers must have appropriate labels
- Action buttons must have ARIA labels
- Loading state must use `aria-busy="true"`
- Focus indicators must be clearly visible for keyboard navigation

---
*Created by ux-designer*