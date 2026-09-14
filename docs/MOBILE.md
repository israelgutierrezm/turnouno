# Flutter Mobile Application

## Users

One application with role-aware modes:
- Member
- Guardian
- Instructor

Do not build separate binaries for each role initially.

## Main member navigation

- Home
- Explore
- Bookings
- QR
- Profile

## Guardian capabilities

- switch dependent
- view calendars
- reserve/cancel
- pay
- view attendance
- view progress
- manage documents

## Instructor capabilities

- agenda
- session roster
- attendance
- participant information allowed by permission
- progress evaluation
- substitution request

## Architecture

Feature-first structure.

Example:

lib/
├── app/
├── core/
│   ├── networking/
│   ├── auth/
│   ├── storage/
│   ├── errors/
│   └── design_system/
└── features/
    ├── auth/
    ├── home/
    ├── bookings/
    ├── schedule/
    ├── membership/
    ├── qr/
    ├── family/
    └── instructor/

Within a feature:
- presentation/
- data/
- domain/ only when useful

Views contain minimal logic.
View models/controllers expose immutable state and commands.
Repositories are sources of truth for feature data.

## API

Flutter does not reproduce backend eligibility logic.

Example:
UI asks API to create booking.
API returns success or an explainable denial code.

## Offline

Not MVP except secure local session/cache where appropriate.

Future:
- cached instructor roster
- attendance operation queue
- resilient check-in
