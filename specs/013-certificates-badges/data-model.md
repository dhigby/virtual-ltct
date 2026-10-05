# Data model: Completion badges and certificates

**Plan**: [plan.md](plan.md) · **Research**: [research.md](research.md)

Items marked **repo** are declarations committed under `moodle/site/`. Items marked **Moodle** live only on the server. **Learner data** lives only in Moodle and never enters the repo (FR-008, Principle III).

## Badge template (repo: `moodle/site/badges.yaml`)

There is one template for every course and every organisation (FR-012).

| Field | Rule |
|---|---|
| `rows` | `[23]` |
| `image` | Path relative to `moodle/site/`, normally `badges/completion.png`. It must be a square PNG, at most 256 KB and at least 512 px (R2). |
| `name` | A template, for example `{course}: training completed`. It must contain `{course}`, and must pass the FR-004 rule after substitution (R15). At most 1333 characters once rendered. |
| `description` | A template. It may use `{competencies}` and `{target_level}`, but `{target_level}` only straight after "designed to support progress towards" (FR-005). |
| `imagecaption` | Plain text, for screen readers. It passes FR-004. |
| `message_subject`, `message` | The award notification. `message` must contain `%badgelink%`. Core substitutes `%badgename%`, `%username%` and `%badgelink%` (R5). |
| `version` | A string, for example `"1"`. Raising it marks a reworded design. Open Badges carries it. |
| `language` | `en` |
| `why` | Required. |

The issuer name and contact come from `settings/badges.yaml` (R12), not from here, so one setting serves both badges and the certificate's text.

**Validation** (`validate`, which CI runs):
- every text passes `cbc_wording.check_recognition()` (R15), rendered against every course title in `modules/*/README.md` and the longest competency list;
- the image rules above;
- placeholders come only from `{course}`, `{competencies}`, `{target_level}` and `{programme}`.

## Certificate template (repo: `moodle/site/certificate/template.yaml`)

```yaml
rows: [23]
name: LTC training completed          # the mod_customcert site template's exact name (identity, R7)
activity_name: Certificate of training completed   # what each course's activity is called
intro: Available once you have completed this course.   # shown beside the restriction (US2-2)
font: freesans                         # embedded subset; covers non-Latin names (R11)
pages:
  - width: 297                         # mm, A4 landscape
    height: 210
    margins: {left: 15, right: 15}
    elements:
      - {type: image, file: logo.png, x: 20, y: 15, width: 40}
      - {type: text, text: "Training completed", x: 148, y: 50, size: 28, align: C}
      - {type: studentname, x: 148, y: 80, size: 24, align: C}
      - {type: text, text: "has completed the course", x: 148, y: 98, size: 14, align: C}
      - {type: coursename, x: 148, y: 112, size: 18, align: C}
      - {type: date, date: completion, format: strftimedate, x: 148, y: 130, size: 12, align: C}
      - {type: text, text: "{programme}", x: 148, y: 150, size: 12, align: C}
      - {type: code, x: 148, y: 185, size: 9, align: C}
      - {type: qrcode, x: 260, y: 165, width: 25}
why: …
```

The layout above shows the shape only. The real positions and the logo come from the maintainer (plan, decision 4).

**Validation**:
- Element `type`s are limited to `text`, `studentname`, `coursename`, `date`, `code`, `qrcode`, `image` and `bgimage`.
- `date` must be `completion`, which maps to `DATE_COMPLETION = -2`. An issue date would show the first download, not when the course was completed (R6, R9).
- `format` is `1`–`5` or a `strftime…` langconfig key such as `strftimedate` (`DATE_FORMAT` accepts `strftime[a-z]+`), as the date element reads it (`element_helper::get_date_format_string`; `DATE_FORMAT` in `scripts/site_config.py`). The template uses `strftimedate`, so the date follows the date format of the language the PDF is generated in. *(2026-10-05: this page showed `"j F Y"` until now; the merged template has always used `strftimedate`, 4c9f12e.)*
- There must be exactly one `studentname`, one `coursename`, one `date` and one `code` (FR-003).
- Every `text`, `name`, `activity_name` and `intro` passes `check_recognition()`, and the page must contain "training completed" or "completed the course".
- The images must exist, and total at most 100 KB (R11).
- `font` must be an embedded Unicode TCPDF family, one of `freesans`, `freeserif` or `dejavusans`.

## Badge (Moodle)

There is one per delivered course. It is created by `local_ltuse`, through `core_badges\badge` and `award_criteria` (R1).

