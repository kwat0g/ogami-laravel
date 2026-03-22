# RBAC v2 Quick Start Guide for Teams

**For:** All ERP Users  
**Time to Read:** 5 minutes  
**Version:** 2.0

---

## 🎯 What's Changed?

We've simplified roles from **11 to 7 core roles**. Your access is now based on:

```
Your Role + Your Department = What You Can Do
```

---

## 🔑 Your Role

### Staff (Rank-and-File)
**What you can do:**
- View your own profile, payslips, attendance
- Submit leave requests, OT requests, loan applications
- View your leave balances

**What you CANNOT do:**
- Access team or department data
- Approve any requests

**Tip:** Use the "My" sections in the sidebar (My Profile, My Payslips, etc.)

---

### Head (Team Supervisor)
**What you can do:**
- View your team's attendance, leave, OT
- First-level approvals (approve team leave, endorse OT)
- View team members' profiles

**What you CANNOT do:**
- Approve your own leave/OT requests (SoD blocks this)
- Access full department management

**Tip:** Look for "Team Management" in the sidebar

---

### Manager (Department Manager)
**What you can do:**
- Full department management (employees, payroll, etc.)
- Approve workflows within your department
- Run reports

**What you CANNOT do:**
- Approve your own requests (SoD enforced)
- Access other departments' data

**Tip:** You'll see module sections based on your department (HR, Accounting, etc.)

---

### Officer (Department Operations)
**What you can do:**
- Create and process records
- Update information
- Prepare documents

**What you CANNOT do:**
- Final approve your own work (different person must approve)

**Tip:** You handle the paperwork, someone else approves it

---

### VP (Vice President)
**What you can do:**
- Cross-department approvals
- Final sign-off on financial transactions
- View all departments (read access)

**What you CANNOT do:**
- Approve your own requests (SoD enforced)

**Tip:** Check your "VP Approvals Dashboard" for pending items

---

### Executive (Board Level)
**What you can do:**
- View-only access to all modules
- Executive approvals for special requests

**What you CANNOT do:**
- Create, edit, or approve standard workflows

**Tip:** You have read-only access for oversight

---

## 🛡️ SoD (Segregation of Duties)

### What is SoD?
**SoD ensures you cannot approve your own requests.**

### Examples:
| If you... | Then you CANNOT... |
|-----------|-------------------|
| Submitted a leave request | Approve your own leave |
| Created a journal entry | Post your own journal entry |
| Filed an OT request | Approve your own OT |
| Initiated a payroll run | Approve that payroll run |

### What you'll see:
- Button shows "Approve (SoD)" with reduced opacity
- Tooltip says: "Segregation of Duties violation: you initiated this record"
- Button is disabled

### Who bypasses SoD?
- **Admin** and **Super Admin** only

---

## ❓ Common Issues

### "I can't see my team!"
**Check:** Are you assigned as a Head or Manager in your department?

### "Approve button is disabled (SoD)"
**This is correct!** You cannot approve your own requests. Ask another manager.

### "I can't access [module]"
**Check:** 
1. Is that module in your department?
2. Do you have the right role for that action?

### "404 when accessing a page"
**Check:** You may not have permission for that feature. Check with your admin.

---

## 📋 Role Comparison Table

| Action | Staff | Head | Officer | Manager | VP | Executive |
|--------|-------|------|---------|---------|----|----|
| View own profile | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Submit leave/OT | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| View team data | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Approve leave (1st level) | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Approve leave (final) | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ |
| Create records | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| Process workflows | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| Final approvals | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ |
| Cross-department view | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| System settings | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

*Admin/Super Admin have special access not shown here*

---

## 🆘 Need Help?

1. **Check your role:** Ask your admin what role you're assigned
2. **Check your department:** Verify you're in the right department
3. **SoD questions:** Remember - you can't approve your own work
4. **Technical issues:** Contact IT/Admin

---

## 🔗 Resources

- [Full RBAC v2 Guide](./RBAC_V2_GUIDE.md)
- [SoD Audit Report](./SOD_AUDIT_REPORT.md)
- [Test Accounts](./ROLE_TEST_ACCOUNTS.md)

---

**Remember:** When in doubt, ask! The system is designed to enforce proper workflows and prevent conflicts of interest.
