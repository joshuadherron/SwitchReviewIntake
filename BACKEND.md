# Free Switch Review landing page

Public route: `/switch-review` (also `/switch-review/`). Source lives in
`public/switch-review/`: `index.html` for copy and choices, `style.css` for layout,
and `form.js` for progressive enhancement. The existing public assets build copies
these files unchanged. No new dependencies or tracking scripts are included.

The Meta ad provides the introductory call to action. The page opens directly at
team size (1 of 5), followed by renewal, concerns, contact, review, and confirmation.
The original intro markup is hidden. The page uses the existing navy/teal palette and IT wordmark.
Without JavaScript, all input sections are visible and submit as a normal form.
The backend must render validation errors with submitted values and return its
own confirmation page for this fallback. URL attribution is captured by JS;
for no-JS attribution the backend must render hidden values from the GET query.

## Backend handoff

This change implements the landing page only. No prospect storage, notifications,
server validation, spam protection, or backend endpoint is added. No deployment
was performed. Connect and verify on staging before running ads.

Default endpoint: `POST /api/switch-review`. To use another endpoint, change both
the HTML form action and `config.endpoint` in `form.js`. Prefer a same-origin
endpoint; a separate origin needs appropriate CORS configuration for JS requests.

Both modes send `application/x-www-form-urlencoded` fields:

| Field | Meaning |
| --- | --- |
| `source` | `Switch Review` |
| `staff_size` | `1–4`, `5–15`, `16–30`, `31–50`, or `50+` |
| `renewal` | `Within 3 months`, `3–6 months`, `6–12 months`, `Not sure`, `No contract` |
| `concerns` | Repeated field, one per checked choice; may be absent |
| `other_concern` | Optional text, maximum 300 characters; only use if Something else is selected |
| `name`, `business_name`, `email` | Required contact values |
| `phone` | Optional |
| `consent` | Required value `yes` |
| `website` | Honeypot; legitimate submissions leave this empty |
| `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `fbclid` | URL attribution, each capped at 2,000 characters in the browser |

JS requests send `Accept: application/json`. Only a successful HTTP response with
`{"ok":true}` displays confirmation. Errors can return
`{"ok":false,"errors":{"email":"Enter a valid work email."}}` with a 4xx status.
HTTP 429 gets a rate-limit message. Network errors preserve entered answers and
offer a retry or phone contact. The request times out after 20 seconds; implement
server idempotency if duplicate protection is required.

The backend owns validation, rate limiting, honeypot checks, prospect persistence,
follow-up dates, target-size flags, and Graph notification. Do not enable ads until
a staging submission appears in the intended prospect list and email arrives.
The `metaTrackingEnabled` config flag defaults to false and is reserved for a
future integration; the marked TODO contains no pixel or Conversions API code.

## Verification

Run `npm run build`, `node scripts/test-switch-review.mjs`, and `npm test`.
The dedicated script checks fallback markup, attribution, navigation, required
fields, review rendering, and both failed and successful mocked submissions.
Preview the compiled application with `npm start`, then visit
`/switch-review?utm_source=facebook`. Confirm back navigation retains answers,
summary Edit buttons work, invalid contact fields block progression, and a missing
backend shows an error instead of success. Test the success response and no-JS
POST against the separately provided backend on staging.
