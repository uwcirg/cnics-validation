# Contract Note: `FHIR_SERVER` environment variable

**Feature**: 001-mark-fhir-unused
**Contract type**: environment variable / configuration surface
**Status**: inert (not currently used)

## What changes

This feature does not modify any HTTP API, CLI, or inter-service contract.
It updates the *configuration contract* exposed by the repository's
environment-variable templates so that the `FHIR_SERVER` variable is
documented as inert.

## Pre-feature contract

> `FHIR_SERVER` — URL of the FHIR server used by the application.

This phrasing implied the variable was required for "the application" to
function, which is false.

## Post-feature contract

> `FHIR_SERVER` — **not currently used.** Retained for backward
> compatibility with deployments that still set it. No runtime component
> reads this value. Safe to omit.

## Compatibility guarantees

- **Unset**: the application stack starts successfully. CI passes
  successfully. No warnings or errors are emitted.
- **Set to any string (including empty, malformed URLs, or real FHIR
  endpoints)**: the application stack behaves identically to the unset
  case. The value is neither validated nor read.
- **Set to a value that was previously working for a downstream
  deployment**: no change. The variable has always been inert; the only
  change here is that its inert status is now documented.

## Test matrix

| Input                                | Expected outcome                    |
|--------------------------------------|-------------------------------------|
| `FHIR_SERVER` unset                  | Stack starts; CI passes.            |
| `FHIR_SERVER=""`                     | Stack starts; CI passes.            |
| `FHIR_SERVER=http://test-server.com` | Stack starts; CI passes (baseline). |
| `FHIR_SERVER=garbage`                | Stack starts; CI passes.            |

All four cases produce identical runtime behavior because no code reads
the variable. The matrix is documentary — we are not introducing new
tests, because introducing a test for "this variable is ignored" would
ossify the inertness and make a future revival harder.

## Non-goals

- Removing `FHIR_SERVER` from downstream deployments' real `.env` files.
  That is a deployment-operator concern and out of scope for this repo.
- Introducing a FHIR integration. If one is ever needed, it should be
  planned as a separate feature with its own spec.
- Deprecating the variable name. "Deprecation" implies we are preparing
  to remove it; we are not. The variable is simply not currently used.
