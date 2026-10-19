# Review handoff

Update: owner has authorized direct production deployment, supplying database
u809002269_swform and user u809002269_swform_insert. Earlier staging-only gates
below are superseded. Credentials must still be entered privately on the server,
and mail remains off until separately configured and verified.

Public PHP changes belong only to joshuadherron/SwitchReviewIntake.
Sales changes are separately prepared in ../sales-switch-review based on the
inspected joshuadherron/the-it-guys-sales revision. Do not put sales code in the
public repo or web root.

1. Review 003 (new swform DB) and 004 (sales DB); owner runs them on staging.
2. Apply sales changes to its staging checkout; keep mail disabled.
3. Configure private PHP credentials for swform_test only, using Hostinger's
   database-scoped user. Public app must never receive sales credentials.
4. Upload only the public staging archive to switchtest.theitguys.us.
5. Run README acceptance checks. Production requires separate authorization.

MAIL_FROM_WEBSITE=website@theitguys.us belongs in the private SALES environment.
MAIL_TO_LEADS defaults to that sender unless overridden. Mail stays OFF unless
SALES_MAIL_ENABLED=true; the reusable module accepts a sender for each feature.
