# Pilot Validation

## Pole tenant

Configuration:
- multiple branches
- Pole Beginner / Intermediate / Advanced
- Exotic
- Flexibility
- Conditioning
- Open Pole
- workshops
- multiple instructors
- instructor visibility
- limited and unlimited memberships
- class packs
- top-ups
- booking priority
- cancellation cutoff
- attendance

Pass criteria:
No pole-specific domain fork.

## Swimming tenant

Configuration:
- households
- guardian + dependents
- pools + lanes
- age/level eligibility
- recurring enrollment
- instructor ratio
- attendance
- makeup entitlement
- basic skill progression

Pass criteria:
No swimming-specific booking engine.

## Gym tenant

Configuration:
- open gym access
- group classes
- personal training
- multi-location membership
- check-in
- basic access validation

Pass criteria:
No gym-specific membership subsystem.

## Architecture gate

Every new pilot requirement must be classified as:
1. core configuration
2. reusable core capability
3. justified vertical extension
4. tenant customization

Tenant-specific code is prohibited.
