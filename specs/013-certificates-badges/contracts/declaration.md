# Contract: Badge and certificate declaration

This extends [spec 001's declaration contract](../../001-site-config-as-code/contracts/declaration.md), as specs 002 and 004 do. Everything in 001's contract still holds.

## Files

| File | Holds |
|---|---|
| `moodle/site/badges.yaml` | The one badge template (shape in [data-model.md](../data-model.md)). |
| `moodle/site/badges/completion.png` | The badge design (R2). |
| `moodle/site/certificate/template.yaml`, `moodle/site/certificate/*.png` | The one certificate site template and its images (R7). |
| `moodle/site/settings/badges.yaml` | `enablebadges`, `badges_allowcoursebadges`, `badges_allowexternalbackpack`, `badges_defaultissuername`, `badges_defaultissuercontact: env:MOODLE_BADGE_CONTACT`, `customcert/verifyallcertificates` (R12). Cites row 23. |

## Changes to existing files

- **`site.yaml`**
  - Adds `mod_customcert` version `2026042014`, with sha256 `434b084f…d7f8b3` and its marketplace URL (R6).
  - Adds `availability_coursecompleted` version `2026070100`, with its sha256 computed at pin time (R8, decision 1, accepted 2026-10-02).
  - Re-pins `local_ltuse`.
  - Each new plugin's `why` cites #23 and the instance check that verified it.
- **`ignore.yaml`**
  - Drops `badges_defaultissuername` and `badges_defaultissuercontact`, which are now declared.
  - Adds `badges_badgesalt`, which is per-site, and which would break every issued assertion if changed (R9).
- **`roles.yaml`**
  - `user`: `moodle/badges:viewotherbadges: inherit` (R10).
  - `ltcpublisher`: `moodle/badges:createbadge`, `moodle/badges:configuredetails`, `moodle/badges:configurecriteria`, `moodle/badges:configuremessages` and `mod/customcert:manage`. The exact set is confirmed when the web service is built, by checking which capabilities the classes we call require (T-task).

## Payload arrays (PHP side)

Two arrays are handled after spec 004's `reports`:
1. `badge_template`: the rendered YAML, plus the image as base64, plus the deny patterns from `cbc_wording.py`.
2. `certificate_template`: the YAML, plus its images as base64.

`apply`:
- **The badge template.** Stores it in `local_ltuse`'s config and file area, at system context. Then re-renders every mapped badge and saves those that differ (US4-2). It is reported per badge as `changed` with the course's idnumber, never with a count of awards.
- **The certificate site template.** Finds it by exact name, creating it if it is absent, and sets its pages and elements to the declaration. Then copies it into every `ltct:<slug>:certificate` activity whose pages differ.

`drift`:
- `missing`: the template is not applied, or a mapped badge has gone.
- `changed`: a badge's text or image differs from its rendering, or the site template or an activity's pages differ.
- `extra`: an unmapped badge in an `ltct:` course, or a second site template with the declared name, which is also `ambiguous` and blocks the run.

Drift does not judge whether a badge *should* be active. That needs the course's stage, and `site_config.py` never reads course state. The publisher reports `active-not-delivery` instead ([publish.md](publish.md)).

Apply never deactivates, archives or deletes a badge, and never deletes a certificate activity or a site template.

## Validation (`validate`)

These are the rules in data-model.md, plus:
- the two plugins are pinned;
- `settings/badges.yaml` has every key above;
- `badges_defaultissuername` passes `check_recognition()`;
- no `badges_badgesalt` is declared.