| Field | Value |
|---|---|
| `type` / `courseid` | `BADGE_TYPE_COURSE` / the course |
| `name`, `description`, `imagecaption`, `message`, `messagesubject` | Rendered from the template (R16). |
| `issuername`, `issuercontact` | From `badges_defaultissuername` and `badges_defaultissuercontact`. |
| `issuerurl` | The scheme and host of `$CFG->wwwroot`. Never a literal host. |
| `version`, `language` | From the template. |
| `attachment` | `0` (R5). |
| `notification` | `BADGE_MESSAGE_NEVER` |
| `expiredate`, `expireperiod` | null: training evidence does not expire. |
| criteria | An overall criterion with `agg = ALL`, and the course criterion `{course_<id>: <id>}`. Nothing else. |

**Lifecycle**:

```text
(none) --publish, stage 7--> INACTIVE --publish, stage 8--> ACTIVE --first award--> ACTIVE_LOCKED
              (created)          (no awards)        (set_status)            (core)
```

- After a reword, the badge **stays active** (R5).
- **Never** INACTIVE_LOCKED, ARCHIVED or deleted by our code. Course deletion archives a badge in core, and that is why a course is retired by hiding (R14).
- A badge found ACTIVE when the course is not at stage 8 is left active and reported. The plugin never deactivates one.

## Course badge map (Moodle: `local_ltuse_course_badge`)

| Column | Type | Rule |
|---|---|---|
| `id` | int | |
| `courseid` | int, unique | The `ltct:` course. |
| `badgeid` | int, unique | Its badge. |
| `imagehash` | char(64) | The sha256 of the template image the badge's image was made from. Core resizes the image, so the stored files cannot be compared. |
| `timecreated` | int | |

The table holds no user data, so the privacy provider stays `null_provider`. A badge in an `ltct:` course that is not in the map is `extra`, reported and never adopted. A map row whose badge is gone is `missing`, and the next publish creates the badge again.

## Certificate activity (Moodle)

There is one per delivered course: a `customcert` course module, created by `local_ltuse` through `add_moduleinfo()`.

| Field | Value |
|---|---|
| cm `idnumber` | `ltct:<slug>:certificate` |
| `name` | `activity_name` |
| `intro` | `intro` |
| `verifyany` | `1` (R9) |
| `emailstudents`, `emailteachers`, `emailothers` | `0`, `0`, `''` |
| `protection_*` | none |
| `requiredtime` | `0` |
| cm `availability` | `{"op":"&","c":[{"type":"coursecompleted","id":"1"}],"showc":[true]}` (R8) |
| cm `completion` | `0`. Never a course criterion (R8). |
| section | The last lesson section of the course, never the hidden Retired section that holds modules the repo dropped. |
| pages and elements | A copy of the site template, made with `template_load_service::replace()` (R7). |

**Lifecycle**: created on the first stage-8 publish, then re-copied from the site template whenever it differs. **Never deleted**, because deleting it deletes every issued code (R14).

A republish rewrites the activity with all the settings above when it is hidden, sits in the Retired section, or `certificate::differs()` finds a difference (`moodle/local_ltuse/classes/recognition/certificate.php`, `sync()`, lines 64-76). `differs()` compares only the name, intro, `verifyany`, `emailstudents`, `emailteachers`, `emailothers` and the availability, so a hand change to any other setting (`requiredtime`, `protection_*`, cm `completion`, and the other fields `fields()` writes, such as `deliveryoption` or `showdescription`) survives until one of those triggers a rewrite. A hand-set `emailteachers`, `emailothers` or `emailstudents` is put back on the next publish; the PDF is not mailed to anyone once the activity has been republished (spec 016 R10, review M4). *(2026-10-05: before this change `differs()` did not compare the three email settings, so a hand-set value survived a republish while everything else matched; fixed in the same PR as this note (Doug, 2026-10-05).)*

## Issued badge and issued certificate (Moodle, learner data)

These are core's `badge_issued` and `mod_customcert`'s `customcert_issues`. They are created by core and by the plugin, never by our code. They are exported by their own privacy providers (FR-013), and `apply`, `drift` and the publisher never read their rows. `drift` and the publisher's output report counts of badges and activities, never of issues.

## Payload additions (`moodle_payload.py`)

```json
"recognition": {
  "delivery": true,
  "certificate": {"idnumber": "ltct:<slug>:certificate"}
}
```

- `delivery` is `course_stage.stage_for(folder)` reporting stage 8 or later. The payload never works the stage out itself (Principle I, R4).
- `certificate` is present only when `delivery` is true.
- `check_moodle_payload.py` asserts these three things:
  - the course title passes `check_recognition()` once rendered into the badge name (R15);
  - the certificate's idnumber fits in 100 characters;
  - no module in `sections` uses the certificate's idnumber.
- The payload carries no start date, and the publisher never sets one. The plugin's
  `startdate-future` warning is the guard against a start date set by hand (R3).
