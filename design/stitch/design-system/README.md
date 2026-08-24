# Stitch Design System — "Academic Career Nexus"

`DESIGN.md` is the design-token definition exported by Google Stitch. It was exported in its own
folder (`academic_career_nexus/`) and contains no screen — only tokens:

- a full surface/primary/secondary/tertiary/error colour ramp (light-mode values);
- typography scale definitions (Inter, display/headline/… sizes, weights, line heights).

Every exported screen in `design/stitch/` uses these tokens, which is why token adherence could not
be used to tell iterations of a screen apart.

Treat this file as the reference for design tokens when `packages/ui` is eventually implemented.
It is a reference, not a production theme file — no dark-mode ramp is defined in the export.
