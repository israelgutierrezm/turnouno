# Domain Model

## Identity vs People

### User
Authentication identity.

### Person
Human being.

### Profiles/Roles
A Person may participate as:
- Customer
- Member
- Guardian
- Staff
- Instructor
- Lead

A person may have several roles simultaneously.

## Tenant structure

Platform
→ Tenant
→ Organization
→ Brand
→ Branch
→ Facility
→ Resource

Tenant is the primary security boundary.

## Household

Household
→ Household Members
→ Guardian Relationships
→ Authorized Pickups

Purchaser and participant may differ.

Example:
Mother pays.
Child participates.

## Catalog

Program
→ Activity
→ Offering
→ Class Template / Schedule Template
→ Session

Examples:
Program: Pole
Activity: Pole Fitness
Level: Beginner
Offering: Group Class
Session: 2026-10-05 19:00

## Membership/commerce separation

Commercial Product
→ purchase/agreement
→ entitlement grants
→ entitlement usage

Commercial products may include:
- membership
- class pack
- day pass
- single class
- add-on
- workshop
- private session

## Public IDs

Use:
- BIGINT internal primary key
- ULID public identifier
