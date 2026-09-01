# ADR-001 Modular Monolith

**Status:** Accepted · 2026-08-31

## Context
Jasapedia spans 40+ domains but starts on a single team/VM. Blueprint forbids starting with microservices (§6).

## Decision
Single Laravel deploy. Domain code in `app/Domain/<Module>` with Models/Actions/Services/Enums/Data/Policies. Cross-module calls go through module service classes or actions — never direct joins across domain tables in controllers. HTTP apps (Customer/Partner/Admin/Api) are route-groups + controllers referencing domain actions.

## Consequences
+ Fastest path to working platform; easy extraction later (clear seams).
− Requires discipline: no god services; enforced via review + architecture tests later.
