# Domain docs

ELYO uses a single-context domain-document layout.

## Read before domain work

- Read relevant material in `docs/ai-context/` for current domain vocabulary and project context.
- Read applicable architectural decisions in `docs/adr-documents/`.
- Read privacy decisions and assessments when work touches Health, Mapping, Reporting, or company-facing aggregates.
- If a root `CONTEXT.md` is added later, treat it as the canonical glossary and use its terminology.

## Vocabulary

Use established concepts such as Identity, Mapping, Health Subject, Health, Reporting, Purpose Code, runtime, tombstone, and anonymity threshold. Do not introduce synonyms that blur domain boundaries.

## ADR conflicts

Surface conflicts with an accepted ADR explicitly. Do not silently override an architectural or privacy decision.
