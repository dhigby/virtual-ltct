# Contract: Recognition through the publisher

These are the changes to the payload, to `local_ltuse`'s publish functions and to the publisher's call sequence. The payload stays platform-neutral (Principle II): it says *whether* a course is delivered, and only `local_ltuse` knows what a badge or a `customcert` is.

## Payload

The `recognition` block is described in [data-model.md](../data-model.md) under "Payload additions". `check_moodle_payload.py` adds the four assertions listed there. Each one is a hard failure and has no `--force`.

## `local_ltuse_set_course_recognition` (new)

| | |
|---|---|
| Parameters | `courseidnumber` (`PARAM_RAW`, `ltct:<slug>`), `delivery` (`PARAM_BOOL`), `certificateidnumber` (`PARAM_RAW`, optional, required when `delivery`) |
| Capability | `local/ltuse:publish`, plus what `add_moduleinfo()` and `mod/customcert:manage` need in the course (the `roles.yaml` changes in [declaration.md](declaration.md)). |
| Precondition | The badge template and the certificate site template have been applied. If they are missing, the call refuses with `recognition-not-applied: run site_config.py apply`, and writes nothing. |

**What the plugin does, in order** (the steps are R1, R4, R5, R7 and R8):
1. **Badge**: create it if the map has no badge for this course. Otherwise re-render its text and image from the stored template, and save them if they differ.
2. **Wording**: re-check the rendered text against the deny list stored at apply (R15). On a match, refuse and write nothing for this badge.
3. **Activation**: if `delivery` is true and the badge is inactive, call `set_status(ACTIVE)`. If `delivery` is false, leave the status as it is. An active badge is **never** deactivated.
4. **Certificate**: if `delivery` is true, create the `customcert` activity if it is absent. Set `verifyany`, the availability and completion `0`. Copy the site template into it if its pages differ.

**Returns**:

| Field | Values |
|---|---|
| `badge` | `created`, `updated`, `unchanged` |
| `status` | `inactive`, `activated`, `active` |
| `certificate` | `created`, `updated`, `unchanged`, `none` (not delivery) |
| `warnings` | A list of `{code, message}`. The codes are `startdate-future`, `badge-extra` (an unmapped badge in the course), `active-not-delivery`, and `course-hidden` (the cron safety net skips hidden courses, R3). |

It never returns award counts, recipient names or issue codes (FR-008).

## `local_ltuse_set_course_completion` (changed)

Its wanted set leaves out the idnumber `ltct:<slug>:certificate` (R8). Nothing else changes.

## Publisher call sequence (`publish_moodle.py`)

The publisher calls `local_ltuse_set_course_recognition` straight **after** `local_ltuse_set_course_completion`, so the badge and the certificate always see the final criteria. It prints one line:

```text
  recognition  badge updated, active; certificate unchanged
```

On a pilot publish it prints:

```text
  recognition  badge unchanged, inactive (pilot: no badge is issued until stage 8)
```

- Any warning goes into `problems`, and the publisher exits 1 after the publish completes, as it does for `completion-differs`.
- A refusal is a `MoodleError`, and the publisher exits 1.
- `--dry-run` prints the call and sends nothing.
- The certificate's idnumber is part of `this_run`, so `hide_modules` never offers it for hiding (R14).
