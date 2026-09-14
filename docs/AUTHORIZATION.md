# Authorization

## Model

RBAC + scope + tenant boundary.

## User-facing role families

Administrative:
- Owner
- Organization Admin
- Branch Manager
- Receptionist
- Sales
- Accountant
- Marketing

Instruction:
- Lead Instructor
- Instructor
- Assistant Instructor
- Coach

Customer:
- Member
- Guardian

## Permission examples

- members.view
- members.create
- members.edit
- bookings.view
- bookings.create
- bookings.cancel
- attendance.view
- attendance.mark
- attendance.override
- payments.view
- payments.create
- payments.refund
- reports.export
- staff.manage
- roles.manage

## Scope

A role assignment may apply to:
- tenant
- organization
- branch

Example:
Receptionist + `branch=roma`

## Rule

Frontend permission checks improve UX only.
Backend policies/gates enforce security.
