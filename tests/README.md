# tests — Executable Tests

**Status: reserved and empty. No tests exist — and no placeholder or fake tests were created.**

| Path | Scope |
| --- | --- |
| `e2e/` | End-to-end user journeys across the full stack. |
| `integration/` | Cross-module/service integration tests. |
| `security/` | Authorization, access control, and security regression tests. |
| `uat/` | Automated coverage of the UAT scenarios documented in `docs/testing/`. |

Test *plans and scenarios* are documented in `docs/testing/`. This directory is for the code that
executes them. Unit tests are expected to live alongside the code they cover, inside each app or
package, rather than here.
