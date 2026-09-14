# Membership and Entitlement Engine

## Principle

Memberships do not directly answer every booking rule.

Commercial products grant entitlements.

Sources of entitlements:
- recurring membership
- limited membership
- class pack
- add-on/top-up
- promotion
- administrative adjustment
- make-up credit
- guest benefit

## Examples

### Unlimited
Pole Beginner: unlimited
Flexibility: unlimited

### Limited
General classes: 8 per entitlement cycle

### Mixed
Pole: 8
Conditioning: 4
Flexibility: unlimited

## Entitlement properties

- scope
- allowance
- valid_from
- valid_until
- reset policy
- rollover policy
- expiration policy
- branch restrictions
- activity restrictions
- level restrictions
- time restrictions

## Cycles

BillingCycle and EntitlementCycle are separate concepts.

Support:
- calendar month
- anniversary period
- fixed billing day
- fixed term
- pack expiration

## Rollover

Policies:
- none
- full
- capped
- expires-after-N-days

## Add-ons

Never edit a four-class membership into six classes just because the member buys two extra.

Create a separate purchase/grant and ledger entries.

## Credit ledger

Ledger entry examples:
+4000 monthly allowance
-1000 Pole Beginner
+2000 add-on purchase
-500 Open Pole

Use scaled integers, never float.

## Holds

Booking may place entitlement units in HOLD state.

Possible states:
- available
- held
- consumed
- released
- forfeited
- expired

Cancellation rules determine whether a hold is released or forfeited.
