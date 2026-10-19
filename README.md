# The IT Guys — Free Switch Review

Standalone, mobile-first Meta ad landing form. Vanilla HTML, CSS and JavaScript;
no installation or build required. Opens at question 1 of 5.

## Hostinger

Upload `index.html`, `style.css`, `form.js`, and `favicon.svg` together to the
chosen website's document root, or a `switch-review` subfolder. Relative asset
paths support either arrangement. Do not replace the main website's files.

Configure the backend URL in both `index.html` (form action) and `form.js`
(`config.endpoint`). Default: `/api/switch-review` on the hosting domain.
This repository contains the frontend only: submissions require a backend.
See BACKEND.md for fields and the JSON success/error contract.

Page stays noindex until launch. No pixels or third-party scripts are included.
Edit copy/choices in index.html; styles in style.css; behavior in form.js.

The Meta ad should link directly to this site's URL, optionally with UTM query
parameters. Verify real submission handling on staging before running ads.
