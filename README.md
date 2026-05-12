# EJ's Plant Nursery Inventory Monitoring & Management

This system is designed to manage the daily operations of **EJ's Plant Nursery**, covering:

- Nursery plot management
- Plant inventory
- Customer orders
- Activity logs
- Reports

The system supports two types of users: **Administrators** and **Staff**. It handles sensitive data including customer personal information, employee records, plot and inventory details, order records, and generated reports.

To protect this data, **six security features** work together to prevent unauthorized access, data breaches, and data loss:

| # | Feature |
|---|---------|
| 1 | Login Attempt Monitoring |
| 2 | Input Validation & SQL Injection Protection |
| 3 | Session Management |
| 4 | Database Encryption |
| 5 | Security Questions |
| 6 | Database Backup |

---

## System Users & Access Control

### ADMIN — Full System Access

The administrator has complete control over all features and functions, including:

- Dashboard with sales and inventory statistics
- Inventory and plant management
- Nursery plot monitoring
- Inventory movement logs
- Staff account management
- Business report monitoring

### STAFF — Limited Access

Staff members have access focused on day-to-day nursery operations:

- Managing nursery plots
- Recording plant activities
- Handling customer orders when a plant is purchased
- Processing basic transactions through the system

### Access Control Implementation

The system uses **Role-Based Access Control (RBAC)**. Each user is assigned a role (`Admin` or `Staff`), and each role has its own defined set of permissions. The system verifies the user's role upon login to ensure they can only access features appropriate to their assigned role.

---

## Security Features

### 1. Login Attempt Monitoring

**Brute Force Protection**

> After **5 failed login attempts**, the device is temporarily locked for **5 minutes** to prevent unauthorized access and brute force attacks.

This ensures that automated or manual attempts to guess credentials are blocked before they can succeed.

---

### 2. Input Validation & Sanitization

**Validation**

Ensures that all data is in the correct format and data type before being processed or stored. Examples:

- Email fields must contain a valid email format
- Numeric fields (e.g., age, quantity) must contain only numbers

**Sanitization**

Makes data safe to use and display by removing or neutralizing harmful inputs:

- Strips dangerous tags (e.g., `<script>`) to protect against **Cross-Site Scripting (XSS)** attacks
- Uses **prepared statements** to protect the database from **SQL Injection** attacks

---

### 3. Session Management

**Automatic Logout**

> Users are automatically logged out after **5 minutes of inactivity** to prevent unauthorized use of open sessions.

**Session Cleanup**

All session data is fully cleared upon logout to prevent **session reuse or hijacking**.

---

### 4. Database Encryption

**Data Protection**

All sensitive data stored in the database is encrypted using **AES-256** encryption. Even if unauthorized users gain access to the database, the contents remain unreadable and unusable.

**Password Hashing**

Passwords are securely **hashed** before being stored in the database — they are never stored in plain text.

---

### 5. Security Questions

**Verification Process**

Security questions serve as an **additional layer of identity verification**, particularly during the **password reset** process. This ensures that only the legitimate account owner can initiate a password change.

---

### 6. Database Backup

**Data Recovery**

The system supports **regular data backups** to ensure full recovery in the event of data loss or corruption. This protects business continuity and prevents permanent loss of critical records.

