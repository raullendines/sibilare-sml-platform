# Reporting Architecture

Reports and presentations must be reproducible.

## Report Flow

1. User requests report.
2. Backend creates `generated_report` with `pending` status.
3. Worker creates data snapshot.
4. Worker generates AI texts if needed.
5. Worker renders PDF/PPTX.
6. File is stored in private storage.
7. Report status becomes `ready`.
8. User downloads through signed URL.

## Required Snapshot Data

- Period.
- Filters.
- Brands.
- Platforms.
- Topics.
- Client configuration version.
- Aggregated data.
- AI texts used.
- Template version.

## Rules

- Do not generate official reports only in the browser.
- Do not depend only on live mutable data for historical reports.
- Store generation errors and duration.

