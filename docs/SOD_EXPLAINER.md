# Understanding SoD (Segregation of Duties)

**What is this?** A guide for when the system blocks you from approving your own work.

---

## The Short Answer

> **You cannot approve your own requests.** This is intentional and required for compliance.

---

## Why Does This Exist?

### The Problem
Imagine if you could:
- Submit a leave request → Approve it yourself → No one knows
- Create an invoice → Approve it yourself → Pay yourself
- File an expense → Approve it yourself → Steal money

### The Solution
**Segregation of Duties (SoD)** means:
- Person who **creates** ≠ Person who **approves**
- This prevents fraud and errors
- Required by accounting standards and audits

---

## Real-World Examples

### Example 1: Leave Request
```
❌ BAD (No SoD):
You submit leave → You approve it → You're on vacation

✅ GOOD (With SoD):
You submit leave → Your manager approves → You're on vacation
```

### Example 2: Invoice Payment
```
❌ BAD (No SoD):
You create vendor invoice → You approve it → Money sent

✅ GOOD (With SoD):
You create vendor invoice → Manager approves → Accounting pays
```

### Example 3: Payroll
```
❌ BAD (No SoD):
HR prepares payroll → HR approves it → Errors/fraud possible

✅ GOOD (With SoD):
HR prepares payroll → Accounting approves → Errors caught
```

---

## What You'll See

### Visual Indicator 1: Disabled Button
```
[Approve (SoD)]  ← Grayed out, can't click
```

### Visual Indicator 2: Tooltip
```
Hover over button → "Segregation of Duties violation: 
                    you initiated this record and cannot approve it."
```

### Visual Indicator 3: Reduced Opacity
```
The button looks "faded" compared to active buttons
```

---

## What To Do When Blocked

### Step 1: Don't Panic
This is **working as designed**. The system is protecting you and the company.

### Step 2: Identify Who Can Approve

| Your Role | Who Should Approve |
|-----------|-------------------|
| Staff | Your Head/Manager |
| Head | Your Manager |
| Manager | Another Manager or VP |
| Officer | Manager or VP |
| VP | Another VP or Executive |

### Step 3: Contact the Approver
- Send them a message
- Ask them to review the request
- Provide context if needed

### Step 4: Alternative - Reassign
If the usual approver is unavailable, ask your manager to reassign approval authority temporarily.

---

## Common Questions

### "This is my work, why can't I approve it?"
That's exactly why! You should never approve your own work. It's like grading your own exam.

### "I'm a manager, I should be able to approve anything!"
Even managers cannot approve their own requests. You need a different manager or VP.

### "This is urgent! Can't the system make an exception?"
No exceptions. For true emergencies, contact your VP or Admin. They can help escalate.

### "I used to be able to do this before!"
The new RBAC v2 system enforces SoD more strictly. This is an improvement, not a bug.

### "Admin doesn't have this restriction?"
Correct. Admin bypasses SoD for system management only. This is necessary for system operation.

---

## SoD Rules Reference

| Rule | What It Means |
|------|---------------|
| SOD-001 | Creator of employee record cannot activate that employee |
| SOD-002 | Submitter of leave cannot approve their own leave |
| SOD-003 | Requester of OT cannot approve their own OT |
| SOD-004 | HR cannot be the final approver on loans |
| SOD-005/006 | Payroll preparer cannot approve payroll |
| SOD-007 | HR cannot approve accounting's work (and vice versa) |
| SOD-008 | Journal entry creator cannot post it |
| SOD-009 | Invoice creator cannot approve it |
| SOD-010 | Same as above for customer invoices |

---

## Exceptions

### Who Can Bypass SoD?
- **Admin** - For system management
- **Super Admin** - For testing and emergencies

### Who CANNOT Bypass SoD?
- Everyone else (including Managers, VPs, Executives)

---

## Your Responsibilities

1. **Understand** that SoD protects everyone
2. **Don't try** to circumvent the system
3. **Report** if you suspect someone is bypassing SoD
4. **Plan ahead** - know who your approver is
5. **Escalate** true emergencies through proper channels

---

## If You Think There's an Error

### Check First:
1. Is it YOUR request? (If yes, SoD is correct)
2. Are you looking at someone else's request? (Should work)
3. Is your role correct? (Check with admin)

### Contact:
- **For role issues:** Your manager or IT Admin
- **For workflow issues:** Your department manager
- **For system errors:** IT Support

---

## Remember

> **SoD is not a bug. It's a feature that protects you and the company.**

When you see an SoD block, the system is working correctly. Find your approver and collaborate!

---

## Quick Reference Card

```
┌─────────────────────────────────────────┐
│  SOD BLOCKED? HERE'S WHAT TO DO:       │
│                                         │
│  1. Confirm it's your own request      │
│     → If YES: Someone else must approve │
│     → If NO: Contact IT support         │
│                                         │
│  2. Find your approver:                │
│     Staff → Head/Manager                │
│     Head → Manager                      │
│     Manager → Another Manager/VP        │
│     VP → Another VP                     │
│                                         │
│  3. Send them a message with context   │
│                                         │
│  4. Wait for approval                  │
│                                         │
│  Questions? Contact your manager or IT │
└─────────────────────────────────────────┘
```
