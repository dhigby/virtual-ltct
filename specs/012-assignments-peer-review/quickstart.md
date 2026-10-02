# Quickstart: validating spec 012

Each scenario names the spec acceptance or success criterion it proves. Moodle scenarios run **only** against the temporary 5.2.3+ instance with **test accounts** (constitution III and X). Nothing below writes learner data into the repo. Fixtures under `tests/fixtures/` are invented course text with no learner work in them.

## Prerequisites

- Python with `pip install -r publish-requirements.txt pyyaml pytest`
- `MOODLE_URL`, `MOODLE_TOKEN` for the temporary instance, from the environment (never a file)
- `local_ltuse` at this spec's version and `workshopallocation_orgcohort` installed; `python scripts/site_config.py apply` run
- Test accounts: 4 learners (L1, L2 in org A, cohort A1; L3 in org A, cohort A2; L4 in org B), 1 Course mentor M for org A, groups named per spec 002

## Phase A: no design gate needed

**A1. The gate holds (US1-4, SC-001).** Add an empty `modules/<any>/09-x-assignment.md` on a scratch branch, then:

```bash
python scripts/check_course_package.py --course <any>
```

Expect exit 1, "assignments wait on the spec 012 design approval". Delete the file.

**A2. The design is decision-ready (US1 Independent Test).** Read [design.md](design.md):
- It answers FR-001 to FR-008a.
- It compares options A–C on all four axes.
- It recommends one, and the decision record is ready to fill in.

**A3. Every course gets a discussion (US4-1, US4-4).**

```bash
python scripts/publish_moodle.py --slug <course> --dry-run
python scripts/publish_moodle.py --slug <course>
```

Expect a `forum  ltct:<course>:discussion  created` line. As L1, post a discussion. Republish, and expect `updated` with the post still there.

**A4. Organisations don't see each other (US4-2, SC-004).** As L4 (org B), open the discussion. L1's post is not listed. Add the course to `moodle/site/course-discussions.yaml` with a `why`, run `site_config.py apply`, and L4 now sees it. Remove the entry and apply again.

**A5. Drift catches a hand change (US4-3).** As admin, set the forum to visible groups by hand. Then:

```bash
python scripts/site_config.py drift
```

Expect exit 1 and a `differs` line for `ltct:<course>:discussion`. Running `apply` restores it.

## Phase B: after the decision is recorded

**B1. Format and disclosure checks are green.**

```bash
python scripts/assignment_parse.py --check-all
python scripts/check_course_package.py --course <test-course>
python -m pytest -q tests/test_disclosure_mentor_only.py tests/test_assignment_parse.py
```

**B2. A planted leak is always caught (US2-5, SC-002).** The pytest fixture copies a grading-note line into a lesson. `check_moodle_payload.py` must exit 1 naming that line. Repeat with the model-answer line in `intro_html`. Both must fail every time.

**B3. Learner and mentor see the right things (US2-1, US2-3).** Publish the test course.
- As L1, open the assignment. The brief and criteria are visible. No grading note or model-answer text appears anywhere, and the "Mentor notes" page is not listed and returns an access error by URL.
- As M, open the submission. The marking guide shows the marker notes, and the mentor-notes page opens.

**B4. Submit offline, get feedback (US2-2, SC-005).**
- In the Android app as L1, prepare a text submission in airplane mode, reconnect, and confirm it is submitted.
- As M (browser), grade with the guide.
- As L1 (app and web), feedback is visible.
- With `**Completion:** required`, the activity is complete only after M grades (FR-019).

**B5. Republish never touches learner work (US2-4, SC-003).**
- Edit the brief text and republish. L1's submission and M's grade are unchanged.
- Rename a criterion and republish. Expect a refusal naming `--allow-criteria-change`.
- Remove the assignment file and republish. The module is **hidden**, not deleted, and the submission is still in the grader view.

**B6. Withheld whole on doubt (edge case).** Put a `## Model answer` heading outside the mentor-only block. The publish reports the file **withheld** and refuses without `--allow-withheld`. With the flag, learners see only the placeholder.

## Phase C: peer review

**C1. Allocation stays inside the organisation (US3-1, SC-004).** Publish a `**Review:** peer` assignment. L1–L4 submit, and M switches to the assessment phase.
- L1 and L2 review each other first (cohort A1), and L3 is drawn in from A2 only to make up numbers.
- L4 gets no reviewers, and the allocator reports it.
- No allocation crosses org A and org B.

**C2. Anonymous both ways (US3-2, US3-3).**
- As L1, the assessment form shows the criteria with no author name and no grading notes.
- As L2, the comments received show no reviewer names.
- As M, both names are shown.

**C3. Mentor fallback (US3-4).** As M, use "Assess" on L4's submission. L4 receives feedback.

## Repo gates, run on every PR

`gen_coverage.py`, `check_competency_descriptors.py`, `check_course_package.py`, `quiz_parse.py --check-all`, `assignment_parse.py --check-all`, `check_learner_view.py`, the review-site build, and `site_config.py validate`.
