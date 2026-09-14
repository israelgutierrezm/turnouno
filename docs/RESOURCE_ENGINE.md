# Resource Engine

## Goal

Represent reservable or capacity-constraining operational assets without industry-specific tables.

## Hierarchy

Facility
→ Resource
→ child Resource

Examples:

Pool A
├── Lane 1
├── Lane 2
└── Lane 3

Studio A
├── Pole 1
├── Pole 2
└── Pole 8

## Resource modes

- UNIT: individually identified resource
- POOL: interchangeable quantity

## Resource data

- type
- parent
- capacity
- availability
- status
- location
- restrictions
- maintenance blocks

## Session requirements

A session may require:
- one studio
- eight poles
- one lead instructor
- optional assistant

Staff are Persons/StaffAssignments, not generic Resource rows, but Scheduling may evaluate all schedulable assets through common availability contracts.

## Avoid

Do not build unrestricted graph complexity in MVP.
Start with hierarchy + typed relations + requirements.
