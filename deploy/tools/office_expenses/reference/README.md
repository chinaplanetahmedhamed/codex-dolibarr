# Original Finens sidebar reference

- `finens-sidebar.php` is copied from the user's sanitized original project, `chinaplanetos-office-expenses/includes/sidebar.php`. Original repository: https://github.com/mazofayd12/finens-saas.
- Template SHA-256: `d030f5a4afbc094d2e3424ad606e1f29ea0e5672d55e211afc7276b6115cee46`.
- `finens-sidebar.utilities.css` contains the actual matching Tailwind declarations from the original rendered sidebar at https://darkorange-reindeer-228322.hostingersite.com/treasury.php, captured 2026-11-02. They are scoped mechanically during generation; no CDN script is executed in the preview.
- The hosted revision's Settings link is preserved in `preview_original_sidebar.py` because it is absent from the older sanitized template copy.

Only the isolated preview renderer includes this template, using synthetic profile values and stub module checks. It never loads the original application, database or credentials. Preview output removes logout and maps destinations to static pages. Do not deploy these reference files as the Office Expenses runtime.

Source publication normalization: removed trailing spaces from two otherwise blank lines only; rendered markup/styles remain identical. The template hash above records the normalized source.
